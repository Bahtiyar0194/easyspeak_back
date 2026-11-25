<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskSentence;
use Illuminate\Http\Request;

/**
 * «Сопоставь предложения с материалами»: у каждого предложения свой файл (картинка, аудио и т.п.).
 *
 * Шаги: 1 — опции, 2 — выбор предложений, 3 — материалы предложений, 4 — материалы задания и сохранение.
 * sentences: [{sentence_id, task_sentence_id?, material?: {task_sentence_material_id?}}].
 * Файл предложения i приходит в полях upload_sentence_file_i, file_i, file_name_i или file_from_library_i.
 */
class MatchSentencesWithMaterialsTaskHandler extends SentencesTaskHandler
{
    protected function taskTypeId(): int
    {
        return 10;
    }

    protected function stepsCount(): int
    {
        return 4;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            return array_merge($this->baseOptionsRules(), [
                'impression_limit' => 'required|min:1',
                'seconds_per_sentence' => 'required|numeric|min:10',
                'sentence_material_type_slug' => 'required|string|min:1',
            ]);
        }

        if ($step === 2) {
            return $this->sentencesRules(2);
        }

        if ($step === 3) {
            return ['sentences' => 'required'];
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        if ($step === 2) {
            return $this->emptyJsonArrayError($request, 'sentences');
        }

        if ($step === 3) {
            $errors = $this->taskService->validateSentenceMaterials($this->decodeJsonArray($request, 'sentences'), $request);

            return $errors ? $errors->toArray() : null;
        }

        return null;
    }

    /**
     * При правке предложения с уже сохранённым материалом остаются как есть,
     * остальные пересоздаются вместе с новыми материалами.
     */
    protected function store(Task $task, Request $request, bool $is_edit): void
    {
        $sentences = $this->decodeJsonArray($request, 'sentences');

        if ($is_edit) {
            $keep_ids = [];

            foreach ($sentences as $sentence) {
                if ($this->hasSavedMaterial($sentence)) {
                    $keep_ids[] = $sentence->task_sentence_id;
                }
            }

            TaskSentence::where('task_id', $task->task_id)
                ->whereNotIn('task_sentence_id', $keep_ids)
                ->delete();
        }

        foreach ($sentences as $index => $sentence) {
            if ($this->hasSavedMaterial($sentence)) {
                continue;
            }

            $task_sentence = $this->saveTaskSentence($task, $sentence);
            $this->taskService->addMaterialsToTaskSentence($index, $task_sentence->task_sentence_id, $request);
        }
    }

    protected function decorateSentences($sentences, $options): void
    {
        foreach ($sentences as $sentence) {
            $sentence->material = $this->taskService->getTaskSentenceMaterial($sentence->task_sentence_id);
        }
    }

    protected function hasSavedMaterial($sentence): bool
    {
        return isset($sentence->material->task_sentence_material_id);
    }
}
