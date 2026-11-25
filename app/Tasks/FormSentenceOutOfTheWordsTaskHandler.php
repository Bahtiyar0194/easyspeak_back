<?php

namespace App\Tasks;

use Illuminate\Http\Request;

/**
 * «Составь предложение из слов».
 *
 * Шаги: 1 — выбор предложений, 2 — опции, 3 — материалы и сохранение.
 */
class FormSentenceOutOfTheWordsTaskHandler extends SentencesTaskHandler
{
    protected function taskTypeId(): int
    {
        return 3;
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
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'in_the_main_lang' => 'required|boolean',
            ]);
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        return $step === 1 ? $this->emptyJsonArrayError($request, 'sentences') : null;
    }
}
