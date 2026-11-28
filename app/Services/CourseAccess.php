<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Единое правило доступа к курсам.
 *
 * courses.school_id = NULL — курс платформы (EasySpeak), число — курс школы.
 *
 * Видеть:  суперадминистратор — всё; остальные — курсы платформы и курсы
 *          своей школы.
 * Менять:  курс школы — владелец школы и методист этой школы (и суперадмин);
 *          курс платформы — только суперадминистратор.
 *
 * Курс по любому параметру маршрута (task_id, lesson_id, section_id, …)
 * находит resolveCourseId(); middleware course_access проверяет доступ.
 */
class CourseAccess
{
    const EDITOR_ROLES = ['school_owner', 'methodist'];

    public function isSuperAdmin(?User $user): bool
    {
        return $user !== null && $user->hasRole('super_admin');
    }

    /** Курс (school_id) — или null, если курса нет */
    public function findCourse($courseId): ?object
    {
        if (!$courseId) {
            return null;
        }
        return DB::table('courses')->where('course_id', $courseId)->first(['course_id', 'school_id']);
    }

    public function canView(?User $user, ?object $course): bool
    {
        if (!$course) {
            return false;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }
        if ($course->school_id === null) {
            return true;
        }
        return $user !== null && (int) $course->school_id === (int) $user->school_id;
    }

    public function canEdit(?User $user, ?object $course): bool
    {
        if (!$course || $user === null) {
            return false;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }
        if ($course->school_id === null) {
            return false;
        }
        return (int) $course->school_id === (int) $user->school_id
            && $user->hasRole(self::EDITOR_ROLES);
    }

    /**
     * Ограничить запрос курсами, которые видит пользователь.
     * $column — колонка courses.school_id в запросе (с алиасом таблицы)
     */
    public function scopeVisible($query, ?User $user, string $column = 'courses.school_id')
    {
        if ($this->isSuperAdmin($user)) {
            return $query;
        }
        return $query->where(function ($q) use ($user, $column) {
            $q->whereNull($column);
            if ($user !== null) {
                $q->orWhere($column, $user->school_id);
            }
        });
    }

    // ===== Файлы =====
    // files.school_id = NULL — файл платформы (загрузил суперадмин), число — школы.
    // Библиотека и правка файлов: суперадмин — файлы платформы, школа — свои

    public function scopeOwnFiles($query, ?User $user, string $column = 'files.school_id')
    {
        if ($this->isSuperAdmin($user)) {
            return $query->whereNull($column);
        }
        return $query->where($column, $user ? $user->school_id : 0);
    }

    public function canEditFile(?User $user, $file): bool
    {
        if (!$file || $user === null) {
            return false;
        }
        if ($this->isSuperAdmin($user)) {
            return $file->school_id === null;
        }
        return $file->school_id !== null && (int) $file->school_id === (int) $user->school_id;
    }

    // ===== Курс по сущности =====

    public function courseIdBySlug($slug)
    {
        return DB::table('courses')->where('course_name_slug', $slug)->value('course_id');
    }

    public function courseIdByLevel($levelId)
    {
        return DB::table('course_levels')->where('level_id', $levelId)->value('course_id');
    }

    public function courseIdBySection($sectionId)
    {
        return DB::table('course_sections')
            ->join('course_levels', 'course_levels.level_id', '=', 'course_sections.level_id')
            ->where('course_sections.section_id', $sectionId)
            ->value('course_levels.course_id');
    }

    public function courseIdByLesson($lessonId)
    {
        return DB::table('lessons')
            ->join('course_sections', 'course_sections.section_id', '=', 'lessons.section_id')
            ->join('course_levels', 'course_levels.level_id', '=', 'course_sections.level_id')
            ->where('lessons.lesson_id', $lessonId)
            ->value('course_levels.course_id');
    }

    public function courseIdByTask($taskId)
    {
        $lessonId = DB::table('tasks')->where('task_id', $taskId)->value('lesson_id');
        return $lessonId ? $this->courseIdByLesson($lessonId) : null;
    }

    public function courseIdByMaterial($materialId)
    {
        $lessonId = DB::table('lesson_materials')->where('lesson_material_id', $materialId)->value('lesson_id');
        return $lessonId ? $this->courseIdByLesson($lessonId) : null;
    }

    public function courseIdByWord($wordId)
    {
        return DB::table('dictionary')->where('word_id', $wordId)->value('course_id');
    }

    public function courseIdBySentence($sentenceId)
    {
        return DB::table('sentences')->where('sentence_id', $sentenceId)->value('course_id');
    }

    /**
     * Курсы, на которые указывают параметры маршрута / запроса.
     * Каждый найденный параметр даёт свой course_id; null — сущность не найдена.
     */
    public function resolveCourseIds(array $params): array
    {
        $map = [
            'task_id' => 'courseIdByTask',
            'lesson_material_id' => 'courseIdByMaterial',
            'lesson_id' => 'courseIdByLesson',
            'section_id' => 'courseIdBySection',
            'level_id' => 'courseIdByLevel',
            'word_id' => 'courseIdByWord',
            'sentence_id' => 'courseIdBySentence',
            'course_slug' => 'courseIdBySlug',
        ];

        $ids = [];
        foreach ($map as $param => $method) {
            if (array_key_exists($param, $params) && $params[$param] !== null && $params[$param] !== '') {
                $ids[$param] = $this->{$method}($params[$param]);
            }
        }
        if (isset($params['course_id']) && $params['course_id'] !== '') {
            $ids['course_id'] = DB::table('courses')->where('course_id', $params['course_id'])->value('course_id');
        }
        return $ids;
    }
}
