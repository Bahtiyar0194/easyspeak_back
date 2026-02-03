<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use App\Services\TextToSpeechService;

class TextToSpeechController extends Controller
{
    protected $textToSpeechService;

    public function __construct(Request $request, TextToSpeechService $textToSpeechService)
    {
        $this->textToSpeechService = $textToSpeechService;
    }

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
        $text = $request->input('text');
        $voice_id = $request->input('voice_id');

        $service = 'elevenlabs';
        $model = "eleven_multilingual_v2"; //$model = $request->input('model', 'tts-1'); //от openai

        if (!$text) {
            return response()->json('Поле "text" обязательно.', 422);
        }

        if (!$voice_id) {
            return response()->json(trans('auth.choose_a_voice'), 422);
        }

        $response = $this->textToSpeechService->textToSpeech($service, $text, $voice_id, $model);

        if ($response->ok()) {
            return response()->json([
                'base64' => base64_encode($response->body()),
            ], 200);
        }

        return response()->json(['error' => $response->json()], 400);
    }
}
?>