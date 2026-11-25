<?php

namespace App\Tasks;

/** «Найди лишнее слово»: в каждой секции нужно выбрать целевое (лишнее) слово. */
class FindAnExtraWordTaskHandler extends WordSectionsTaskHandler
{
    protected function taskTypeId(): int
    {
        return 8;
    }

    protected function shuffleSectionWords(): bool
    {
        return true;
    }

    protected function optionsRules(): array
    {
        return [
            'show_audio_button' => 'required|boolean',
            'impression_limit' => 'required|min:1',
            'seconds_per_section' => 'required|numeric|min:3',
        ];
    }
}
