<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cConferenceMember extends Model
{
    use HasFactory;
    protected $table = 'b2c_conference_members';
    protected $primaryKey = 'conference_member_id';

    // Указываем поля, которые можно заполнять через Mass Assignment
    protected $fillable = [
        'conference_id',
        'member_id',
    ];
}