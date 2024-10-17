<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationRole extends Model
{
    use HasFactory;
    protected $table = 'operations_roles';
    protected $primaryKey = 'id';
}
