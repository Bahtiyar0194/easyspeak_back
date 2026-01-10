<?php
namespace App\Services;
use App\Models\Language;
use App\Models\User;
use App\Models\Conference;
use App\Models\GroupMember;
use App\Models\BoughtLesson;
use Carbon\Carbon;

use App\Services\CourseService;

class ScheduleService
{
    protected $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    public function getSchedule($request, $user_id, $lang_id, $for_dashboard, $group_id){

        $user = User::find($user_id);

        $conferences = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
        ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
        ->leftJoin('users as mentor', 'conferences.mentor_id', '=', 'mentor.user_id')
        ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
        ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
        ->leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
        ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
        ->select(
            'conferences.uuid',
            'conferences.lesson_id',
            // 'conferences.operator_id',
            // 'conferences.created_at',
            'conferences.start_time',
            'conferences.end_time',
            'conferences.forced',
            'conferences.moved',
            'lessons.lesson_name',
            'types_of_lessons_lang.lesson_type_name',
            'mentor.avatar as mentor_avatar',
            'mentor.first_name as mentor_first_name',
            'mentor.last_name as mentor_last_name',
            'courses_lang.course_name',
            'course_levels_lang.level_name',
            'conferences.mentor_id',
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

        if ($started_at_from && $started_at_to) {
            $conferences->whereBetween('groups.started_at', [$started_at_from . ' 00:00:00', $started_at_to . ' 23:59:00']);
        }

        if ($started_at_from) {
            $conferences->where('groups.started_at', '>=', $started_at_from . ' 00:00:00');
        }

        if ($started_at_to) {
            $conferences->where('groups.started_at', '<=', $started_at_to . ' 23:59:00');
        }

        if($for_dashboard === true){
            // Вывести и текущий урок за 2 часа
            $threshold = Carbon::now()->subHours(env('CONFERENCE_HOUR'))->format('Y-m-d H:i:s');
            // На месяц вперед

            $monthAhead = Carbon::now()->addMonth();
            $conferences->where('conferences.start_time', '>=', $threshold)
            ->where('conferences.start_time', '<=', $monthAhead);
        }

        $isOwner = $user->hasRole(['super_admin', 'school_owner', 'school_admin']);
        $isMentor = $user->hasRole(['mentor']);
        $isLearner = $user->hasRole(['learner']);
        $isOnlyLearner = $user->hasOnlyRoles(['learner']);

        if ($isOwner || $isMentor || $isLearner) {
            $conferences->where(function ($query) use ($isOwner, $isMentor, $isLearner, $user) {
                if ($isOwner) {
                    $query->orWhere('mentor.school_id', '=', $user->school_id);
                }
                if ($isMentor || $isLearner) {
                    $query->orWhere('conferences.mentor_id', '=', $user->user_id)
                    ->orWhere('group_members.member_id', '=', $user->user_id);
                }
            });
        }


        $conferences = $conferences->get()->map(function ($conference) use($isOnlyLearner, $user) {

            if($isOnlyLearner === true){
                $conference->is_bought_status = $this->courseService->lessonIsBoughtStatus($conference->lesson_id, $user->user_id);

                if(isset($group_member)){
                    $conference->lesson_price = $group_member->lesson_price;
                }
            }

            $conference->start_time_formatted = humanDate($conference->start_time);

            $conference->end_time_formatted = humanDate($conference->end_time);

            $conference->is_gone = now() > $conference->end_time;

        
            $conference->date = Carbon::parse($conference->start_time)
                ->translatedFormat('Y-m-d');

            $conference->time = Carbon::parse($conference->start_time)
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

            foreach ($members as $key => $member) {
                $bought_lesson = BoughtLesson::where('learner_id', $member->user_id)
                ->where('lesson_id', $conference->lesson_id)
                ->first();

                $member->is_bought = isset($bought_lesson);
                $member->bought_lesson = $bought_lesson;
            }

            $conference->members = $members;

            //Идет ли конференция сейчас?
            $conference->is_active = now()->between(Carbon::parse($conference->start_time)->subMinutes(env('CONFERENCE_BEFORE_MINUTES')), Carbon::parse($conference->end_time));
        
            return $conference;
        });

        return $conferences;
    }
}
?>