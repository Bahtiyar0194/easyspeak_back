<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskWord;
use Illuminate\Http\Request;

/**
 * Задания «слова с удалёнными буквами».
 *
 * Шаги: 1 — выбор слов, 2 — выбор букв, 3 — опции (optionsRules), 4 — материалы и сохранение.
 * words: [{word_id, word, removedLetters: [индексы с 0]}].
 */
abstract class LettersTaskHandler extends TaskHandler
{
    /** Правила шага опций, специфичные для типа (общие добавляются из baseOptionsRules) */
    abstract protected function optionsRules(): array;

    protected function stepsCount(): int
    {
        return 4;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            return [
                'words_count' => 'required|numeric|min:1',
                'words' => 'required',
            ];
        }

        if ($step === 3) {
            return array_merge($this->baseOptionsRules(), $this->optionsRules());
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        if ($step === 1) {
            return $this->emptyJsonArrayError($request, 'words');
        }

        if ($step === 2) {
            foreach ($this->decodeJsonArray($request, 'words') as $word) {
                $removed = isset($word->removedLetters) && is_array($word->removedLetters)
                    ? $word->removedLetters
                    : [];

                if (count($removed) === 0) {
                    return ['letters_failed' => [trans('auth.remove_at_least_one_letter_in_each_word')]];
                }

                // mb_strlen: strlen считает байты, и для кириллицы проверка не срабатывала
                if (mb_strlen($word->word ?? '') <= count(array_unique($removed))) {
                    return ['letters_failed' => [trans('auth.you_cannot_delete_all_the_letters_in_a_word')]];
                }
            }
        }

        return null;
    }

    protected function store(Task $task, Request $request, bool $is_edit): void
    {
        if ($is_edit) {
            // missing_letters удаляются каскадно вместе с task_words
            TaskWord::where('task_id', $task->task_id)->delete();
        }

        foreach ($this->decodeJsonArray($request, 'words') as $word) {
            $this->saveTaskWord($task, $word->word_id, $word->removedLetters);
        }
    }

    public function show(Request $request): array
    {
        $language = $this->language($request);
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $words = $this->taskService->getTaskWords($task->task_id, $language, $options);
        $missing_letters = $this->missingLettersByTaskWord($words->pluck('task_word_id'));

        foreach ($words as $word) {
            $word->missingLetters = $missing_letters->get($word->task_word_id, collect());
        }

        return [
            'task' => $task,
            'options' => $options,
            'words' => $words,
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }
}
