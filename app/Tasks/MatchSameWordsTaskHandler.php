<?php

namespace App\Tasks;

use Illuminate\Http\Request;

/** «Сопоставь одинаковые слова»: ученик находит целевое слово выбранным способом. */
class MatchSameWordsTaskHandler extends WordSectionsTaskHandler
{
    protected function taskTypeId(): int
    {
        return 11;
    }

    protected function optionsRules(): array
    {
        return [
            'show_audio_button' => 'required|boolean',
            'impression_limit' => 'required|min:1',
            'seconds_per_section' => 'required|numeric|min:3',
        ];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        if ($step === 3) {
            if (
                !isset($request->match_by_typing) &&
                !isset($request->match_by_clicking) &&
                !isset($request->match_by_drag_and_drop)
            ) {
                return ['choose_one_of_the_methods' => ['Choose one of the methods is required.']];
            }

            return null;
        }

        return parent::validateStep($step, $request);
    }
}
