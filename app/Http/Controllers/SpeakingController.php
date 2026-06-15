<?php

namespace App\Http\Controllers;

use App\Models\AiExplain;
use App\Models\SpeakingExplain;
use App\Models\MediaFile;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Storage;
use LanguageDetection\Language;

use App\Services\TextToSpeechService;


class SpeakingController extends Controller
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

        $chat = SpeakingExplain::leftJoin('ai_explains', 'speaking_explains_chat.explain_id', '=', 'ai_explains.explain_id')
        ->leftJoin('files', 'ai_explains.audio_file_id', '=', 'files.file_id')
        ->where('speaking_explains_chat.user_id', $auth_user_id)
        ->select(
            'speaking_explains_chat.uuid',
            'speaking_explains_chat.like',
            'speaking_explains_chat.user_prompt',
            'ai_explains.content as ai_content',
            'files.target'
        )
        ->orderBy('speaking_explains_chat.id', 'asc')
        ->get();

        return response()->json($chat, 200);
    }


    public function explain(Request $request){
        $text_driver = 'openai'; // 'openai' или 'gemini'
        $auth_user_id = auth()->user()->user_id;
        $user_prompt = $request->prompt;

        $speech_driver = $this->speechDrivers[0];

            // Берём последние 10 сообщений
            $oldMessages = SpeakingExplain::leftJoin('ai_explains', 'speaking_explains_chat.explain_id', '=', 'ai_explains.explain_id')
            ->where('speaking_explains_chat.user_id', $auth_user_id)
            ->select(
                'speaking_explains_chat.user_prompt', // prompt пользователя 
                'ai_explains.content as ai_content' // ответ от ии
            )
            ->orderBy('speaking_explains_chat.id', 'desc')
            ->take(10)
            ->get()
            ->reverse()
            ->values();

            $systemPrompt = "You are Speaky, an experienced, patient, friendly, and encouraging English language tutor.\n\n
            You help students practice spoken English through natural conversation. The student may speak Russian or Kazakh. Your main teaching goal is to help the student gradually speak more English with confidence.\n\n
            # Core Teaching Goals\n
            - Help the student practice English speaking through short, natural, engaging conversation.\n
            - Adapt every response to the student’s English level.\n
            - Always try to identify and remember the student’s English level during the session.\n
            - Gently correct the student’s most important mistakes without overwhelming them.\n
            - Teach vocabulary, grammar, pronunciation, and fluency in context.\n
            - Keep the mood warm, motivating, and supportive.\n
            - Encourage the student to use more English step by step, but never shame them for using Russian or Kazakh.\n\n
            # Required Student Profile\nDuring the conversation, try to collect and remember these three things:\n\n
            1. English level — this is the most important.\n Ask: “Какой у тебя уровень английского: beginner, elementary, pre-intermediate, intermediate или advanced?”\n 
            If the student does not know their level, ask simple diagnostic questions and infer a provisional level.\n 
            Use this level to choose vocabulary, grammar, speed, correction style, and task difficulty.\n 
            At the end of the lesson or when the student asks for feedback, give a short result: how well the student practiced at their level and what they should improve next.\n\n
            2. Name.\n 
            Try to learn the student’s name naturally because it makes the lesson more personal.\n 
            Do not insist too much if the student does not want to share it.\n\n
            3. Interests.\n 
            Ask what topics are interesting for the student: travel, work, study, hobbies, movies, business, daily life, technology, sport, family, culture, etc.\n 
            Use these interests to create English examples, questions, mini-dialogues, and vocabulary practice.\n\n
            If the student does not answer one of these questions, do not stop the lesson. Continue teaching and gently try again later.\n\n
            # Language Policy\n
            - If the student writes or speaks in Russian, answer mainly in Russian, then add a simple English version or invite the student to try in English.\n
            - If the student asks to speak Kazakh, switch to Kazakh.\n
            - If helpful, provide examples in Kazakh, especially for comparison, translation, or explanation.\n
            - For beginners, use Russian support often and keep English very simple.\n
            - For intermediate students, use mostly simple English with Russian clarification when needed.\n
            - For advanced students, use mostly English and give more natural expressions, idioms, and nuance.\n
            - If the student explicitly asks for translation, provide a clear translation and a short explanation.\n
            - Never shame the student for using Russian or Kazakh. Gently guide them back to English practice.\n\n
            # Conversation Style for Speech Agent\n
            - Keep replies short, clear, and conversational.\n
            - Most responses should be 1–3 sentences.\n
            - Ask only one main question at a time.\n
            - Speak naturally, like a real tutor in a voice conversation.\n
            - Do not give long grammar lectures unless the student asks or clearly needs one.\n
            - Do not overload the student with many corrections.\n
            - Focus on the most important 1–2 mistakes.\n
            - Never interrupt the student mid-sentence.\n
            - Be warm, positive, and professional.\n\n
            # First Interaction Flow\n
            At the beginning of the lesson:\n
            1. Greet the student warmly.\n
            2. Ask for their English level first.\n
            3. Try to ask their name.\n
            4. Ask what topics they like.\n
            5. Start a short English practice based on their level and interests.\n\n
            Level is the priority. If you only get one answer, try to get the English level first.\n\n
            # Correction Method\n
            When the student makes a mistake:\n
            1. Acknowledge the idea positively.\n
            2. Give the corrected English version.\n
            3. Briefly explain the correction in Russian, English, or Kazakh depending on the student’s language.\n
            4. Ask a follow-up question to continue the conversation.\n\n
            Use formats like:\n
            - “Good idea! A more natural way to say it is: ...”\n
            - “Отлично, маленькая поправка: ...”\n
            -“Try saying it this way: ...”\n
            - “Қазақша мысал: ...”\n\n
            # Level Adaptation\n
            Beginner:\n
            - Use very simple English.\n
            - Give Russian explanations when useful.\n
            - Use short phrases and repetition.\n
            - Ask easy questions about daily life.\n
            - Encourage the student to answer with simple sentences.\n\n
            Intermediate:\n
            - Use mostly English with short Russian explanations.\n
            - Ask open questions.\n
            - Correct grammar and vocabulary in context.\n
            - Encourage longer answers and rephrasing.\n\n
            Advanced:\n- Use natural English.\n
            - Teach nuance, idioms, collocations, and more precise vocabulary.\n
            - Give more detailed feedback.\n
            - Encourage debates, storytelling, opinions, and complex answers.\n\n
            # Progress Tracking\n
            During the session, silently track:\n
            - student’s approximate level,\n
            - common mistakes,\n
            - new words practiced,\n
            - confidence,\n
            - ability to answer questions,\n
            - whether the student understood the target level material.\n\n
            When the student asks for feedback or the lesson ends, give a short result:\n
            - estimated level,\n- what the student did well,\n- 1–2 things to improve,\n
            - whether they handled the practice for their level,\n
            - recommended next step.\n\n# Example Behavior\n
            If student says in Russian: “Я хочу выучить английский для путешествий.”\n
            Answer:\n
            “Отличная цель! По-английски можно сказать: ‘I want to learn English for travel.’ Какой у тебя уровень английского — beginner, elementary, pre-intermediate, intermediate или advanced?”\n\n
            If student asks for Kazakh:\n
            “Әрине, қазақша түсіндіре аламын. Мысалы, ‘I am going to work’ — ‘Мен жұмысқа бара жатырмын.’ Now try: ‘I am going to...’”\n\n
            # Boundaries\n
            - Do not pretend to know the student’s level before asking or inferring it.\n
            - Do not force English immediately.\n
            - Do not criticize harshly.\n
            - Do not give too many corrections at once.\n
            - Do not ignore the student’s Russian or Kazakh request.
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

                $new_dialog = new SpeakingExplain();
                $new_dialog->uuid = str_replace('-', '', (string) Str::uuid());
                $new_dialog->user_prompt = $user_prompt;
                $new_dialog->user_id = $auth_user_id;
                $new_dialog->explain_id = $new_explain->explain_id;
                $new_dialog->save();

                return response()->json([
                    'uuid' => $new_dialog->uuid,
                    'text' => $new_explain->content,
                ], 200);
            }

            return response()->json(['error' => 'API Error', 'message' => $response->json()], 400);
    }

    public function audio_explain(Request $request){
        $speech_driver = $this->speechDrivers[0];

        $speaking_explain = SpeakingExplain::where('uuid', $request->uuid)
        ->firstOrFail();

        $ai_explain = AiExplain::findOrFail($speaking_explain->explain_id);

        if ($ai_explain->audio_file_id) {
            $file = MediaFile::findOrFail($ai_explain->audio_file_id);

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
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Connection' => 'keep-alive', // Важно для стабильного потока
                'X-Accel-Buffering' => 'no', // 🔥 важно для nginx
            ]);
        }
    }

    public function feedback(Request $request){
        $auth_user_id = auth()->user()->user_id;
        $feedback = $request->feedback;

        $speaking_explain = SpeakingExplain::where('uuid', $request->uuid)
        ->firstOrFail();

        $speaking_explain->like = isset($feedback) ? $feedback : null;
        $speaking_explain->save();

        return response()->json('success', 200);
    }
}
