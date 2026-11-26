<?php

namespace App\Console\Commands;

use App\Models\TaskAnswer;
use App\Services\HtmlSanitizer;
use Illuminate\Console\Command;

/**
 * Разовая очистка HTML в уже сохранённых ответах (task_answers).
 *
 * Сохраняемые ответы чистятся при записи, но до этого HTML с клиента сохранялся как есть.
 * Команда меняет только записи, где очистка что-то реально удалила; косметические
 * отличия (кавычки, сущности) не считаются. По умолчанию ничего не пишет — только отчёт.
 */
class SanitizeTaskAnswers extends Command
{
    protected $signature = 'task-answers:sanitize {--apply : Записать изменения (без флага — только отчёт)}';

    protected $description = 'Очистить HTML в сохранённых ответах на задания (right_answer, user_answer, comment)';

    public function handle(HtmlSanitizer $sanitizer)
    {
        $apply = (bool) $this->option('apply');
        $changed = 0;

        TaskAnswer::query()->orderBy('task_answer_id')->chunkById(500, function ($answers) use ($sanitizer, $apply, &$changed) {
            foreach ($answers as $answer) {
                $fields = ['right_answer', 'comment'];

                // Непроверенный user_answer — простой текст, его не трогаем
                if ($answer->is_correct !== null) {
                    $fields[] = 'user_answer';
                }

                $updates = [];

                foreach ($fields as $field) {
                    $value = $answer->{$field};

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $clean = $sanitizer->clean($value);

                    if ($clean !== $sanitizer->normalize($value)) {
                        $updates[$field] = $clean;
                        $this->line("#{$answer->task_answer_id} {$field}: " . mb_substr($value, 0, 200));
                    }
                }

                if ($updates) {
                    $changed++;

                    if ($apply) {
                        TaskAnswer::where('task_answer_id', $answer->task_answer_id)->update($updates);
                    }
                }
            }
        }, 'task_answer_id');

        $this->info($apply
            ? "Очищено ответов: {$changed}"
            : "Ответов с опасным HTML: {$changed}. Запустите с --apply, чтобы очистить.");

        return 0;
    }
}
