<?php
namespace App\Services;

use App\Models\User;
use App\Models\GroupMember;
use App\Models\B2cConferenceMember;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Уведомления в личном кабинете (колокольчик).
 *
 * Текст не хранится — только вид события и подстановки: фронтенд собирает
 * текст на языке пользователя. Ошибка отправки никогда не ломает основное
 * действие (перенос урока, проверку задания, оплату) — только пишется в лог.
 */
class NotificationService
{
    /**
     * Отправить уведомление пользователям (id). Пустые и повторяющиеся id
     * отбрасываются; $exclude — кому не отправлять (обычно тот, кто сделал действие).
     */
    public function send($userIds, string $type, array $params = [], ?string $link = null, $exclude = null): void
    {
        try {
            $ids = collect($userIds)
                ->filter()
                ->unique()
                ->reject(function ($id) use ($exclude) {
                    return $exclude !== null && (int) $id === (int) $exclude;
                })
                ->values();

            if ($ids->isEmpty()) {
                return;
            }

            $users = User::whereIn('user_id', $ids)->get();
            Notification::send($users, new AppNotification($type, $params, $link));
        } catch (\Throwable $e) {
            Log::warning('[notifications] ' . $type . ': ' . $e->getMessage());
        }
    }

    /** Активные участники группы */
    public function groupMemberIds($group_id): array
    {
        return GroupMember::where('group_id', $group_id)
            ->where('status_type_id', 1)
            ->pluck('member_id')
            ->all();
    }

    /** Кому сообщать об уроке: участники (группы или ИИ-конференции) и учитель */
    public function conferenceRecipientIds($conference, bool $isB2c = false): array
    {
        $members = $isB2c
            ? B2cConferenceMember::where('conference_id', $conference->conference_id)->pluck('member_id')->all()
            : $this->groupMemberIds($conference->group_id);

        $members[] = $conference->mentor_id;

        return $members;
    }

    /** Владельцы и администраторы школы */
    public function schoolManagerIds($school_id): array
    {
        return DB::table('users')
            ->join('users_roles', 'users.user_id', '=', 'users_roles.user_id')
            ->join('types_of_user_roles', 'users_roles.role_type_id', '=', 'types_of_user_roles.role_type_id')
            ->where('users.school_id', $school_id)
            ->whereIn('types_of_user_roles.role_type_slug', ['school_owner', 'school_admin'])
            ->distinct()
            ->pluck('users.user_id')
            ->all();
    }

    /** Название урока и ссылка на него в кабинете */
    public function lessonInfo($lesson_id): array
    {
        $lesson = DB::table('lessons')
            ->leftJoin('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
            ->leftJoin('course_levels', 'course_sections.level_id', '=', 'course_levels.level_id')
            ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
            ->where('lessons.lesson_id', $lesson_id)
            ->select(
                'lessons.lesson_id',
                'lessons.lesson_name',
                'lessons.section_id',
                'course_levels.level_slug',
                'courses.course_name_slug'
            )
            ->first();

        if (!$lesson) {
            return ['name' => null, 'link' => null];
        }

        $link = ($lesson->course_name_slug && $lesson->level_slug)
            ? "/dashboard/courses/{$lesson->course_name_slug}/{$lesson->level_slug}/{$lesson->section_id}/{$lesson->lesson_id}"
            : null;

        return ['name' => $lesson->lesson_name, 'link' => $link];
    }

    /** Название урока конференции (у ИИ-школы — тема) */
    public function conferenceTitle($conference, bool $isB2c = false): ?string
    {
        if ($isB2c) {
            return $conference->topic;
        }

        return DB::table('lessons')->where('lesson_id', $conference->lesson_id)->value('lesson_name');
    }

    /** Время в ISO 8601 — фронтенд покажет его в часовом поясе пользователя */
    public function iso($time): ?string
    {
        return $time ? \Carbon\Carbon::parse($time)->toIso8601String() : null;
    }
}
