<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionTypeLevel extends Model
{
    use HasFactory;
    protected $table = 'types_of_subscriptions_for_level';
    protected $primaryKey = 'subscription_type_id';
}
