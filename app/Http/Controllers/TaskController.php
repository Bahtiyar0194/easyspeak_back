<?php

namespace App\Http\Controllers;
use App\Models\Group;
use App\Models\Task;
use App\Models\TaskLang;
use App\Models\TaskOption;
use App\Models\TaskType;
use App\Models\TaskWord;
use App\Models\MissingLetter;
use App\Models\TaskSentence;
use App\Models\TaskQuestion;
use App\Models\TaskAnswer;
use App\Models\CompletedTask;
use App\Models\MissingWord;
use App\Models\WordSection;
use App\Models\WordSectionItem;
use App\Models\WordSyllable;
use App\Models\Language;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\UploadConfiguration;

use App\Services\TaskService;

use Validator;
use DB;
use File;
use Image;
use Storage;
use Http;

use Illuminate\Http\Request;

class TaskController extends Controller
{

    protected $taskService;

    public function __construct(Request $request, TaskService $taskService)
    {
        $this->taskService = $taskService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_task_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
    
        $all_task_types = TaskType::leftJoin('types_of_tasks_lang', 'types_of_tasks.task_type_id', '=', 'types_of_tasks_lang.task_type_id')
        ->select(
            'types_of_tasks.task_type_id',
            'types_of_tasks.task_type_slug',
            'types_of_tasks.icon',
            'types_of_tasks.task_type_component',
            'types_of_tasks_lang.task_type_name',
            'types_of_tasks.sort_num'
        )
        ->where('types_of_tasks.show_status_id', '=', 1)
        ->where('types_of_tasks_lang.lang_id', '=', $language->lang_id)
        ->distinct()
        ->orderBy('types_of_tasks.sort_num', 'asc')
        ->get();

        $task_types = DB::table('tasks')
        ->leftJoin('types_of_tasks', 'types_of_tasks.task_type_id', '=', 'tasks.task_type_id')
        ->leftJoin('types_of_tasks_lang', 'types_of_tasks.task_type_id', '=', 'types_of_tasks_lang.task_type_id')
        ->select(
            'types_of_tasks.task_type_id',
            'types_of_tasks_lang.task_type_name'
        )
        ->where('types_of_tasks_lang.lang_id', '=', $language->lang_id)
        ->distinct()
        ->orderBy('types_of_tasks.task_type_id', 'asc')
        ->get();

        $operators = Task::leftJoin('users', 'users.user_id', '=', 'tasks.operator_id')
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

        $mentors = CompletedTask::leftJoin('users', 'users.user_id', '=', 'completed_tasks.mentor_id')
        ->select(
            'users.user_id',
            'users.first_name',
            'users.last_name',
            DB::raw("CONCAT(users.last_name, ' ', users.first_name) AS full_name"),
            'users.avatar'
        )
        ->distinct()
        ->where('users.school_id', '=', auth()->user()->school_id)
        ->where('users.status_type_id', '!=', 2)
        ->orderBy('users.last_name', 'asc')
        ->get();

        $statuses = DB::table('tasks')
        ->leftJoin('types_of_status', 'tasks.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
        ->select(
            'tasks.status_type_id',
            'types_of_status_lang.status_type_name'
        )
        ->groupBy('tasks.status_type_id', 'types_of_status_lang.status_type_name')
        ->get();

        $courses = Task::leftJoin('lessons', 'tasks.lesson_id', '=', 'lessons.lesson_id')
        ->leftJoin('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
        ->leftJoin('course_levels', 'course_sections.level_id', '=', 'course_levels.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->distinct()
        ->orderBy('courses.course_id', 'asc')
        ->get();


        foreach ($courses as $c => $course) {
            $levels = Task::leftJoin('lessons', 'tasks.lesson_id', '=', 'lessons.lesson_id')
            ->leftJoin('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
            ->leftJoin('course_levels', 'course_sections.level_id', '=', 'course_levels.level_id')
            ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
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

            foreach ($levels as $l => $level) {
                $sections = Task::leftJoin('lessons', 'tasks.lesson_id', '=', 'lessons.lesson_id')
                ->leftJoin('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
                ->where('course_sections.level_id', '=', $level->level_id)
                ->select(
                    'course_sections.section_id',
                    'course_sections.section_name'
                )
                ->distinct()
                ->orderBy('course_sections.section_id', 'asc')
                ->get();

                $level->sections = $sections;

                foreach ($sections as $s => $section) {
                    $lessons = Task::leftJoin('lessons', 'tasks.lesson_id', '=', 'lessons.lesson_id')
                    ->leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
                    ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
                    ->where('lessons.section_id', '=', $section->section_id)
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

                $groups = Group::leftJoin('users', 'users.user_id', '=', 'groups.mentor_id')
                ->where('groups.level_id', '=', $level->level_id)
                ->where('groups.status_type_id', '=', 1)
                ->select(
                    'groups.group_id',
                    'groups.group_name'
                );

                // Проверяем роли авторизованного пользователя
                $auth_user = auth()->user();
                $isOwner = $auth_user->hasRole(['school_owner']);
                $isAdmin = $auth_user->hasRole(['school_admin']);
                $isMentor = $auth_user->hasRole(['mentor']);

                // Если пользователь - куратор, то показываем только свои группы
                if ($isMentor && !$isAdmin && !$isOwner) {
                    $groups->where('groups.mentor_id', '=', $auth_user->user_id);
                }
                else{
                    $groups->where('users.school_id', '=', $auth_user->school_id);
                }

                $level->groups = $groups->get();
            }
        }

        $attributes = new \stdClass();

        $attributes->all_task_types = $all_task_types;
        $attributes->task_types = $task_types;
        $attributes->statuses = $statuses;
        $attributes->operators = $operators;
        $attributes->mentors = $mentors;
        $attributes->courses = $courses;

        return response()->json($attributes, 200);
    }

    public function get_tasks(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем параметры лимита на страницу
        $per_page = $request->per_page ? $request->per_page : 10;
        // Получаем параметры сортировки
        $sortKey = $request->input('sort_key', 'created_at');  // Поле для сортировки по умолчанию
        $sortDirection = $request->input('sort_direction', 'asc');  // Направление по умолчанию

        $tasks = Task::leftJoin('tasks_lang', 'tasks_lang.task_id', '=', 'tasks.task_id')
        ->leftJoin('types_of_tasks', 'types_of_tasks.task_type_id', '=', 'tasks.task_type_id')
        ->leftJoin('types_of_tasks_lang', 'types_of_tasks_lang.task_type_id', '=', 'types_of_tasks.task_type_id')
        ->leftJoin('lessons', 'lessons.lesson_id', '=', 'tasks.lesson_id')
        ->leftJoin('course_sections', 'course_sections.section_id', '=', 'lessons.section_id')
        ->leftJoin('course_levels', 'course_levels.level_id', '=', 'course_sections.level_id')
        ->leftJoin('course_levels_lang', 'course_levels_lang.level_id', '=', 'course_levels.level_id')
        ->leftJoin('courses', 'courses.course_id', '=', 'course_levels.course_id')
        ->leftJoin('courses_lang', 'courses_lang.course_id', '=', 'courses.course_id')
        ->leftJoin('users as operator', 'tasks.operator_id', '=', 'operator.user_id')
        ->leftJoin('types_of_status', 'tasks.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->select(
            'tasks.task_id',
            'tasks.task_slug',
            'tasks.task_example',
            'tasks.task_type_id',
            'types_of_tasks.task_type_component',
            'types_of_tasks_lang.task_type_name',
            'tasks_lang.task_name',
            'tasks.created_at',
            'lessons.lesson_name',
            'course_sections.section_name',
            'course_levels_lang.level_name',
            'courses_lang.course_name',
            'operator.first_name as operator_first_name',
            'operator.last_name as operator_last_name',
            'operator.avatar as operator_avatar',
            'types_of_status.color as status_color',
            'types_of_status_lang.status_type_name'
        )     
        ->where('tasks_lang.lang_id', '=', $language->lang_id)
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)  
        ->where('courses_lang.lang_id', '=', $language->lang_id)  
        ->where('types_of_tasks_lang.lang_id', '=', $language->lang_id)     
        ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
        ->distinct()
        ->orderBy($sortKey, $sortDirection);

        // Применяем фильтрацию по параметрам из запроса
        $task_name = $request->task_name;
        $task_slug = $request->task_slug;
        $course_id = $request->course_id;
        $level_id = $request->level_id;
        $section_id = $request->section_id;
        $lesson_id = $request->lesson_id;
        $task_types_id = $request->task_types;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;
        $operators_id = $request->operators;
        $statuses_id = $request->statuses;


        if (!empty($task_name)) {
            $tasks->where('tasks_lang.task_name', 'LIKE', '%' . $task_name . '%');
        }

        if (!empty($task_slug)) {
            $tasks->where('tasks.task_slug', 'LIKE', '%' . $task_slug . '%');
        }

        if (!empty($course_id)) {
            $tasks->where('courses.course_id', '=', $course_id);
        }

        if (!empty($level_id)) {
            $tasks->where('course_levels.level_id', '=', $level_id);
        }

        if (!empty($section_id)) {
            $tasks->where('course_sections.section_id', '=', $section_id);
        }

        if (!empty($lesson_id)) {
            $tasks->where('lessons.lesson_id', '=', $lesson_id);
        }

        if(!empty($task_types_id)){
            $tasks->whereIn('types_of_tasks.task_type_id', $task_types_id);
        }

        if(!empty($operators_id)){
            $tasks->whereIn('tasks.operator_id', $operators_id);
        }

        if (!empty($statuses_id)) {
            $tasks->whereIn('tasks.status_type_id', $statuses_id);
        }

        if ($created_at_from && $created_at_to) {
            $tasks->whereBetween('tasks.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:59']);
        } elseif ($created_at_from) {
            $tasks->where('tasks.created_at', '>=', $created_at_from . ' 00:00:00');
        } elseif ($created_at_to) {
            $tasks->where('tasks.created_at', '<=', $created_at_to . ' 23:59:59');
        }

        // Возвращаем пагинированный результат
        return response()->json($tasks->paginate($per_page)->onEachSide(1), 200);
    }

    public function get_lesson_tasks(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
        return response()->json($this->taskService->getLessonTasks($request->lesson_id, $language, true), 200);
    }

    public function order(Request $request){
        $tasks = json_decode($request->tasks);

        foreach ($tasks as $key => $task_item) {
            $task = Task::where('task_id', $task_item->task_id)
            ->where('lesson_id', $request->lesson_id)
            ->first();
            $task->sort_num = $key + 1;
            $task->save();
        }

        return response()->json('success', 200);
    }

    public function delete_task(Request $request){
        $task = Task::where('lesson_id', $request->lesson_id)
        ->where('task_id', $request->task_id)
        ->first();

        if(isset($task)){
            $task->delete();
            return response()->json('delete task is success', 200);
        }
    }

    public function save_task_result(Request $request){
        $task = Task::findOrFail($request->task_id);
        $task_result = json_decode($request->task_result);

        return $this->taskService->saveTaskResult($task->task_id, $task_result);
    }

    public function change_task_result(Request $request){
        $completed_task = CompletedTask::findOrFail($request->completed_task_id);
        $answers = json_decode($request->answers);

        return $this->taskService->changeTaskResult($completed_task->completed_task_id, $answers);
    }

    public function check_answers(Request $request){
        $task = Task::findOrFail($request->task_id);

        $task_result = [];
        $questions = json_decode($request->questions);

        foreach ($questions as $key => $question) {
            if (strlen(trim($question->userInput)) > 0) {
                if($question->checking_by == 'by_ai'){
                    $prompt = "Question: {$question->sentence}\nLearner answer: {$question->userInput}";
    
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                        'Content-Type' => 'application/json',
                    ])->post(env('OPENAI_API_URL').'/chat/completions', [
                        'model' => 'gpt-4', // или 'gpt-3.5-turbo'
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => <<<EOT
                                    You are an English language examiner. Your task is to evaluate a student's answer to a basic question (A1–A2 level).

                                    For each answer, assess:
                                    - whether it is grammatically and semantically correct
                                    - whether it matches the expected structure

                                    Respond strictly in JSON format:
                                    {
                                    "grade": 1 or 0,
                                    "comment": "A short comment, max 1 sentence. The correct answer to the question should be as an example. Mark the correct answer in the comment with the <b> tag"
                                    }

                                    Do not add any extra text before or after the JSON.

                                    If the answer is grammatically correct and logical, return 1. A learner's partially correct answer won't do.
                                    EOT,
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                        'temperature' => 0,
                    ]);
    
                    if ($response->successful()) {
                        $answer = $response['choices'][0]['message']['content'] ?? '';


                        // Пытаемся вытащить JSON
                        if (preg_match('/\{.*\}/s', $answer, $matches)) {
                            $jsonAnswer = $matches[0];

                            $parsed = json_decode($jsonAnswer, true);

                            if (json_last_error() === JSON_ERROR_NONE) {
                                $grade = $parsed['grade'] ?? null;
                                $comment = $parsed['comment'] ?? null;

                                if (isset($grade)) {
                                    array_push($task_result, [
                                        'question_id' => $question->sentence_id,
                                        'is_correct' => $grade,
                                        'right_answer' => ($grade === 1 || $grade === '1') ? "<p class='font-medium mb-0 text-success underline'>{$question->userInput}</p>" : null,
                                        'user_answer' => ($grade === 0 || $grade === '0') ? "<p class='font-medium mb-0 text-danger underline'>{$question->userInput}</p>" : null,
                                        'comment' => $comment,
                                    ]);
                                } else {
                                    array_push($task_result, [
                                        'question_id' => $question->sentence_id,
                                        'user_answer' => $question->userInput,
                                    ]);
                                }
                            } else {
                                array_push($task_result, [
                                    'question_id' => $question->sentence_id,
                                    'user_answer' => $question->userInput,
                                ]);
                            }
                        } else {
                            array_push($task_result, [
                                'question_id' => $question->sentence_id,
                                'user_answer' => $question->userInput,
                            ]);
                        }
                    }
                    else{
                        array_push($task_result, [
                            'question_id' => $question->sentence_id,
                            'user_answer' => $question->userInput,
                        ]);
                    }
                }
                else{
                    array_push($task_result, [
                        'question_id' => $question->sentence_id,
                        'user_answer' => $question->userInput,
                    ]);
                }
            }
            else{
                array_push($task_result, [
                    'question_id' => $question->sentence_id,
                    'is_correct' => 0
                ]);
            }
        }

        return $this->taskService->saveTaskResult($task->task_id, json_decode(json_encode($task_result)));
    }

    public function get_task_results(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
    
        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();
    
        // Получаем параметры лимита на страницу
        $per_page = $request->per_page ?: 10;
    
        // Получаем параметры сортировки
        $sortKey = $request->input('sort_key', 'completed_tasks.created_at');
        $sortDirection = $request->input('sort_direction', 'asc');
    
        // Строим основной запрос
        $query = CompletedTask::leftJoin('tasks', 'tasks.task_id', '=', 'completed_tasks.task_id')
            ->leftJoin('tasks_lang', 'tasks_lang.task_id', '=', 'tasks.task_id')
            ->leftJoin('users as learner', 'learner.user_id', '=', 'completed_tasks.learner_id')
            ->leftJoin('users as mentor', 'mentor.user_id', '=', 'completed_tasks.mentor_id')
            ->leftJoin('types_of_tasks', 'types_of_tasks.task_type_id', '=', 'tasks.task_type_id')
            ->leftJoin('types_of_tasks_lang', 'types_of_tasks_lang.task_type_id', '=', 'types_of_tasks.task_type_id')
            ->leftJoin('lessons', 'lessons.lesson_id', '=', 'tasks.lesson_id')
            ->leftJoin('course_sections', 'course_sections.section_id', '=', 'lessons.section_id')
            ->leftJoin('course_levels', 'course_levels.level_id', '=', 'course_sections.level_id')
            ->leftJoin('course_levels_lang', 'course_levels_lang.level_id', '=', 'course_levels.level_id')
            ->leftJoin('courses', 'courses.course_id', '=', 'course_levels.course_id')
            ->leftJoin('courses_lang', 'courses_lang.course_id', '=', 'courses.course_id')

                // ← Добавляем связи с группами
            ->leftJoin('group_members', function ($join) {
                $join->on('group_members.member_id', '=', 'completed_tasks.learner_id');
            })
            ->leftJoin('groups', function ($join) {
                $join->on('groups.group_id', '=', 'group_members.group_id')
                    ->on('groups.mentor_id', '=', 'completed_tasks.mentor_id')
                    ->where('groups.status_type_id', '=', 1);
            })
            ->select(
                'completed_tasks.completed_task_id',
                'completed_tasks.is_completed',
                'completed_tasks.created_at',
                'completed_tasks.updated_at',
                'completed_tasks.learner_id',
                'completed_tasks.mentor_id',
                'tasks.task_id',
                'tasks.task_slug',
                'tasks_lang.task_name',
                'lessons.lesson_name',
                'course_sections.section_name',
                'course_levels_lang.level_name',
                'courses_lang.course_name',
                'learner.first_name as learner_first_name',
                'learner.last_name as learner_last_name',
                'learner.avatar as learner_avatar',
                'mentor.first_name as mentor_first_name',
                'mentor.last_name as mentor_last_name',
                'mentor.avatar as mentor_avatar',
                'types_of_tasks_lang.task_type_name',

                        // ← Поля группы
                'groups.group_id',
                'groups.group_name'
            )
            ->distinct()
            ->where('learner.school_id', $auth_user->school_id)
            ->where('mentor.school_id', $auth_user->school_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)  
            ->where('courses_lang.lang_id', '=', $language->lang_id)  
            ->where('tasks_lang.lang_id', $language->lang_id)
            ->where('types_of_tasks_lang.lang_id', $language->lang_id);

        // Проверяем роли авторизованного пользователя
        $isOwner = $auth_user->hasRole(['school_owner']);
        $isAdmin = $auth_user->hasRole(['school_admin']);
        $isMentor = $auth_user->hasRole(['mentor']);


        // Если пользователь - куратор, то показываем только свои задачи
        if ($isMentor && !$isAdmin && !$isOwner) {
            $query->where('completed_tasks.mentor_id', $auth_user->user_id);
        }

        // Параметры фильтрации
        $task_name = $request->input('task_name');
        $task_slug = $request->input('task_slug');
        $task_types_id = $request->task_types;
        $course_id = $request->course_id;
        $level_id = $request->level_id;
        $groups_id = $request->groups;
        $section_id = $request->section_id;
        $lesson_id = $request->lesson_id;
        $learner_fio = $request->learner;
        $mentors_id = $request->mentors;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;
        $is_completed = $request->is_completed;
    
        // Применяем фильтрацию по task_slug ДО paginate
        if (!empty($task_name)) {
            $query->where('tasks_lang.task_name', 'LIKE', '%' . $task_name . '%');
        }

        if (!empty($task_slug)) {
            $query->where('tasks.task_slug', 'LIKE', '%' . $task_slug . '%');
        }

        if(!empty($task_types_id)){
            $query->whereIn('types_of_tasks.task_type_id', $task_types_id);
        }

        if (!empty($course_id)) {
            $query->where('courses.course_id', '=', $course_id);
        }

        if (!empty($level_id)) {
            $query->where('course_levels.level_id', '=', $level_id);
        }

        if(!empty($groups_id)){
            $query->whereIn('groups.group_id', $groups_id);
        }

        if (!empty($section_id)) {
            $query->where('course_sections.section_id', '=', $section_id);
        }

        if (!empty($lesson_id)) {
            $query->where('lessons.lesson_id', '=', $lesson_id);
        }

        if (!empty($learner_fio)) {
            $query->whereRaw("CONCAT(learner.last_name, ' ', learner.first_name) LIKE ?", ['%' . $learner_fio . '%']);
        }

        if(!empty($mentors_id)){
            $query->whereIn('completed_tasks.mentor_id', $mentors_id);
        }

        if (!empty($lesson_id)) {
            $query->where('lessons.lesson_id', '=', $lesson_id);
        }

        // Фильтрация по дате создания
        if ($created_at_from && $created_at_to) {
            $query->whereBetween('completed_tasks.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:59']);
        } elseif ($created_at_from) {
            $query->where('completed_tasks.created_at', '>=', $created_at_from . ' 00:00:00');
        } elseif ($created_at_to) {
            $query->where('completed_tasks.created_at', '<=', $created_at_to . ' 23:59:59');
        }

        
        if (!empty($is_completed)) {
            $query->where('completed_tasks.is_completed', '=', $is_completed);
        }
    
        // Сортировка
        $query->orderBy($sortKey, $sortDirection);
    
        // Пагинация
        $completed_tasks = $query->paginate($per_page)->onEachSide(1);
    
        // Добавляем результаты задач
        $completed_tasks->getCollection()->transform(function ($task) {
            $task->task_result = $this->taskService->getTaskResult($task->task_id, $task->learner_id);

            return $task;
        });
    
        return response()->json($completed_tasks, 200);
    }    

    public function create_missing_letters_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        } elseif ($request->step == 2) {
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    if(!isset($word->removedLetters) || count($word->removedLetters) == 0){
                        return response()->json(['letters_failed' => [trans('auth.remove_at_least_one_letter_in_each_word')]], 422);
                    }

                    if(strlen($word->word) <= count($word->removedLetters)){
                        return response()->json(['letters_failed' => [trans('auth.you_cannot_delete_all_the_letters_in_a_word')]], 422);
                    }
                }

                return response()->json([
                    'step' => 2
                ], 200);
            }
        } elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'show_image' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'impression_limit' => 'required|min:1',
                'seconds_per_word' => 'required|numeric|min:3',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif($request->step == 4){
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 1);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $new_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();

                    foreach ($word->removedLetters as $letter) {
                        $new_missing_letter = new MissingLetter();
                        $new_missing_letter->task_word_id = $new_task_word->task_word_id;
                        $new_missing_letter->position = ($letter + 1);
                        $new_missing_letter->save();
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_missing_letters_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        } elseif ($request->step == 2) {
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    if(!isset($word->removedLetters) || count($word->removedLetters) == 0){
                        return response()->json(['letters_failed' => [trans('auth.remove_at_least_one_letter_in_each_word')]], 422);
                    }

                    if(strlen($word->word) <= count($word->removedLetters)){
                        return response()->json(['letters_failed' => [trans('auth.you_cannot_delete_all_the_letters_in_a_word')]], 422);
                    }
                }

                return response()->json([
                    'step' => 2
                ], 200);
            }
        } elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'show_image' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'impression_limit' => 'required|min:1',
                'seconds_per_word' => 'required|numeric|min:3',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif($request->step == 4){
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $edit_task = $this->taskService->editTask($request);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                TaskWord::where('task_id', $edit_task->task_id)
                ->delete();
                
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $edit_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();

                    foreach ($word->removedLetters as $letter) {
                        $new_missing_letter = new MissingLetter();
                        $new_missing_letter->task_word_id = $new_task_word->task_word_id;
                        $new_missing_letter->position = ($letter + 1);
                        $new_missing_letter->save();
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);

            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_missing_letters_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = $this->taskService->getTaskWords($find_task->task_id, $language, $task_options);

        // Оптимизация получения отсутствующих букв
        $task_word_ids = $task_words->pluck('task_word_id');
    
        $missing_letters = MissingLetter::whereIn('task_word_id', $task_word_ids)
            ->select('task_word_id', 'position')
            ->orderBy('position', 'asc')
            ->get()
            ->groupBy('task_word_id');
    
        // Добавление отсутствующих букв к словам
        foreach ($task_words as $word) {
            $word->missingLetters = $missing_letters->get($word->task_word_id, collect())->pluck('position');
        }
        
        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->words = $task_words;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }
    

    public function create_match_words_by_pictures_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'impression_limit' => 'required',
                'seconds_per_word' => 'required|numeric|min:10',
                'match_words_by_pictures_option' => 'required|string',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif($request->step == 3){
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 2);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $new_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_match_words_by_pictures_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'impression_limit' => 'required',
                'seconds_per_word' => 'required|numeric|min:10',
                'match_words_by_pictures_option' => 'required|string',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif($request->step == 3){
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $edit_task = $this->taskService->editTask($request);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                TaskWord::where('task_id', $edit_task->task_id)
                ->delete();
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $edit_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);

            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_match_words_by_pictures_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
        ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
        ->leftJoin('files as image_file', 'dictionary.image_file_id', '=', 'image_file.file_id')
        ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
        ->select(
            'task_words.task_word_id',
            'task_words.word_id',
            'dictionary.word',
            'dictionary.transcription',
            'image_file.target as image_file',
            'audio_file.target as audio_file',
            'dictionary_translate.word_translate'
        )
        ->where('task_words.task_id', '=', $find_task->task_id)
        ->where('dictionary_translate.lang_id', '=', $language->lang_id)
        ->get();

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->words = $task_words;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_form_a_sentence_out_of_the_words_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sentences_count' => 'required|numeric|min:1',
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'in_the_main_lang' => 'required|boolean',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 3);

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $new_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_form_a_sentence_out_of_the_words_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sentences_count' => 'required|numeric|min:1',
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'in_the_main_lang' => 'required|boolean',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            $edit_task = $this->taskService->editTask($request);

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                TaskSentence::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($sentences as $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $edit_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_form_a_sentence_out_of_the_words_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = $this->taskService->getTaskSentences($find_task->task_id, $language, $task_options);

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->sentences = $task_sentences;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_learning_words_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:2',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'play_audio_at_the_begin' => 'required|boolean',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'show_image' => 'required|boolean',
                'show_word' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'options_num' => 'required|numeric',
                'seconds_per_word' => 'required|numeric|min:3',
                'in_the_main_lang' => 'required|boolean',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 4);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $new_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);
            
            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_learning_words_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:2',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'play_audio_at_the_begin' => 'required|boolean',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'show_image' => 'required|boolean',
                'show_word' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'options_num' => 'required|numeric',
                'seconds_per_word' => 'required|numeric|min:3',
                'in_the_main_lang' => 'required|boolean',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $edit_task = $this->taskService->editTask($request);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                TaskWord::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $edit_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_learning_words_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = $this->taskService->getTaskWords($find_task->task_id, $language, $task_options);

        foreach ($task_words as $key => $word) {
            // Получаем случайные слова-ответы для вариативности
            $answer_options = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
            ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
            ->select(
                'task_words.task_word_id',
                'dictionary.word',
                'dictionary.transcription',
                'dictionary_translate.word_translate'
            )
            ->where('task_words.task_word_id', '!=', $word->task_word_id) // Исключаем текущее слово
            ->where('task_words.task_id', '=', $find_task->task_id)
            ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
            ->inRandomOrder()
            ->limit($task_options->options_num - 1)
            ->distinct()
            ->get(); 

            // Вставляем текущий перевод как правильный ответ
            $correct_answer = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
            ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
            ->select(
                'task_words.task_word_id',
                'dictionary.word',
                'dictionary.transcription',
                'dictionary_translate.word_translate'
            )
            ->where('task_words.task_word_id', '=', $word->task_word_id) // Исключаем текущее слово
            ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
            ->first(); 

            $answers = $answer_options->toArray();
            array_push($answers, $correct_answer);

            // Перемешиваем варианты
            shuffle($answers);

            // Добавляем ответы в структуру
            $word->answer_options = $answers;
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);
    
        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->words = $task_words;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_form_a_word_out_of_the_letters_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    if(!isset($word->removedLetters) || count($word->removedLetters) == 0){
                        return response()->json(['letters_failed' => [trans('auth.remove_at_least_one_letter_in_each_word')]], 422);
                    }

                    if(strlen($word->word) <= count($word->removedLetters)){
                        return response()->json(['letters_failed' => [trans('auth.you_cannot_delete_all_the_letters_in_a_word')]], 422);
                    }
                }

                return response()->json([
                    'step' => 2
                ], 200);
            }
        }
        elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'play_audio_at_the_begin' => 'required|boolean',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'show_image' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'seconds_per_word' => 'required|numeric|min:3',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif ($request->step == 4) {

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 5);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $new_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();

                    foreach ($word->removedLetters as $letter) {
                        $new_missing_letter = new MissingLetter();
                        $new_missing_letter->task_word_id = $new_task_word->task_word_id;
                        $new_missing_letter->position = ($letter + 1);
                        $new_missing_letter->save();
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_form_a_word_out_of_the_letters_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    if(!isset($word->removedLetters) || count($word->removedLetters) == 0){
                        return response()->json(['letters_failed' => [trans('auth.remove_at_least_one_letter_in_each_word')]], 422);
                    }

                    if(strlen($word->word) <= count($word->removedLetters)){
                        return response()->json(['letters_failed' => [trans('auth.you_cannot_delete_all_the_letters_in_a_word')]], 422);
                    }
                }

                return response()->json([
                    'step' => 2
                ], 200);
            }
        }
        elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'play_audio_at_the_begin' => 'required|boolean',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'show_image' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'seconds_per_word' => 'required|numeric|min:3',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif ($request->step == 4) {

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            $edit_task = $this->taskService->editTask($request);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                TaskWord::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $edit_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();

                    foreach ($word->removedLetters as $letter) {
                        $new_missing_letter = new MissingLetter();
                        $new_missing_letter->task_word_id = $new_task_word->task_word_id;
                        $new_missing_letter->position = ($letter + 1);
                        $new_missing_letter->save();
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_form_a_word_out_of_the_letters_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = $this->taskService->getTaskWords($find_task->task_id, $language, $task_options);

        foreach ($task_words as $word) {
            $missing_letters = MissingLetter::where('task_word_id', '=', $word->task_word_id)
            ->select(
                'missing_letters.position',
            )
            ->orderBy('position', 'asc')
            ->pluck('position')->toArray();

            $word->missingLetters = $missing_letters;
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);
        
        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->words = $task_words;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_fill_in_the_blanks_in_the_sentence_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'impression_limit' => 'required|min:1',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'sentences_count' => 'required|numeric|min:2',
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'sentences' => 'required',
                'find_word_option' => 'required|string',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    if($request->find_word_option == 'with_options'){
                        if(!isset($sentence->removedWordIndex)){
                            return response()->json(['words_failed' => [trans('auth.remove_one_word_in_each_sentence')]], 422);
                        }

                        if(!isset($sentence->removedWordOptions) || count($sentence->removedWordOptions) < 2){
                            return response()->json(['words_failed' => [trans('auth.you_must_add_two_or_more_options_of_the_missing_words_for_each_sentence')]], 422);
                        }
                    }
                    else{
                        if(!isset($sentence->removedWordsIndex) || count($sentence->removedWordsIndex) == 0){
                            return response()->json(['words_failed' => [trans('auth.remove_one_word_in_each_sentence')]], 422);
                        }

                        if($request->find_word_option == 'with_first_letter'){
                            foreach ($sentence->removedWordsIndex as $wordIndex) {
                                if(strlen(explode(" ", $sentence->sentence)[$wordIndex]) <= 1){
                                    return response()->json(['words_failed' => [trans('auth.you_cannot_remove_a_word_with_one_letter')]], 422);
                                }
                            }
                        }
                    }
                }
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif($request->step == 4){

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 6);

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $new_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->save();

                    if($request->find_word_option == 'with_options'){
                        if(count($sentence->removedWordOptions) > 0){
                            foreach ($sentence->removedWordOptions as $key => $option) {
                                $new_missing_word = new MissingWord();
                                if($key === 0 && isset($sentence->removedWordIndex)){
                                    $new_missing_word->word_position = $sentence->removedWordIndex;
                                }
                                $new_missing_word->word_option = $option;
                                $new_missing_word->task_sentence_id = $new_task_sentence->task_sentence_id;
                                $new_missing_word->save();
                            }
                        }
                    }
                    else{
                        if(count($sentence->removedWordsIndex) > 0){
                            foreach ($sentence->removedWordsIndex as $key => $wordIndex) {
                                $new_missing_word = new MissingWord();
                                $new_missing_word->word_position = $wordIndex;
                                $new_missing_word->task_sentence_id = $new_task_sentence->task_sentence_id;
                                $new_missing_word->save();
                            }
                        }
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);
            
            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_fill_in_the_blanks_in_the_sentence_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'impression_limit' => 'required|min:1',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'sentences_count' => 'required|numeric|min:2',
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'sentences' => 'required',
                'find_word_option' => 'required|string',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    if($request->find_word_option == 'with_options'){
                        if(!isset($sentence->removedWordIndex)){
                            return response()->json(['words_failed' => [trans('auth.remove_one_word_in_each_sentence')]], 422);
                        }

                        if(!isset($sentence->removedWordOptions) || count($sentence->removedWordOptions) < 2){
                            return response()->json(['words_failed' => [trans('auth.you_must_add_two_or_more_options_of_the_missing_words_for_each_sentence')]], 422);
                        }
                    }
                    else{
                        if(!isset($sentence->removedWordsIndex) || count($sentence->removedWordsIndex) == 0){
                            return response()->json(['words_failed' => [trans('auth.remove_one_word_in_each_sentence')]], 422);
                        }

                        if($request->find_word_option == 'with_first_letter'){
                            foreach ($sentence->removedWordsIndex as $wordIndex) {
                                if(strlen(explode(" ", $sentence->sentence)[$wordIndex]) <= 1){
                                    return response()->json(['words_failed' => [trans('auth.you_cannot_remove_a_word_with_one_letter')]], 422);
                                }
                            }
                        }
                    }
                }
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif($request->step == 4){

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            $edit_task = $this->taskService->editTask($request);

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {

                TaskSentence::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($sentences as $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $edit_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->save();

                    if($request->find_word_option == 'with_options'){
                        if(count($sentence->removedWordOptions) > 0){
                            foreach ($sentence->removedWordOptions as $key => $option) {
                                $new_missing_word = new MissingWord();
                                if($key === 0 && isset($sentence->removedWordIndex)){
                                    $new_missing_word->word_position = $sentence->removedWordIndex;
                                }
                                $new_missing_word->word_option = $option;
                                $new_missing_word->task_sentence_id = $new_task_sentence->task_sentence_id;
                                $new_missing_word->save();
                            }
                        }
                    }
                    else{
                        if(count($sentence->removedWordsIndex) > 0){
                            foreach ($sentence->removedWordsIndex as $key => $wordIndex) {
                                $new_missing_word = new MissingWord();
                                $new_missing_word->word_position = $wordIndex;
                                $new_missing_word->task_sentence_id = $new_task_sentence->task_sentence_id;
                                $new_missing_word->save();
                            }
                        }
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_fill_in_the_blanks_in_the_sentence_task(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = $this->taskService->getTaskSentences($find_task->task_id, $language, $task_options);

        foreach ($task_sentences as $sentence) {
            $missing_words = MissingWord::where('task_sentence_id', $sentence->task_sentence_id)
                ->select(
                    'missing_words.missing_word_id',
                    'missing_words.word_position',
                    'missing_words.word_option'
                );

            if ($task_options->find_word_option == 'with_hints') {
                $missing_words = $missing_words->orderBy('word_position', 'asc');
            } 

            $sentence->missingWords = $missing_words->get();
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);
    
        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->sentences = $task_sentences;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_match_same_words_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sections_count' => 'required|numeric|min:2',
                'sections' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            
            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                foreach ($sections as $section) {
                    $target_found = false;
                    if(count($section->words) > 0){
                        foreach ($section->words as $word) {
                            if(isset($word->target) && $word->target == 'true'){
                                $target_found = true;
                            }
                        }

                        if($target_found === false){
                            return response()->json(['target_failed' => 'Target failed'], 422);
                        }
                    }
                }
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif($request->step == 2){
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'impression_limit' => 'required|min:1',
                'seconds_per_section' => 'required|numeric|min:3',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            // Добавляем кастомную проверку через after()
            $validator->after(function ($validator) use ($request) {
                if (
                    !isset($request->match_by_typing) &&
                    !isset($request->match_by_clicking) &&
                    !isset($request->match_by_drag_and_drop)
                ) {
                    $validator->errors()->add('choose_one_of_the_methods', 'Choose one of the methods is required.');
                }
            });

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif ($request->step == 4) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 11);

            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                foreach ($sections as $section) {
                    $new_word_section = new WordSection();
                    $new_word_section->task_id = $new_task->task_id;
                    $new_word_section->save();

                    if(count($section->words) > 0){
                        foreach ($section->words as $word) {
                            $new_word_section_item = new WordSectionItem();
                            $new_word_section_item->word_section_id = $new_word_section->word_section_id;
                            $new_word_section_item->word_id = $word->word_id;
                            $new_word_section_item->target = (isset($word->target) && $word->target == 'true') ? true : false;
                            $new_word_section_item->save();

                            if(isset($word->removedLetters) && count($word->removedLetters) > 0){
                                $new_task_word = new TaskWord();
                                $new_task_word->task_id = $new_task->task_id;
                                $new_task_word->word_id = $word->word_id;
                                $new_task_word->save();
            
                                foreach ($word->removedLetters as $letter) {
                                    $new_missing_letter = new MissingLetter();
                                    $new_missing_letter->task_word_id = $new_task_word->task_word_id;
                                    $new_missing_letter->position = ($letter + 1);
                                    $new_missing_letter->save();
                                }
                            }
                        }
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_match_same_words_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sections_count' => 'required|numeric|min:2',
                'sections' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            
            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                foreach ($sections as $section) {
                    $target_found = false;
                    if(count($section->words) > 0){
                        foreach ($section->words as $word) {
                            if(isset($word->target) && $word->target == 'true'){
                                $target_found = true;
                            }
                        }

                        if($target_found === false){
                            return response()->json(['target_failed' => 'Target failed'], 422);
                        }
                    }
                }
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif($request->step == 2){
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'impression_limit' => 'required|min:1',
                'seconds_per_section' => 'required|numeric|min:3',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            // Добавляем кастомную проверку через after()
            $validator->after(function ($validator) use ($request) {
                if (
                    !isset($request->match_by_typing) &&
                    !isset($request->match_by_clicking) &&
                    !isset($request->match_by_drag_and_drop)
                ) {
                    $validator->errors()->add('choose_one_of_the_methods', 'Choose one of the methods is required.');
                }
            });

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif ($request->step == 4) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $edit_task = $this->taskService->editTask($request);

            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                WordSection::where('task_id', $edit_task->task_id)
                ->delete();

                TaskWord::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($sections as $section) {
                    $new_word_section = new WordSection();
                    $new_word_section->task_id = $edit_task->task_id;
                    $new_word_section->save();

                    if(count($section->words) > 0){
                        foreach ($section->words as $word) {
                            $new_word_section_item = new WordSectionItem();
                            $new_word_section_item->word_section_id = $new_word_section->word_section_id;
                            $new_word_section_item->word_id = $word->word_id;
                            $new_word_section_item->target = (isset($word->target) && $word->target == 'true') ? true : false;
                            $new_word_section_item->save();

                            if(isset($word->removedLetters) && count($word->removedLetters) > 0){
                                $new_task_word = new TaskWord();
                                $new_task_word->task_id = $edit_task->task_id;
                                $new_task_word->word_id = $word->word_id;
                                $new_task_word->save();
            
                                foreach ($word->removedLetters as $letter) {
                                    $new_missing_letter = new MissingLetter();
                                    $new_missing_letter->task_word_id = $new_task_word->task_word_id;
                                    $new_missing_letter->position = ($letter + 1);
                                    $new_missing_letter->save();
                                }
                            }
                        }
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_match_same_words_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $word_sections = WordSection::select(
            'word_sections.word_section_id',
            'word_sections.task_id'
        )
        ->where('word_sections.task_id', '=', $find_task->task_id);

        if($task_options->random_order == 1){
            $word_sections->inRandomOrder();
        }

        $word_sections = $word_sections->get();

        if(count($word_sections) === 0){
            return response()->json('word sections is not found', 404);
        }

        foreach ($word_sections as $key => $section) {
            $section_items = WordSectionItem::leftJoin('dictionary', 'word_section_items.word_id', '=', 'dictionary.word_id')
            ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
            ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
            ->select(
                'word_section_items.word_section_item_id',
                'word_section_items.word_section_id',
                'word_section_items.word_id',
                'word_section_items.target',
                'dictionary.word',
                'dictionary_translate.word_translate',
                'audio_file.target as audio_file',
            )
            ->where('word_section_items.word_section_id', '=', $section->word_section_id)
            ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
            ->distinct()
            ->get();

            foreach ($section_items as $key => $section_item) {
                $task_word = TaskWord::where('task_id', '=', $find_task->task_id)
                ->where('word_id', '=', $section_item->word_id)
                ->first();

                if(isset($task_word)){
                    $missing_letters = MissingLetter::where('task_word_id', '=', $task_word->task_word_id)
                    ->select('task_word_id', 'position')
                    ->orderBy('position', 'asc')
                    ->get()
                    ->pluck('position');

                    if(count($missing_letters) > 0){
                        $section_item->missingLetters = $missing_letters;
                    }
                }
            }

            $section->words = $section_items;
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->word_sections = $word_sections;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_find_an_extra_word_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sections_count' => 'required|numeric|min:2',
                'sections' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            
            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                foreach ($sections as $section) {
                    $target_found = false;
                    if(count($section->words) > 0){
                        foreach ($section->words as $word) {
                            if(isset($word->target) && $word->target == 'true'){
                                $target_found = true;
                            }
                        }

                        if($target_found === false){
                            return response()->json(['target_failed' => 'Target failed'], 422);
                        }
                    }
                }
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif($request->step == 2){
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'impression_limit' => 'required|min:1',
                'seconds_per_section' => 'required|numeric|min:3',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif ($request->step == 4) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 8);

            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                foreach ($sections as $section) {
                    $new_word_section = new WordSection();
                    $new_word_section->task_id = $new_task->task_id;
                    $new_word_section->save();

                    if(count($section->words) > 0){
                        foreach ($section->words as $word) {
                            $new_word_section_item = new WordSectionItem();
                            $new_word_section_item->word_section_id = $new_word_section->word_section_id;
                            $new_word_section_item->word_id = $word->word_id;
                            $new_word_section_item->target = (isset($word->target) && $word->target == 'true') ? true : false;
                            $new_word_section_item->save();

                            if(isset($word->removedLetters) && count($word->removedLetters) > 0){
                                $new_task_word = new TaskWord();
                                $new_task_word->task_id = $new_task->task_id;
                                $new_task_word->word_id = $word->word_id;
                                $new_task_word->save();
            
                                foreach ($word->removedLetters as $letter) {
                                    $new_missing_letter = new MissingLetter();
                                    $new_missing_letter->task_word_id = $new_task_word->task_word_id;
                                    $new_missing_letter->position = ($letter + 1);
                                    $new_missing_letter->save();
                                }
                            }
                        }
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_find_an_extra_word_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sections_count' => 'required|numeric|min:2',
                'sections' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            
            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                foreach ($sections as $section) {
                    $target_found = false;
                    if(count($section->words) > 0){
                        foreach ($section->words as $word) {
                            if(isset($word->target) && $word->target == 'true'){
                                $target_found = true;
                            }
                        }

                        if($target_found === false){
                            return response()->json(['target_failed' => 'Target failed'], 422);
                        }
                    }
                }
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif($request->step == 2){
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'impression_limit' => 'required|min:1',
                'seconds_per_section' => 'required|numeric|min:3',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif ($request->step == 4) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            $edit_task = $this->taskService->editTask($request);

            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                WordSection::where('task_id', $edit_task->task_id)
                ->delete();

                TaskWord::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($sections as $section) {
                    $new_word_section = new WordSection();
                    $new_word_section->task_id = $edit_task->task_id;
                    $new_word_section->save();

                    if(count($section->words) > 0){
                        foreach ($section->words as $word) {
                            $new_word_section_item = new WordSectionItem();
                            $new_word_section_item->word_section_id = $new_word_section->word_section_id;
                            $new_word_section_item->word_id = $word->word_id;
                            $new_word_section_item->target = (isset($word->target) && $word->target == 'true') ? true : false;
                            $new_word_section_item->save();

                            if(isset($word->removedLetters) && count($word->removedLetters) > 0){
                                $new_task_word = new TaskWord();
                                $new_task_word->task_id = $edit_task->task_id;
                                $new_task_word->word_id = $word->word_id;
                                $new_task_word->save();
            
                                foreach ($word->removedLetters as $letter) {
                                    $new_missing_letter = new MissingLetter();
                                    $new_missing_letter->task_word_id = $new_task_word->task_word_id;
                                    $new_missing_letter->position = ($letter + 1);
                                    $new_missing_letter->save();
                                }
                            }
                        }
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_find_an_extra_word_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $word_sections = WordSection::select(
            'word_sections.word_section_id',
            'word_sections.task_id'
        )
        ->where('word_sections.task_id', '=', $find_task->task_id);

        if($task_options->random_order == 1){
            $word_sections->inRandomOrder();
        }

        $word_sections = $word_sections->get();

        if(count($word_sections) === 0){
            return response()->json('word sections is not found', 404);
        }

        foreach ($word_sections as $key => $section) {
            $section_items = WordSectionItem::leftJoin('dictionary', 'word_section_items.word_id', '=', 'dictionary.word_id')
            ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
            ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
            ->select(
                'word_section_items.word_section_item_id',
                'word_section_items.word_section_id',
                'word_section_items.word_id',
                'word_section_items.target',
                'dictionary.word',
                'dictionary_translate.word_translate',
                'audio_file.target as audio_file',
            )
            ->where('word_section_items.word_section_id', '=', $section->word_section_id)
            ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
            ->distinct()
            ->inRandomOrder()
            ->get();

            foreach ($section_items as $key => $section_item) {
                $task_word = TaskWord::where('task_id', '=', $find_task->task_id)
                ->where('word_id', '=', $section_item->word_id)
                ->first();

                if(isset($task_word)){
                    $missing_letters = MissingLetter::where('task_word_id', '=', $task_word->task_word_id)
                    ->select('task_word_id', 'position')
                    ->orderBy('position', 'asc')
                    ->get()
                    ->pluck('position');

                    if(count($missing_letters) > 0){
                        $section_item->missingLetters = $missing_letters;
                    }
                }
            }

            $section->words = $section_items;
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->word_sections = $word_sections;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_true_or_false_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sentences_count' => 'required|numeric|min:2',
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    if(!isset($sentence->answer)){
                        return response()->json(['answers_failed' => [trans('Answers failed')]], 422);
                    }
                }

                return response()->json([
                    'step' => 2
                ], 200);
            }
        }
        if ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'play_audio_at_the_begin' => 'required|boolean',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        if ($request->step == 4) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 9);

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $new_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->answer = $sentence->answer;
                    $new_task_sentence->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_true_or_false_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sentences_count' => 'required|numeric|min:2',
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    if(!isset($sentence->answer)){
                        return response()->json(['answers_failed' => [trans('Answers failed')]], 422);
                    }
                }

                return response()->json([
                    'step' => 2
                ], 200);
            }
        }
        if ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'play_audio_at_the_begin' => 'required|boolean',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        if ($request->step == 4) {
            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            $edit_task = $this->taskService->editTask($request);

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                TaskSentence::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($sentences as $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $edit_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->answer = $sentence->answer;
                    $new_task_sentence->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_true_or_false_task(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = $this->taskService->getTaskSentences($find_task->task_id, $language, $task_options);

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->sentences = $task_sentences;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_match_sentences_with_materials_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'impression_limit' => 'required|min:1',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'max_attempts' => 'required|numeric',
                'sentence_material_type_slug' => 'required|string|min:1',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'sentences_count' => 'required|numeric|min:2',
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $sentences = json_decode($request->sentences);

            // Проверяем материалы для фразы
            $validate_errors = $this->taskService->validateSentenceMaterials($sentences, $request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif($request->step == 4){

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 10);

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentenceIndex => $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $new_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->save();

                    // Добавляем материалы к фразе
                    $this->taskService->addMaterialsToTaskSentence($sentenceIndex, $new_task_sentence->task_sentence_id, $request);
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);
            
            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // // $user_operation = new UserOperation();
            // // $user_operation->operator_id = auth()->user()->user_id;
            // // $user_operation->operation_type_id = 3;
            // // $user_operation->description = $description;
            // // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_match_sentences_with_materials_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'impression_limit' => 'required|min:1',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'max_attempts' => 'required|numeric',
                'sentence_material_type_slug' => 'required|string|min:1',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'sentences_count' => 'required|numeric|min:2',
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'sentences' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $sentences = json_decode($request->sentences);

            // Проверяем материалы для фразы
            $validate_errors = $this->taskService->validateSentenceMaterials($sentences, $request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif($request->step == 4){

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $edit_task = $this->taskService->editTask($request);

            $sentences = json_decode($request->sentences);
            $leave_task_sentence_ids = [];

            if (count($sentences) > 0) {
                foreach ($sentences as $sentenceIndex => $sentence) {
                    if(isset($sentence->material->task_sentence_material_id)){
                        array_push($leave_task_sentence_ids, $sentence->task_sentence_id);
                    }
                }

                if(count($leave_task_sentence_ids) > 0){
                    TaskSentence::where('task_id', $edit_task->task_id)
                    ->whereNotIn('task_sentence_id', $leave_task_sentence_ids)
                    ->delete();
                }
                else{
                    TaskSentence::where('task_id', $edit_task->task_id)
                    ->delete();
                }

                foreach ($sentences as $sentenceIndex => $sentence) {
                    // Добавляем материалы к фразе
                    if(!isset($sentence->material->task_sentence_material_id)){
                        $new_task_sentence = new TaskSentence();
                        $new_task_sentence->task_id = $edit_task->task_id;
                        $new_task_sentence->sentence_id = $sentence->sentence_id;
                        $new_task_sentence->save();

                        $this->taskService->addMaterialsToTaskSentence($sentenceIndex, $new_task_sentence->task_sentence_id, $request);
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // // $user_operation = new UserOperation();
            // // $user_operation->operator_id = auth()->user()->user_id;
            // // $user_operation->operation_type_id = 3;
            // // $user_operation->description = $description;
            // // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_match_sentences_with_materials_task(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = $this->taskService->getTaskSentences($find_task->task_id, $language, $task_options);

        foreach ($task_sentences as $sentence) {
            $sentence->material = $this->taskService->getTaskSentenceMaterial($sentence->task_sentence_id);
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);
    
        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->sentences = $task_sentences;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_find_the_stressed_syllable_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    if(!isset($word->syllables) || count($word->syllables) < 2){
                        return response()->json(['syllables_failed' => [trans('auth.divide_each_word_into_syllables')]], 422);
                    }
                }

                foreach ($words as $word) {
                    $stress_found = false;

                    foreach ($word->syllables as $syllable) {
                        if(isset($syllable->target) && $syllable->target == 'true'){
                            $stress_found = true;
                        }
                    }

                    if($stress_found === false){
                        return response()->json(['syllables_failed' => [trans('auth.specify_one_of_the_syllables_as_the_stress_in_each_word')]], 422);
                    }
                }
                
                return response()->json([
                    'step' => 2
                ], 200);
            }
        }
        elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'seconds_per_word' => 'required|numeric|min:3',
                'impression_limit' => 'required|min:1',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif ($request->step == 4) {

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 12);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $new_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();

                    foreach ($word->syllables as $syllable) {
                        $new_word_syllable = new WordSyllable();
                        $new_word_syllable->task_word_id = $new_task_word->task_word_id;
                        $new_word_syllable->syllable = $syllable->syllable;
                        $new_word_syllable->target = (isset($syllable->target) && $syllable->target == 'true') ? true : false;
                        $new_word_syllable->save();
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_find_the_stressed_syllable_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    if(!isset($word->syllables) || count($word->syllables) < 2){
                        return response()->json(['syllables_failed' => [trans('auth.divide_each_word_into_syllables')]], 422);
                    }
                }

                foreach ($words as $word) {
                    $stress_found = false;

                    foreach ($word->syllables as $syllable) {
                        if(isset($syllable->target) && $syllable->target == 'true'){
                            $stress_found = true;
                        }
                    }

                    if($stress_found === false){
                        return response()->json(['syllables_failed' => [trans('auth.specify_one_of_the_syllables_as_the_stress_in_each_word')]], 422);
                    }
                }
                
                return response()->json([
                    'step' => 2
                ], 200);
            }
        }
        elseif ($request->step == 3) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'seconds_per_word' => 'required|numeric|min:3',
                'impression_limit' => 'required|min:1',
                'max_attempts' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif ($request->step == 4) {

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $edit_task = $this->taskService->editTask($request);

            $words = json_decode($request->words);

            if (count($words) > 0) {
                TaskWord::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $edit_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();

                    foreach ($word->syllables as $syllable) {
                        $new_word_syllable = new WordSyllable();
                        $new_word_syllable->task_word_id = $new_task_word->task_word_id;
                        $new_word_syllable->syllable = $syllable->syllable;
                        $new_word_syllable->target = (isset($syllable->target) && $syllable->target == 'true') ? true : false;
                        $new_word_syllable->save();
                    }
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_find_the_stressed_syllable_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = $this->taskService->getTaskWords($find_task->task_id, $language, $task_options);

        foreach ($task_words as $word) {
            $word_syllables = WordSyllable::where('task_word_id', '=', $word->task_word_id)
            ->select(
                'word_syllables.word_syllable_id',
                'word_syllables.syllable',
                'word_syllables.target'
            )
            ->orderBy('word_syllables.word_syllable_id', 'asc')
            ->get();

            $word->syllables = $word_syllables;
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);
        
        $task = new \stdClass();

        $task->task = $find_task;
        $task->options = $task_options;
        $task->words = $task_words;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_answer_the_questions_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'impression_limit' => 'required|min:1',
                'seconds_per_question' => 'required|numeric|min:10',
                'max_answer_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'questions_count' => 'required|numeric|min:1',
                'questions' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'questions' => 'required',
                'answer_the_questions_option' => 'required|string',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif($request->step == 4){

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            // Добавляем задание
            $new_task = $this->taskService->newTask($request, 13);

            $questions = json_decode($request->questions);

            if (count($questions) > 0) {
                foreach ($questions as $key => $question) {
                    $new_task_question = new TaskQuestion();
                    $new_task_question->task_id = $new_task->task_id;
                    $new_task_question->question_id = $question->sentence_id;
                    $new_task_question->checking_by = $question->checking_by;
                    $new_task_question->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($new_task->task_id, $request);
            
            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($new_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function edit_answer_the_questions_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'impression_limit' => 'required|min:1',
                'seconds_per_question' => 'required|numeric|min:10',
                'max_answer_attempts' => 'required|numeric',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'questions_count' => 'required|numeric|min:1',
                'questions' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'questions' => 'required',
                'answer_the_questions_option' => 'required|string',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 3
            ], 200);
        }
        elseif($request->step == 4){

            // Проверяем материалы на задание
            $validate_errors = $this->taskService->validateTaskMaterials($request);

            if ($validate_errors) {
                return response()->json($validate_errors, 422);
            }

            $edit_task = $this->taskService->editTask($request);

            $questions = json_decode($request->questions);

            if (count($questions) > 0) {
                TaskQuestion::where('task_id', $edit_task->task_id)
                ->delete();

                foreach ($questions as $key => $question) {
                    $new_task_question = new TaskQuestion();
                    $new_task_question->task_id = $edit_task->task_id;
                    $new_task_question->question_id = $question->sentence_id;
                    $new_task_question->checking_by = $question->checking_by;
                    $new_task_question->save();
                }
            }

            // Добавляем материалы к заданию
            $this->taskService->addMaterialsToTask($edit_task->task_id, $request);
            
            // Удаляем старые опции задания
            TaskOption::where('task_id', $edit_task->task_id)
            ->delete();

            // Добавляем опции к заданию
            $this->taskService->addTaskOptions($edit_task->task_id, $request);

            // $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
            // <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
            // <p><span>Категория:</span> <b>{$category->category_name}</b></p>
            // <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

            // $user_operation = new UserOperation();
            // $user_operation->operator_id = auth()->user()->user_id;
            // $user_operation->operation_type_id = 3;
            // $user_operation->description = $description;
            // $user_operation->save();

            return response()->json('success', 200);
        }
    }

    public function get_answer_the_questions_task(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
                        
        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $find_task = $this->taskService->findTask($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $is_member = Task::join('lessons', 'lessons.lesson_id', '=', 'tasks.lesson_id')
        ->join('course_sections', 'course_sections.section_id', '=', 'lessons.section_id')
        ->join('course_levels', 'course_levels.level_id', '=', 'course_sections.level_id')
        ->join('groups', 'groups.level_id', '=', 'course_levels.level_id')
        ->join('group_members', 'group_members.group_id', '=', 'groups.group_id')
        ->where('tasks.task_id', $find_task->task_id)
        ->where('group_members.member_id', $auth_user->user_id)
        ->exists();

        // Проверяем, является ли пользователь участником группы
        if($is_member || $auth_user->hasRole(['school_owner', 'school_admin', 'mentor'])){
            $task_questions = $this->taskService->getTaskQuestions($find_task->task_id, $language, $task_options);

            $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);
        
            $task = new \stdClass();
    
            $task->task = $find_task;
            $task->options = $task_options;
            $task->questions = $task_questions;
            $task->materials = $task_materials;
    
            return response()->json($task, 200);
        }
        // Если пользователь не является участником группы, возвращаем ошибку
        return response()->json([trans('auth.you_are_not_a_member_of_group')], 422);
    }
}