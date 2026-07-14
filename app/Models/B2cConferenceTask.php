<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2cConferenceTask extends Model
{
    use HasFactory;
    protected $table = 'b2c_conference_tasks';
    protected $primaryKey = 'conference_task_id';
}
