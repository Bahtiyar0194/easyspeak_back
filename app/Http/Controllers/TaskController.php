<?php

namespace App\Http\Controllers;
use App\Models\Group;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\CompletedTask;
use App\Models\Language;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;

use App\Services\TaskService;
use App\Services\SchoolService;

use App\Jobs\LessonProgressJob;

use App\Tasks\MissingLettersTaskHandler;
use App\Tasks\LearningWordsTaskHandler;
use App\Tasks\FormWordOutOfTheLettersTaskHandler;
use App\Tasks\FindAnExtraWordTaskHandler;
use App\Tasks\MatchSameWordsTaskHandler;
use App\Tasks\MatchWordsByPicturesTaskHandler;
use App\Tasks\FindTheStressedSyllableTaskHandler;
use App\Tasks\FormSentenceOutOfTheWordsTaskHandler;
use App\Tasks\FillInTheBlanksInTheSentenceTaskHandler;
use App\Tasks\RightOrWrongTaskHandler;
use App\Tasks\MatchSentencesWithMaterialsTaskHandler;
use App\Tasks\AnswerTheQuestionsTaskHandler;
use App\Tasks\ChooseTheRightAnswerTaskHandler;
use App\Tasks\PronunciationCheckTaskHandler;

use DB;

use Illuminate\Http\Request;

class TaskController extends Controller
{

    protected $taskService;
    protected $schoolService;

    public function __construct(Request $request, TaskService $taskService, SchoolService $schoolService)
    {
        $this->taskService = $taskService;
        $this->schoolService = $schoolService;
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
                    'course_sections.sort_num',
                    'course_sections.section_name'
                )
                ->distinct()
                ->orderBy('course_sections.sort_num', 'asc')
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
            LessonProgressJob::dispatch();
            return response()->json('delete task is success', 200);
        }
    }

    public function save_task_result(Request $request){
        $task = Task::findOrFail($request->task_id);
        $task_result = json_decode($request->task_result);

        return $this->taskService->saveTaskResult($task->task_id, $task->lesson_id, $task_result);
    }

    public function change_task_result(Request $request){
        $completed_task = CompletedTask::leftJoin('users as learner', 'learner.user_id', '=', 'completed_tasks.learner_id')
        ->where('learner.school_id', '=', auth()->user()->school_id)
        ->select('completed_tasks.*')
        ->findOrFail($request->completed_task_id);

        // Оценить может учитель этой работы, владелец или администратор школы.
        // Раньше запрос принимал любой сотрудник с правом 26, хотя кнопку
        // интерфейс показывал только назначенному учителю
        $auth_user = auth()->user();
        if (
            (int) $completed_task->mentor_id !== (int) $auth_user->user_id &&
            !$auth_user->hasRole(['super_admin', 'school_owner', 'school_admin'])
        ) {
            return response()->json('Forbidden', 403);
        }

        // Уже проверенную работу второй раз не сохраняем (двойной клик, вторая вкладка)
        if ((int) $completed_task->is_completed === 1) {
            return response()->json('Task result already checked', 409);
        }

        $answers = json_decode($request->answers);

        return $this->taskService->changeTaskResult($completed_task->completed_task_id, $answers);
    }

    public function check_answers(Request $request){
        return app(AnswerTheQuestionsTaskHandler::class)->checkAnswers($request);
    }

    public function get_task_results(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
    
        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        // Результаты учеников видят только сотрудники школы
        if (!$auth_user->hasRole(['super_admin', 'school_owner', 'school_admin', 'mentor'])) {
            return response()->json('Access denied', 403);
        }

        // Получаем параметры лимита на страницу (не больше 100)
        $per_page = min(max((int) $request->per_page ?: 10, 1), 100);

        // Сортировка только по разрешённым колонкам: ключ приходит с клиента.
        // lessons_lang.lesson_name — ключ фронтенда, такой таблицы в запросе нет
        $sortable = [
            'tasks_lang.task_name' => 'tasks_lang.task_name',
            'tasks.task_slug' => 'tasks.task_slug',
            'types_of_tasks_lang.task_type_name' => 'types_of_tasks_lang.task_type_name',
            'lessons_lang.lesson_name' => 'lessons.lesson_name',
            'lessons.lesson_name' => 'lessons.lesson_name',
            'course_sections.section_name' => 'course_sections.section_name',
            'course_levels_lang.level_name' => 'course_levels_lang.level_name',
            'courses_lang.course_name' => 'courses_lang.course_name',
            'groups.group_name' => 'groups.group_name',
            'learner_last_name' => 'learner_last_name',
            'mentor_last_name' => 'mentor_last_name',
            'completed_tasks.created_at' => 'completed_tasks.created_at',
            'completed_tasks.is_completed' => 'completed_tasks.is_completed',
        ];
        $sortKey = $sortable[$request->input('sort_key')] ?? 'completed_tasks.created_at';
        $sortDirection = strtolower((string) $request->input('sort_direction')) === 'desc' ? 'desc' : 'asc';

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
                $join->on('group_members.member_id', '=', 'completed_tasks.learner_id')
                ->where('group_members.status_type_id', '=', 1);
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

        // Сколько работ ждут проверки — для вкладки «Ждут проверки», без учёта фильтров
        $pending_count = (clone $query)
            ->where('completed_tasks.is_completed', '=', 0)
            ->distinct()
            ->count('completed_tasks.completed_task_id');

        // Одна конкретная работа — переход из уведомления
        if ($request->filled('completed_task_id')) {
            $query->where('completed_tasks.completed_task_id', '=', (int) $request->completed_task_id);
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

        // Фильтрация по дате создания
        if ($created_at_from && $created_at_to) {
            $query->whereBetween('completed_tasks.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:59']);
        } elseif ($created_at_from) {
            $query->where('completed_tasks.created_at', '>=', $created_at_from . ' 00:00:00');
        } elseif ($created_at_to) {
            $query->where('completed_tasks.created_at', '<=', $created_at_to . ' 23:59:59');
        }

        
        // Фронтенд отправляет «не проверено» как '0' в кавычках, чтобы обойти empty('0')
        $is_completed = trim((string) $is_completed, "'\" ");

        if (in_array($is_completed, ['0', '1'], true)) {
            $query->where('completed_tasks.is_completed', '=', (int) $is_completed);
        }
    
        // Сортировка (вторичная — по номеру, чтобы порядок был стабильным)
        $query->orderBy($sortKey, $sortDirection)
            ->orderBy('completed_tasks.completed_task_id', $sortDirection);
    
        // Пагинация
        $completed_tasks = $query->paginate($per_page)->onEachSide(1);
    
        // Добавляем результаты задач
        $results = $this->taskService->getTaskResults($completed_tasks->getCollection()->map(function ($task) {
            return [$task->task_id, $task->learner_id];
        })->all());

        $completed_tasks->getCollection()->transform(function ($task) use ($results) {
            $task->task_result = $results[$this->taskService->taskResultKey($task->task_id, $task->learner_id)];

            return $task;
        });
    
        return response()->json(array_merge($completed_tasks->toArray(), [
            'pending_count' => $pending_count,
        ]), 200);
    }    

    public function create_missing_letters_task(Request $request)
    {
        return app(MissingLettersTaskHandler::class)->create($request);
    }

    public function edit_missing_letters_task(Request $request)
    {
        return app(MissingLettersTaskHandler::class)->edit($request);
    }

    public function get_missing_letters_task(Request $request)
    {
        return response()->json(app(MissingLettersTaskHandler::class)->show($request), 200);
    }

    public function create_match_words_by_pictures_task(Request $request)
    {
        return app(MatchWordsByPicturesTaskHandler::class)->create($request);
    }

    public function edit_match_words_by_pictures_task(Request $request)
    {
        return app(MatchWordsByPicturesTaskHandler::class)->edit($request);
    }

    public function get_match_words_by_pictures_task(Request $request)
    {
        return response()->json(app(MatchWordsByPicturesTaskHandler::class)->show($request), 200);
    }

    public function create_form_a_sentence_out_of_the_words_task(Request $request)
    {
        return app(FormSentenceOutOfTheWordsTaskHandler::class)->create($request);
    }

    public function edit_form_a_sentence_out_of_the_words_task(Request $request)
    {
        return app(FormSentenceOutOfTheWordsTaskHandler::class)->edit($request);
    }

    public function get_form_a_sentence_out_of_the_words_task(Request $request)
    {
        return response()->json(app(FormSentenceOutOfTheWordsTaskHandler::class)->show($request), 200);
    }

    public function create_learning_words_task(Request $request)
    {
        return app(LearningWordsTaskHandler::class)->create($request);
    }

    public function edit_learning_words_task(Request $request)
    {
        return app(LearningWordsTaskHandler::class)->edit($request);
    }

    public function get_learning_words_task(Request $request)
    {
        return response()->json(app(LearningWordsTaskHandler::class)->show($request), 200);
    }

    public function create_form_a_word_out_of_the_letters_task(Request $request)
    {
        return app(FormWordOutOfTheLettersTaskHandler::class)->create($request);
    }

    public function edit_form_a_word_out_of_the_letters_task(Request $request)
    {
        return app(FormWordOutOfTheLettersTaskHandler::class)->edit($request);
    }

    public function get_form_a_word_out_of_the_letters_task(Request $request)
    {
        return response()->json(app(FormWordOutOfTheLettersTaskHandler::class)->show($request), 200);
    }

    public function create_fill_in_the_blanks_in_the_sentence_task(Request $request)
    {
        return app(FillInTheBlanksInTheSentenceTaskHandler::class)->create($request);
    }

    public function edit_fill_in_the_blanks_in_the_sentence_task(Request $request)
    {
        return app(FillInTheBlanksInTheSentenceTaskHandler::class)->edit($request);
    }

    public function get_fill_in_the_blanks_in_the_sentence_task(Request $request)
    {
        return response()->json(app(FillInTheBlanksInTheSentenceTaskHandler::class)->show($request), 200);
    }

    public function create_match_same_words_task(Request $request)
    {
        return app(MatchSameWordsTaskHandler::class)->create($request);
    }

    public function edit_match_same_words_task(Request $request)
    {
        return app(MatchSameWordsTaskHandler::class)->edit($request);
    }

    public function get_match_same_words_task(Request $request)
    {
        return response()->json(app(MatchSameWordsTaskHandler::class)->show($request), 200);
    }

    public function create_find_an_extra_word_task(Request $request)
    {
        return app(FindAnExtraWordTaskHandler::class)->create($request);
    }

    public function edit_find_an_extra_word_task(Request $request)
    {
        return app(FindAnExtraWordTaskHandler::class)->edit($request);
    }

    public function get_find_an_extra_word_task(Request $request)
    {
        return response()->json(app(FindAnExtraWordTaskHandler::class)->show($request), 200);
    }

    public function create_right_or_wrong_task(Request $request)
    {
        return app(RightOrWrongTaskHandler::class)->create($request);
    }

    public function edit_right_or_wrong_task(Request $request)
    {
        return app(RightOrWrongTaskHandler::class)->edit($request);
    }

    public function get_right_or_wrong_task(Request $request)
    {
        return response()->json(app(RightOrWrongTaskHandler::class)->show($request), 200);
    }

    public function create_match_sentences_with_materials_task(Request $request)
    {
        return app(MatchSentencesWithMaterialsTaskHandler::class)->create($request);
    }

    public function edit_match_sentences_with_materials_task(Request $request)
    {
        return app(MatchSentencesWithMaterialsTaskHandler::class)->edit($request);
    }

    public function get_match_sentences_with_materials_task(Request $request)
    {
        return response()->json(app(MatchSentencesWithMaterialsTaskHandler::class)->show($request), 200);
    }

    public function create_find_the_stressed_syllable_task(Request $request)
    {
        return app(FindTheStressedSyllableTaskHandler::class)->create($request);
    }

    public function edit_find_the_stressed_syllable_task(Request $request)
    {
        return app(FindTheStressedSyllableTaskHandler::class)->edit($request);
    }

    public function get_find_the_stressed_syllable_task(Request $request)
    {
        return response()->json(app(FindTheStressedSyllableTaskHandler::class)->show($request), 200);
    }

    public function create_answer_the_questions_task(Request $request)
    {
        return app(AnswerTheQuestionsTaskHandler::class)->create($request);
    }

    public function edit_answer_the_questions_task(Request $request)
    {
        return app(AnswerTheQuestionsTaskHandler::class)->edit($request);
    }

    public function get_answer_the_questions_task(Request $request)
    {
        return response()->json(app(AnswerTheQuestionsTaskHandler::class)->show($request), 200);
    }

    public function create_pronunciation_check_task(Request $request)
    {
        return app(PronunciationCheckTaskHandler::class)->create($request);
    }

    public function edit_pronunciation_check_task(Request $request)
    {
        return app(PronunciationCheckTaskHandler::class)->edit($request);
    }

    public function get_pronunciation_check_task(Request $request)
    {
        return response()->json(app(PronunciationCheckTaskHandler::class)->show($request), 200);
    }

    public function create_choose_the_right_answer_task(Request $request)
    {
        return app(ChooseTheRightAnswerTaskHandler::class)->create($request);
    }

    public function edit_choose_the_right_answer_task(Request $request)
    {
        return app(ChooseTheRightAnswerTaskHandler::class)->edit($request);
    }

    public function get_choose_the_right_answer_task(Request $request)
    {
        return response()->json(app(ChooseTheRightAnswerTaskHandler::class)->show($request), 200);
    }

    public function set_task_progress(Request $request){
        $completed_tasks = CompletedTask::where('progress', '=', 0)
        ->limit(1000)
        ->get();

        foreach ($completed_tasks as $key => $ct) {
            $get_task_result = $this->taskService->getTaskResult($ct->task_id, $ct->learner_id);

            $save_task_progress = CompletedTask::find($ct->completed_task_id);
            $save_task_progress->progress = $get_task_result->percentage;
            $save_task_progress->is_completed = $get_task_result->completed;
            $save_task_progress->save();
        }

        echo 123;
    }

    public function set_lesson_progress(Request $request){
        set_time_limit(3000);

        $users = User::get();
        $lessons = Lesson::get();

        foreach ($lessons as $key => $lesson) {
            $tasks = Task::where('lesson_id', $lesson->lesson_id)
            ->get();

            $lesson->tasks = $tasks;
        }

        foreach ($users as $user) {
            foreach ($lessons as $lesson) {

                $total_progress = 0;

                foreach ($lesson->tasks as $task) {
                    $completed_tasks = CompletedTask::where('learner_id', $user->user_id)
                    ->where('task_id', $task->task_id)
                    ->get();

                    foreach ($completed_tasks as $completed_task) {
                        $total_progress += $completed_task->progress;
                    }
                }

                $task_count = count($lesson->tasks);

                if($total_progress > 0 && $task_count > 0){
                    // Удаляем старый прогресс по уроку
                    LessonProgress::where('lesson_id', '=', $lesson->lesson_id)
                    ->where('learner_id', '=', $user->user_id)
                    ->delete();

                    $new_lesson_progress = new LessonProgress();
                    $new_lesson_progress->lesson_id = $lesson->lesson_id;
                    $new_lesson_progress->learner_id = $user->user_id;
                    $new_lesson_progress->progress = $task_count > 0 ? $total_progress / $task_count : 0;
                    $new_lesson_progress->save();
                }
            }
        }

        echo 123;
    }
}