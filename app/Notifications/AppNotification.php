<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Уведомление в личном кабинете.
 *
 * type   — вид события (lesson_reminder, task_checked, ...): по нему фронтенд
 *          выбирает текст и иконку на языке пользователя;
 * params — подстановки для текста (название урока, оценка, дата и т.п.);
 * link   — куда перейти по нажатию (путь в кабинете) или null.
 */
class AppNotification extends Notification
{
    use Queueable;

    protected $kind;
    protected $params;
    protected $link;

    public function __construct(string $kind, array $params = [], ?string $link = null)
    {
        $this->kind = $kind;
        $this->params = $params;
        $this->link = $link;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => $this->kind,
            'params' => $this->params,
            'link' => $this->link,
        ];
    }
}
