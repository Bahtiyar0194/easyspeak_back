<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationType extends Model
{
    use HasFactory;
    protected $table = 'types_of_operations';
    protected $primaryKey = 'operation_type_id';
}
