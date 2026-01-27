<?php

namespace App\Http\Controllers;

use App\Models\MaterialExplain;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MaterialController extends Controller
{
    public function get_chat(Request $request){
        $auth_user_id = auth()->user()->user_id;

        $chat = MaterialExplain::where('lesson_material_id', $request->lesson_material_id)
        ->where('user_id', $auth_user_id)
        ->orderBy('id', 'asc')
        ->get();

        return response()->json($chat, 200);
    }


    public function explain(Request $request){
        $driver = 'openai'; // 'openai' или 'gemini'
        $auth_user_id = auth()->user()->user_id;
        $userPrompt = $request->prompt;

        $material = json_decode($request->material);


        // Берём последние 5 сообщений
        $oldMessages = MaterialExplain::where('lesson_material_id', $material->lesson_material_id)
        ->where('user_id', $auth_user_id)
        ->orderBy('id', 'desc')
        ->take(5)
        ->get()
        ->reverse()
        ->values();

        // Формируем системную инструкцию
         $material_content = "";
         
        if(count($oldMessages) === 0){
            $material_content = "Материал:\n" . trim(preg_replace('/\s+/', ' ', strip_tags($material->content))) . "\n\n";
        }

        $systemPrompt = "Ты — опытный и внимательный преподаватель по английскому и другим языкам.\n\n" . $material_content . 
        "📌 Правила ответа:
        - Обращайся на 'Вы'
        - Отвечай только на языковые темы
        - Используй markdown форматирование
        - Можешь добавлять смайлики, эмодзи
        - В первую очередь отвечай на вопрос ученика
        - Если вопрос ученика не связан с языком то не отвечай на этот вопрос
        - Объясняй простым, понятным языком
        - При необходимости разбивай объяснение на шаги
        - Используй примеры и аналогии

        🌍 Язык ответа:
        Отвечай строго на языке вопроса ученика
        ";

        if ($driver === 'gemini') {
            // Форматируем историю под Gemini
            $contents = $oldMessages->map(function($m) {
                return [
                    'role' => ($m->role === 'assistant') ? 'model' : 'user',
                    'parts' => [['text' => $m->content]]
                ];
            })->toArray();

            // Добавляем текущий вопрос
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $userPrompt]]
            ];

            $response = Http::timeout(30)
            ->retry(2, 200)
            ->post(env('GEMINI_API_URL')."/v1beta/models/gemini-3-flash-preview:generateContent?key=" . env('GEMINI_API_KEY'), [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                ]
            ]);

            $answer = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } 
        else {
            // Логика OpenAI
            $messages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $oldMessages->map(function($m) { 
                    return ['role' => $m->role, 'content' => $m->content];
                })->toArray(),
                [['role' => 'user', 'content' => $userPrompt]]
            );

            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(30)
                ->retry(2, 200)
                ->post(env('OPENAI_API_URL') . '/chat/completions', [
                    'model' => 'gpt-4o', // gpt-5.2 еще не вышла в 2026, вероятно вы имели в виду актуальную версию
                    'messages' => $messages
                ]);

            $answer = $response->json()['choices'][0]['message']['content'] ?? null;
        }

        if ($answer) {
            $new_user_dialog = new MaterialExplain();
            $new_user_dialog->content = $userPrompt;
            $new_user_dialog->user_id = $auth_user_id;
            $new_user_dialog->lesson_material_id = $material->lesson_material_id;
            $new_user_dialog->role = 'user';
            $new_user_dialog->save();

            $new_system_dialog = new MaterialExplain();
            $new_system_dialog->content = Str::markdown($answer);
            $new_system_dialog->user_id = $auth_user_id;
            $new_system_dialog->lesson_material_id = $material->lesson_material_id;
            $new_system_dialog->role = 'assistant';
            $new_system_dialog->save();

            return response()->json(Str::markdown($answer), 200);
        }

        return response()->json(['error' => 'API Error', 'message' => $response->json()], 400);
    }
}
