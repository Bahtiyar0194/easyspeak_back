<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskSentence extends Model
{
    use HasFactory;
    protected $table = 'task_sentences';
    protected $primaryKey = 'task_sentence_id';
}
