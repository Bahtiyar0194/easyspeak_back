<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAnswerFile extends Model
{
    use HasFactory;
    protected $table = 'task_answer_files';
    protected $primaryKey = 'task_answer_file_id';
}
