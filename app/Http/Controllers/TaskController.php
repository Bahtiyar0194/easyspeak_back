<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskLang;
use App\Models\TaskOption;
use App\Models\TaskType;
use App\Models\TaskWord;
use App\Models\MissingLetter;
use App\Models\TaskSentence;
use App\Models\MissingWord;
use App\Models\WordSection;
use App\Models\WordSectionItem;
use App\Models\Language;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;

use App\Services\TaskService;

use Validator;
use DB;
use File;
use Image;
use Storage;

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
            }
        }

        $attributes = new \stdClass();

        $attributes->all_task_types = $all_task_types;
        $attributes->task_types = $task_types;
        $attributes->statuses = $statuses;
        $attributes->operators = $operators;
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

        $tasks = Task::leftJoin('tasks_lang', 'tasks_lang.task_id', '=', 'tasks.task_id')
        ->leftJoin('types_of_tasks', 'types_of_tasks.task_type_id', '=', 'tasks.task_type_id')
        ->leftJoin('types_of_tasks_lang', 'types_of_tasks_lang.task_type_id', '=', 'types_of_tasks.task_type_id')
        ->select(
            'tasks.task_id',
            'tasks.task_slug',
            'tasks.task_example',
            'tasks.task_type_id',
            'types_of_tasks.task_type_component',
            'types_of_tasks.icon',
            'types_of_tasks_lang.task_type_name',
            'tasks_lang.task_name',
            'tasks.created_at'
        )     
        ->where('tasks_lang.lang_id', '=', $language->lang_id)
        ->where('types_of_tasks_lang.lang_id', '=', $language->lang_id)    
        ->where('tasks.lesson_id', '=', $request->lesson_id) 
        ->distinct()
        ->orderBy('tasks.task_id', 'asc')
        ->get();

        return response()->json($tasks, 200);
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

    public function get_missing_letters_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
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

    public function get_match_words_by_pictures_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = $this->taskService->getTaskWords($find_task->task_id, $language, $task_options);

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

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

    public function get_form_a_sentence_out_of_the_words_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = $this->taskService->getTaskSentences($find_task->task_id, $language, $task_options);

        $task = new \stdClass();

        $task->options = $task_options;
        $task->sentences = $task_sentences;

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

    public function get_learning_words_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

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

    public function get_form_a_word_out_of_the_letters_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = $this->taskService->getTaskWords($request->task_id, $language, $task_options);

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

    public function get_fill_in_the_blanks_in_the_sentence_task(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = $this->taskService->getTaskSentences($find_task->task_id, $language, $task_options);

        foreach ($task_sentences as $sentence) {
            $missing_words = MissingWord::where('task_sentence_id', '=', $sentence->task_sentence_id)
            ->select(
                'missing_words.missing_word_id',
                'missing_words.word_position',
                'missing_words.word_option'
            )
            ->get();

            $sentence->missingWords = $missing_words;
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);
    
        $task = new \stdClass();

        $task->options = $task_options;
        $task->sentences = $task_sentences;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }

    public function create_match_paired_words_task(Request $request)
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

            return response()->json([
                'step' => 1
            ], 200);
        }
        elseif ($request->step == 2) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'impression_limit' => 'required|min:1',
                'seconds_per_word' => 'required|numeric|min:3',
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
            $new_task = $this->taskService->newTask($request, 7);

            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                foreach ($sections as $section) {
                    $new_word_section = new WordSection();
                    $new_word_section->task_id = $new_task->task_id;
                    $new_word_section->save();

                    if(count($section) > 0){
                        foreach ($section as $section_item) {
                            $new_word_section_item = new WordSectionItem();
                            $new_word_section_item->word_section_id = $new_word_section->word_section_id;
                            $new_word_section_item->word_id = $section_item->word_id;
                            $new_word_section_item->target = (isset($section_item->target) && $section_item->target == 'true') ? true : false;
                            $new_word_section_item->save();
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

    public function get_match_paired_words_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

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
            ->select(
                'word_section_items.word_section_item_id',
                'word_section_items.word_section_id',
                'word_section_items.word_id',
                'word_section_items.target',
                'dictionary.word',
                'dictionary_translate.word_translate',
                'dictionary.audio_file'
            )
            ->where('word_section_items.word_section_id', '=', $section->word_section_id)
            ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
            ->distinct()
            ->get();


            $section->words = $section_items;
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

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
                    if(count($section) > 0){
                        foreach ($section as $section_item) {
                            if(isset($section_item->target) && $section_item->target == 'true'){
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
        elseif ($request->step == 2) {
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
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
            $new_task = $this->taskService->newTask($request, 8);

            $sections = json_decode($request->sections);

            if (count($sections) > 0) {
                foreach ($sections as $section) {
                    $new_word_section = new WordSection();
                    $new_word_section->task_id = $new_task->task_id;
                    $new_word_section->save();

                    if(count($section) > 0){
                        foreach ($section as $section_item) {
                            $new_word_section_item = new WordSectionItem();
                            $new_word_section_item->word_section_id = $new_word_section->word_section_id;
                            $new_word_section_item->word_id = $section_item->word_id;
                            $new_word_section_item->target = (isset($section_item->target) && $section_item->target == 'true') ? true : false;
                            $new_word_section_item->save();
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

    public function get_find_an_extra_word_task(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $word_sections = WordSection::select(
            'word_sections.word_section_id',
            'word_sections.task_id'
        )
        ->where('word_sections.task_id', '=', $request->task_id);

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
            ->select(
                'word_section_items.word_section_item_id',
                'word_section_items.word_section_id',
                'word_section_items.word_id',
                'word_section_items.target',
                'dictionary.word',
                'dictionary_translate.word_translate',
                'dictionary.audio_file'
            )
            ->where('word_section_items.word_section_id', '=', $section->word_section_id)
            ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
            ->distinct()
            ->inRandomOrder()
            ->get();

            $section->words = $section_items;
        }

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

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

    public function get_true_or_false_task(Request $request){
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $find_task = Task::findOrFail($request->task_id);

        $task_options = TaskOption::where('task_id', '=', $find_task->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = $this->taskService->getTaskSentences($find_task->task_id, $language, $task_options);

        $task_materials = $this->taskService->getTaskMaterials($find_task->task_id);

        $task = new \stdClass();

        $task->options = $task_options;
        $task->sentences = $task_sentences;
        $task->materials = $task_materials;

        return response()->json($task, 200);
    }
}