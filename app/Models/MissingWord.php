<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissingWord extends Model
{
    use HasFactory;
    protected $table = 'missing_words';
    protected $primaryKey = 'missing_word_id';
}
