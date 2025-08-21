<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationType extends Model
{
    use HasFactory;
    protected $table = 'types_of_locations';
    protected $primaryKey = 'location_type_id';

    protected $fillable = ['location_type_slug'];
}
