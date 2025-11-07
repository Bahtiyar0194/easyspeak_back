<?php
namespace App\Services;
use App\Models\Payment;
use App\Models\School;
use App\Models\SubscriptionPlanType;
use Carbon\Carbon;

class PaymentService
{
    public function savePayment($payment_id, $operator_id){

        $payment = Payment::find($payment_id);
        $payment->operator_id = $operator_id;
        $payment->is_paid = 1;
        $payment->save();

        $selectedPlan = SubscriptionPlanType::find($payment->subscription_plan_id);

        $school = School::find($payment->school_id);

        $now = Carbon::now()->toDateString();
        $expiration = Carbon::parse($school->subscription_expiration_at)->toDateString();
        
        $start_date = $now > $expiration
            ? Carbon::now()
            : Carbon::parse($school->subscription_expiration_at);

        // Добавляем нужное количество месяцев
        $end_date = $start_date->copy()->addMonths(1);

        $school->subscription_plan_id = $selectedPlan->subscription_plan_id;
        $school->subscription_expiration_at = $end_date;
        $school->save();
    }
}
?>