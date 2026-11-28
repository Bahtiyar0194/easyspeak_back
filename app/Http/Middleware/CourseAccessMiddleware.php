<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CourseAccess;

/**
 * Доступ к контенту курса: ->middleware('course_access:view') или 'course_access:edit'.
 *
 * Курс определяется по параметрам маршрута (task_id, lesson_id, section_id,
 * lesson_material_id, word_id, sentence_id, course_slug) и по course_id из
 * запроса (словарь / предложения). Если параметров несколько — доступ нужен
 * ко всем найденным курсам (например, перенос слова в другой курс).
 *
 * Пара «урок + задание / материал» должна совпадать: иначе можно было бы
 * передать свой урок, а изменить чужое задание.
 */
class CourseAccessMiddleware
{
    protected $access;

    public function __construct(CourseAccess $access)
    {
        $this->access = $access;
    }

    public function handle(Request $request, Closure $next, string $mode = 'view')
    {
        $params = $request->route() ? $request->route()->parameters() : [];
        if ($request->filled('course_id')) {
            $params['course_id'] = $request->input('course_id');
        }

        // Задание / материал должны принадлежать указанному уроку
        if (!empty($params['lesson_id']) && !empty($params['task_id'])) {
            $taskLesson = DB::table('tasks')->where('task_id', $params['task_id'])->value('lesson_id');
            if ((int) $taskLesson !== (int) $params['lesson_id']) {
                return response()->json(['message' => 'Not found'], 404);
            }
        }
        if (!empty($params['lesson_id']) && !empty($params['lesson_material_id'])) {
            $materialLesson = DB::table('lesson_materials')
                ->where('lesson_material_id', $params['lesson_material_id'])
                ->value('lesson_id');
            if ((int) $materialLesson !== (int) $params['lesson_id']) {
                return response()->json(['message' => 'Not found'], 404);
            }
        }

        $courseIds = $this->access->resolveCourseIds($params);

        // Маршрут без параметров курса под этой проверкой — ошибка настройки:
        // закрыто, пока не исправят, а не открыто
        if (empty($courseIds)) {
            return response()->json(['message' => 'Course is not specified'], 403);
        }

        if (in_array(null, $courseIds, true)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $request->user();
        foreach (array_unique(array_values($courseIds)) as $courseId) {
            $course = $this->access->findCourse($courseId);
            $allowed = $mode === 'edit'
                ? $this->access->canEdit($user, $course)
                : $this->access->canView($user, $course);

            if (!$allowed) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return $next($request);
    }
}
