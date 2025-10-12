<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLevel extends Model
{
    use HasFactory;
    protected $table = 'course_levels';
    protected $primaryKey = 'level_id';

    public function sections()
    {
        return $this->hasMany(CourseSection::class, 'level_id');
    }

    public function translation()
    {
        return $this->hasOne(CourseLevelLang::class, 'level_id');
    }
}
