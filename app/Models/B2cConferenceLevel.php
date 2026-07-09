<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cConferenceLevel extends Model
{
    use HasFactory;
    protected $table = 'b2c_conferences_levels';
    protected $primaryKey = 'id';
}