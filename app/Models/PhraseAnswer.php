<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhraseAnswer extends Model
{
    use HasFactory;
    protected $table = 'phrase_answers';
    protected $primaryKey = 'answer_id';
}