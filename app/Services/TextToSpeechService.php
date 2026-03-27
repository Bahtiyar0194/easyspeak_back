<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TextToSpeechService
{
    public function textToSpeech($service, $text, $voice_id, $model){

        info($model);

        switch ($service) {
            case 'openai':

                $apiKey = env('OPENAI_API_KEY');
                $apiUrl = env('OPENAI_API_URL');
                
                $instructions = "Voice Affect: Calm, composed, and reassuring; project quiet authority and confidence.\n\nTone: Sincere, empathetic, and gently authoritative—express genuine apology while conveying competence.\n\nPacing: Steady and moderate; unhurried enough to communicate care, yet efficient enough to demonstrate professionalism.\n\nEmotion: Genuine empathy and understanding; speak with warmth, especially during apologies (\"I'm very sorry for any disruption...\").\n\nPronunciation: Clear and precise, emphasizing key reassurances (\"smoothly,\" \"quickly,\" \"promptly\") to reinforce confidence.\n\nPauses: Brief pauses after offering assistance or requesting details, highlighting willingness to listen and support.";

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])->post($apiUrl . '/audio/speech', [
                    'model' => $model ?? 'gpt-4o-mini-tts',
                    'input' => $text,
                    'voice' => $voice_id,
                    'instructions' => $instructions
                ]);

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