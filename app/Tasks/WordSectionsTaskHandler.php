<?php

namespace App\Tasks;

use App\Models\Task;
use App\Models\TaskWord;
use App\Models\WordSection;
use App\Models\WordSectionItem;
use Illuminate\Http\Request;

/**
 * Задания с секциями слов, в каждой секции есть «целевое» слово (target).
 *
 * Шаги: 1 — секции и целевые слова, 2 — удаление букв (необязательно),
 * 3 — опции (optionsRules), 4 — материалы и сохранение.
 * sections: [{words: [{word_id, target: 'true'|..., removedLetters?: [индексы с 0]}]}].
 */
abstract class WordSectionsTaskHandler extends TaskHandler
{
    /** Правила шага опций, специфичные для типа (общие добавляются из baseOptionsRules) */
    abstract protected function optionsRules(): array;

    /** Перемешивать ли слова внутри секции при выдаче */
    protected function shuffleSectionWords(): bool
    {
        return false;
    }

    protected function stepsCount(): int
    {
        return 4;
    }

    protected function stepRules(int $step): array
    {
        if ($step === 1) {
            return [
                'sections_count' => 'required|numeric|min:2',
                'sections' => 'required',
            ];
        }

        if ($step === 3) {
            return array_merge($this->baseOptionsRules(), $this->optionsRules());
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        if ($step !== 1) {
            return null;
        }

        $empty_error = $this->emptyJsonArrayError($request, 'sections');

        if ($empty_error) {
            return $empty_error;
        }

        foreach ($this->decodeJsonArray($request, 'sections') as $section) {
            $words = $this->sectionWords($section);

            if (count($words) === 0) {
                continue;
            }

            $target_found = false;

            foreach ($words as $word) {
                if ($this->isTarget($word)) {
                    $target_found = true;
                    break;
                }
            }

            if ($target_found === false) {
                return ['target_failed' => 'Target failed'];
            }
        }

        return null;
    }

    protected function store(Task $task, Request $request, bool $is_edit): void
    {
        if ($is_edit) {
            // word_section_items и missing_letters удаляются каскадно
            WordSection::where('task_id', $task->task_id)->delete();
            TaskWord::where('task_id', $task->task_id)->delete();
        }

        foreach ($this->decodeJsonArray($request, 'sections') as $section) {
            $word_section = new WordSection();
            $word_section->task_id = $task->task_id;
            $word_section->save();

            foreach ($this->sectionWords($section) as $word) {
                $item = new WordSectionItem();
                $item->word_section_id = $word_section->word_section_id;
                $item->word_id = $word->word_id;
                $item->target = $this->isTarget($word);
                $item->save();

                if (isset($word->removedLetters) && is_array($word->removedLetters) && count($word->removedLetters) > 0) {
                    $this->saveTaskWord($task, $word->word_id, $word->removedLetters);
                }
            }
        }
    }

    public function show(Request $request): array
    {
        $language = $this->language($request);
        $task = $this->taskService->findTask($request->task_id);
        $options = $this->findOptions($task);

        $sections = WordSection::select('word_sections.word_section_id', 'word_sections.task_id')
            ->where('word_sections.task_id', '=', $task->task_id);

        if ($options->random_order == 1) {
            $sections->inRandomOrder();
        }

        $sections = $sections->get();

        if (count($sections) === 0) {
            abort(response()->json('word sections is not found', 404));
        }

        // Пропущенные буквы по word_id. Если слово встречается в задании несколько раз,
        // берётся первая запись, как и раньше (TaskWord::...->first())
        $task_words = TaskWord::where('task_id', '=', $task->task_id)
            ->orderBy('task_word_id', 'asc')
            ->get()
            ->unique('word_id')
            ->keyBy('word_id');
        $missing_letters = $this->missingLettersByTaskWord($task_words->pluck('task_word_id'));

        foreach ($sections as $section) {
            $items = WordSectionItem::leftJoin('dictionary', 'word_section_items.word_id', '=', 'dictionary.word_id')
                ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
                ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
                ->select(
                    'word_section_items.word_section_item_id',
                    'word_section_items.word_section_id',
                    'word_section_items.word_id',
                    'word_section_items.target',
                    'dictionary.word',
                    'dictionary_translate.word_translate',
                    'audio_file.target as audio_file'
                )
                ->where('word_section_items.word_section_id', '=', $section->word_section_id)
                ->where('dictionary_translate.lang_id', '=', $language->lang_id)
                ->distinct();

            if ($this->shuffleSectionWords()) {
                $items->inRandomOrder();
            }

            $items = $items->get();

            foreach ($items as $item) {
                $task_word = $task_words->get($item->word_id);
                $letters = $task_word ? $missing_letters->get($task_word->task_word_id) : null;

                if ($letters && count($letters) > 0) {
                    $item->missingLetters = $letters;
                }
            }

            $section->words = $items;
        }

        return [
            'task' => $task,
            'options' => $options,
            'word_sections' => $sections,
            'materials' => $this->taskService->getTaskMaterials($task->task_id),
        ];
    }

    protected function sectionWords($section): array
    {
        return isset($section->words) && is_array($section->words) ? $section->words : [];
    }

    protected function isTarget($word): bool
    {
        return isset($word->target) && $word->target == 'true';
    }
}
