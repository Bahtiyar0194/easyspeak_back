<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Language;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Conference;
use App\Models\ConferenceTask;
use App\Models\ConferenceMember;

use App\Services\CourseService;
use App\Services\TaskService;

use Illuminate\Http\Request;
use Validator;
use Str;

class ConferenceController extends Controller
{
    protected $courseService;
    protected $taskService;

    public function __construct(Request $request, CourseService $courseService, TaskService $taskService)
    {
        $this->courseService = $courseService;
        $this->taskService = $taskService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $groups = Group::leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
        ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'groups.group_id',
            'groups.level_id',
            'groups.group_name',
            'course_levels_lang.level_name',
            'courses_lang.course_name',
            'courses.course_id'
        )
        ->where('groups.mentor_id', '=', $auth_user->user_id)
        ->where('groups.status_type_id', '=', 1)
        ->get();

        foreach ($groups as $g => $group) {

            $members = GroupMember::where('group_id', '=', $group->group_id)
            ->leftJoin('users', 'group_members.member_id', '=', 'users.user_id')
            ->select(
                'users.user_id',
                'users.last_name',
                'users.first_name',
                'users.avatar'
            )
            ->get();

            $group->members = $members;

            $sections = CourseSection::where('course_sections.level_id', '=', $group->level_id)
            ->select(
                'course_sections.section_id',
                'course_sections.section_name'
            )
            ->distinct()
            ->orderBy('course_sections.section_id', 'asc')
            ->get();

            $group->sections = $sections;

            foreach ($sections as $s => $section) {
                $lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
                ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
                ->where('lessons.section_id', '=', $section->section_id)
                ->where('lessons.lesson_type_id', '=', 1)
                ->where('types_of_lessons_lang.lang_id', '=', $language->lang_id)
                ->select(
                    'lessons.lesson_id',
                    'lessons.lesson_name',
                    'lessons.sort_num',
                    'types_of_lessons_lang.lesson_type_name'
                )
                ->distinct()
                ->orderBy('lessons.sort_num', 'asc')
                ->get();

                $section->lessons = $lessons;
            }
        }

        $attributes = new \stdClass();

        $attributes->groups = $groups;
        return response()->json($attributes, 200);
    }

    public function get_current_conferences(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();
        $current_conferences = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
        ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
        ->leftJoin('users as mentor', 'groups.mentor_id', '=', 'mentor.user_id')
        ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
        ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
        ->select(
            'conferences.uuid',
            'conferences.operator_id',
            'conferences.created_at',
            'conferences.start_time',
            'conferences.end_time',
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
        ->where('conferences.start_time', '<=', now())
        ->where('conferences.end_time', '>=', now())
        ->distinct();

        $isOwner = $auth_user->hasRole(['super_admin', 'school_owner', 'school_admin']);
        $isMentor = $auth_user->hasRole(['mentor']);
        $isLearner = $auth_user->hasRole(['learner']);

        if ($isOwner || $isMentor || $isLearner) {
            $current_conferences->where(function ($query) use ($isOwner, $isMentor, $isLearner, $auth_user) {
                if ($isOwner) {
                    $query->orWhere('mentor.school_id', '=', $auth_user->school_id);
                }
                if ($isMentor || $isLearner) {
                    $query->orWhere('groups.mentor_id', '=', $auth_user->user_id)
                    ->orWhere('group_members.member_id', '=', $auth_user->user_id);
                }
            });
        }        

        $current_conferences = $current_conferences->get()->map(function ($conference) {
            $conference->created_at_formatted = Carbon::parse($conference->created_at)
                ->translatedFormat('d F Y, H:i');
        
            $conference->start_time_formatted = Carbon::parse($conference->start_time)
                ->translatedFormat('d F Y, H:i');
        
            $conference->end_time_formatted = Carbon::parse($conference->end_time)
                ->translatedFormat('d F Y, H:i');

            $members = GroupMember::where('group_id', '=', $conference->group_id)
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


        return response()->json($current_conferences, 200);
    }

    public function get_conference(Request $request)
    {
        $language = Language::where('lang_tag', $request->header('Accept-Language'))->first();
        $auth_user = auth()->user();
    
        // Получаем конференцию без ограничения по времени
        $conference = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
            ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
            ->leftJoin('users', 'groups.mentor_id', '=', 'users.user_id')
            ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
            ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
            ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
            ->leftJoin('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
            ->select(
                'conferences.conference_id',
                'conferences.uuid',
                'conferences.created_at',
                'conferences.start_time',
                'conferences.end_time',
                'conferences.participated',
                'lessons.lesson_name',
                'conferences.lesson_id',
                'courses_lang.course_name',
                'course_levels_lang.level_name',
                'groups.group_name',
                'groups.mentor_id',
                'groups.group_id',
                'users.school_id',
                'group_members.member_id'
            )
            ->where('conferences.uuid', $request->conference_id)
            ->where('courses_lang.lang_id', $language->lang_id)
            ->where('course_levels_lang.lang_id', $language->lang_id)
            ->first();
    
        // Если конференции не существует
        if (!$conference) {
            return response()->json(['message' => 'Conference not found'], 404);
        }
        
        $allowed = false;

        $isOwner = $auth_user->hasRole(['school_owner', 'school_admin']);

        if($isOwner && $auth_user->school_id === $conference->school_id){
            $allowed = true;
        }
    
        if ($conference->mentor_id == $auth_user->user_id) {
            $allowed = true;
        }
    
        $isMember = GroupMember::where('group_id', $conference->group_id)
            ->where('member_id', $auth_user->user_id)
            ->exists();

        if ($isMember) {
            $allowed = true;
        }
        
        if (!$allowed) {
            return response()->json(['type' => 'error', 'message' => 'Access denied'], 403);
        }
        
        // Если конференция уже закончилась
        if (now()->greaterThan(Carbon::parse($conference->end_time))) {
            return response()->json(['type' => 'ended', 'message' => trans('auth.conference_has_already_ended'), 'conference' => $conference], 200);
        }
    
        // Если конференция ещё не началась
        if (now()->lessThan(Carbon::parse($conference->start_time))) {
            return response()->json(['type' => 'pending', 'message' => trans('auth.conference_has_not_started_yet'), 'conference' => $conference], 200);
        }

        $conference->materials = $this->courseService->getLessonMaterials($conference->lesson_id, $language);

        if(count($conference->materials) > 0){
            foreach ($conference->materials as $key => $material) {
                $material->is_show = false;
            }
        }

        $find_conference_member = ConferenceMember::where('conference_id', '=', $conference->conference_id)
        ->where('member_id', '=', $auth_user->user_id)
        ->first();

        if(!isset($find_conference_member)){
            $conference_member = new ConferenceMember();
            $conference_member->conference_id = $conference->conference_id;
            $conference_member->member_id = $auth_user->user_id;
            $conference_member->save();

            $save_conference = Conference::find($conference->conference_id);
            $save_conference->participated = $conference->participated + 1;
            $save_conference->save();
        }
    
        return response()->json(['conference' => $conference], 200);
    }    

    public function get_conference_tasks(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
        $auth_user = auth()->user();

        $conference = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
        ->leftJoin('users', 'groups.mentor_id', '=', 'users.user_id')
        ->select(
            'conferences.conference_id',
            'conferences.uuid',
            'conferences.lesson_id',
            'groups.mentor_id',
            'groups.group_id'
        )
        ->where('conferences.uuid', '=', $request->conference_id)
        ->first();

        // Если конференции не существует
        if (!$conference) {
            return response()->json(['message' => 'Conference not found'], 404);
        }

        if($conference->mentor_id === $auth_user->user_id){
            $get_my_result = false;
        }
        else{
            $get_my_result = true;
        }

        $tasks = $this->taskService->getLessonTasks($conference->lesson_id, $language, $get_my_result);

        if($conference->mentor_id === $auth_user->user_id){
            $members = GroupMember::where('group_id', '=', $conference->group_id)
            ->leftJoin('users', 'group_members.member_id', '=', 'users.user_id')
            ->select(
                'users.user_id',
                'users.last_name',
                'users.first_name',
                'users.avatar'
            )
            ->get();

            foreach ($tasks as $t) {
                $t->learners = collect($members->map(function ($member) {
                    return clone $member;
                }));

                $completed_learners_tasks = 0;

                foreach ($t->learners as $learner) {
                    $task_result = $this->taskService->getTaskResult($t->task_id, $learner->user_id);
                    $learner->task_result = $task_result;
                    if($learner->task_result->completed === true){
                        $completed_learners_tasks++;
                    }
                }

                $t->completed_learners_tasks = $completed_learners_tasks;
            }
        }

        foreach ($tasks as $key => $task) {
            $launched = ConferenceTask::where('conference_tasks.conference_id', '=', $conference->conference_id)
            ->where('conference_tasks.task_id', '=', $task->task_id)
            ->first();

            if(isset($launched)){
                $task->launched = true;

                if($conference->mentor_id !== $auth_user->user_id){
                    if($task->task_result->completed === false){
                        $task->to_complete = true;
                    }
                }
            }
            else{
                $task->launched = false;
            }
        }



        return response()->json($tasks, 200);
    }

    public function run_task(Request $request)
    {
        $conference = Conference::select(
            'conferences.uuid',
            'conferences.conference_id',
        )
        ->where('conferences.uuid', '=', $request->conference_id)
        ->first();
        
        $conference_task = new ConferenceTask();
        $conference_task->conference_id = $conference->conference_id;
        $conference_task->task_id = $request->task_id;
        $conference_task->save();

        return response()->json($conference_task, 200);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer',
            'lesson_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $conference = new Conference();
        $conference->uuid = str_replace('-', '', (string) Str::uuid());
        $conference->group_id = $request->group_id;
        $conference->lesson_id = $request->lesson_id;
        $conference->operator_id = auth()->user()->user_id;
        $conference->start_time = date('Y-m-d H:i:s');
        $conference->end_time = date('Y-m-d H:i:s', strtotime('+2 hour'));
        $conference->save();

        return response()->json($conference, 200);
    }

    public function delete(Request $request)
    {
        $auth_user = auth()->user();

        $conference = Conference::where('uuid', $request->uuid)
        ->first();

        if(isset($conference) && $conference->operator_id === $auth_user->user_id){
            $conference->delete();
            return response()->json('delete conference is success', 200);
        }

        return response()->json('delete conference is failed', 404);
    }
}
