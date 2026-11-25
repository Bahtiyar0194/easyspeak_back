<?php

namespace App\Tasks;

use App\Models\TaskWord;
use Illuminate\Http\Request;

/**
 * «Изучение слов»: к каждому слову ученик выбирает перевод из вариантов.
 * Минимум 2 слова — из других слов задания строятся неверные варианты.
 */
class LearningWordsTaskHandler extends WordsTaskHandler
{
    protected function taskTypeId(): int
    {
        return 4;
    }

    protected function minWords(): int
    {
        return 2;
    }

    protected function optionsRules(): array
    {
        return [
            'show_audio_button' => 'required|boolean',
            'play_audio_at_the_begin' => 'required|boolean',
            'play_audio_with_the_correct_answer' => 'required|boolean',
            'play_error_sound_with_the_incorrect_answer' => 'required|boolean',
            'show_image' => 'required|boolean',
            'show_word' => 'required|boolean',
            'show_transcription' => 'required|boolean',
            'options_num' => 'required|numeric',
            'seconds_per_word' => 'required|numeric|min:3',
            'in_the_main_lang' => 'required|boolean',
        ];
    }

    public function show(Request $request): array
    {
        $language = $this->language($request);
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $words = $this->taskService->getTaskWords($task->task_id, $language, $options);

        foreach ($words as $word) {
            // Случайные неверные варианты из других слов задания + правильный ответ
            $answers = $this->answerOptionsQuery($language)
                ->where('task_words.task_word_id', '!=', $word->task_word_id)
                ->where('task_words.task_id', '=', $task->task_id)
                ->inRandomOrder()
                ->limit($options->options_num - 1)
                ->distinct()
                ->get()
                ->toArray();

            $answers[] = $this->answerOptionsQuery($language)
                ->where('task_words.task_word_id', '=', $word->task_word_id)
                ->first();

            shuffle($answers);

            $word->answer_options = $answers;
        }

        return [
            'task' => $task,
            'options' => $options,
            'words' => $words,
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }

    protected function answerOptionsQuery($language)
    {
        return TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
            ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
            ->select(
                'task_words.task_word_id',
                'dictionary.word',
                'dictionary.transcription',
                'dictionary_translate.word_translate'
            )
            ->where('dictionary_translate.lang_id', '=', $language->lang_id);
    }
}
