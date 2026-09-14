<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserQuizResult extends Model
{
    use HasFactory;
    protected $table = 'user_quiz_result';
    protected $primaryKey = 'id';

    protected $fillable = [
        'lesson_id',
        'user_id',
        'is_completed',
        'progress',
    ];
}
