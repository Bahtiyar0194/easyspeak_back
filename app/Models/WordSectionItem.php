<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordSectionItem extends Model
{
    use HasFactory;
    protected $table = 'word_section_items';
    protected $primaryKey = 'word_section_item_id';
}