<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonType extends Model
{
    use HasFactory;
    protected $table = 'types_of_lessons';
    protected $primaryKey = 'lesson_type_id';
}
