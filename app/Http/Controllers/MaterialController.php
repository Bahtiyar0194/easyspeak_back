<?php

namespace App\Http\Controllers;

use App\Models\AiExplain;
use App\Models\MaterialExplain;
use App\Models\MediaFile;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Storage;
use LanguageDetection\Language;

use App\Services\TextToSpeechService;


class MaterialController extends Controller
{
    protected $textToSpeechService;

    protected $speechDrivers;

    public function __construct(Request $request, TextToSpeechService $textToSpeechService)
    {
        $this->textToSpeechService = $textToSpeechService;

        $this->speechDrivers = [
            [
                'name' => 'openai',
                'voice_id' => 'nova', //coral
                'model' => 'gpt-4o-mini-tts', //gpt-audio
            ],
            [
                'name' => 'elevenlabs',
                'voice_id' => '56AoDkrOh6qfVPDXZ7Pt', //Cassidy
                'model' => 'eleven_v3' //eleven_flash_v2_5
            ]
        ];
    }

    public function get_chat(Request $request){
        $auth_user_id = auth()->user()->user_id;

        $chat = MaterialExplain::leftJoin('ai_explains', 'material_explains_chat.explain_id', '=', 'ai_explains.explain_id')
        ->leftJoin('files', 'ai_explains.audio_file_id', '=', 'files.file_id')
        ->where('material_explains_chat.lesson_material_id', $request->lesson_material_id)
        ->where('material_explains_chat.user_id', $auth_user_id)
        ->select(
            'material_explains_chat.uuid',
            'material_explains_chat.like',
            'material_explains_chat.user_prompt',
            'ai_explains.content as ai_content',
            'files.target'
        )
        ->orderBy('material_explains_chat.id', 'asc')
        ->get();

        return response()->json($chat, 200);
    }


    public function explain(Request $request){
        $text_driver = 'openai'; // 'openai' или 'gemini'
        $auth_user_id = auth()->user()->user_id;
        $user_prompt = $request->prompt;

        $speech_driver = $this->speechDrivers[0];

        $material = json_decode($request->material);

        $searchSameExplain = MaterialExplain::leftJoin(
            'ai_explains',
            'material_explains_chat.explain_id',
            '=',
            'ai_explains.explain_id'
        )
        ->leftJoin(
            'files',
            'ai_explains.audio_file_id',
            '=',
            'files.file_id'
        )
        ->where('material_explains_chat.lesson_material_id', $material->lesson_material_id)
        ->where('material_explains_chat.user_prompt', $user_prompt)
        ->whereNotNull('material_explains_chat.explain_id')
        ->where(function ($q) {
            $q->where('material_explains_chat.like', 1)
            ->orWhereNull('material_explains_chat.like');
        })
        ->select(
            'material_explains_chat.explain_id',
            'ai_explains.content',
            'files.target'
        )
        ->orderByDesc('material_explains_chat.like')
        ->orderByDesc('material_explains_chat.id')
        ->first();


        if(isset($searchSameExplain)){
            $new_dialog = new MaterialExplain();
            $new_dialog->uuid = str_replace('-', '', (string) Str::uuid());
            $new_dialog->user_prompt = $user_prompt;
            $new_dialog->user_id = $auth_user_id;
            $new_dialog->lesson_material_id = $material->lesson_material_id;
            $new_dialog->explain_id = $searchSameExplain->explain_id;
            $new_dialog->save();

            return response()->json([
                'uuid' => $new_dialog->uuid,
                'text' => $searchSameExplain->content,
            ], 200);
        }
        else{
            // Берём последние 5 сообщений
            $oldMessages = MaterialExplain::leftJoin('ai_explains', 'material_explains_chat.explain_id', '=', 'ai_explains.explain_id')
            ->where('material_explains_chat.lesson_material_id', $material->lesson_material_id)
            ->where('material_explains_chat.user_id', $auth_user_id)
            ->select(
                'material_explains_chat.user_prompt', // prompt пользователя 
                'ai_explains.content as ai_content' // ответ от ии
            )
            ->orderBy('material_explains_chat.id', 'desc')
            ->take(3)
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
            - Можешь добавлять смайлики, эмодзи
            - В первую очередь отвечай на вопрос ученика
            - Если вопрос ученика не связан с языком то не отвечай на этот вопрос
            - Объясняй простым, понятным языком
            - При необходимости разбивай объяснение на шаги
            - Используй примеры и аналогии

            🌍 Язык ответа:
            Отвечай строго на языке вопроса ученика
            ";

            if ($text_driver === 'gemini') {
                // Форматируем историю под Gemini
                $contents = $oldMessages->map(function($m) {
                    $contents[] = [
                        'role' => 'user',
                        'parts' => [['text' => $m->user_prompt]]
                    ];
                    $contents[] = [
                        'role' => 'model',
                        'parts' => [['text' => $m->ai_content]]
                    ];
                })->toArray();

                // Добавляем текущий вопрос
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => $user_prompt]]
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
                $messages = [
                    ['role' => 'system', 'content' => $systemPrompt]
                ];

                foreach ($oldMessages as $m) {
                    $messages[] = [
                        'role' => 'user',
                        'content' => $m->user_prompt
                    ];
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $m->ai_content
                    ];
                }

                $messages[] = [
                    'role' => 'user',
                    'content' => $user_prompt
                ];

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
                $new_explain = new AiExplain();
                $new_explain->content = Str::markdown($answer);
                $new_explain->text_driver = $text_driver;
                $new_explain->save();

                $new_dialog = new MaterialExplain();
                $new_dialog->uuid = str_replace('-', '', (string) Str::uuid());
                $new_dialog->user_prompt = $user_prompt;
                $new_dialog->user_id = $auth_user_id;
                $new_dialog->lesson_material_id = $material->lesson_material_id;
                $new_dialog->explain_id = $new_explain->explain_id;
                $new_dialog->save();

                return response()->json([
                    'uuid' => $new_dialog->uuid,
                    'text' => $new_explain->content,
                ], 200);
            }

            return response()->json(['error' => 'API Error', 'message' => $response->json()], 400);
        }
    }

    public function audio_explain(Request $request){
        $speech_driver = $this->speechDrivers[0];

        $material_explain = MaterialExplain::where('uuid', $request->uuid)
        ->firstOrFail();

        $ai_explain = AiExplain::findOrFail($material_explain->explain_id);

        if ($ai_explain->audio_file_id) {
            $file = MediaFile::find($ai_explain->audio_file_id);

            return response()->file(storage_path('app/public/' . $file->target));
        }
        else{
            
            $streamResponse = $this->textToSpeechService->textToSpeechStream($speech_driver['name'], $ai_explain->content, $speech_driver['voice_id'], $speech_driver['model']);

            $file_name = uniqid() . '.mp3';
            $file_path = storage_path("app/public/{$file_name}");

            // перед стримом
            ignore_user_abort(true); //Если пользователь покинул страницу
            set_time_limit(0);

            return response()->stream(function () use ($streamResponse, $file_name, $file_path, $ai_explain, $speech_driver) {
                if (ob_get_level()) ob_end_clean();

                $body = $streamResponse->getBody();
                $file = fopen($file_path, 'wb');

                try {
                    while (!$body->eof()) {
                        $chunk = $body->read(4096);

                        if (!$chunk) continue;

                        echo $chunk;
                        flush();

                        fwrite($file, $chunk);
                    }

                } catch (\Throwable $e) {

                    fclose($file);
                    @unlink($file_path); // ❌ удаляем битый файл

                    throw $e;

                } finally {

                    if (is_resource($file)) {
                        fclose($file);
                    }
                }

                // 👉 сохраняем файл в БД ПОСЛЕ завершения стрима 
                // 👉 выполняется только если не было ошибок

                $file_size = filesize($file_path);

                $new_file = new MediaFile();
                $new_file->file_name = $file_name;
                $new_file->target = basename($file_path);
                $new_file->size = $file_size / 1048576;
                $new_file->material_type_id = 2;
                $new_file->show_on_library = 0;
                $new_file->save();

                $ai_explain->audio_file_id = $new_file->file_id;
                $ai_explain->audio_driver = $speech_driver['name'];
                $ai_explain->save();

            }, 200, [
                'Content-Type' => 'audio/mpeg',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no', // 🔥 важно для nginx
            ]);
        }
    }

    public function feedback(Request $request){
        $auth_user_id = auth()->user()->user_id;
        $feedback = $request->feedback;

        $material_explain = MaterialExplain::where('uuid', $request->uuid)
        ->firstOrFail();

        $material_explain->like = isset($feedback) ? $feedback : null;
        $material_explain->save();

        return response()->json('success', 200);
    }
}
