<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TextToSpeechController extends Controller
{
    public function tts(Request $request)
    {
        $apiKey = env('OPENAI_API_KEY');

        $text = $request->input('text');
        $voice = $request->input('voice', 'shimmer'); // shimmer по умолчанию
        $model = $request->input('model', 'tts-1');
        $instructions = $request->input('instructions', '');

        if (!$text) {
            return response()->json(['error' => 'Поле "text" обязательно.'], 422);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->withBody(json_encode([
            'model' => $model,
            'input' => $text,
            'voice' => $voice,
            'instructions' => $instructions,
        ]), 'application/json')->post(env('OPENAI_API_URL').'/audio/speech');

        if ($response->successful()) {
            return response()->json([
                'base64' => base64_encode($response->body()),
            ], 200);
        }

        return response()->json(['error' => $response->json()], 400);
    }
}
?>