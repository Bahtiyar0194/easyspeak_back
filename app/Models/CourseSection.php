<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    use HasFactory;
    protected $table = 'course_sections';
    protected $primaryKey = 'section_id';

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'section_id');
    }
}
