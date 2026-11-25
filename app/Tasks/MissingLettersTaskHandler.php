<?php

namespace App\Tasks;

/** «Пропущенные буквы»: в словах скрываются выбранные буквы, ученик их вписывает. */
class MissingLettersTaskHandler extends LettersTaskHandler
{
    protected function taskTypeId(): int
    {
        return 1;
    }

    protected function optionsRules(): array
    {
        return [
            'show_audio_button' => 'required|boolean',
            'show_image' => 'required|boolean',
            'show_transcription' => 'required|boolean',
            'show_translate' => 'required|boolean',
            'impression_limit' => 'required|min:1',
            'seconds_per_word' => 'required|numeric|min:3',
        ];
    }
}
