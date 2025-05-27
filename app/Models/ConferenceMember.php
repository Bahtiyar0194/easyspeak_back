<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceMember extends Model
{
    use HasFactory;
    protected $table = 'conference_members';
    protected $primaryKey = 'conference_member_id';
}
