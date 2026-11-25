<?php

namespace App\Tasks;

use App\Models\Language;
use App\Models\MissingLetter;
use App\Models\Task;
use App\Models\TaskOption;
use App\Models\TaskWord;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Базовый обработчик типа задания: пошаговое создание/редактирование и получение.
 *
 * Фронтенд отправляет форму на каждом шаге (параметр step) со всеми полями всех шагов.
 * Промежуточные шаги только валидируются и возвращают {step: N}.
 * Последний шаг заново проверяет все предыдущие шаги и материалы,
 * после чего сохраняет задание в одной транзакции.
 *
 * Чтобы добавить тип: унаследоваться, указать taskTypeId(), stepsCount(),
 * stepRules()/validateStep() для промежуточных шагов, store() и show().
 */
abstract class TaskHandler
{
    /** @var TaskService */
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /** ID типа задания из types_of_tasks */
    abstract protected function taskTypeId(): int;

    /** Общее число шагов формы (последний — материалы и сохранение) */
    abstract protected function stepsCount(): int;

    /** Правила Validator для промежуточного шага */
    abstract protected function stepRules(int $step): array;

    /**
     * Сохранить данные, специфичные для типа (слова, предложения и т.п.).
     * Вызывается внутри транзакции.
     */
    abstract protected function store(Task $task, Request $request, bool $is_edit): void;

    /** Данные для GET-эндпоинта задания */
    abstract public function show(Request $request): array;

    /**
     * Дополнительная проверка шага, которую не выразить правилами Validator.
     * Возвращает массив ошибок в формате ['field' => ['message']] или null.
     */
    protected function validateStep(int $step, Request $request): ?array
    {
        return null;
    }

    public function create(Request $request)
    {
        return $this->handle($request, false);
    }

    public function edit(Request $request)
    {
        return $this->handle($request, true);
    }

    protected function handle(Request $request, bool $is_edit)
    {
        $step = (int) $request->step;
        $final_step = $this->stepsCount();

        if ($step < 1 || $step > $final_step) {
            return response()->json(['step' => ['Invalid step']], 422);
        }

        // На последнем шаге перепроверяем все предыдущие: клиент мог их пропустить
        $steps_to_check = $step === $final_step ? range(1, $final_step - 1) : [$step];

        foreach ($steps_to_check as $check_step) {
            $errors = $this->runStepValidation($check_step, $request);

            if ($errors) {
                return response()->json($errors, 422);
            }
        }

        if ($step < $final_step) {
            return response()->json(['step' => $step], 200);
        }

        $errors = $this->taskService->validateTaskMaterials($request);

        if ($errors) {
            return response()->json($errors, 422);
        }

        DB::transaction(function () use ($request, $is_edit) {
            $task = $is_edit
                ? $this->taskService->editTask($request)
                : $this->taskService->newTask($request, $this->taskTypeId());

            $this->store($task, $request, $is_edit);

            $this->taskService->addMaterialsToTask($task->task_id, $request);

            if ($is_edit) {
                TaskOption::where('task_id', $task->task_id)->delete();
            }

            $this->taskService->addTaskOptions($task->task_id, $request);
        });

        return response()->json('success', 200);
    }

    /** Ошибки правил и дополнительной проверки шага объединяются, как при Validator::after() */
    protected function runStepValidation(int $step, Request $request): ?array
    {
        $validator = Validator::make($request->all(), $this->stepRules($step));

        $errors = $validator->fails() ? $validator->errors()->toArray() : [];
        $errors = array_merge($errors, $this->validateStep($step, $request) ?? []);

        return $errors ?: null;
    }

    /** Декодировать JSON-поле запроса в массив (пустой массив, если не массив) */
    protected function decodeJsonArray(Request $request, string $field): array
    {
        $value = json_decode($request->input($field));

        return is_array($value) ? $value : [];
    }

    /** Ошибка, если поле заполнено, но в JSON нет ни одного элемента (пустое поле ловит required) */
    protected function emptyJsonArrayError(Request $request, string $field): ?array
    {
        if ($request->filled($field) && count($this->decodeJsonArray($request, $field)) === 0) {
            return [$field => [trans('validation.required', ['attribute' => $field])]];
        }

        return null;
    }

    /** Опции задания или 404 */
    protected function findOptions(Task $task): TaskOption
    {
        $options = TaskOption::where('task_id', '=', $task->task_id)->first();

        if (!isset($options)) {
            abort(response()->json('task option is not found', 404));
        }

        return $options;
    }

    /** Сохранить слово задания и его пропущенные буквы (индексы с 0, в БД position с 1) */
    protected function saveTaskWord(Task $task, $word_id, array $removed_letters = []): TaskWord
    {
        $task_word = new TaskWord();
        $task_word->task_id = $task->task_id;
        $task_word->word_id = $word_id;
        $task_word->save();

        foreach ($removed_letters as $letter) {
            $missing_letter = new MissingLetter();
            $missing_letter->task_word_id = $task_word->task_word_id;
            $missing_letter->position = $letter + 1;
            $missing_letter->save();
        }

        return $task_word;
    }

    /** Позиции пропущенных букв, сгруппированные по task_word_id */
    protected function missingLettersByTaskWord($task_word_ids)
    {
        return MissingLetter::whereIn('task_word_id', $task_word_ids)
            ->select('task_word_id', 'position')
            ->orderBy('position', 'asc')
            ->get()
            ->groupBy('task_word_id')
            ->map(function ($letters) {
                return $letters->pluck('position');
            });
    }

    protected function language(Request $request): Language
    {
        return Language::where('lang_tag', '=', $request->header('Accept-Language'))->firstOrFail();
    }

    /** Общие правила шага с опциями задания (имя, slug, платформа, попытки) */
    protected function baseOptionsRules(): array
    {
        return [
            'task_slug' => 'required',
            'task_name_kk' => 'required',
            'task_name_ru' => 'required',
            'max_attempts' => 'required|numeric',
            'show_on_platform' => 'required|string',
        ];
    }
}
