<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskWord;
use Illuminate\Http\Request;

/**
 * «Изучение слов»: к каждому слову ученик выбирает перевод из вариантов.
 *
 * Шаги: 1 — выбор слов (минимум 2, нужны варианты ответа), 2 — опции, 3 — материалы и сохранение.
 */
class LearningWordsTaskHandler extends TaskHandler
{
    protected function taskTypeId(): int
    {
        return 4;
    }

    protected function stepsCount(): int
    {
        return 3;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            return [
                'words_count' => 'required|numeric|min:2',
                'words' => 'required',
            ];
        }

        if ($step === 2) {
            return array_merge($this->baseOptionsRules(), [
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
            ]);
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        return $step === 1 ? $this->emptyJsonArrayError($request, 'words') : null;
    }

    protected function store(Task $task, Request $request, bool $is_edit): void
    {
        if ($is_edit) {
            TaskWord::where('task_id', $task->task_id)->delete();
        }

        foreach ($this->decodeJsonArray($request, 'words') as $word) {
            $this->saveTaskWord($task, $word->word_id);
        }
    }

    public function show(Request $request): array
    {
        $language = $this->language($request);
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $words = $this->taskService->getTaskWords($task->task_id, $language, $options);

        foreach ($words as $word) {
            // Случайные неверные варианты из других слов задания + правильный ответ
            $answers = $this->answerOptionsQuery($language)
                ->where('task_words.task_word_id', '!=', $word->task_word_id)
                ->where('task_words.task_id', '=', $task->task_id)
                ->inRandomOrder()
                ->limit($options->options_num - 1)
                ->distinct()
                ->get()
                ->toArray();

            $answers[] = $this->answerOptionsQuery($language)
                ->where('task_words.task_word_id', '=', $word->task_word_id)
                ->first();

            shuffle($answers);

            $word->answer_options = $answers;
        }

        return [
            'task' => $task,
            'options' => $options,
            'words' => $words,
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }

    protected function answerOptionsQuery($language)
    {
        return TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
            ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
            ->select(
                'task_words.task_word_id',
                'dictionary.word',
                'dictionary.transcription',
                'dictionary_translate.word_translate'
            )
            ->where('dictionary_translate.lang_id', '=', $language->lang_id);
    }
}
