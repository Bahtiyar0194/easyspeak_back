<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\School;
use App\Models\SubscriptionPlanType;
use App\Models\Payment;
use App\Models\PaymentMethod;

use App\Services\PaymentService;

use Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(Request $request, PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $plans = SubscriptionPlanType::leftJoin('types_of_subscription_plans_lang', 'types_of_subscription_plans.subscription_plan_id', '=', 'types_of_subscription_plans_lang.subscription_plan_id')
        ->select(
            'types_of_subscription_plans.subscription_plan_id',
            'types_of_subscription_plans.price',
            'types_of_subscription_plans_lang.subscription_plan_name'
        )
        ->where('types_of_subscription_plans_lang.lang_id', '=', $language->lang_id)
        ->get();

        $methods = PaymentMethod::leftJoin('payment_methods_lang', 'payment_methods_lang.payment_method_id', '=', 'payment_methods.payment_method_id')
        ->select(
            'payment_methods.payment_method_id',
            'payment_methods_lang.payment_method_name',
        )
        ->where('payment_methods_lang.lang_id', '=', $language->lang_id)
        ->get();

        $data = [
            'plans' => $plans,
            'methods' => $methods,
            'tiptop' => [
                'public_id' => env('TIPTOPPAY_PUBLIC_ID'),
                'checkout_url' => env('TIPTOPPAY_CHECKOUT_URL')
            ]
        ];

        return response()->json($data, 200);
    }

    public function get_payments(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $per_page = $request->per_page ? $request->per_page : 10;
        // Получаем параметры сортировки
        $sortKey = $request->input('sort_key', 'created_at');  // Поле для сортировки по умолчанию
        $sortDirection = $request->input('sort_direction', 'asc');  // Направление по умолчанию

        $payments = Payment::leftJoin('types_of_subscription_plans', 'types_of_subscription_plans.subscription_plan_id', '=', 'payments.subscription_plan_id')
        ->leftJoin('types_of_subscription_plans_lang', 'types_of_subscription_plans.subscription_plan_id', '=', 'types_of_subscription_plans_lang.subscription_plan_id')
        ->leftJoin('schools', 'schools.school_id', '=', 'payments.school_id')
        ->leftJoin('payment_methods', 'payment_methods.payment_method_id', '=', 'payments.payment_method_id')
        ->leftJoin('payment_methods_lang', 'payment_methods_lang.payment_method_id', '=', 'payment_methods.payment_method_id')
        ->leftJoin('users as iniciator', 'payments.iniciator_id', '=', 'iniciator.user_id')
        ->leftJoin('users as operator', 'payments.operator_id', '=', 'operator.user_id')
        ->select(
            'payments.payment_id',
            'payments.sum',
            'payments.is_paid',
            'payments.created_at',
            'payments.accepted_at',
            'payments.expiration_at',
            'types_of_subscription_plans.subscription_plan_id',
            'types_of_subscription_plans_lang.subscription_plan_name',
            'schools.school_name',
            'schools.full_school_name',
            'schools.bin',
            'payment_methods.payment_method_id',
            'payment_methods.payment_method_slug',
            'payment_methods_lang.payment_method_name',
            'iniciator.first_name as iniciator_first_name',
            'iniciator.last_name as iniciator_last_name',
            'operator.first_name as operator_first_name',
            'operator.last_name as operator_last_name',
        )
        ->where('types_of_subscription_plans_lang.lang_id', '=', $language->lang_id)
        ->where('payment_methods_lang.lang_id', '=', $language->lang_id)
        ->orderBy($sortKey, $sortDirection);


        $payment_id = ltrim($request->payment_id, '0');
        $school_name = $request->school_name;
        $full_school_name = $request->full_school_name;
        $bin = $request->bin;
        $subscription_plans_id = $request->subscription_plans;
        $payment_methods_id = $request->payment_methods;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;
        $is_paid = $request->is_paid;

        if (!empty($payment_id)) {
            $payments->where('payments.payment_id', '=', $payment_id);
        }

        if (!empty($school_name)) {
            $payments->where('schools.school_name', 'LIKE', '%' . $school_name . '%');
        }

        if (!empty($full_school_name)) {
            $payments->where('schools.full_school_name', 'LIKE', '%' . $full_school_name . '%');
        }

        if (!empty($bin)) {
            $payments->where('schools.bin', 'LIKE', '%' . $bin . '%');
        }

        if(!empty($subscription_plans_id)){
            $payments->whereIn('payments.subscription_plan_id', $subscription_plans_id);
        }

        if(!empty($payment_methods_id)){
            $payments->whereIn('payments.payment_method_id', $payment_methods_id);
        }

        if ($created_at_from && $created_at_to) {
            $payments->whereBetween('payments.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:00']);
        }

        if ($created_at_from) {
            $payments->where('payments.created_at', '>=', $created_at_from . ' 00:00:00');
        }

        if ($created_at_to) {
            $payments->where('payments.created_at', '<=', $created_at_to . ' 23:59:00');
        }

        if ($is_paid != '') {
            $payments->where('payments.is_paid', '=', $is_paid);
        }

        return response()->json($payments->paginate($per_page)->onEachSide(1), 200);
    }

    public function handle(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->lang)->first();

        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'subscription_plan_id' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);

        } elseif ($request->step == 2) {
            $rules = [
                'payment_method' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
            
        } elseif ($request->step == 3) {
            $rules = [
                'payment_method' => 'required',
                'subscription_plan_id' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            if($request->payment_method === 'card'){
                $rules['cryptogram'] = 'required|string|min:1';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $school = School::select(
                'schools.*',
            )
            ->where('school_id', '=', auth()->user()->school_id)
            ->first();

            $selectedPlan = SubscriptionPlanType::leftJoin('types_of_subscription_plans_lang', 'types_of_subscription_plans.subscription_plan_id', '=', 'types_of_subscription_plans_lang.subscription_plan_id')
            ->select(
                'types_of_subscription_plans.subscription_plan_id',
                'types_of_subscription_plans.price',
                'types_of_subscription_plans.months_count',
                'types_of_subscription_plans_lang.subscription_plan_name'
            )
            ->where('types_of_subscription_plans.subscription_plan_id', '=', $request->subscription_plan_id)
            ->where('types_of_subscription_plans_lang.lang_id', '=', $language->lang_id)
            ->first();

            if (!$selectedPlan) {
                return response()->json(['error' => 'Subscription plan is not found'], 404);
            }

            $amount = number_format($selectedPlan->price, 2, '.', '');
            $currency = 'KZT';
            $cryptogram = $request->cryptogram;

            $payment_method = PaymentMethod::where('payment_method_slug', '=', $request->payment_method)
            ->select('*')
            ->first();

            if (!$payment_method) {
                return response()->json(['error' => 'Payment method is not found'], 404);
            }

            $now = Carbon::now()->toDateString();

            $expiration = Carbon::parse($school->subscription_expiration_at)->toDateString();

            $start_date = $now > $expiration
                ? Carbon::now()
                : Carbon::parse($school->subscription_expiration_at);

            // Добавляем нужное количество месяцев
            $end_date = $start_date->copy()->addMonths($selectedPlan->months_count);

            $invoices = Payment::where('payments.school_id', '=', $school->school_id)
            ->where('payments.is_paid', '=', 0)
            ->where('payments.payment_method_id', '=', 1)
            ->delete();

            $new_payment = new Payment();
            $new_payment->description = $selectedPlan->subscription_plan_name.' - '.$school->school_name.', ('.$start_date->format('d.m.Y').' - '.$end_date->format('d.m.Y').')';
            $new_payment->sum = $amount;
            $new_payment->subscription_plan_id = $selectedPlan->subscription_plan_id;
            $new_payment->school_id = auth()->user()->school_id;
            $new_payment->payment_method_id = $payment_method->payment_method_id;
            $new_payment->iniciator_id = auth()->user()->user_id;
            $new_payment->accepted_at = $start_date;
            $new_payment->expiration_at = $end_date;
            $new_payment->save();

            if($request->payment_method === 'invoice'){
                return response()->json($new_payment, 200);
            }
            elseif($request->payment_method === 'card'){
                $apiPublicId = env('TIPTOPPAY_PUBLIC_ID');
                $apiSecretKey = env('TIPTOPPAY_SECRET_KEY');
                $apiUrl = env('TIPTOPPAY_API_URL');

                $response = Http::withBasicAuth($apiPublicId, $apiSecretKey)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($apiUrl, [
                    'Amount' => $amount,
                    'Currency' => $currency,
                    'CardCryptogramPacket' => $cryptogram,
                    'InvoiceId' => $new_payment->payment_id,
                    'Description' => $selectedPlan->subscription_plan_name,
                    'Email' => $school->email,
                    'JsonData' => json_encode([
                        'PaymentUrl' => $request->header('Origin'),
                    ])
                ]);

                if ($response->ok()) {
                    $result = $response->json();

                    if ($result['Success'] === true) {
                        // транзакция прошла успешно
                        $this->paymentService->savePayment($result['Model']['InvoiceId'], null);
                    }

                    return response()->json($response->json(), 200);
                }

                return response()->json(['error' => $response->json()], 400);
            }
        }                
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

        if(isset($result['Model'])){
            $jsonData = json_decode($result['Model']['JsonData']);
        
            $redirectUrl = $jsonData->PaymentUrl. '/dashboard/payment-result?success=';

            //Логируем, чтобы посмотреть
            \Log::info('3DS Result', $result);

            if ($result['Success'] === true) {
                // транзакция прошла успешно

                $this->paymentService->savePayment($result['Model']['InvoiceId'], null);

                return redirect()->away($redirectUrl . 'true');
            } else {
                //транзакция отклонена
                if(isset($result['Model']) && isset($result['Model']['ReasonCode'])){
                    return redirect()->away($redirectUrl . 'false&message='.$result['Model']['CardHolderMessage'].'&reason=' . ($result['Model']['ReasonCode'] ?? 'unknown'));
                }

                return redirect()->away($redirectUrl . 'false&message='.$result['Message'].'&reason=unknown');
            }
        }
    }

    public function accept_payment(Request $request)
    {
        $operator_id = auth()->user()->user_id;
        $this->paymentService->savePayment($request->payment_id, $operator_id);

        return response()->json(['success' => true], 200);
    }
}
