<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearnerLevelPayment extends Model
{
    use HasFactory;
    protected $table = 'learner_level_payments';
    protected $primaryKey = 'payment_id';
}
