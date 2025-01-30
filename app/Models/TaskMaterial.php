<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskMaterial extends Model
{
    use HasFactory;
    protected $table = 'task_materials';
    protected $primaryKey = 'task_material_id';
}
