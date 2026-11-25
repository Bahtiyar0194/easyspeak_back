<?php

namespace App\Tasks;

/** «Составь слово из букв»: ученик собирает удалённые буквы слова. */
class FormWordOutOfTheLettersTaskHandler extends LettersTaskHandler
{
    protected function taskTypeId(): int
    {
        return 5;
    }

    protected function optionsRules(): array
    {
        return [
            'show_audio_button' => 'required|boolean',
            'play_audio_at_the_begin' => 'required|boolean',
            'play_audio_with_the_correct_answer' => 'required|boolean',
            'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
            'show_image' => 'required|boolean',
            'show_transcription' => 'required|boolean',
            'show_translate' => 'required|boolean',
            'seconds_per_word' => 'required|numeric|min:3',
        ];
    }
}
