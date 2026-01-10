<?php
namespace App\Services;
use App\Models\Language;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Conference;
use App\Models\Group;
use App\Models\GroupMember;
use Str;
use Carbon\Carbon;

use App\Services\CourseService;

class ConferenceService
{
    protected $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
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

    public function editConference($group_id, $lesson_id, $start_time, $end_time){
        $group = Group::findOrFail($group_id);

        $conference = Conference::where('group_id', '=', $group_id)
        ->where('lesson_id', '=', $lesson_id)
        ->where('forced', '=', 0)
        ->first();

        if(isset($conference)){
            $conference->operator_id = auth()->user()->user_id;
            $conference->mentor_id = $group->mentor_id;
            $conference->start_time = $start_time;
            $conference->end_time = $end_time;
            $conference->save();
        }
    }

    public function createConferences($group_id, $level_id, $start_time, $selected_days)
    {
        $days = json_decode($selected_days);
        $current = Carbon::parse($start_time);

        // Если стартовый день не выбран — смещаем на ближайший выбранный
        if (!in_array($current->dayOfWeekIso, $days)) {
            $current = getNextDate($current, $days);
        }

        $sections = CourseSection::where('level_id', '=', $level_id)
            ->select('section_id')
            ->orderBy('section_id', 'asc')
            ->get();

        foreach ($sections as $s => $section) {

            $lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
                ->where('lessons.section_id', '=', $section->section_id)
                ->whereIn('types_of_lessons.lesson_type_slug', ['conference', 'file_test'])
                ->select('lessons.lesson_id', 'lessons.sort_num')
                ->distinct()
                ->orderBy('lessons.sort_num', 'asc')
                ->get();

            $forced = false;

            foreach ($lessons as $key => $lesson) {

                // Создаём конференцию
                $new_conference = $this->createConference(
                    $group_id,
                    $lesson->lesson_id,
                    $forced,
                    $current->format('Y-m-d H:i:s'),
                    $current->copy()->addHours(env('CONFERENCE_HOUR'))->format('Y-m-d H:i:s')
                );

                // Получаем следующую дату
                $current = getNextDate($current, $days);
            }
        }
    }


    public function editConferences($group_id, $level_id, $start_time, $selected_days){
        $days = json_decode($selected_days);
        $current = Carbon::parse($start_time);

        // Если стартовый день не выбран — смещаем на ближайший выбранный
        if (!in_array($current->dayOfWeekIso, $days)) {
            $current = getNextDate($current, $days);
        }

        $sections = CourseSection::where('level_id', '=', $level_id)
        ->select(
            'section_id'
        )
        ->orderBy('section_id', 'asc')
        ->get();

        foreach ($sections as $s => $section) {
            $lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
            ->where('lessons.section_id', '=', $section->section_id)
            ->whereIn('types_of_lessons.lesson_type_slug', ['conference', 'file_test'])
            ->select(
                'lessons.lesson_id',
                'lessons.sort_num'
            )
            ->distinct()
            ->orderBy('lessons.sort_num', 'asc')
            ->get();

            foreach ($lessons as $key => $lesson) {
                $edit_conference = $this->editConference(
                    $group_id, 
                    $lesson->lesson_id, 
                    $current->format('Y-m-d H:i:s'),
                    $current->copy()->addHours(env('CONFERENCE_HOUR'))->format('Y-m-d H:i:s')
                );
                
                // Получаем следующую дату
                $current = getNextDate($current, $days);
            }
        }
    }

    public function getCurrentConferences($request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

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
        ->where('conferences.start_time', '<=', Carbon::now()->addMinutes(env('CONFERENCE_BEFORE_MINUTES')))
        ->where('conferences.end_time', '>=', now())
        ->distinct();

        $isOwner = $auth_user->hasRole(['super_admin', 'school_owner', 'school_admin']);
        $isMentor = $auth_user->hasRole(['mentor']);
        $isLearner = $auth_user->hasRole(['learner']);
        $isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);

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

        return $current_conferences;
    }
}
?>