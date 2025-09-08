<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationLang extends Model
{
    protected $table = 'locations_lang';
    protected $primaryKey = 'id'; // если есть
    public $timestamps = false;

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }
}

