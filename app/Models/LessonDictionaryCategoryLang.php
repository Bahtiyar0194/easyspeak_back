<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonDictionaryCategoryLang extends Model
{
    use HasFactory;
    protected $table = 'lesson_dictionary_category_lang';
    protected $primaryKey = 'id';
}
