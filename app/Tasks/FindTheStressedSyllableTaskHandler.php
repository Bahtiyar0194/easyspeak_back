<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskWord;
use App\Models\WordSyllable;
use Illuminate\Http\Request;

/**
 * «Найди ударный слог»: слова разбиты на слоги, один из них отмечен ударным.
 *
 * Шаги: 1 — выбор слов, 2 — разбиение на слоги и ударение, 3 — опции, 4 — материалы и сохранение.
 * words: [{word_id, syllables: [{syllable, target: 'true'|...}]}].
 */
class FindTheStressedSyllableTaskHandler extends TaskHandler
{
    protected function taskTypeId(): int
    {
        return 12;
    }

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
            return array_merge($this->baseOptionsRules(), [
                'show_audio_button' => 'required|boolean',
                'show_transcription' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'seconds_per_word' => 'required|numeric|min:3',
                'impression_limit' => 'required|min:1',
            ]);
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        if ($step === 1) {
            return $this->emptyJsonArrayError($request, 'words');
        }

        if ($step === 2) {
            $words = $this->decodeJsonArray($request, 'words');

            // Сначала проверяем разбиение всех слов, потом ударения — как и раньше
            foreach ($words as $word) {
                if (count($this->syllables($word)) < 2) {
                    return ['syllables_failed' => [trans('auth.divide_each_word_into_syllables')]];
                }
            }

            foreach ($words as $word) {
                $stress_found = false;

                foreach ($this->syllables($word) as $syllable) {
                    if ($this->isTarget($syllable)) {
                        $stress_found = true;
                        break;
                    }
                }

                if ($stress_found === false) {
                    return ['syllables_failed' => [trans('auth.specify_one_of_the_syllables_as_the_stress_in_each_word')]];
                }
            }
        }

        return null;
    }

    protected function store(Task $task, Request $request, bool $is_edit): void
    {
        if ($is_edit) {
            // word_syllables удаляются каскадно вместе с task_words
            TaskWord::where('task_id', $task->task_id)->delete();
        }

        foreach ($this->decodeJsonArray($request, 'words') as $word) {
            $task_word = $this->saveTaskWord($task, $word->word_id);

            foreach ($this->syllables($word) as $syllable) {
                $word_syllable = new WordSyllable();
                $word_syllable->task_word_id = $task_word->task_word_id;
                $word_syllable->syllable = $syllable->syllable;
                $word_syllable->target = $this->isTarget($syllable);
                $word_syllable->save();
            }
        }
    }

    public function show(Request $request): array
    {
        $language = $this->language($request);
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $words = $this->taskService->getTaskWords($task->task_id, $language, $options);

        $syllables = WordSyllable::whereIn('task_word_id', $words->pluck('task_word_id'))
            ->select(
                'word_syllables.word_syllable_id',
                'word_syllables.task_word_id',
                'word_syllables.syllable',
                'word_syllables.target'
            )
            ->orderBy('word_syllables.word_syllable_id', 'asc')
            ->get()
            ->groupBy('task_word_id');

        foreach ($words as $word) {
            // task_word_id убираем, чтобы ответ совпадал с прежним форматом
            $word->syllables = $syllables->get($word->task_word_id, collect())
                ->map(function ($syllable) {
                    return $syllable->makeHidden('task_word_id');
                })
                ->values();
        }

        return [
            'task' => $task,
            'options' => $options,
            'words' => $words,
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }

    protected function syllables($word): array
    {
        return isset($word->syllables) && is_array($word->syllables) ? $word->syllables : [];
    }

    protected function isTarget($syllable): bool
    {
        return isset($syllable->target) && $syllable->target == 'true';
    }
}
