<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanType extends Model
{
    use HasFactory;
    protected $table = 'types_of_subscription_plans';
    protected $primaryKey = 'subscription_plan_id';
}
