<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonDictionaryCategory extends Model
{
    use HasFactory;
    protected $table = 'lesson_dictionary_category';
    protected $primaryKey = 'category_id';
}
