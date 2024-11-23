<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskOption extends Model
{
    use HasFactory;
    protected $table = 'task_options';
    protected $primaryKey = 'task_option_id';
}
