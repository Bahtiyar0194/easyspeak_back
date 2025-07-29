<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TextToSpeechController extends Controller
{
    public function list_voices()
    {
        $apiKey = env('ELEVENLABS_API_KEY');
        $apiUrl = env('ELEVENLABS_API_URL');

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->get($apiUrl.'/v1/voices');

        if ($response->ok()) {
            return response()->json($response->json(), 200);
        }

        return response()->json([
            'error' => 'Не удалось получить список голосов',
            'message' => $response->body(),
        ], 400);
    }

    public function tts(Request $request)
    {
        $apiKey = env('ELEVENLABS_API_KEY');
        $apiUrl = env('ELEVENLABS_API_URL');

        $text = $request->input('text');
        $voiceId = $request->input('voice_id');
        $model = $request->input('model', 'tts-1');
        $instructions = $request->input('instructions', '');

        if (!$text) {
            return response()->json('Поле "text" обязательно.', 422);
        }

        if (!$voiceId) {
            return response()->json(trans('auth.choose_a_voice'), 422);
        }

        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $apiKey,
        //     'Content-Type' => 'application/json',
        // ])->withBody(json_encode([
        //     'model' => $model,
        //     'input' => $text,
        //     'voice' => $voice,
        //     'instructions' => $instructions,
        // ]), 'application/json')->post(env('OPENAI_API_URL').'/audio/speech');

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$apiUrl}/v1/text-to-speech/{$voiceId}", [
            'text' => $text,
            'model_id' => 'eleven_monolingual_v1',
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.75,
            ],
        ]);

        if ($response->ok()) {
            return response()->json([
                'base64' => base64_encode($response->body()),
            ], 200);
        }

        return response()->json(['error' => $response->json()], 400);
    }
}
?>