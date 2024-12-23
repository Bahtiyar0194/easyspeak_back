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

use Validator;
use DB;

use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(Request $request)
    {
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
                    ->leftJoin('lessons_lang', 'lessons.lesson_id', '=', 'lessons_lang.lesson_id')
                    ->where('lessons.section_id', '=', $section->section_id)
                    ->where('types_of_lessons_lang.lang_id', '=', $language->lang_id)
                    ->where('lessons_lang.lang_id', '=', $language->lang_id)
                    ->select(
                        'lessons.lesson_id',
                        'lessons_lang.lesson_name',
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
        ->leftJoin('lessons_lang', 'lessons_lang.lesson_id', '=', 'lessons.lesson_id')
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
            'tasks.task_type_id',
            'types_of_tasks.task_type_component',
            'types_of_tasks_lang.task_type_name',
            'tasks_lang.task_name',
            'tasks.created_at',
            'lessons_lang.lesson_name',
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
        ->where('lessons_lang.lang_id', '=', $language->lang_id)  
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
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'section_id' => 'required|numeric',
                'lesson_id' => 'required|numeric',
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
            $rules = [
                'task_slug' => 'required',
                'task_name_kk' => 'required',
                'task_name_ru' => 'required',
                'show_audio_button' => 'required|boolean',
                'show_image' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            $new_task = new Task();
            $new_task->task_slug = $request->task_slug;
            $new_task->task_type_id = 1;
            $new_task->lesson_id = $request->lesson_id;
            $new_task->operator_id = auth()->user()->user_id;
            $new_task->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_kk;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 1;
            $new_task_lang->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_ru;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 2;
            $new_task_lang->save();

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

            $new_task_option = new TaskOption();
            $new_task_option->task_id = $new_task->task_id;
            $new_task_option->show_audio_button = $request->show_audio_button;
            $new_task_option->show_image = $request->show_image;
            $new_task_option->show_transcription = $request->show_transcription;
            $new_task_option->show_translate = $request->show_translate;
            $new_task_option->impression_limit = $request->impression_limit;
            $new_task_option->save();

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

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
        ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
        ->select(
            'task_words.task_word_id',
            'dictionary.word',
            'dictionary.transcription',
            'dictionary.image_file',
            'dictionary.audio_file',
            'dictionary_translate.word_translate'
        )
        ->where('task_words.task_id', '=', $request->task_id)
        ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
        ->distinct()
        ->inRandomOrder()
        ->get();

        if(count($task_words) === 0){
            return response()->json('task words is not found', 404);
        }

        foreach ($task_words as $word) {
            $missing_letters = MissingLetter::where('task_word_id', '=', $word->task_word_id)
            ->select(
                'missing_letters.position',
            )
            ->orderBy('position', 'asc')
            ->pluck('position')->toArray();

            $word->missingLetters = $missing_letters;
        }

        $task = new \stdClass();

        $task->options = $task_options;
        $task->words = $task_words;

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
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'section_id' => 'required|numeric',
                'lesson_id' => 'required|numeric',
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
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            $new_task = new Task();
            $new_task->task_slug = $request->task_slug;
            $new_task->task_type_id = 2;
            $new_task->lesson_id = $request->lesson_id;
            $new_task->operator_id = auth()->user()->user_id;
            $new_task->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_kk;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 1;
            $new_task_lang->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_ru;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 2;
            $new_task_lang->save();

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $new_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();
                }
            }

            $new_task_option = new TaskOption();
            $new_task_option->task_id = $new_task->task_id;
            $new_task_option->show_audio_button = $request->show_audio_button;
            $new_task_option->show_transcription = $request->show_transcription;
            $new_task_option->show_translate = $request->show_translate;
            $new_task_option->impression_limit = $request->impression_limit;
            $new_task_option->save();

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

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
        ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
        ->select(
            'task_words.task_word_id',
            'dictionary.word',
            'dictionary.transcription',
            'dictionary.audio_file',
            'dictionary_translate.word_translate'
        )
        ->where('task_words.task_id', '=', $request->task_id)
        ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
        ->distinct()
        ->inRandomOrder()
        ->get();

        $task_pictures = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
        ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
        ->select(
            'task_words.task_word_id',
            'dictionary.image_file',
        )
        ->where('task_words.task_id', '=', $request->task_id)
        ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
        ->distinct()
        ->inRandomOrder()
        ->get();

        if(count($task_words) === 0 || count($task_pictures) === 0){
            return response()->json('task words is not found', 404);
        }

        $task = new \stdClass();

        $task->options = $task_options;
        $task->words = $task_words;
        $task->pictures = $task_pictures;

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
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'section_id' => 'required|numeric',
                'lesson_id' => 'required|numeric',
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
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'in_the_main_lang' => 'required|boolean',
                'step' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            $new_task = new Task();
            $new_task->task_slug = $request->task_slug;
            $new_task->task_type_id = 3;
            $new_task->lesson_id = $request->lesson_id;
            $new_task->operator_id = auth()->user()->user_id;
            $new_task->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_kk;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 1;
            $new_task_lang->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_ru;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 2;
            $new_task_lang->save();

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $new_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->save();
                }
            }

            $new_task_option = new TaskOption();
            $new_task_option->task_id = $new_task->task_id;
            $new_task_option->play_audio_with_the_correct_answer = $request->play_audio_with_the_correct_answer;
            $new_task_option->play_error_sound_with_the_incorrect_answer = $request->play_error_sound_with_the_incorrect_answer;
            $new_task_option->seconds_per_sentence = $request->seconds_per_sentence;
            $new_task_option->in_the_main_lang = $request->in_the_main_lang;
            $new_task_option->save();

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

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = TaskSentence::leftJoin('sentences', 'task_sentences.sentence_id', '=', 'sentences.sentence_id')
        ->leftJoin('sentences_translate', 'sentences.sentence_id', '=', 'sentences_translate.sentence_id')
        ->select(
            'task_sentences.task_sentence_id',
            'sentences.sentence',
            'sentences.audio_file',
            'sentences_translate.sentence_translate'
        )
        ->where('task_sentences.task_id', '=', $request->task_id)
        ->where('sentences_translate.lang_id', '=', $language->lang_id)  
        ->distinct()
        ->inRandomOrder()
        ->get();

        if(count($task_sentences) === 0){
            return response()->json('task sentences is not found', 404);
        }

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
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'section_id' => 'required|numeric',
                'lesson_id' => 'required|numeric',
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
                'play_audio_at_the_begin' => 'required|boolean',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'show_image' => 'required|boolean',
                'show_word' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'options_num' => 'required|numeric',
                'seconds_per_word' => 'required|numeric|min:3',
                'in_the_main_lang' => 'required|boolean',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            $new_task = new Task();
            $new_task->task_slug = $request->task_slug;
            $new_task->task_type_id = 4;
            $new_task->lesson_id = $request->lesson_id;
            $new_task->operator_id = auth()->user()->user_id;
            $new_task->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_kk;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 1;
            $new_task_lang->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_ru;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 2;
            $new_task_lang->save();

            $words = json_decode($request->words);

            if (count($words) > 0) {
                foreach ($words as $word) {
                    $new_task_word = new TaskWord();
                    $new_task_word->task_id = $new_task->task_id;
                    $new_task_word->word_id = $word->word_id;
                    $new_task_word->save();
                }
            }

            $new_task_option = new TaskOption();
            $new_task_option->task_id = $new_task->task_id;
            $new_task_option->show_audio_button = $request->show_audio_button;
            $new_task_option->play_audio_at_the_begin = $request->play_audio_at_the_begin;
            $new_task_option->play_audio_with_the_correct_answer = $request->play_audio_with_the_correct_answer;
            $new_task_option->play_error_sound_with_the_incorrect_answer = $request->play_error_sound_with_the_incorrect_answer;
            $new_task_option->show_image = $request->show_image;
            $new_task_option->show_word = $request->show_word;
            $new_task_option->show_transcription = $request->show_transcription;
            $new_task_option->options_num = $request->options_num;
            $new_task_option->seconds_per_word = $request->seconds_per_word;
            $new_task_option->in_the_main_lang = $request->in_the_main_lang;
            $new_task_option->save();

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

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
        ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
        ->select(
            'task_words.task_word_id',
            'dictionary.word',
            'dictionary.transcription',
            'dictionary.image_file',
            'dictionary.audio_file',
            'dictionary_translate.word_translate'
        )
        ->where('task_words.task_id', '=', $request->task_id)
        ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
        ->inRandomOrder()
        ->distinct()
        ->get();

        if(count($task_words) === 0){
            return response()->json('task words is not found', 404);
        }

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
            ->where('task_words.task_id', '=', $request->task_id)
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

        $task = new \stdClass();

        $task->options = $task_options;
        $task->words = $task_words;

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
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'section_id' => 'required|numeric',
                'lesson_id' => 'required|numeric',
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
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            $new_task = new Task();
            $new_task->task_slug = $request->task_slug;
            $new_task->task_type_id = 5;
            $new_task->lesson_id = $request->lesson_id;
            $new_task->operator_id = auth()->user()->user_id;
            $new_task->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_kk;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 1;
            $new_task_lang->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_ru;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 2;
            $new_task_lang->save();

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

            $new_task_option = new TaskOption();
            $new_task_option->task_id = $new_task->task_id;
            $new_task_option->show_audio_button = $request->show_audio_button;
            $new_task_option->play_audio_at_the_begin = $request->play_audio_at_the_begin;
            $new_task_option->play_audio_with_the_correct_answer = $request->play_audio_with_the_correct_answer;
            $new_task_option->play_error_sound_with_the_incorrect_answer = $request->play_error_sound_with_the_incorrect_answer;
            $new_task_option->show_image = $request->show_image;
            $new_task_option->show_transcription = $request->show_transcription;
            $new_task_option->show_translate = $request->show_translate;
            $new_task_option->options_num = $request->options_num;
            $new_task_option->seconds_per_word = $request->seconds_per_word;
            $new_task_option->save();

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

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_words = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
        ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
        ->select(
            'task_words.task_word_id',
            'dictionary.word',
            'dictionary.transcription',
            'dictionary.image_file',
            'dictionary.audio_file',
            'dictionary_translate.word_translate'
        )
        ->where('task_words.task_id', '=', $request->task_id)
        ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
        ->distinct()
        ->inRandomOrder()
        ->get();

        if(count($task_words) === 0){
            return response()->json('task words is not found', 404);
        }

        foreach ($task_words as $word) {
            $missing_letters = MissingLetter::where('task_word_id', '=', $word->task_word_id)
            ->select(
                'missing_letters.position',
            )
            ->orderBy('position', 'asc')
            ->pluck('position')->toArray();

            $word->missingLetters = $missing_letters;
        }

        $task = new \stdClass();

        $task->options = $task_options;
        $task->words = $task_words;

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
                'find_word_with_options' => 'required|boolean',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    if(!isset($sentence->removedWordIndex)){
                        return response()->json(['words_failed' => [trans('auth.remove_one_word_in_each_sentence')]], 422);
                    }

                    if($request->find_word_with_options == 1){
                        if(!isset($sentence->removedWordOptions) || count($sentence->removedWordOptions) < 2){
                            return response()->json(['words_failed' => [trans('auth.you_must_add_two_or_more_options_of_the_missing_words_for_each_sentence')]], 422);
                        }
                    }
                }

                return response()->json([
                    'step' => 3
                ], 200);
            }
        }
        elseif ($request->step == 4) {
            $rules = [
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'section_id' => 'required|numeric',
                'lesson_id' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            $new_task = new Task();
            $new_task->task_slug = $request->task_slug;
            $new_task->task_type_id = 6;
            $new_task->lesson_id = $request->lesson_id;
            $new_task->operator_id = auth()->user()->user_id;
            $new_task->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_kk;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 1;
            $new_task_lang->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_ru;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 2;
            $new_task_lang->save();

            $sentences = json_decode($request->sentences);

            if (count($sentences) > 0) {
                foreach ($sentences as $sentence) {
                    $new_task_sentence = new TaskSentence();
                    $new_task_sentence->task_id = $new_task->task_id;
                    $new_task_sentence->sentence_id = $sentence->sentence_id;
                    $new_task_sentence->missing_word_position = $sentence->removedWordIndex;
                    $new_task_sentence->save();

                    if($request->find_word_with_options == 1){
                        if(count($sentence->removedWordOptions) > 0){
                            foreach ($sentence->removedWordOptions as $key => $option) {
                                $new_missing_word = new MissingWord();
                                $new_missing_word->word = $option;
                                $new_missing_word->task_sentence_id = $new_task_sentence->task_sentence_id;
                                $new_missing_word->is_correct = ($key === 0 ? true : false);
                                $new_missing_word->save();
                            }
                        }
                    }
                }
            }

            $new_task_option = new TaskOption();
            $new_task_option->task_id = $new_task->task_id;
            $new_task_option->seconds_per_sentence = $request->seconds_per_sentence;
            $new_task_option->find_word_with_options = $request->find_word_with_options;
            $new_task_option->impression_limit = $request->impression_limit;
            $new_task_option->save();

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

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $task_sentences = TaskSentence::leftJoin('sentences', 'task_sentences.sentence_id', '=', 'sentences.sentence_id')
        ->leftJoin('sentences_translate', 'sentences.sentence_id', '=', 'sentences_translate.sentence_id')
        ->select(
            'task_sentences.task_sentence_id',
            'task_sentences.missing_word_position',
            'sentences.sentence',
            'sentences.audio_file',
            'sentences_translate.sentence_translate'
        )
        ->where('task_sentences.task_id', '=', $request->task_id)
        ->where('sentences_translate.lang_id', '=', $language->lang_id)  
        ->distinct()
        ->inRandomOrder()
        ->get();

        if(count($task_sentences) === 0){
            return response()->json('task sentences is not found', 404);
        }

        if($task_options->find_word_with_options == 1){
            foreach ($task_sentences as $sentence) {
                $missing_words = MissingWord::where('task_sentence_id', '=', $sentence->task_sentence_id)
                ->select(
                    'missing_words.missing_word_id',
                    'missing_words.word',
                    'missing_words.is_correct',
                )
                ->inRandomOrder()
                ->get();
    
                $sentence->missingWords = $missing_words;
            }
        }

        $task = new \stdClass();

        $task->options = $task_options;
        $task->sentences = $task_sentences;

        return response()->json($task, 200);
    }

    public function create_match_paired_words_task(Request $request)
    {
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'sections_count' => 'required|numeric|min:1',
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
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'section_id' => 'required|numeric',
                'lesson_id' => 'required|numeric',
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
                // 'show_audio_button' => 'required|boolean',
                // 'play_audio_at_the_begin' => 'required|boolean',
                // 'play_audio_with_the_correct_answer' => 'required|boolean',
                // 'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                // 'show_image' => 'required|boolean',
                // 'show_word' => 'required|boolean',
                // 'show_transcription' => 'required|boolean',
                // 'options_num' => 'required|numeric',
                'seconds_per_word' => 'required|numeric|min:3',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            
            $new_task = new Task();
            $new_task->task_slug = $request->task_slug;
            $new_task->task_type_id = 7;
            $new_task->lesson_id = $request->lesson_id;
            $new_task->operator_id = auth()->user()->user_id;
            $new_task->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_kk;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 1;
            $new_task_lang->save();

            $new_task_lang = new TaskLang();
            $new_task_lang->task_name = $request->task_name_ru;
            $new_task_lang->task_id = $new_task->task_id;
            $new_task_lang->lang_id = 2;
            $new_task_lang->save();

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
                            $new_word_section_item->target = isset($section_item->target) ? true : false;
                            $new_word_section_item->save();
                        }
                    }
                }
            }

            $new_task_option = new TaskOption();
            $new_task_option->task_id = $new_task->task_id;
            // $new_task_option->show_audio_button = $request->show_audio_button;
            // $new_task_option->play_audio_at_the_begin = $request->play_audio_at_the_begin;
            // $new_task_option->play_audio_with_the_correct_answer = $request->play_audio_with_the_correct_answer;
            // $new_task_option->play_error_sound_with_the_incorrect_answer = $request->play_error_sound_with_the_incorrect_answer;
            // $new_task_option->show_image = $request->show_image;
            // $new_task_option->show_word = $request->show_word;
            // $new_task_option->show_transcription = $request->show_transcription;
            // $new_task_option->options_num = $request->options_num;
            $new_task_option->seconds_per_word = $request->seconds_per_word;
            // $new_task_option->in_the_main_lang = $request->in_the_main_lang;
            $new_task_option->save();

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

        $task_options = TaskOption::where('task_id', '=', $request->task_id)
        ->first();

        if(!isset($task_options)){
            return response()->json('task option is not found', 404);
        }

        $word_sections = WordSection::select(
            'word_sections.word_section_id',
            'word_sections.task_id'
        )
        ->where('word_sections.task_id', '=', $request->task_id)
        ->inRandomOrder()
        ->get();

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
                'dictionary.audio_file',
                'dictionary_translate.word_translate'
            )
            ->where('word_section_items.word_section_id', '=', $section->word_section_id)
            ->where('dictionary_translate.lang_id', '=', $language->lang_id)  
            ->distinct()
            ->inRandomOrder()
            ->get();

            $section->words = $section_items;
        }

        $task = new \stdClass();

        $task->options = $task_options;
        $task->word_sections = $word_sections;

        return response()->json($task, 200);
    }
}