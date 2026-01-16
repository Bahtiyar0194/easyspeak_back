<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conference extends Model
{
    use HasFactory;
    protected $table = 'conferences';
    protected $primaryKey = 'conference_id';

    protected $fillable = [
        'notification_sent_day_before',
        'notification_sent_hour_before',
        'notification_sent',
    ];

    protected $casts = [
        'start_time' => 'datetime'
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class, 'lesson_id', 'lesson_id');
    }
}
