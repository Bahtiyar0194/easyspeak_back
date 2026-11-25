<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskWord;
use Illuminate\Http\Request;

/**
 * Задания из одного списка слов без дополнительных данных.
 *
 * Шаги: 1 — выбор слов, 2 — опции (optionsRules), 3 — материалы и сохранение.
 * words: [{word_id, ...}].
 */
abstract class WordsTaskHandler extends TaskHandler
{
    /** Правила шага опций, специфичные для типа (общие добавляются из baseOptionsRules) */
    abstract protected function optionsRules(): array;

    /** Минимальное количество слов в задании */
    protected function minWords(): int
    {
        return 1;
    }

    protected function stepsCount(): int
    {
        return 3;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            return [
                'words_count' => 'required|numeric|min:' . $this->minWords(),
                'words' => 'required',
            ];
        }

        if ($step === 2) {
            return array_merge($this->baseOptionsRules(), $this->optionsRules());
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
}
