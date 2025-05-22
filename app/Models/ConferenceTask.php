<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceTask extends Model
{
    use HasFactory;
    protected $table = 'conference_tasks';
    protected $primaryKey = 'conference_task_id';
}
