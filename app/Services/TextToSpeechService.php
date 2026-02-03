<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TextToSpeechService
{
    public function textToSpeech($service, $text, $voice_id, $model){

        switch ($service) {
            case 'openai':

                $apiKey = env('OPENAI_API_KEY');
                $apiUrl = env('OPENAI_API_URL');

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->withBody(json_encode([
                    'model' => $model,
                    'input' => $text,
                    'voice' => $voice_id,
                ]), 'application/json')->post($apiUrl.'/audio/speech');
                break;

            case 'elevenlabs':

                $apiKey = env('ELEVENLABS_API_KEY');
                $apiUrl = env('ELEVENLABS_API_URL');

                $response = Http::withHeaders([
                    'xi-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->post("{$apiUrl}/v1/text-to-speech/{$voice_id}", [
                    'text' => $text,
                    'model_id' => $model, //eleven_multilingual_v2
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.75,
                    ],
                ]);
                break;
            default:
                # code...
                break;
        }

        return $response;
    }
}
?>