<?php
namespace App\Services;
use App\Models\Language;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Conference;
use App\Models\B2cConference;
use App\Models\B2cConferenceLevel;
use App\Models\B2cConferenceMember;
use App\Models\Group;
use App\Models\GroupMember;
use Str;
use Carbon\Carbon;
use DB;

use App\Services\CourseService;
use App\Services\SchoolService;

class ConferenceService
{
    protected $courseService;
    protected $schoolService;

    public function __construct(CourseService $courseService, SchoolService $schoolService)
    {
        $this->courseService = $courseService;
        $this->schoolService = $schoolService;
    }

    public function createConference($group_id, $lesson_id, $forced, $start_time, $end_time){

        $group = Group::findOrFail($group_id);

        $conference = new Conference();
        $conference->uuid = str_replace('-', '', (string) Str::uuid());
        $conference->group_id = $group_id;
        $conference->lesson_id = $lesson_id;
        $conference->forced = $forced;
        $conference->operator_id = auth()->user()->user_id;
        $conference->mentor_id = $group->mentor_id;
        $conference->start_time = $start_time;
        $conference->end_time = $end_time;
        $conference->save();

        return $conference;
    }

    public function createConferences($group_id, $level_id, $start_time, $selected_days, $all_lessons_is_conference){
        $schedule = $this->generateScheduleDates($level_id, $start_time, $selected_days, $all_lessons_is_conference);

        foreach ($schedule as $item) {
            $this->createConference(
                $group_id,
                $item['lesson_id'],
                false,
                $item['start_time'],
                $item['end_time']
            );
        }
    }

    public function editConferences(
        $group_id, 
        $level_id, 
        $start_time, 
        $selected_days, 
        $all_lessons_is_conference, 
        $only_future = false
    ) {
        DB::transaction(function () use (
            $group_id, 
            $level_id, 
            $start_time, 
            $selected_days, 
            $all_lessons_is_conference, 
            $only_future
        ) {
            $group = Group::findOrFail($group_id);
            $operatorId = auth()->user()->user_id;
            $now = Carbon::now();

            if ($only_future) {
                // === РЕЖИМ 1: Изменения только для будущих уроков ===

                // 1. Находим крайний пройденный урок (по максимальной секции и sort_num)
                $lastConference = Conference::join('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
                    ->join('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
                    ->where('conferences.group_id', $group_id)
                    ->where('conferences.start_time', '<=', $now->format('Y-m-d H:i:s'))
                    ->orderBy('course_sections.section_id', 'desc')
                    ->orderBy('lessons.sort_num', 'desc')
                    ->select('course_sections.section_id', 'lessons.sort_num')
                    ->first();

                // 2. Запрашиваем все уроки уровня с учетом нового значения $all_lessons_is_conference
                $futureLessonsQuery = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
                    ->join('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
                    ->where('course_sections.level_id', '=', $level_id);

                if ((int)$all_lessons_is_conference === 0) {
                    $futureLessonsQuery->whereIn('types_of_lessons.lesson_type_slug', ['conference', 'file_test']);
                }

                // 3. Отсекаем все, что было ДО (или РАВНО) последнему пройденному уроку
                if ($lastConference) {
                    $lastSectionId = $lastConference->section_id;
                    $lastSortNum   = $lastConference->sort_num;

                    $futureLessonsQuery->where(function ($query) use ($lastSectionId, $lastSortNum) {
                        $query->where('course_sections.section_id', '>', $lastSectionId)
                            ->orWhere(function ($q) use ($lastSectionId, $lastSortNum) {
                                $q->where('course_sections.section_id', '=', $lastSectionId)
                                ->where('lessons.sort_num', '>', $lastSortNum);
                            });
                    });
                }

                $futureLessonIds = $futureLessonsQuery->pluck('lessons.lesson_id')->toArray();

                // Если будущих уроков не осталось — завершаем
                if (empty($futureLessonIds)) {
                    return;
                }

                // 4. Точка отсчета для предстоящих уроков
                $newStartDate = Carbon::parse($start_time)->isPast() ? $now->format('Y-m-d H:i:s') : $start_time;

                // 5. Генерируем даты только для оставшихся уроков
                $schedule = $this->generateScheduleDates(
                    $level_id, 
                    $newStartDate, 
                    $selected_days, 
                    $all_lessons_is_conference, 
                    $futureLessonIds
                );

                // 6. Удаляем только нефиксированные будущие конференции
                Conference::where('group_id', $group_id)
                    ->where('forced', 0)
                    ->where('start_time', '>', $now->format('Y-m-d H:i:s'))
                    ->delete();

            } else {
                // === РЕЖИМ 2: Полный пересчет всего расписания с самого начала ===

                $schedule = $this->generateScheduleDates(
                    $level_id, 
                    $start_time, 
                    $selected_days, 
                    $all_lessons_is_conference
                );

                $newLessonIds = array_column($schedule, 'lesson_id');

                Conference::where('group_id', $group_id)
                    ->where('forced', 0)
                    ->whereNotIn('lesson_id', $newLessonIds)
                    ->delete();
            }

            // 7. Записываем / обновляем новые даты в базе
            foreach ($schedule as $item) {
                $conference = Conference::where('group_id', $group_id)
                    ->where('lesson_id', $item['lesson_id'])
                    ->where('forced', 0)
                    ->first();

                if ($conference) {
                    $conference->update([
                        'operator_id' => $operatorId,
                        'mentor_id'   => $group->mentor_id,
                        'start_time'  => $item['start_time'],
                        'end_time'    => $item['end_time'],
                    ]);
                } else {
                    Conference::create([
                        'uuid'        => str_replace('-', '', (string) Str::uuid()),
                        'group_id'    => $group_id,
                        'lesson_id'   => $item['lesson_id'],
                        'forced'      => 0,
                        'operator_id' => $operatorId,
                        'mentor_id'   => $group->mentor_id,
                        'start_time'  => $item['start_time'],
                        'end_time'    => $item['end_time'],
                    ]);
                }
            }
        });
    }

    public function getCurrentConferences($request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $isOwner = $auth_user->hasRole(['super_admin', 'school_owner', 'school_admin']);
        $isMentor = $auth_user->hasRole(['mentor']);
        $isLearner = $auth_user->hasRole(['learner']);
        $isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);

        if(!$this->schoolService->isAiSchoolDomain($auth_user->school_id)){
            $current_conferences = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
            ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
            ->leftJoin('users as mentor', 'conferences.mentor_id', '=', 'mentor.user_id')
            ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
            ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
            ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
            ->leftJoin('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
            ->select(
                'conferences.uuid',
                'conferences.lesson_id',
                'conferences.operator_id',
                'conferences.created_at',
                'conferences.start_time',
                'conferences.end_time',
                'conferences.forced',
                'lessons.lesson_name',
                'mentor.first_name as mentor_first_name',
                'mentor.last_name as mentor_last_name',
                'courses_lang.course_name',
                'course_levels_lang.level_name',
                'groups.group_name',
                'groups.group_id'
            )
            ->where('courses_lang.lang_id', '=', $language->lang_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)
            // Доступ за 10 минут до начала
            ->where('conferences.start_time', '<=', Carbon::now()->addMinutes(config('app.conference_before_minutes')))
            ->where('conferences.end_time', '>=', now())
            ->distinct();

            if ($isOwner || $isMentor || $isLearner) {
                $current_conferences->where(function ($query) use ($isOwner, $isMentor, $isLearner, $auth_user) {
                    if ($isOwner) {
                        $query->orWhere('mentor.school_id', '=', $auth_user->school_id);
                    }
                    if ($isMentor || $isLearner) {
                        $query->orWhere('conferences.mentor_id', '=', $auth_user->user_id)
                        ->orWhere('group_members.member_id', '=', $auth_user->user_id)
                        ->where('group_members.status_type_id', '=', 1);
                    }
                });
            }        

            $current_conferences = $current_conferences->get()->map(function ($conference) use($isOnlyLearner, $auth_user) {

                if($isOnlyLearner === true){
                    $conference->is_bought_status = $this->courseService->lessonIsBoughtStatus($conference->lesson_id, $auth_user->user_id);
                }

                $conference->created_at_formatted = Carbon::parse($conference->created_at)
                    ->translatedFormat('H:i');
            
                $conference->start_time_formatted = Carbon::parse($conference->start_time)
                    ->translatedFormat('H:i');
            
                $conference->end_time_formatted = Carbon::parse($conference->end_time)
                    ->translatedFormat('H:i');

                $members = GroupMember::where('group_members.group_id', '=', $conference->group_id)
                ->where('group_members.status_type_id', '=', 1)
                ->leftJoin('users', 'group_members.member_id', '=', 'users.user_id')
                ->select(
                    'users.user_id',
                    'users.last_name',
                    'users.first_name',
                    'users.avatar'
                )
                ->get();

                $conference->members = $members;
            
                return $conference;
            });
        }
        else{
            $current_conferences = B2cConference::leftJoin('users as moderator', 'b2c_conferences.mentor_id', '=', 'moderator.user_id')
            ->leftJoin('users as operator', 'b2c_conferences.operator_id', '=', 'operator.user_id')
            ->leftJoin('files as poster_file', 'b2c_conferences.poster_file_id', '=', 'poster_file.file_id')
            ->leftJoin('b2c_conference_members', 'b2c_conferences.conference_id', '=', 'b2c_conference_members.conference_id')
            ->select(
                'b2c_conferences.conference_id',
                'b2c_conferences.uuid',
                'b2c_conferences.topic',
                'b2c_conferences.topic_description',
                'b2c_conferences.created_at',
                'b2c_conferences.start_time',
                'b2c_conferences.end_time',
                'b2c_conferences.forced',
                'b2c_conferences.operator_id',
                'poster_file.target as poster_file',
                'moderator.avatar as moderator_avatar',
                'moderator.first_name as moderator_first_name',
                'moderator.last_name as moderator_last_name',
            )
            ->where('b2c_conferences.start_time', '<=', Carbon::now()->addMinutes(config('app.conference_before_minutes')))
            ->where('b2c_conferences.end_time', '>=', now())
            ->distinct();

            if($isOnlyLearner){
                $current_conferences->where(function($query) use($auth_user) {
                    $query->where('b2c_conference_members.member_id', '=', $auth_user->user_id)
                    ->orWhere('b2c_conferences.is_free', '=' , 1);
                });
            }

            $current_conferences = $current_conferences->get()->map(function ($conference) use($isOnlyLearner, $auth_user, $language) {

                $levels = B2cConferenceLevel::leftJoin('course_levels_lang', 'b2c_conferences_levels.level_id', '=', 'course_levels_lang.level_id')
                ->select(
                    'course_levels_lang.level_name'
                )
                ->where('b2c_conferences_levels.conference_id', $conference->conference_id)
                ->where('course_levels_lang.lang_id', '=', $language->lang_id)
                ->get();

                $conference->levels = $levels;

                $conference->created_at_formatted = Carbon::parse($conference->created_at)
                    ->translatedFormat('H:i');
            
                $conference->start_time_formatted = Carbon::parse($conference->start_time)
                    ->translatedFormat('H:i');
            
                $conference->end_time_formatted = Carbon::parse($conference->end_time)
                    ->translatedFormat('H:i');

                $members = B2cConferenceMember::leftJoin('users', 'b2c_conference_members.member_id', '=', 'users.user_id')
                ->select(
                    'users.user_id',
                    'users.last_name',
                    'users.first_name',
                    'users.avatar'
                )
                ->where('conference_id', $conference->conference_id)
                ->get();

                $conference->members = $members;
            
                return $conference;
            });
        }

        return $current_conferences;
    }


    /**
     * Генерирует массив Carbon дат для каждого урока курса.
     */
    private function generateScheduleDates(
        int $levelId, 
        string $startTime, 
        string $selectedDaysJson, 
        int $all_lessons_is_conference,
        array $targetLessonIds = [] // Добавили параметр для фильтрации конкретных уроков
    ): array {
        $rawDays = json_decode($selectedDaysJson, true);
        $selectedDays = collect($rawDays)->where('selected', true)->values()->toArray();

        if (empty($selectedDays)) {
            throw new \Exception("Не выбрано ни одного дня недели.");
        }

        $current = Carbon::parse($startTime);
        $selectedDayIds = array_column($selectedDays, 'id');

        if (!in_array($current->dayOfWeekIso, $selectedDayIds)) {
            $current = getNextDate($current, $selectedDays);
        } else {
            $todayConfig = collect($selectedDays)->firstWhere('id', $current->dayOfWeekIso);
            if (!empty($todayConfig['start_time'])) {
                [$hour, $minute] = explode(':', $todayConfig['start_time']);
                $current->setTime((int)$hour, (int)$minute, 0);
            }
        }

        $lessonsQuery = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
            ->join('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
            ->where('course_sections.level_id', '=', $levelId);

        if ((int)$all_lessons_is_conference === 0) {
            $lessonsQuery->whereIn('types_of_lessons.lesson_type_slug', ['conference', 'file_test']);
        }

        // Если передан список конкретных ID уроков (например, только не прошедшие)
        if (!empty($targetLessonIds)) {
            $lessonsQuery->whereIn('lessons.lesson_id', $targetLessonIds);
        }

        $lessons = $lessonsQuery
            ->select('lessons.lesson_id', 'course_sections.section_id', 'lessons.sort_num')
            ->distinct()
            ->orderBy('course_sections.section_id', 'asc')
            ->orderBy('lessons.sort_num', 'asc')
            ->get();

        $schedule = [];

        foreach ($lessons as $lesson) {
            $start = $current->copy();
            $end = $start->copy()->addHours((int) config('app.conference_hour', 2));

            $schedule[] = [
                'lesson_id'  => $lesson->lesson_id,
                'start_time' => $start->format('Y-m-d H:i:s'),
                'end_time'   => $end->format('Y-m-d H:i:s'),
            ];

            $current = getNextDate($current, $selectedDays);
        }

        return $schedule;
    }
}
?>