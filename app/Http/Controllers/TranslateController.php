<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TranslateController extends Controller
{
    public function translate(Request $request)
    {
        $text = $request->input('text');
        $transcription = $request->transcription;

        if(isset($transcription) && $transcription == 'true'){ 
            $responseTranscription = Http::withToken(env('OPENAI_API_KEY'))->post(env('OPENAI_API_URL').'/chat/completions', [
                'model' => 'gpt-4-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Make Britain IPA transcription like [text] of the text. Do not add any extra text before or after the transcription.'
                    ],
                    ['role' => 'user', 'content' => $text],
                ]
            ]);
        }

        $responseRu = Http::withToken(env('OPENAI_API_KEY'))->post(env('OPENAI_API_URL').'/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a translator from English to Russian. Translate what i write. Display only the Cyrillic translation and nothing else.'
                ],
                ['role' => 'user', 'content' => $text],
            ]
        ]);

        $responseKk = Http::withToken(env('OPENAI_API_KEY'))->post(env('OPENAI_API_URL').'/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a translator from English to Kazakh. Translate what i write. Display only the Cyrillic translation and nothing else.'
                ],
                ['role' => 'user', 'content' => $text],
            ]
        ]);

        return response()->json([
            'transcription' => isset($responseTranscription) ? ($responseTranscription['choices'][0]['message']['content'] ?? '') : '',
            'ru' => $responseRu['choices'][0]['message']['content'] ?? '',
            'kk' => $responseKk['choices'][0]['message']['content'] ?? '',
        ]);
    }
}