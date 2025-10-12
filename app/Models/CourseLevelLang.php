<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLevelLang extends Model
{
    use HasFactory;

    protected $table = 'course_levels_lang';
    protected $primaryKey = 'id';

    public function level()
    {
        return $this->belongsTo(CourseLevel::class, 'level_id');
    }
}
