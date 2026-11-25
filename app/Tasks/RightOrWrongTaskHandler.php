<?php

namespace App\Tasks;

use Illuminate\Http\Request;

/**
 * «Верно или неверно»: для каждого предложения задан правильный ответ.
 *
 * Шаги: 1 — выбор предложений, 2 — ответы, 3 — опции, 4 — материалы и сохранение.
 * sentences: [{sentence_id, answer, answer_type}].
 */
class RightOrWrongTaskHandler extends SentencesTaskHandler
{
    protected function taskTypeId(): int
    {
        return 9;
    }

    protected function stepsCount(): int
    {
        return 4;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            return $this->sentencesRules(2);
        }

        if ($step === 2) {
            return ['sentences' => 'required'];
        }

        if ($step === 3) {
            return array_merge($this->baseOptionsRules(), [
                'play_audio_at_the_begin' => 'required|boolean',
                'play_audio_with_the_correct_answer' => 'required|boolean',
                'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
                'show_translate' => 'required|boolean',
                'seconds_per_sentence' => 'required|numeric|min:10',
            ]);
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        if ($step === 1) {
            return $this->emptyJsonArrayError($request, 'sentences');
        }

        if ($step === 2) {
            foreach ($this->decodeJsonArray($request, 'sentences') as $sentence) {
                if (!isset($sentence->answer)) {
                    return ['answers_failed' => [trans('Answers failed')]];
                }
            }
        }

        return null;
    }

    protected function sentenceAttributes($sentence): array
    {
        return [
            'answer' => $sentence->answer,
            'answer_type' => $sentence->answer_type ?? null,
        ];
    }

    protected function decorateSentences($sentences, $options): void
    {
        foreach ($sentences as $sentence) {
            if ($sentence->answer_type === null) {
                $sentence->answer_type = 0;
            }
        }
    }
}
