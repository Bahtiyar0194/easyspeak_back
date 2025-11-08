<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoResolutionType extends Model
{
    use HasFactory;
    protected $table = 'types_of_video_resolutions';
    protected $primaryKey = 'resolution_id';
}
