<?php
namespace App\Http\Controllers;
use App\Models\Language;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Conference;
use App\Models\B2cConference;
use App\Models\Course;
use App\Models\CourseLevel;

use DB;
use Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Services\ScheduleService;
use App\Services\SchoolService;
use App\Services\NotificationService;

class ScheduleController extends Controller
{
    protected $scheduleService;
    protected $schoolService;

    public function __construct(Request $request, ScheduleService $scheduleService, SchoolService $schoolService)
    {
        $this->scheduleService = $scheduleService;
        $this->schoolService = $schoolService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_schedule_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $courses = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->distinct()
        ->orderBy('courses.course_id', 'asc')
        ->get();


        foreach ($courses as $c => $course) {
            $levels = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->where('course_levels.course_id', '=', $course->course_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)
            ->select(
                'course_levels.level_id',
                'course_levels_lang.level_name'
            )
            ->distinct()
            ->orderBy('course_levels.level_id', 'asc')
            ->get();

            $course->levels = $levels;
        }

        $all_mentors = DB::table('users')
        ->where('users.school_id', '=', auth()->user()->school_id)
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('users_roles')
                ->whereColumn('users.user_id', 'users_roles.user_id')
                ->whereIn('users_roles.role_type_id', [3, 4]);
        })
        ->select(
            'users.user_id',
            'users.first_name',
            'users.last_name',
            'users.avatar'
        )
        ->distinct()
        ->orderBy('users.last_name', 'asc')
        ->get();

        $mentors = Conference::leftJoin('users', 'users.user_id', '=', 'conferences.mentor_id')
        ->where('users.school_id', '=', auth()->user()->school_id)
        ->where('users.status_type_id', '!=', 2)
        ->select(
            'users.user_id',
            'users.first_name',
            'users.last_name',
            DB::raw("CONCAT(users.last_name, ' ', users.first_name) AS full_name"),
            'users.avatar'
        )
        ->distinct()
        ->orderBy('users.last_name', 'asc')
        ->get();

        $attributes = new \stdClass();

        $attributes->courses = $courses;
        $attributes->all_mentors = $all_mentors;
        $attributes->group_mentors = $mentors;

        return response()->json($attributes, 200);
    }

    /**
     * Расписание. Календарь передаёт период from/to (по началу урока, [from, to))
     * и грузит его помесячно; без периода — всё расписание, как раньше.
     */
    public function get_schedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after:from',
        ]);

        if($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $auth_user = auth()->user();
        $for_dashboard = false;

        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $from = $request->filled('from') ? Carbon::parse($request->from) : null;
        $to = $request->filled('to') ? Carbon::parse($request->to) : null;

        $conferences = $this->scheduleService->getSchedule($request, $auth_user->user_id, $language->lang_id, $for_dashboard, null, $from, $to);
        return response()->json($conferences, 200);
    }

    /** Количество уроков по месяцам года (режим «Год» в календаре), с теми же фильтрами */
    public function get_month_counts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        if($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $auth_user = auth()->user();
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $counts = $this->scheduleService->getMonthCounts($request, $auth_user->user_id, $language->lang_id, (int) $request->year);

        return response()->json($counts, 200);
    }

    public function update(Request $request)
    {
        $auth_user = auth()->user();
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $rules = [
            // Учителем можно назначить только сотрудника своей школы
            'mentor_id' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($auth_user) {
                    $mentor = User::find($value);
                    if (
                        !$mentor ||
                        (int) $mentor->school_id !== (int) $auth_user->school_id ||
                        !$mentor->hasRole(['super_admin', 'school_owner', 'school_admin', 'mentor'])
                    ) {
                        $fail(trans('validation.exists'));
                    }
                },
            ],
            'start_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
        ];

        if($this->schoolService->isAiSchoolDomain($auth_user->school_id)){
            $rules['conf_topic'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $conference = $this->schoolService->isAiSchoolDomain($auth_user->school_id) ? 
        B2cConference::where('uuid', '=', $request->uuid)->first() : 
        Conference::where('uuid', '=', $request->uuid)->first();

        if(isset($conference)){
            // Раньше перенести (и сдвинуть всё расписание группы) мог любой
            // авторизованный, зная uuid конференции
            if(!$this->canManageConference($conference, $auth_user)){
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // Для уведомления «урок перенесён»
            $isB2c = $this->schoolService->isAiSchoolDomain($auth_user->school_id);
            $oldStart = Carbon::parse($conference->start_time);

            // У учителя в новое время уже есть другой урок — предупреждаем.
            // Пользователь может перенести всё равно (force=1)
            if (!$request->boolean('force')) {
                $conflict = $this->findMentorConflict($conference, $isB2c, $request);
                if ($conflict) {
                    return response()->json(['message' => 'mentor_busy', 'conflict' => $conflict], 409);
                }
            }

            if($isB2c){
                $conference->topic = $request->conf_topic;

                if(isset($request->conf_topic_description)){
                    $conference->topic_description = $request->conf_topic_description;
                }

                $conference->mentor_id = $request->mentor_id;
                $conference->operator_id = $auth_user->user_id;
                $conference->is_free = isset($request->is_free) ? 1 : 0;
                $conference->start_time = $request->start_date.' '.$request->start_time;
                $conference->end_time = Carbon::parse($conference->start_time)->addMinutes(config('app.conference_minute'))->toDateTimeString();
            }
            else{
                $referenceDate = Carbon::parse($conference->start_time)->toDateString();
                $requestDate = Carbon::parse($request->start_date)->toDateString();

                // Разница в днях — по датам. Раньше считалась по дате и времени,
                // и при более раннем времени сдвиг выходил на день меньше
                $diffInDays = Carbon::parse($referenceDate)->diffInDays(Carbon::parse($requestDate));

                // Создаем допустимый диапазон: от -2 до +2 дней
                $minDate = Carbon::parse($referenceDate)->subDays(2);
                $maxDate = Carbon::parse($referenceDate)->addDays(2);

                if(isset($request->date_shift_by_week) && $request->date_shift_by_week == 1){
                    if ($requestDate < $minDate->toDateString()) {
                        return response()->json(['start_date' => trans('auth.date_should_be_no_earlier_or_no_later_than_two_days')], 422);
                    }
                }
                else{
                    if ($requestDate < $minDate->toDateString() || $requestDate > $maxDate->toDateString()) {
                        return response()->json(['start_date' => trans('auth.date_should_be_no_earlier_or_no_later_than_two_days')], 422);
                    }
                }

                if(isset($request->mentor_only_for_this_lesson) && $request->mentor_only_for_this_lesson == 0){
                    $conferences = Conference::where('start_time', '>=', $conference->start_time)
                    ->where('group_id', '=', $conference->group_id)
                    ->get();

                    if(count($conferences) > 0){
                        foreach ($conferences as $key => $value) {
                            $c = Conference::find($value->conference_id);
                            $c->mentor_id = $request->mentor_id;
                            $c->save();
                        }
                    }
                }
                else{
                    $conference->mentor_id = $request->mentor_id;
                }

                if(isset($request->date_shift_by_week) && $request->date_shift_by_week == 1){

                    $conferences = Conference::where('start_time', '>=', $conference->start_time)
                    ->where('group_id', '=', $conference->group_id)
                    ->get();

                    if(count($conferences) > 0){
                        foreach ($conferences as $key => $value) {
                            $c = Conference::find($value->conference_id);
                            if($referenceDate < $requestDate){
                                $c->start_time = Carbon::parse($c->start_time)->addDays($diffInDays);
                                $c->end_time = Carbon::parse($c->end_time)->addDays($diffInDays);
                            }
                            elseif($referenceDate > $requestDate){
                                $c->start_time = Carbon::parse($c->start_time)->subDays($diffInDays);
                                $c->end_time = Carbon::parse($c->end_time)->subDays($diffInDays);
                            }
                            $c->save();
                        }
                    }
                }
                else{
                    $conference->start_time = $request->start_date.' '.$request->start_time;
                    $conference->end_time = Carbon::parse($conference->start_time)->addMinutes(config('app.conference_minute'))->toDateTimeString();
                    $conference->moved = 1;
                }
            }

            $conference->save();

            // Время изменилось — сообщаем участникам и учителю. При сдвиге на
            // неделю конференции меняются отдельными моделями, поэтому новое
            // время берём из базы
            $newStart = Carbon::parse(optional($conference->fresh())->start_time ?? $conference->start_time);

            if(!$oldStart->equalTo($newStart)){
                $notifications = app(NotificationService::class);
                $shifted = !$isB2c && isset($request->date_shift_by_week) && $request->date_shift_by_week == 1;

                $notifications->send(
                    $notifications->conferenceRecipientIds($conference, $isB2c),
                    $shifted ? 'lessons_shifted' : 'lesson_moved',
                    [
                        'lesson' => $notifications->conferenceTitle($conference, $isB2c),
                        'from' => $notifications->iso($oldStart),
                        'to' => $notifications->iso($newStart),
                    ],
                    '/dashboard/schedule',
                    $auth_user->user_id
                );
            }

            return response()->json('Conference saved sucessfully', 200);
        }
        else{
            return response()->json('Conference not found', 404);
        }
    }

    /**
     * Может ли пользователь переносить урок: его учитель, тот, кто его создал,
     * либо владелец / администратор той же школы (школа — по учителю урока,
     * а если его нет — по создателю)
     */
    /**
     * Другой урок того же учителя, который пересекается с новым временем
     * переносимого урока. null — пересечений нет.
     * При сдвиге расписания на неделю меняется только дата, время то же.
     */
    protected function findMentorConflict($conference, bool $isB2c, Request $request): ?array
    {
        $oldStart = Carbon::parse($conference->start_time);
        $oldEnd = Carbon::parse($conference->end_time);
        $shift = !$isB2c && $request->input('date_shift_by_week') == 1;

        // Дата вне допустимых ±2 дней — дальше вернётся ошибка даты, конфликт не ищем
        if (!$isB2c) {
            $requestDate = Carbon::parse($request->start_date)->toDateString();
            $minDate = $oldStart->copy()->subDays(2)->toDateString();
            $maxDate = $oldStart->copy()->addDays(2)->toDateString();
            if ($requestDate < $minDate || (!$shift && $requestDate > $maxDate)) {
                return null;
            }
        }

        if ($shift) {
            $days = Carbon::parse($oldStart->toDateString())->diffInDays(Carbon::parse($request->start_date), false);
            $newStart = $oldStart->copy()->addDays($days);
            $newEnd = $oldEnd->copy()->addDays($days);
        } else {
            $newStart = Carbon::parse($request->start_date . ' ' . $request->start_time);
            $newEnd = $newStart->copy()->addMinutes(config('app.conference_minute'));
        }

        $model = $isB2c ? B2cConference::class : Conference::class;

        $other = $model::where('mentor_id', $request->mentor_id)
            ->where('conference_id', '!=', $conference->conference_id)
            ->where('start_time', '<', $newEnd)
            ->where('end_time', '>', $newStart)
            ->orderBy('start_time')
            ->first();

        if (!$other) {
            return null;
        }

        $notifications = app(NotificationService::class);

        return [
            'title' => $notifications->conferenceTitle($other, $isB2c),
            'group_name' => $isB2c ? null : Group::where('group_id', $other->group_id)->value('group_name'),
            'start_time' => $notifications->iso($other->start_time),
            'end_time' => $notifications->iso($other->end_time),
        ];
    }

    protected function canManageConference($conference, $user): bool
    {
        if ((int) $conference->mentor_id === (int) $user->user_id || (int) $conference->operator_id === (int) $user->user_id) {
            return true;
        }

        if (!$user->hasRole(['super_admin', 'school_owner', 'school_admin'])) {
            return false;
        }

        $schoolId = User::where('user_id', $conference->mentor_id)->value('school_id')
            ?? User::where('user_id', $conference->operator_id)->value('school_id');

        return $schoolId !== null && (int) $schoolId === (int) $user->school_id;
    }
}
