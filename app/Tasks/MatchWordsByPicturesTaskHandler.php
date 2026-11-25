<?php

namespace App\Tasks;

use App\Models\TaskWord;
use Illuminate\Http\Request;

/** «Сопоставь слова с картинками»: ученик соотносит слова с их изображениями. */
class MatchWordsByPicturesTaskHandler extends WordsTaskHandler
{
    protected function taskTypeId(): int
    {
        return 2;
    }

    protected function optionsRules(): array
    {
        return [
            'show_audio_button' => 'required|boolean',
            'show_transcription' => 'required|boolean',
            'show_translate' => 'required|boolean',
            'impression_limit' => 'required',
            'seconds_per_word' => 'required|numeric|min:10',
            'match_words_by_pictures_option' => 'required|string',
        ];
    }

    public function show(Request $request): array
    {
        $language = $this->language($request);
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $words = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
            ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
            ->leftJoin('files as image_file', 'dictionary.image_file_id', '=', 'image_file.file_id')
            ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
            ->select(
                'task_words.task_word_id',
                'task_words.word_id',
                'dictionary.word',
                'dictionary.transcription',
                'image_file.target as image_file',
                'audio_file.target as audio_file',
                'dictionary_translate.word_translate'
            )
            ->where('task_words.task_id', '=', $task->task_id)
            ->where('dictionary_translate.lang_id', '=', $language->lang_id)
            ->get();

        return [
            'task' => $task,
            'options' => $options,
            'words' => $words,
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }
}
