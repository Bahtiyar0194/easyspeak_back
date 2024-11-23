<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskLang extends Model
{
    use HasFactory;
    protected $table = 'tasks_lang';
    protected $primaryKey = 'id';
}
