<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskWord extends Model
{
    use HasFactory;
    protected $table = 'task_words';
    protected $primaryKey = 'task_word_id';
}
