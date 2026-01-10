<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoughtLesson extends Model
{
    use HasFactory;

    protected $table = 'bought_lessons';
    protected $primaryKey = 'id';

    protected $fillable = [
        'learner_id',
        'lesson_id',
        'iniciator_id',
        'is_free',
    ];
}
