<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonDictionary extends Model
{
    use HasFactory;
    protected $table = 'lesson_dictionary';
    protected $primaryKey = 'lesson_dictionary_id';
}
