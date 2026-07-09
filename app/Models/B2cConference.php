<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cConference extends Model
{
    use HasFactory;
    protected $table = 'b2c_conferences';
    protected $primaryKey = 'conference_id';

    protected $fillable = [
        'notification_sent_day_before',
        'notification_sent_hour_before',
        'notification_sent',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime'
    ];

    public function levels()
    {
        return $this->belongsToMany(
            CourseLevel::class,          // Модель, с которой связываемся
            'b2c_conferences_levels',    // Имя промежуточной таблицы
            'conference_id',             // Внешний ключ этой модели в промежуточной таблице
            'level_id'                   // Внешний ключ связываемой модели в промежуточной таблице
        );
    }
}
