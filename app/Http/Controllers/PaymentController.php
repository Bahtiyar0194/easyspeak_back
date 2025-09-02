<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\SubscriptionPlanType;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function get_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $plans = SubscriptionPlanType::leftJoin('types_of_subscription_plans_lang', 'types_of_subscription_plans.subscription_plan_id', '=', 'types_of_subscription_plans_lang.subscription_plan_id')
        ->select(
            'types_of_subscription_plans.subscription_plan_id',
            'types_of_subscription_plans_lang.subscription_plan_name'
        )
        ->where('types_of_subscription_plans_lang.lang_id', '=', $language->lang_id)
        ->get();

        $data = [
            'plans' => $plans,
            'tiptop' => [
                'public_id' => env('TIPTOPPAY_PUBLIC_ID'),
                'checkout_url' => env('TIPTOPPAY_CHECKOUT_URL')
            ]
        ];

        return response()->json($data, 200);
    }

    public function tiptop_handle(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
                
        $apiPublicId = env('TIPTOPPAY_PUBLIC_ID');
        $apiSecretKey = env('TIPTOPPAY_SECRET_KEY');
        $apiUrl = env('TIPTOPPAY_API_URL');

        $selectedPlan = SubscriptionPlanType::leftJoin('types_of_subscription_plans_lang', 'types_of_subscription_plans.subscription_plan_id', '=', 'types_of_subscription_plans_lang.subscription_plan_id')
        ->select(
            'types_of_subscription_plans.subscription_plan_id',
            'types_of_subscription_plans_lang.subscription_plan_name'
        )
        ->where('types_of_subscription_plans.subscription_plan_id', '=', $request->selectedPlanId)
        ->first();

        if (!$selectedPlan) {
            return response()->json(['error' => 'Subscription plan is not found'], 404);
        }

        $amount = number_format($selectedPlan->price, 2, '.', '');
        $currency = 'KZT';
        $cryptogram = $request->cryptogram;

        $response = Http::withBasicAuth($apiPublicId, $apiSecretKey)
        ->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($apiUrl, [
            'Amount' => $amount,
            'Currency' => $currency,
            'CardCryptogramPacket' => $cryptogram,
            'Description' => $selectedPlan->subscription_plan_name
        ]);

        if ($response->ok()) {
            return response()->json($response->json(), 200);
        }

        return response()->json(['error' => $response->json()], 400);
    }

    public function tiptop_handle3ds(Request $request)
    {

        $apiPublicId = env('TIPTOPPAY_PUBLIC_ID');
        $apiSecretKey = env('TIPTOPPAY_SECRET_KEY');
        $api3dsUrl = env('TIPTOPPAY_API_POST_3DS_URL');

        $md = $request->input('MD');
        $paRes = $request->input('PaRes');

        // Отправляем их обратно в платёжный шлюз для подтверждения
        $response = Http::withBasicAuth($apiPublicId, $apiSecretKey)
        ->post($api3dsUrl, [
            'TransactionId' => $md,
            'PaRes' => $paRes,
        ]);

        $result = $response->json();

        $redirectUrl = env('FRONTEND_URL'). '/dashboard/payment-result?success=';

        //Логируем, чтобы посмотреть
        \Log::info('3DS Result', $result);

        if ($result['Success'] === true) {
            // транзакция прошла успешно
            return redirect()->away($redirectUrl . 'true&order=' . $result['Model']['TransactionId']);
        } else {

            //транзакция отклонена
            if(isset($result['Model']) && isset($result['Model']['ReasonCode'])){
                return redirect()->away($redirectUrl . 'false&message='.$result['Model']['CardHolderMessage'].'&reason=' . ($result['Model']['ReasonCode'] ?? 'unknown'));
            }

            return redirect()->away($redirectUrl . 'false&message='.$result['Message'].'&reason=unknown');
        }
    }
}
