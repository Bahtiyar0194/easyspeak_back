<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskQuestion;
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
    /** @var SchoolService */
    protected $schoolService;

    public function __construct(TaskService $taskService, SchoolService $schoolService)
    {
        parent::__construct($taskService);
        $this->schoolService = $schoolService;
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
