<?php

namespace App\Tasks;

use Illuminate\Http\Request;

/**
 * «Проверка произношения»: ученик читает предложения вслух.
 *
 * Шаги: 1 — выбор предложений, 2 — опции, 3 — материалы и сохранение.
 */
class PronunciationCheckTaskHandler extends SentencesTaskHandler
{
    protected function taskTypeId(): int
    {
        return 14;
    }

    protected function stepsCount(): int
    {
        return 3;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            return $this->sentencesRules(1);
        }

        if ($step === 2) {
            return array_merge($this->baseOptionsRules(), [
                'show_audio_button' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'impression_limit' => 'required|min:1',
                'seconds_per_sentence' => 'required|numeric|min:15',
            ]);
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        return $step === 1 ? $this->emptyJsonArrayError($request, 'sentences') : null;
    }
}
