<?php

namespace App\Tasks;

use App\Models\PhraseAnswer;
use App\Models\PhraseQuestion;
use App\Models\Task;
use Illuminate\Http\Request;

/**
 * «Выбери правильный ответ»: вопросы с вариантами, хотя бы один вариант верный и хотя бы один неверный.
 *
 * Шаги: 1 — опции, 2 — вопросы и ответы, 3 — материалы и сохранение.
 * questions: [{question, answers: [{title, is_correct}]}]; lang — язык сообщений об ошибках.
 */
class ChooseTheRightAnswerTaskHandler extends TaskHandler
{
    protected function taskTypeId(): int
    {
        return 15;
    }

    protected function stepsCount(): int
    {
        return 3;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            return array_merge($this->baseOptionsRules(), [
                'option_label' => 'required',
                'seconds_per_question' => 'required|numeric|min:10',
            ]);
        }

        if ($step === 2) {
            return [
                'questions_count' => 'required|numeric|min:1',
                'questions' => 'required|array|min:1',
                'questions.*.question' => 'required|string',
                'questions.*.answers.*.title' => 'required|string',
                'questions.*.answers' => [
                    'required',
                    'array',
                    'min:2',
                    function ($attribute, $value, $fail) {
                        $correct_count = collect($value)->filter(function ($answer) {
                            return isset($answer['is_correct']) && filter_var($answer['is_correct'], FILTER_VALIDATE_BOOLEAN);
                        })->count();

                        if ($correct_count === 0) {
                            $fail(trans('app.quiz.indicate'));
                        } elseif ($correct_count === count($value)) {
                            $fail(trans('app.quiz.one'));
                        }
                    },
                ],
            ];
        }

        return [];
    }

    /** Правила шага 2 проверяют questions как массив, а с фронтенда приходит JSON-строка */
    protected function validationData(int $step, Request $request): array
    {
        if ($step !== 2) {
            return $request->all();
        }

        if ($request->filled('lang')) {
            app()->setLocale($request->lang);
        }

        return array_merge($request->all(), [
            'questions' => json_decode($request->input('questions'), true),
        ]);
    }

    protected function store(Task $task, Request $request, bool $is_edit): void
    {
        if ($is_edit) {
            // phrase_answers удаляются каскадно
            PhraseQuestion::where('task_id', $task->task_id)->delete();
        }

        foreach ($this->decodeJsonArray($request, 'questions') as $question) {
            $phrase_question = new PhraseQuestion();
            $phrase_question->task_id = $task->task_id;
            $phrase_question->question = $question->question;
            $phrase_question->save();

            foreach ($question->answers as $answer) {
                $phrase_answer = new PhraseAnswer();
                $phrase_answer->question_id = $phrase_question->question_id;
                $phrase_answer->answer = $answer->title;
                $phrase_answer->is_correct = $answer->is_correct;
                $phrase_answer->save();
            }
        }
    }

    public function show(Request $request): array
    {
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $questions = PhraseQuestion::where('task_id', '=', $task->task_id)
            ->select('question_id', 'question');

        if ($options->random_order == 1) {
            $questions->inRandomOrder();
        }

        $questions = $questions->get();

        foreach ($questions as $question) {
            $answers = PhraseAnswer::where('question_id', '=', $question->question_id)
                ->select('answer', 'is_correct')
                ->inRandomOrder()
                ->get();

            foreach ($answers as $answer) {
                $answer->title = $answer->answer;
                unset($answer->answer);
                $answer->is_correct = $answer->is_correct === 1;
            }

            $question->answers = $answers;
        }

        return [
            'task' => $task,
            'options' => $options,
            'questions' => $questions,
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }
}
