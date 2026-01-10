<?php
namespace App\Services;
use App\Models\Payment;
use App\Models\LearnerPayment;
use App\Models\BoughtLesson;
use App\Models\Conference;
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

    public function saveLearnerPayment($payment_id, $lesson_ids){

        $payment = LearnerPayment::find($payment_id);
        $payment->is_paid = 1;
        $payment->save();

        foreach ($lesson_ids as $lesson) {

            // 1. Добавляем платную покупку
            BoughtLesson::create([
                'learner_id' => $payment->iniciator_id,
                'lesson_id'  => $lesson['lesson_id'],
                'iniciator_id' => $payment->iniciator_id,
                'is_free'    => 0,
            ]);

            // 2. Находим все прошедшие конференции по этому уроку
            $passed_conferences = Conference::where('group_id', $lesson['group_id'])
                ->where('start_time', '<=', now())
                ->get();

            info('Passed conference: ' . json_encode($passed_conferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
 

            // 3. Делаем их бесплатными, если ученик их не покупал
            foreach ($passed_conferences as $conf) {

                $exists = BoughtLesson::where('lesson_id', $conf->lesson_id)
                    ->where('learner_id', $payment->iniciator_id)
                    ->exists();

                if (!$exists) {
                    BoughtLesson::create([
                        'learner_id' => $payment->iniciator_id,
                        'lesson_id'  => $conf->lesson_id,
                        'iniciator_id' => $payment->iniciator_id,
                        'is_free'    => 1,
                    ]);
                }
            }
        }
    }
}
?>