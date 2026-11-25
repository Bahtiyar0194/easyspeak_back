<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskSentence;
use Illuminate\Http\Request;

/**
 * Задания из списка предложений.
 *
 * Порядок шагов у типов разный, поэтому шаги и правила задаёт наследник.
 * Здесь — общее сохранение предложений и выдача задания.
 * sentences: [{sentence_id, sentence, ...поля типа}].
 */
abstract class SentencesTaskHandler extends TaskHandler
{
    /** Дополнительные поля task_sentences для предложения (answer и т.п.) */
    protected function sentenceAttributes($sentence): array
    {
        return [];
    }

    /** Сохранить данные, связанные с предложением (пропущенные слова, материалы) */
    protected function afterSentenceSaved(TaskSentence $task_sentence, $sentence, int $index, Request $request): void
    {
    }

    /** Дополнить предложения перед выдачей */
    protected function decorateSentences($sentences, $options): void
    {
    }

    protected function sentencesRules(int $min): array
    {
        return [
            'sentences_count' => 'required|numeric|min:' . $min,
            'sentences' => 'required',
        ];
    }

    protected function store(Task $task, Request $request, bool $is_edit): void
    {
        if ($is_edit) {
            // missing_words и task_sentence_materials удаляются каскадно
            TaskSentence::where('task_id', $task->task_id)->delete();
        }

        foreach ($this->decodeJsonArray($request, 'sentences') as $index => $sentence) {
            $task_sentence = $this->saveTaskSentence($task, $sentence);
            $this->afterSentenceSaved($task_sentence, $sentence, $index, $request);
        }
    }

    protected function saveTaskSentence(Task $task, $sentence): TaskSentence
    {
        $task_sentence = new TaskSentence();
        $task_sentence->task_id = $task->task_id;
        $task_sentence->sentence_id = $sentence->sentence_id;

        foreach ($this->sentenceAttributes($sentence) as $field => $value) {
            $task_sentence->{$field} = $value;
        }

        $task_sentence->save();

        return $task_sentence;
    }

    public function show(Request $request): array
    {
        $language = $this->language($request);
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $sentences = $this->taskService->getTaskSentences($task->task_id, $language, $options);
        $this->decorateSentences($sentences, $options);

        return [
            'task' => $task,
            'options' => $options,
            'sentences' => $sentences,
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }
}
