<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordSyllable extends Model
{
    use HasFactory;
    protected $table = 'word_syllables';
    protected $primaryKey = 'word_syllable_id';
}