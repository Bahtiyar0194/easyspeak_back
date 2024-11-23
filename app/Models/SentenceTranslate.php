<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentenceTranslate extends Model
{
    use HasFactory;
    protected $table = 'sentences_translate';
    protected $primaryKey = 'id';
}
