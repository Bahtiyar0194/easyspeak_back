<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiExplain extends Model
{
    use HasFactory;
    protected $table = 'ai_explains';
    protected $primaryKey = 'explain_id';
}
