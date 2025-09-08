<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    protected $table = 'locations';
    protected $primaryKey = 'location_id';

    protected $fillable = ['location_slug', 'location_type_id', 'parent_id', 'region_center'];

    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function locations_lang()
    {
        return $this->hasMany(LocationLang::class, 'location_id', 'location_id');
    }
}
