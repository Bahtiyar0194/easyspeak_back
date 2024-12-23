<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordSection extends Model
{
    use HasFactory;
    protected $table = 'word_sections';
    protected $primaryKey = 'word_section_id';
}