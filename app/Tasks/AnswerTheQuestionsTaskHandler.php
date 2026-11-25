<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskQuestion;
use App\Services\AiAnswerChecker;
use App\Services\SchoolService;
use App\Services\TaskService;
use Illuminate\Http\Request;

/**
 * «Ответь на вопросы»: вопросы — предложения из базы, ответы проверяет ментор или ИИ (checking_by).
 *
 * Шаги: 1 — опции, 2 — выбор вопросов, 3 — способ ответа, 4 — материалы и сохранение.
 * questions: [{sentence_id, checking_by}].
 *
 * Внимание: при правке вопросы пересоздаются, и ответы учеников (task_answers)
 * удаляются каскадно.
 */
class AnswerTheQuestionsTaskHandler extends TaskHandler
{
    /** Максимальная длина ответа ученика, остальное обрезается */
    const MAX_ANSWER_LENGTH = 1000;

    /** @var SchoolService */
    protected $schoolService;

    /** @var AiAnswerChecker */
    protected $aiAnswerChecker;

    public function __construct(TaskService $taskService, SchoolService $schoolService, AiAnswerChecker $aiAnswerChecker)
    {
        parent::__construct($taskService);
        $this->schoolService = $schoolService;
        $this->aiAnswerChecker = $aiAnswerChecker;
    }

    /**
     * Проверить ответы ученика и сохранить результат.
     *
     * questions: [{task_question_id, userInput}]. Текст вопроса и способ проверки (checking_by)
     * берутся из БД, а не из запроса: иначе ученик мог подменить вопрос или способ проверки.
     * Пустой ответ — неверный; by_ai (или ИИ-школа) — оценка модели; иначе ответ ждёт ментора.
     */
    public function checkAnswers(Request $request)
    {
        $auth_user = auth()->user();

        $task = Task::where('task_type_id', '=', $this->taskTypeId())->findOrFail($request->task_id);

        // До обращения к OpenAI: не участник группы не должен расходовать запросы
        [$allowed] = $this->taskService->resolveResultMentor($task->task_id, $auth_user);

        if (!$allowed) {
            return $this->taskService->notMemberResponse();
        }

        $answers = [];

        foreach ($this->decodeJsonArray($request, 'questions') as $submitted) {
            if (isset($submitted->task_question_id)) {
                $answers[$submitted->task_question_id] = trim(mb_substr((string) ($submitted->userInput ?? ''), 0, self::MAX_ANSWER_LENGTH));
            }
        }

        // Только вопросы этого задания, в порядке отправки
        $questions = TaskQuestion::leftJoin('sentences', 'task_questions.question_id', '=', 'sentences.sentence_id')
            ->where('task_questions.task_id', '=', $task->task_id)
            ->whereIn('task_questions.task_question_id', array_keys($answers))
            ->select(
                'task_questions.task_question_id',
                'task_questions.question_id',
                'task_questions.checking_by',
                'sentences.sentence'
            )
            ->get()
            ->keyBy('task_question_id');

        $check_all_by_ai = $this->schoolService->isAiSchoolDomain($auth_user->school_id);
        $to_ai = [];

        foreach ($answers as $task_question_id => $answer) {
            $question = $questions->get($task_question_id);

            if ($question && $answer !== '' && ($question->checking_by === 'by_ai' || $check_all_by_ai)) {
                $to_ai[$task_question_id] = ['question' => (string) $question->sentence, 'answer' => $answer];
            }
        }

        $grades = $this->aiAnswerChecker->checkMany($to_ai);

        $task_result = [];

        foreach ($answers as $task_question_id => $answer) {
            $question = $questions->get($task_question_id);

            if (!$question) {
                continue;
            }

            $task_result[] = $this->answerResult($question->question_id, $answer, $grades[$task_question_id] ?? null);
        }

        return $this->taskService->saveTaskResult($task->task_id, $task->lesson_id, json_decode(json_encode($task_result)));
    }

    /**
     * Результат по одному вопросу в формате task_answers.
     * user_answer без оценки хранится как текст; обёрнутый в HTML — экранируется (выводится через v-html).
     */
    protected function answerResult($question_id, string $answer, ?array $grade): array
    {
        if ($answer === '') {
            return ['question_id' => $question_id, 'is_correct' => 0];
        }

        if ($grade === null) {
            return ['question_id' => $question_id, 'user_answer' => $answer];
        }

        $escaped = e($answer);

        return [
            'question_id' => $question_id,
            'is_correct' => $grade['grade'],
            'right_answer' => $grade['grade'] === 1 ? "<p class='font-medium mb-0 text-success underline'>{$escaped}</p>" : null,
            'user_answer' => $grade['grade'] === 0 ? "<p class='font-medium mb-0 text-danger underline'>{$escaped}</p>" : null,
            'comment' => $grade['comment'],
        ];
    }

    protected function taskTypeId(): int
    {
        return 13;
    }

    protected function stepsCount(): int
    {
        return 4;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            // У этого типа нет max_attempts, вместо него max_answer_attempts
            $rules = $this->baseOptionsRules();
            unset($rules['max_attempts']);

            return array_merge($rules, [
                'impression_limit' => 'required|min:1',
                'seconds_per_question' => 'required|numeric|min:10',
                'max_answer_attempts' => 'required|numeric',
            ]);
        }

        if ($step === 2) {
            return [
                'questions_count' => 'required|numeric|min:1',
                'questions' => 'required',
            ];
        }

        if ($step === 3) {
            return [
                'questions' => 'required',
                'answer_the_questions_option' => 'required|string',
            ];
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        return $step === 2 ? $this->emptyJsonArrayError($request, 'questions') : null;
    }

    protected function store(Task $task, Request $request, bool $is_edit): void
    {
        if ($is_edit) {
            TaskQuestion::where('task_id', $task->task_id)->delete();
        }

        foreach ($this->decodeJsonArray($request, 'questions') as $question) {
            $task_question = new TaskQuestion();
            $task_question->task_id = $task->task_id;
            $task_question->question_id = $question->sentence_id;
            $task_question->checking_by = $question->checking_by;
            $task_question->save();
        }
    }

    public function show(Request $request): array
    {
        $language = $this->language($request);
        $auth_user = auth()->user();
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $is_member = Task::join('lessons', 'lessons.lesson_id', '=', 'tasks.lesson_id')
            ->join('course_sections', 'course_sections.section_id', '=', 'lessons.section_id')
            ->join('course_levels', 'course_levels.level_id', '=', 'course_sections.level_id')
            ->join('groups', 'groups.level_id', '=', 'course_levels.level_id')
            ->join('group_members', 'group_members.group_id', '=', 'groups.group_id')
            ->where('tasks.task_id', $task->task_id)
            ->where('group_members.member_id', $auth_user->user_id)
            ->where('group_members.status_type_id', '=', 1)
            ->exists();

        $has_access = $is_member
            || $auth_user->hasRole(['super_admin', 'school_owner', 'school_admin', 'mentor'])
            || $this->schoolService->isAiSchoolDomain($auth_user->school_id);

        if (!$has_access) {
            abort(response()->json([trans('auth.you_are_not_a_member_of_group')], 422));
        }

        return [
            'task' => $task,
            'options' => $options,
            'questions' => $this->taskService->getTaskQuestions($task->task_id, $language, $options),
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }
}
