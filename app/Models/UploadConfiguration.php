<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadConfiguration extends Model
{
    use HasFactory;
    protected $table = 'upload_configuration';
    protected $primaryKey = 'id';
}
