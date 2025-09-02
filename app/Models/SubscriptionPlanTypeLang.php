<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanTypeLang extends Model
{
    use HasFactory;
    protected $table = 'types_of_subscription_plans_lang';
    protected $primaryKey = 'id';
}
