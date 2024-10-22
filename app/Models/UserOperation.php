<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOperation extends Model
{
    use HasFactory;
    protected $table = 'user_operations';
    protected $primaryKey = 'user_operation_id';
}
