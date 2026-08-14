<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Language;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Conference;
use App\Models\B2cConference;
use App\Models\B2cConferenceLevel;
use App\Models\LearnerLevelPayment;
use App\Models\ConferenceTask;
use App\Models\B2cConferenceTask;
use App\Models\ConferenceMember;
use App\Models\B2cConferenceMember;
use App\Models\UploadConfiguration;
use App\Models\MediaFile;
use App\Models\TelegramToken;

use App\Services\SchoolService;
use App\Services\ConferenceService;
use App\Services\CourseService;
use App\Services\TaskService;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Validator;
use Str;
use Storage;
use Image;

use Mail;
use App\Mail\SignedUpToConferenceMail;

use App\Jobs\SendTelegramMessage;

class ConferenceController extends Controller
{
    protected $schoolService;
    protected $conferenceService;
    protected $courseService;
    protected $taskService;

    public function __construct(Request $request, SchoolService $schoolService, ConferenceService $conferenceService, CourseService $courseService, TaskService $taskService)
    {
        $this->schoolService = $schoolService;
        $this->conferenceService = $conferenceService;
        $this->courseService = $courseService;
        $this->taskService = $taskService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $attributes = new \stdClass();

        if($this->schoolService->isAiSchoolDomain($auth_user->school_id)){

            $courses = $this->courseService->getCourses($request);

            foreach ($courses as $c => $course) {
                $levels = $this->courseService->getCourseLevels($course->course_id, $language->lang_id);

                $course->levels = $levels;
            }

            $attributes->courses = $courses;
        }
        else{
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

                $members = GroupMember::where('group_members.group_id', '=', $group->group_id)
                ->where('group_members.status_type_id', '=', 1)
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
                    ->whereIn('types_of_lessons.lesson_type_slug', ['conference', 'file_test'])
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
            
            $attributes->groups = $groups;
        }

        return response()->json($attributes, 200);
    }

    public function get_current_conferences(Request $request)
    {
        $current_conferences = $this->conferenceService->getCurrentConferences($request);

        return response()->json($current_conferences, 200);
    }

    public function get_conference(Request $request)
    {
        $language = Language::where('lang_tag', $request->header('Accept-Language'))->first();
        $auth_user = auth()->user();

        if(!$this->schoolService->isAiSchoolDomain($auth_user->school_id)){
            // Получаем конференцию без ограничения по времени
            $conference = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
            ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
            ->leftJoin('users', 'conferences.mentor_id', '=', 'users.user_id')
            ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
            ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
            ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
            ->leftJoin('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
            ->leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
            ->select(
                'conferences.conference_id',
                'conferences.uuid',
                'conferences.created_at',
                'conferences.start_time',
                'conferences.end_time',
                'conferences.participated',
                'conferences.forced',
                'lessons.lesson_name',
                'types_of_lessons.lesson_type_slug',
                'conferences.lesson_id',
                'courses_lang.course_name',
                'course_levels_lang.level_name',
                'groups.group_name',
                'conferences.mentor_id',
                'groups.group_id',
                'users.school_id',
                'group_members.member_id'
            )
            ->where('conferences.uuid', $request->conference_id)
            ->where('courses_lang.lang_id', $language->lang_id)
            ->where('course_levels_lang.lang_id', $language->lang_id)
            ->first();
        }
        else{
            $conference = B2cConference::select(
                'b2c_conferences.conference_id',
                'b2c_conferences.uuid',
                'b2c_conferences.created_at',
                'b2c_conferences.start_time',
                'b2c_conferences.end_time',
                'b2c_conferences.participated',
                'b2c_conferences.mentor_id',
                'b2c_conferences.topic'
            )
            ->where('b2c_conferences.uuid', $request->conference_id)
            ->first();
        }

        // Если конференции не существует
        if (!$conference) {
            return response()->json(['message' => 'Conference not found'], 404);
        }

        $allowed = false;

        $isOwner = $auth_user->hasRole(['school_owner', 'school_admin']);
        $isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);

        $conference->is_only_learner = $isOnlyLearner;
        $conference->lesson_type_slug = 'conference';

        if(!$this->schoolService->isAiSchoolDomain($auth_user->school_id)){
            if($isOnlyLearner === true){
                $conference->is_bought_status = $this->courseService->lessonIsBoughtStatus($conference->lesson_id, $auth_user->user_id);
            }

            if($isOwner && $auth_user->school_id === $conference->school_id){
                $allowed = true;
            }
        
            if ($conference->mentor_id == $auth_user->user_id) {
                $allowed = true;
            }
        
            $isMember = GroupMember::where('group_id', $conference->group_id)
            ->where('status_type_id', '=', 1)
            ->where('member_id', $auth_user->user_id)
            ->exists();

            if ($isMember) {
                $allowed = true;
                $conference->is_member = true;
            }
        }
        else{
            if($isOwner){
                $allowed = true;
            }

            if ($conference->mentor_id == $auth_user->user_id) {
                $allowed = true;
            }

            $isMember = B2cConferenceMember::where('conference_id', $conference->conference_id)
            ->where('member_id', $auth_user->user_id)
            ->exists();

            if($isMember) {
                $allowed = true;
                $conference->is_member = true;
            }
        }
        
        if (!$allowed) {
            return response()->json(['type' => 'error', 'message' => 'Access denied'], 403);
        }
        
        // Если конференция уже закончилась
        if (now()->greaterThan(Carbon::parse($conference->end_time))) {
            return response()->json(['type' => 'ended', 'message' => trans('auth.conference_has_already_ended'), 'conference' => $conference], 200);
        }
    
        // Если конференция ещё не началась
        if (now()->lessThan(Carbon::parse($conference->start_time)->subMinutes(env('CONFERENCE_BEFORE_MINUTES')))) {
            return response()->json(['type' => 'pending', 'message' => trans('auth.conference_has_not_started_yet'), 'conference' => $conference], 200);
        }

        if(!$this->schoolService->isAiSchoolDomain($auth_user->school_id)){
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
        }
        else{
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
        }
    
        return response()->json(['conference' => $conference], 200);
    }    

    public function get_conference_tasks(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $auth_user = auth()->user();

        if(!$this->schoolService->isAiSchoolDomain($auth_user->school_id)){
            $conference = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
            ->select(
                'conferences.conference_id',
                'conferences.uuid',
                'conferences.lesson_id',
                'conferences.mentor_id',
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

            return response()->json([
                'tasks' => $tasks
            ], 200);
        }
        else{
            $conference = B2cConference::select(
                'b2c_conferences.conference_id',
                'b2c_conferences.mentor_id',
            )
            ->where('b2c_conferences.uuid', '=', $request->conference_id)
            ->first();

            // Если конференции не существует
            if (!$conference) {
                return response()->json(['message' => 'Conference not found'], 404);
            }

            $tasks = B2cConferenceTask::leftJoin('tasks', 'b2c_conference_tasks.task_id', '=', 'tasks.task_id')
            ->leftJoin('tasks_lang', 'tasks_lang.task_id', '=', 'tasks.task_id')
            ->leftJoin('types_of_tasks', 'types_of_tasks.task_type_id', '=', 'tasks.task_type_id')
            ->leftJoin('types_of_tasks_lang', 'types_of_tasks_lang.task_type_id', '=', 'types_of_tasks.task_type_id')
            ->leftJoin('task_options', 'tasks.task_id', '=', 'task_options.task_id')
            ->select(
                'b2c_conference_tasks.conference_task_id',
                'tasks.task_id',
                'tasks.task_slug',
                'tasks.task_example',
                'tasks.task_type_id',
                'tasks.sort_num',
                'types_of_tasks.task_type_component',
                'types_of_tasks.icon',
                'types_of_tasks_lang.task_type_name',
                'tasks_lang.task_name',
                'tasks.created_at'
            )     
            ->where('tasks_lang.lang_id', '=', $language->lang_id)
            ->where('types_of_tasks_lang.lang_id', '=', $language->lang_id)    
            ->where('b2c_conference_tasks.conference_id', '=', $conference->conference_id)
            ->distinct()
            ->orderBy('b2c_conference_tasks.conference_task_id', 'asc')
            ->get();

            if($conference->mentor_id === $auth_user->user_id){

                $members = B2cConferenceMember::leftJoin('users', 'b2c_conference_members.member_id', '=', 'users.user_id')
                ->select(
                    'users.user_id',
                    'users.last_name',
                    'users.first_name',
                    'users.avatar'
                )
                ->where('conference_id', $conference->conference_id)
                ->get();

                foreach ($tasks as $t) {
                    $t->learners = collect($members->map(function ($member) {
                        return clone $member;
                    }));

                    $t->launched = true;

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

                $levels = B2cConferenceLevel::leftJoin('course_levels', 'b2c_conferences_levels.level_id', '=', 'course_levels.level_id')
                ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
                ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
                ->select(
                    'b2c_conferences_levels.level_id',
                    'course_levels_lang.level_name',
                    'course_levels.level_id',
                    'course_levels.level_slug',
                    'course_levels.is_available_always',
                    'courses.course_name_slug'
                )
                ->where('b2c_conferences_levels.conference_id', $conference->conference_id)
                ->where('course_levels_lang.lang_id', '=', $language->lang_id)
                ->get();

                foreach ($levels as $key => $level) {
                    $level->available_status = $this->courseService->levelAvailableStatus($level, $auth_user);

                    $sections = $this->courseService->getLevelSections($level->level_id);

                    foreach ($sections as $s => $section) {

                        $lessons = $this->courseService->getLessons($section->section_id, $language->lang_id);

                        foreach ($lessons as $l => $lesson) {
                            $lesson->tasks = $this->taskService->getLessonTasks($lesson->lesson_id, $language, false);
                        }

                        $section->lessons = $lessons;
                    }

                    $level->sections = $sections;
                }

                return response()->json([
                    'levels' => $levels,
                    'tasks' => $tasks
                ], 200);
            }
            else{

                if(count($tasks) > 0){
                    foreach ($tasks as $key => $task) {
                        $task->launched = true;
                        $task->task_result = $this->taskService->getTaskResult($task->task_id, $auth_user->user_id);

                        if($task->task_result->completed === false){
                            $task->to_complete = true;
                        }
                    }
                }

                return response()->json([
                    'tasks' => $tasks
                ], 200);
            }
        }
    }

    public function run_task(Request $request)
    {
        $auth_user = auth()->user();

        if(!$this->schoolService->isAiSchoolDomain($auth_user->school_id)){
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
        }
        else{
            $conference = B2cConference::select(
                'b2c_conferences.uuid',
                'b2c_conferences.conference_id',
            )
            ->where('b2c_conferences.uuid', '=', $request->conference_id)
            ->first();
            
            $conference_task = new B2cConferenceTask();
            $conference_task->conference_id = $conference->conference_id;
            $conference_task->task_id = $request->task_id;
            $conference_task->save();
        }

        return response()->json($conference_task, 200);
    }

    public function create(Request $request)
    {
        $auth_user = auth()->user();

        $mode = $request->mode;

        if($this->schoolService->isAiSchoolDomain($auth_user->school_id)){
            
            $upload_config = UploadConfiguration::where('material_type_id', '=', 3)
            ->first();

            $rules = [
                'course_id' => 'required|integer',
                'levels' => 'required|array',
                'conf_topic' => 'required|string',
                'upload_poster_file_create' => 'file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb
            ];
        }
        else{
            $rules = [
                'group_id' => 'required|integer',
                'lesson_id' => 'required|integer'
            ];
        }

        if($mode === 'plan'){
            $rules['start_time'] = 'required|date|after_or_equal:now';
        }

        if($mode === 'current'){
            $start_time = date('Y-m-d H:i:s');
            $end_time = date('Y-m-d H:i:s', strtotime('+2 hour'));
        }
        else{
            $start_time = $request->start_time;
            $end_time = Carbon::parse($request->start_time)->addHours(2);
        }

        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if($this->schoolService->isAiSchoolDomain($auth_user->school_id)){

            $new_conference = new B2cConference();
            $new_conference->uuid = str_replace('-', '', (string) Str::uuid());
            $new_conference->topic = $request->conf_topic;

            if(isset($request->conf_topic_description)){
                $new_conference->topic_description = $request->conf_topic_description;
            }

            $new_conference->start_time = $start_time;
            $new_conference->end_time = $end_time;
            $new_conference->mentor_id = $auth_user->user_id;
            $new_conference->operator_id = $auth_user->user_id;

            $poster_file = $request->file('upload_poster_file_create');

            if($poster_file){
                $file_name = $poster_file->hashName();

                $resized_image = Image::make($poster_file)->resize(500, null, function ($constraint) {
                    $constraint->aspectRatio();
                })->stream('png', 80);

                Storage::disk('local')->put('/public/'.$file_name, $resized_image);

                $new_file = new MediaFile();
                $new_file->file_name = $request->conf_topic;
                $new_file->target = $file_name;
                $new_file->size = $poster_file->getSize() / 1048576;
                $new_file->material_type_id = 3;
                $new_file->save();

                $new_conference->poster_file_id = $new_file->file_id;
            }

            $new_conference->save();

            $new_conference->levels()->attach($request->levels);
        }
        else{
            $forced = true;

            $new_conference = $this->conferenceService->createConference($request->group_id, $request->lesson_id, $forced, $start_time, $end_time);
        }

        return response()->json($new_conference, 200);
    }

    public function delete(Request $request)
    {
        $auth_user = auth()->user();

        $conference = Conference::where('uuid', $request->uuid)
        ->firstOrFail();

        if(isset($conference) && $conference->operator_id === $auth_user->user_id){
            $conference->delete();
            return response()->json('Delete conference is success', 200);
        }

        return response()->json('Delete conference is failed', 404);
    }

    public function accept(Request $request)
    {
        $auth_user = auth()->user();

        // Записываем результат работы сервиса в переменную
        $conference = $this->courseService->levelIsBoughtStatus($request->uuid, $auth_user->user_id);

        // Если сервис вернул объект (что приравнивается к true), у нас есть доступ и сама конференция
        if ($conference) { 

            if(isset($conference->conferences_remain) && $conference->conferences_remain <= 0){
                return response()->json([
                    'message' => 'limit_has_been_reached'
                ], 400);
            }

            $isMember = B2cConferenceMember::where('conference_id', $conference->conference_id)
            ->where('member_id', $auth_user->user_id)
            ->exists();

            if (!$isMember) {
                $moderator = User::findOrFail($conference->mentor_id);

                $moderator_selected_language = Language::find($moderator->lang_id);

                if(isset($moderator_selected_language)){
                    app()->setLocale($moderator_selected_language->lang_tag);
                }

                $moderator_telegram_token = TelegramToken::where('user_id', $moderator->user_id)
                ->first();

                $mail_body = new \stdClass();
                $mail_body->subject = trans('app.bot.conference.signed_up');
                $mail_body->learner_name = $auth_user->last_name.' '.$auth_user->first_name;
                $mail_body->conf_url = $request->header('Origin') . '/dashboard/conference/'.$conference->uuid;
                $mail_body->start_time = humanDate($conference->start_time, $moderator_selected_language->lang_tag);
                $mail_body->for_moderator = true;
                $mail_body->conference = $conference;

                if(isset($moderator_telegram_token)){
                    SendTelegramMessage::dispatch(
                        $moderator_telegram_token->chat_id,
                        trans('app.bot.conference.accept.moderator', [
                            'learner_name' => $auth_user->last_name.' '.$auth_user->first_name,
                            'moderator_name' => $moderator->last_name.' '.$moderator->first_name,
                            'conf_url' => $request->header('Origin') . '/dashboard/conference/'.$conference->uuid,
                            'start_time' => humanDate($conference->start_time, $moderator_selected_language->lang_tag),
                            'lesson_name' => $conference->topic
                        ]),
                        null
                    );
                }
                else{
                    Mail::to($moderator->email)->send(new SignedUpToConferenceMail($mail_body));
                }

                $learner_selected_language = Language::find($auth_user->lang_id);

                if(isset($learner_selected_language)){
                    app()->setLocale($learner_selected_language->lang_tag);
                }

                $learner_telegram_token = TelegramToken::where('user_id', $auth_user->user_id)
                ->first();

                $mail_body->for_moderator = false;
                $mail_body->moderator_name = $moderator->last_name.' '.$moderator->first_name;

                if(isset($learner_telegram_token)){
                    SendTelegramMessage::dispatch(
                        $learner_telegram_token->chat_id,
                        trans('app.bot.conference.accept.learner', [
                            'learner_name' => $auth_user->last_name.' '.$auth_user->first_name,
                            'moderator_name' => $moderator->last_name.' '.$moderator->first_name,
                            'conf_url' => $request->header('Origin') . '/dashboard/conference/'.$conference->uuid,
                            'start_time' => humanDate($conference->start_time, $learner_selected_language->lang_tag),
                            'lesson_name' => $conference->topic
                        ]),
                        null
                    );
                }
                else{
                    Mail::to($auth_user->email)->send(new SignedUpToConferenceMail($mail_body));
                }
                
                B2cConferenceMember::create([
                    'conference_id' => $conference->conference_id, // Теперь переменная существует!
                    'member_id'     => $auth_user->user_id,
                ]);

                if(isset($conference->level_payment_id)){
                    $learner_payment = LearnerLevelPayment::find($conference->level_payment_id);
                    if(isset($learner_payment->conferences_remain) && $learner_payment->conferences_remain > 0){
                        $learner_payment->conferences_remain = $learner_payment->conferences_remain - 1;
                        $learner_payment->save();
                    }
                }

                return response()->json('Success', 200);
            }
            else{
                return response()->json([
                    'message' => 'already_exists'
                ], 400);
            }
        }

        return response()->json([
            'message' => 'not_bought'
        ], 400);
    }

    public function save_settings(Request $request){
        $rules = [
            'mode' => 'required|string',
            'bg_mode' => 'required|string',
            'bg_image' => 'required|string'
        ];

        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $auth_user = auth()->user();

        $findUser = User::find($auth_user->user_id);
        $findUser->conf_mode = e($request->mode);
        $findUser->conf_bg_mode = e($request->bg_mode);
        $findUser->conf_bg_image = e($request->bg_image);
        $findUser->save();

        return response()->json('User settings change successful', 200);
    }
}
