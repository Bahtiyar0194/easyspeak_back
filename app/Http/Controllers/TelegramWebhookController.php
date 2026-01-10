<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use TelegramBot\Api\BotApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Language;
use App\Models\Conference;
use App\Models\TelegramToken;

use App\Services\ScheduleService;

use App\Jobs\SendTelegramMessage;

class TelegramWebhookController extends Controller
{
    protected $scheduleService;

    public function __construct(Request $request, ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    public function get_account(Request $request)
    {
        // Ищем токен
        $token = TelegramToken::where('token', $request->token)
        ->firstOrFail();
        
        return response()->json($token, 200);
    }

    public function connect(Request $request)
    {
        // Получаем текущего аутентифицированного пользователя
        $user = auth()->user();

        DB::transaction(function () use ($request, $user) {
            // Ищем токен и проверяем, что он свободен
            $token = TelegramToken::where('token', $request->token)
            ->lockForUpdate()
            ->firstOrFail();

            // Отвязываем старые Telegram у пользователя (НЕ удаляем)
            TelegramToken::where('user_id', $user->user_id)
                ->update(['user_id' => null]);

            // Привязываем новый
            $token->update([
                'user_id' => $user->user_id,
                //'lang_id' => $user->lang_id
            ]);

            $selected_language = Language::find($token->lang_id);

            if(isset($selected_language)){
                app()->setLocale($selected_language->lang_tag);
            }

            $keyboard = [
                [
                    ['text' => trans('app.bot.buttons.menu'), 'callback_data' => 'start'],
                ]
            ];
            
            SendTelegramMessage::dispatch(
                $token->chat_id,
                trans('app.bot.connect_congrat', [
                    'name' => $user->first_name
                ]),
                $keyboard
            );
        });

        return response()->json('success', 200);
    }

    public function disconnect(Request $request)
    {
        $user = auth()->user();

        DB::transaction(function () use ($user) {

            $tokens = TelegramToken::where('user_id', $user->user_id)->get();

            foreach ($tokens as $token) {
                if (!$token->chat_id) {
                    continue;
                }

                try {
                    SendTelegramMessage::dispatch(
                        $token->chat_id,
                        trans('app.bot.disconnect_title', [
                            'name' => $user->first_name,
                        ]),
                        null
                    );
                } catch (\Throwable $e) {
                    // логируем, но не роняем процесс
                    Log::warning('Telegram disconnect notify failed', [
                        'chat_id' => $token->chat_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // отвязываем после уведомления
            TelegramToken::where('user_id', $user->user_id)
                ->update(['user_id' => null]);
        });

        return response()->json(['status' => 'success']);
    }

    public function handle(Request $request)
    {
        //Log::info('Telegram update', $request->all());

        $bot = new BotApi(config('services.telegram.token'));
        $update = json_decode($request->getContent(), true);

        /*
        |----------------------------------------
        | CALLBACK QUERY (inline-кнопки)
        |----------------------------------------
        */

        if (isset($update['callback_query'])) {

            $callback = $update['callback_query'];
            $chatId = $callback['message']['chat']['id'];
            $userName = $callback['message']['from']['username'];
            $messageId = $callback['message']['message_id'];
            $data = $callback['data'];


            [$action, $id] = array_pad(explode(':', $data, 2), 2, null);

            // ищем токен по chat_id
            $token = TelegramToken::where('chat_id', $chatId)
            ->first();

            $selected_language = Language::find($token->lang_id);

            if(isset($selected_language)){
                app()->setLocale($selected_language->lang_tag);
            }

            switch ($action) {

                case 'conference_confirm':
                    $this->confirmOrDecline('confirm', $id, $token, $selected_language);
                    $title = trans('app.bot.conference.your_answer_is_accepted');
                    break;

                case 'conference_decline':
                    $this->confirmOrDecline('decline', $id, $token, $selected_language);
                    $title = trans('app.bot.conference.your_answer_is_accepted');
                    break;

                case 'progress':
                    // показать прогресс
                    break;

                case 'schedule':
                    // показать расписание
                    if(isset($token->user_id)){
                        $conferences = $this->scheduleService->getSchedule($request, $token->user_id, $selected_language->lang_id, true, null);

                        if(count($conferences) > 0){
                             $title = trans('app.bot.schedule.title');
              
                            foreach ($conferences as $key => $conference) {
                                $title .= "\n• {$conference->start_time_formatted} - {$conference->lesson_name} ({$conference->lesson_type_name})";
                            }
                        }
                        else{
                            $title = trans('app.bot.schedule.no_lessons_title');
                        }
                    }
                    else{
                        $title = trans('app.bot.schedule.no_lessons_title');
                    }
                    break;

                case 'settings':
                    // показать настройки
                    $title = trans('app.bot.settings.select_title');

                    $keyboard = [
                        [
                            ['text' => trans('app.bot.settings.language.title'), 'callback_data' => 'language'],
                        ]
                    ];

                    if(isset($token->user_id)){
                        $keyboard[] = [
                            [
                                'text' => trans('app.bot.disconnect'), 'callback_data' => 'disconnect'
                            ]
                        ];
                    }

                    break;

                case 'language':
                    // показать список языков
                    $title = trans('app.bot.settings.language.select_title');

                    $languages = Language::select('lang_id', 'lang_name')->get();

                    $keyboard = [];

                    foreach ($languages as $language) {
                        $keyboard[] = [
                            [
                                'text' => $language->lang_name,
                                'callback_data' => 'select_language:' . $language->lang_id,
                            ],
                        ];
                    }

                    break;
                
                case 'select_language':

                    $selected_language = Language::find($id);

                    if(isset($selected_language)){
                        app()->setLocale($selected_language->lang_tag);
                    }

                    $title = trans('app.bot.settings.language.after_select_title');

                    $save_token = TelegramToken::find($token->id);
                    $save_token->lang_id = $id;
                    $save_token->save();

                    break;

                case 'disconnect':  
                    $title = trans('app.bot.disconnect_confirm');

                    $keyboard = [
                        [
                            ['text' => trans('app.bot.buttons.disconnect.confirm'), 'callback_data' => 'disconnect_confirm'],
                            ['text' => trans('app.bot.buttons.disconnect.decline'), 'callback_data' => 'start'],
                        ]
                    ];

                    break;

                case 'disconnect_confirm':  
                    if(isset($token->user_id)){
                        $user = User::find($token->user_id);

                        if(isset($user)){
                            $title = trans('app.bot.disconnect_title', [
                                'name' => $user->first_name,
                            ]);

                            $save_token = TelegramToken::find($token->id);
                            $save_token->user_id = null;
                            $save_token->save();
                        }
                    }
                    break;
            }

            if(isset($title)){
                // Отправить сообщение
                SendTelegramMessage::dispatch(
                    $chatId,
                    $title,
                    isset($keyboard) ? $keyboard : null
                );
            }

            // ❌ удалить кнопки
            $bot->editMessageReplyMarkup(
                $chatId,
                $messageId,
                null
            );

            $bot->answerCallbackQuery($callback['id']);

            if($action === 'select_language' || $action === 'start'){
                $this->start($chatId, $token);
            }

            return response()->json(['ok' => true]);
        }

        /*
        |----------------------------------------
        | MESSAGE
        |----------------------------------------
        */

        // ❗ если это не сообщение — выходим
        if (!isset($update['message'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = $update['message']['chat']['id'];
        $userName = $update['message']['from']['username'];
        $text = trim($update['message']['text'] ?? '');
        [$cmd, $payload] = array_pad(explode(' ', $text, 2), 2, null);

        // ищем токен по chat_id
        $token = TelegramToken::where('chat_id', $chatId)
        ->first();

        // ищем токен по chat_id
        if (!$token) {
            $token = TelegramToken::create([
                'chat_id' => $chatId,
                'user_name' => $userName,
                'token'   => (string) Str::uuid(),
            ]);
        }

        $selected_language = Language::find($token->lang_id);

        if(isset($selected_language)){
            app()->setLocale($selected_language->lang_tag);
        }

        if (str_starts_with($text, '/start')) {
            $this->start($chatId, $token);
        }

        return response()->json(['ok' => true]);
    }


    protected function start(int $chatId, TelegramToken $token): void
    {
        // ❌ Telegram ещё не привязан к пользователю
        if ($token->user_id === null) {

            $keyboard = [
                [
                    [
                        'text' => trans('app.bot.connect_title'),
                        'url' => env('FRONTEND_URL')."/dashboard/profile?tg_token={$token->token}"
                    ],
                ],
                [
                    ['text' => trans('app.bot.settings.language.title'), 'callback_data' => 'language'],
                ]
            ];
                    
            SendTelegramMessage::dispatch(
                $chatId,
                trans('app.bot.connect_description'),
                $keyboard
            );

            return;
        }

        // ✅ Пользователь найден → меню
        $keyboard = [
            // [
            //     ['text' => trans('app.bot.my_progress'), 'callback_data' => 'progress'],
            // ],
            [
                ['text' => trans('app.bot.my_schedule'), 'callback_data' => 'schedule'],
            ],
            [
                ['text' => trans('app.bot.settings.title'), 'callback_data' => 'settings'],
            ],
        ];

        SendTelegramMessage::dispatch(
            $chatId,
            trans('app.bot.welcome'),
            $keyboard
        );
    }

    protected function confirmOrDecline(string $method, int $conferenceId, TelegramToken $token, Language $selected_language): void
    {
        $conference = Conference::find($conferenceId);

        if(isset($conference)){
            $mentor = User::find($conference->mentor_id);
            $learner = User::find($token->user_id);

            if(isset($mentor) && isset($learner)){
                if($mentor->user_id !== $learner->user_id){
                    $mentor_token = TelegramToken::where('user_id', $mentor->user_id)
                    ->first();

                    if(isset($mentor_token)){
                        $mentor_selected_language = Language::find($mentor_token->lang_id);

                        //Язык учителя
                        if(isset($mentor_selected_language)){
                            app()->setLocale($mentor_selected_language->lang_tag);
                        }

                        if($method === 'confirm'){
                            $mentor_title = trans('app.bot.conference.confirm', [
                                'learner_name' => $learner->last_name.' '.$learner->first_name,
                                'lesson' => $conference->lesson->lesson_name,
                                'time' => humanDate($conference->start_time)
                            ]);
                        }
                        elseif($method === 'decline'){
                            $mentor_title = trans('app.bot.conference.decline', [
                                'learner_name' => $learner->last_name.' '.$learner->first_name,
                                'lesson' => $conference->lesson->lesson_name,
                                'time' => humanDate($conference->start_time)
                            ]);
                        }

                        if(isset($mentor_title)){
                            // Отправить сообщение учителю
                            SendTelegramMessage::dispatch(
                                $mentor_token->chat_id,
                                $mentor_title,
                                null
                            );
                        }

                        //Снова переключаем язык для ученика
                        if(isset($selected_language)){
                            app()->setLocale($selected_language->lang_tag);
                        }
                    }
                }
            }
        }
    }
}
