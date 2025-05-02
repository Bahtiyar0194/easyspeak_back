<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TranslateController extends Controller
{
    public function translate(Request $request)
    {
        $text = $request->input('text');

        $responseRu = Http::withToken(env('OPENAI_API_KEY'))->post(env('OPENAI_API_URL').'/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'user', 'content' => 'Translate this to Russian: ' . $text],
            ]
        ]);

        $responseKk = Http::withToken(env('OPENAI_API_KEY'))->post(env('OPENAI_API_URL').'/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                ['role' => 'user', 'content' => 'Translate this to Kazakh: ' . $text],
            ]
        ]);

        return response()->json([
            'ru' => $responseRu['choices'][0]['message']['content'] ?? '',
            'kk' => $responseKk['choices'][0]['message']['content'] ?? '',
        ]);
    }
}