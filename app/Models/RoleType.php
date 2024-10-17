<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleType extends Model
{
    use HasFactory;
    protected $table = 'types_of_user_roles';
    protected $primaryKey = 'role_type_id';
}
