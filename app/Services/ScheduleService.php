<?php
namespace App\Services;
use App\Models\Language;
use App\Models\User;
use App\Models\Conference;
use App\Models\B2cConference;
use App\Models\B2cConferenceLevel;
use App\Models\B2cConferenceMember;
use App\Models\GroupMember;
use App\Models\BoughtLesson;
use Carbon\Carbon;

use App\Services\CourseService;
use App\Services\SchoolService;

class ScheduleService
{
    protected $courseService;
    protected $schoolService;

    public function __construct(CourseService $courseService, SchoolService $schoolService)
    {
        $this->courseService = $courseService;
        $this->schoolService = $schoolService;
    }

    /**
     * Расписание пользователя.
     *
     * $from / $to (необязательно) — период по start_time: [from, to). Календарь
     * грузит расписание помесячно; без периода — всё, как раньше.
     *
     * Участники, покупки уроков и уровни загружаются пакетно (несколько запросов
     * на весь ответ), а не отдельными запросами на каждую конференцию и участника.
     */
    public function getSchedule($request, $user_id, $lang_id, $for_dashboard, $group_id, $from = null, $to = null){

        $user = User::find($user_id);
        $isOnlyLearner = $user->hasOnlyRoles(['learner']);

        if(!$this->schoolService->isAiSchoolDomain($user->school_id)){
            $conferences = $this->b2bQuery($request, $user, $lang_id, $for_dashboard, $group_id);
            $this->applyPeriod($conferences, 'conferences.start_time', $from, $to);

            return $this->decorateB2b($conferences->get(), $user, $isOnlyLearner);
        }

        $conferences = $this->b2cQuery($for_dashboard);
        $this->applyPeriod($conferences, 'b2c_conferences.start_time', $from, $to);

        return $this->decorateB2c($conferences->get(), $user, $isOnlyLearner, $lang_id);
    }

    /**
     * Сколько уроков в каждом месяце года — для режима «Год» в календаре.
     * Те же фильтры и права доступа, что у getSchedule, но один запрос с
     * GROUP BY вместо загрузки самих уроков. Возвращает 12 чисел (январь — [0]).
     */
    public function getMonthCounts($request, $user_id, $lang_id, $year): array
    {
        $user = User::find($user_id);
        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = $from->copy()->addYear();

        if(!$this->schoolService->isAiSchoolDomain($user->school_id)){
            $query = $this->b2bQuery($request, $user, $lang_id, false, null);
            $table = 'conferences';
        }
        else{
            $query = $this->b2cQuery(false);
            $table = 'b2c_conferences';
        }

        $this->applyPeriod($query, $table . '.start_time', $from, $to);

        // Тот же запрос, но вместо строк — количество уроков по месяцам
        $query->getQuery()->distinct = false;
        // select() заменяет колонки выборки (selectRaw добавил бы к ним)
        $rows = $query->reorder()
        ->select(\DB::raw('MONTH(' . $table . '.start_time) as month_num, COUNT(DISTINCT ' . $table . '.conference_id) as total'))
        ->groupBy('month_num')
        ->get();

        $counts = array_fill(0, 12, 0);

        foreach ($rows as $row) {
            $counts[(int) $row->month_num - 1] = (int) $row->total;
        }

        return $counts;
    }

    /** Период по началу урока: [from, to) */
    protected function applyPeriod($query, string $column, $from, $to): void
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<', $to);
        }
    }

    /** Конференции обычной школы: выборка, фильтры из запроса и права доступа */
    protected function b2bQuery($request, $user, $lang_id, $for_dashboard, $group_id)
    {
        $isOwner = $user->hasRole(['super_admin', 'school_owner', 'school_admin']);
        $isMentor = $user->hasRole(['mentor']);
        $isLearner = $user->hasRole(['learner']);

        $conferences = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
        ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
        ->leftJoin('users as mentor', 'conferences.mentor_id', '=', 'mentor.user_id')
        ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
        ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
        ->leftJoin('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
        ->leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
        ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
        ->select(
            'conferences.uuid',
            'conferences.lesson_id',
            'conferences.start_time',
            'conferences.end_time',
            'conferences.forced',
            'conferences.moved',
            'lessons.lesson_name',
            'types_of_lessons_lang.lesson_type_name',
            'course_sections.section_name',
            'mentor.avatar as mentor_avatar',
            'mentor.first_name as mentor_first_name',
            'mentor.last_name as mentor_last_name',
            'courses_lang.course_name',
            'course_levels_lang.level_name',
            'conferences.mentor_id',
            'conferences.operator_id',
            'groups.group_name',
            'groups.group_id',
            'groups.current_price'
        )
        ->where('courses_lang.lang_id', '=', $lang_id)
        ->where('course_levels_lang.lang_id', '=', $lang_id)
        ->where('types_of_lessons_lang.lang_id', '=', $lang_id)
        ->orderBy('conferences.start_time', 'asc')
        ->distinct();

        $course_id = $request->course_id;
        $levels_id = $request->levels;
        $mentors_id = $request->mentors;
        $group_name = $request->group_name;
        $lesson_name = $request->lesson_name;
        $started_at_from = $request->started_at_from;
        $started_at_to = $request->started_at_to;

        if (!empty($course_id)) {
            $conferences->where('courses.course_id', $course_id);
        }

        if (!empty($levels_id)) {
            $conferences->whereIn('course_levels.level_id', $levels_id);
        }

        if(!empty($mentors_id)){
            $conferences->whereIn('conferences.mentor_id', $mentors_id);
        }

        if (isset($group_id)) {
            $conferences->where('groups.group_id', '=', $group_id);
        }

        if (!empty($group_name)) {
            $conferences->where('groups.group_name', 'LIKE', '%' . $group_name . '%');
        }

        if (!empty($lesson_name)) {
            $conferences->where('lessons.lesson_name', 'LIKE', '%' . $lesson_name . '%');
        }

        if ($started_at_from) {
            $conferences->where('groups.started_at', '>=', $started_at_from . ' 00:00:00');
        }

        if ($started_at_to) {
            $conferences->where('groups.started_at', '<=', $started_at_to . ' 23:59:00');
        }

        if($for_dashboard === true){
            // Ещё не закончившиеся уроки (в т.ч. идущий сейчас) на месяц вперёд
            $monthAhead = Carbon::now()->addMonth();
            $conferences->where('conferences.end_time', '>=', now())
            ->where('conferences.start_time', '<=', $monthAhead);
        }

        if ($isOwner || $isMentor || $isLearner) {
            $conferences->where(function ($query) use ($isOwner, $isMentor, $isLearner, $user) {

                // Если пользователь — владелец, он видит всю школу
                if ($isOwner) {
                    $query->where('mentor.school_id', '=', $user->school_id);
                }

                // Если ментор или ученик, добавляем их персональные условия
                if ($isMentor || $isLearner) {
                    // orWhere — чтобы не конфликтовать с условием владельца (если $isOwner тоже true)
                    $method = $isOwner ? 'orWhere' : 'where';

                    $query->$method(function ($subQuery) use ($user) {
                        $subQuery->where('conferences.mentor_id', '=', $user->user_id)
                        ->orWhere('group_members.member_id', '=', $user->user_id);
                    });
                }
            });

            // Статус участника — только для менторов/учеников, чтобы не ломать выборку владельца
            if (!$isOwner) {
                $conferences->where('group_members.status_type_id', '=', 1);
            }
        }

        return $conferences;
    }

    /** Конференции ИИ-школы */
    protected function b2cQuery($for_dashboard)
    {
        $conferences = B2cConference::leftJoin('users as moderator', 'b2c_conferences.mentor_id', '=', 'moderator.user_id')
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
            'b2c_conferences.is_free',
            'b2c_conferences.mentor_id',
            'b2c_conferences.operator_id',
            'poster_file.target as poster_file',
            'moderator.avatar as moderator_avatar',
            'moderator.first_name as moderator_first_name',
            'moderator.last_name as moderator_last_name'
        )
        ->orderBy('b2c_conferences.start_time', 'asc')
        ->distinct();

        if($for_dashboard === true){
            // Ещё не закончившиеся уроки (в т.ч. идущий сейчас) на месяц вперёд
            $monthAhead = Carbon::now()->addMonth();

            $conferences->where('b2c_conferences.end_time', '>=', now())
            ->where('b2c_conferences.start_time', '<=', $monthAhead);
        }

        return $conferences;
    }

    /** Поля времени, общие для обеих веток */
    protected function decorateTimes($conference): void
    {
        $conference->start_time_formatted = humanDate($conference->start_time, null);

        $conference->end_time_formatted = humanDate($conference->end_time, null);

        $conference->is_gone = now() > $conference->end_time;

        // Сколько секунд до конца урока (0 — уже закончился). Клиент отсчитывает
        // от момента загрузки — не зависит от часового пояса и часов устройства
        $conference->ends_in_seconds = max(0, now()->diffInSeconds(Carbon::parse($conference->end_time), false));

        $conference->date = Carbon::parse($conference->start_time)
            ->translatedFormat('Y-m-d');

        $conference->date_end = Carbon::parse($conference->end_time)
            ->translatedFormat('Y-m-d');

        $conference->time = Carbon::parse($conference->start_time)
            ->translatedFormat('H:i');

        $conference->time_end = Carbon::parse($conference->end_time)
            ->translatedFormat('H:i');
    }

    /** Идёт ли конференция сейчас (вход открыт за N минут до начала) */
    protected function isActive($conference): bool
    {
        return now()->between(Carbon::parse($conference->start_time)->subMinutes(config('app.conference_before_minutes')), Carbon::parse($conference->end_time));
    }

    /** Копия модели без служебного поля, по которому группировали */
    protected function withoutKey($model, string $key)
    {
        $copy = clone $model;
        unset($copy->{$key});
        return $copy;
    }

    protected function decorateB2b($conferences, $user, $isOnlyLearner)
    {
        $groupIds = $conferences->pluck('group_id')->filter()->unique()->values();
        $lessonIds = $conferences->pluck('lesson_id')->filter()->unique()->values();

        // Активные участники всех групп — одним запросом
        $membersByGroup = GroupMember::whereIn('group_members.group_id', $groupIds)
        ->where('group_members.status_type_id', '=', 1)
        ->leftJoin('users', 'group_members.member_id', '=', 'users.user_id')
        ->select(
            'group_members.group_id as schedule_group_id',
            'users.user_id',
            'users.last_name',
            'users.first_name',
            'users.avatar'
        )
        ->orderBy('group_members.id')
        ->get()
        ->groupBy('schedule_group_id');

        // Покупки уроков всеми участниками (и самим пользователем) — одним запросом.
        // Ключ «ученик:урок», берём первую запись, как раньше делал first()
        $learnerIds = $membersByGroup->flatten(1)->pluck('user_id')->push($user->user_id)->unique()->values();

        $boughtLessons = $lessonIds->isEmpty() ? collect() : BoughtLesson::whereIn('lesson_id', $lessonIds)
        ->whereIn('learner_id', $learnerIds)
        ->orderBy('id')
        ->get()
        ->groupBy(function ($bought) {
            return $bought->learner_id . ':' . $bought->lesson_id;
        })
        ->map(function ($items) {
            return $items->first();
        });

        return $conferences->map(function ($conference) use ($isOnlyLearner, $user, $membersByGroup, $boughtLessons) {

            if($isOnlyLearner === true){
                // Как CourseService::lessonIsBoughtStatus, но из пакета
                $status = new \stdClass();
                $own = $boughtLessons->get($user->user_id . ':' . $conference->lesson_id);

                if(isset($own)){
                    $status->is_free = $own->is_free === 1;
                    $status->is_bought = true;
                }
                else{
                    $status->is_bought = false;
                }

                $conference->is_bought_status = $status;
            }

            $this->decorateTimes($conference);

            // Свои копии участников: is_bought у каждой конференции свой
            $members = collect($membersByGroup->get($conference->group_id, []))
            ->map(function ($member) use ($conference, $boughtLessons) {
                $member = $this->withoutKey($member, 'schedule_group_id');
                $bought_lesson = $boughtLessons->get($member->user_id . ':' . $conference->lesson_id);

                $member->is_bought = isset($bought_lesson);
                $member->bought_lesson = $bought_lesson;

                return $member;
            })
            ->values();

            $conference->members = new \Illuminate\Database\Eloquent\Collection($members->all());

            $conference->is_member = $members->contains('user_id', $user->user_id);

            //Идет ли конференция сейчас?
            $conference->is_active = $this->isActive($conference);

            return $conference;
        });
    }

    protected function decorateB2c($conferences, $user, $isOnlyLearner, $lang_id)
    {
        $conferenceIds = $conferences->pluck('conference_id')->unique()->values();

        // Уровни и участники всех конференций — по одному запросу
        $levelsByConference = B2cConferenceLevel::leftJoin('course_levels_lang', 'b2c_conferences_levels.level_id', '=', 'course_levels_lang.level_id')
        ->select(
            'b2c_conferences_levels.conference_id as schedule_conference_id',
            'course_levels_lang.level_name'
        )
        ->whereIn('b2c_conferences_levels.conference_id', $conferenceIds)
        ->where('course_levels_lang.lang_id', '=', $lang_id)
        ->orderBy('b2c_conferences_levels.id')
        ->get()
        ->groupBy('schedule_conference_id');

        $membersByConference = B2cConferenceMember::leftJoin('users', 'b2c_conference_members.member_id', '=', 'users.user_id')
        ->select(
            'b2c_conference_members.conference_id as schedule_conference_id',
            'users.user_id',
            'users.last_name',
            'users.first_name',
            'users.avatar'
        )
        ->whereIn('b2c_conference_members.conference_id', $conferenceIds)
        ->orderBy('b2c_conference_members.conference_member_id')
        ->get()
        ->groupBy('schedule_conference_id');

        $strip = function ($items) {
            return new \Illuminate\Database\Eloquent\Collection(
                collect($items)->map(function ($item) {
                    return $this->withoutKey($item, 'schedule_conference_id');
                })->values()->all()
            );
        };

        return $conferences->map(function ($conference) use ($isOnlyLearner, $user, $levelsByConference, $membersByConference, $strip) {

            $conference->is_learner = $isOnlyLearner === true;

            $conference->levels = $strip($levelsByConference->get($conference->conference_id, []));

            $this->decorateTimes($conference);

            $members = $strip($membersByConference->get($conference->conference_id, []));

            $conference->members = $members;

            $conference->is_member = $members->contains('user_id', $user->user_id);

            //Идет ли конференция сейчас?
            $conference->is_active = $this->isActive($conference);

            return $conference;
        });
    }
}
?>
