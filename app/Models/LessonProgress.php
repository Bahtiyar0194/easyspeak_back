<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    use HasFactory;
    protected $table = 'lesson_progress';
    protected $primaryKey = 'lesson_progress_id';
}