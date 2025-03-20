<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskQuestion extends Model
{
    use HasFactory;
    protected $table = 'task_questions';
    protected $primaryKey = 'task_question_id';
}