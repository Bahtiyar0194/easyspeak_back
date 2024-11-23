<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonLang extends Model
{
    use HasFactory;
    protected $table = 'lessons_lang';
    protected $primaryKey = 'id';
}
