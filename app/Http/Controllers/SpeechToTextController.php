<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SpeechToTextController extends Controller
{
    public function stt(Request $request)
    {
        $request->validate([
            'audio' => 'required|file',
        ]);

        $file = $request->file('audio');

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(30)
            ->retry(2, 200)
            ->attach(
                'file',
                fopen($file->getPathname(), 'r'),
                $file->getClientOriginalName()
            )
            ->post(env('OPENAI_API_URL') . '/audio/transcriptions', [
                'model' => 'gpt-4o-transcribe',
                // 'language' => 'ru', // можно не указывать
            ]);

        if ($response->failed()) {
            return response()->json([
                'error' => $response->json(),
            ], 400);
        }

        return response()->json([
            'text' => $response->json('text'),
        ], 200);
    }
}
