<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function get_attributes(Request $request)
    {
        $data = [
            'tiptop' => [
                'public_id' => env('TIPTOPPAY_PUBLIC_ID'),
                'checkout_url' => env('TIPTOPPAY_CHECKOUT_URL')
            ]
        ];

        return response()->json($data, 200);
    }

    public function tiptop_handle(Request $request)
    {
        $apiPublicId = env('TIPTOPPAY_PUBLIC_ID');
        $apiSecretKey = env('TIPTOPPAY_SECRET_KEY');
        $apiUrl = env('TIPTOPPAY_API_URL');

        $amount = $request->amount;
        $currency = $request->currency;
        $cryptogram = $request->cryptogram;

        $response = Http::withBasicAuth($apiPublicId, $apiSecretKey)
        ->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($apiUrl, [
            'Amount' => $amount,
            'Currency' => $currency,
            'CardCryptogramPacket' => $cryptogram
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
            'MD'   => $md,
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

            var_dump($result);

            // транзакция отклонена
            // if(isset($result['Model']) && isset($result['Model']['ReasonCode'])){
            //     return redirect()->away($redirectUrl . 'false&message='.$result['Model']['CardHolderMessage'].'&reason=' . ($result['Model']['ReasonCode'] ?? 'unknown'));
            // }

            // return redirect()->away($redirectUrl . 'false&reason=unknown');
        }
    }
}
