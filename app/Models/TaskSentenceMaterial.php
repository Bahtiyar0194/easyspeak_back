<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskSentenceMaterial extends Model
{
    use HasFactory;
    protected $table = 'task_sentence_materials';
    protected $primaryKey = 'task_sentence_material_id';
}
