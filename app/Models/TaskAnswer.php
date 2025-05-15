<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAnswer extends Model
{
    use HasFactory;
    protected $table = 'task_answers';
    protected $primaryKey = 'task_answer_id';
}
