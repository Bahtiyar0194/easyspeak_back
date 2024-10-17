<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaviconType extends Model
{
    protected $table = 'types_of_favicons';
    protected $primaryKey = 'icon_id';
    use HasFactory;
}
