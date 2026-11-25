<?php

namespace App\Tasks;

use App\Models\MissingWord;
use App\Models\TaskSentence;
use Illuminate\Http\Request;

/**
 * «Заполни пропуски в предложении».
 *
 * Шаги: 1 — опции, 2 — выбор предложений, 3 — пропуски, 4 — материалы и сохранение.
 * find_word_option:
 *   with_options — один пропуск (removedWordIndex) и варианты слов (removedWordOptions, первый — правильный);
 *   иначе (with_hints, without_hints, with_first_letter) — пропуски по индексам слов (removedWordsIndex).
 */
class FillInTheBlanksInTheSentenceTaskHandler extends SentencesTaskHandler
{
    protected function taskTypeId(): int
    {
        return 6;
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
            ]);
        }

        if ($step === 2) {
            return $this->sentencesRules(2);
        }

        if ($step === 3) {
            return [
                'sentences' => 'required',
                'find_word_option' => 'required|string',
            ];
        }

        return [];
    }

    protected function validateStep(int $step, Request $request): ?array
    {
        if ($step === 2) {
            return $this->emptyJsonArrayError($request, 'sentences');
        }

        if ($step !== 3) {
            return null;
        }

        foreach ($this->decodeJsonArray($request, 'sentences') as $sentence) {
            if ($request->find_word_option == 'with_options') {
                if (!isset($sentence->removedWordIndex)) {
                    return $this->wordsFailed('auth.remove_one_word_in_each_sentence');
                }

                if (!isset($sentence->removedWordOptions) || !is_array($sentence->removedWordOptions) || count($sentence->removedWordOptions) < 2) {
                    return $this->wordsFailed('auth.you_must_add_two_or_more_options_of_the_missing_words_for_each_sentence');
                }

                continue;
            }

            if (!isset($sentence->removedWordsIndex) || !is_array($sentence->removedWordsIndex) || count($sentence->removedWordsIndex) == 0) {
                return $this->wordsFailed('auth.remove_one_word_in_each_sentence');
            }

            if ($request->find_word_option == 'with_first_letter') {
                $words = explode(' ', $sentence->sentence ?? '');

                foreach ($sentence->removedWordsIndex as $word_index) {
                    // mb_strlen: strlen считал однобуквенные кириллические слова двухбуквенными
                    if (mb_strlen($words[$word_index] ?? '') <= 1) {
                        return $this->wordsFailed('auth.you_cannot_remove_a_word_with_one_letter');
                    }
                }
            }
        }

        return null;
    }

    protected function afterSentenceSaved(TaskSentence $task_sentence, $sentence, int $index, Request $request): void
    {
        if ($request->find_word_option == 'with_options') {
            foreach ($sentence->removedWordOptions as $key => $option) {
                $missing_word = new MissingWord();

                // Позиция хранится только у первого (правильного) варианта
                if ($key === 0 && isset($sentence->removedWordIndex)) {
                    $missing_word->word_position = $sentence->removedWordIndex;
                }

                $missing_word->word_option = $option;
                $missing_word->task_sentence_id = $task_sentence->task_sentence_id;
                $missing_word->save();
            }

            return;
        }

        foreach ($sentence->removedWordsIndex as $word_index) {
            $missing_word = new MissingWord();
            $missing_word->word_position = $word_index;
            $missing_word->task_sentence_id = $task_sentence->task_sentence_id;
            $missing_word->save();
        }
    }

    protected function decorateSentences($sentences, $options): void
    {
        foreach ($sentences as $sentence) {
            $words = explode(' ', $sentence->sentence);

            $query = MissingWord::where('task_sentence_id', $sentence->task_sentence_id)
                ->select(
                    'missing_words.missing_word_id',
                    'missing_words.word_position',
                    'missing_words.word_option'
                );

            if ($options->find_word_option == 'with_hints') {
                $query->orderBy('word_position', 'asc');
            }

            // Записи с позицией за пределами предложения отбрасываются
            $sentence->missingWords = $query->get()->filter(function ($missing_word) use ($words) {
                $position = $missing_word->word_position;

                if (is_null($position)) {
                    $missing_word->word = null;
                    return true;
                }

                if ($position >= 0 && isset($words[$position])) {
                    $missing_word->word = $words[$position];
                    return true;
                }

                return false;
            })->values();
        }
    }

    protected function wordsFailed(string $message_key): array
    {
        return ['words_failed' => [trans($message_key)]];
    }
}
