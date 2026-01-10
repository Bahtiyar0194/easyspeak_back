<?php

namespace App\Console\Commands;
use App\Models\Language;
use App\Models\Conference;
use App\Models\GroupMember;
use App\Models\TelegramToken;
use App\Jobs\SendTelegramMessage;
use Illuminate\Support\Facades\Log;

use Illuminate\Console\Command;

class NotifyConference extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conferences:notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send conference notify 5 minutes before';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $from = now()->addMinutes(env('CONFERENCE_BEFORE_MINUTES'))->startOfMinute();
        $to = now()->addMinutes(env('CONFERENCE_BEFORE_MINUTES'))->endOfMinute();

        Conference::whereBetween('start_time', [$from, $to])
            ->where('notification_sent', 0)
            ->chunkById(20, function ($conferences) {

                foreach ($conferences as $conference) {

                    $members = GroupMember::leftJoin(
                            'telegram_tokens',
                            'group_members.member_id',
                            '=',
                            'telegram_tokens.user_id'
                        )
                        ->leftJoin(
                            'users',
                            'group_members.member_id',
                            '=',
                            'users.user_id'
                        )
                        ->where('group_members.group_id', $conference->group_id)
                        ->where('group_members.status_type_id', 1)
                        ->whereNotNull('telegram_tokens.chat_id')
                        ->select(
                            'telegram_tokens.chat_id',
                            'telegram_tokens.lang_id',
                            'users.first_name'
                        )
                        ->get();

                    foreach ($members as $member) {
                        $selected_language = Language::find($member->lang_id);

                        if(isset($selected_language)){
                            app()->setLocale($selected_language->lang_tag);
                        }

                        SendTelegramMessage::dispatch(
                            $member->chat_id,
                            $this->message($conference, $member),
                            $this->keyboard($conference->uuid)
                        );
                    }

                    $mentor_token = TelegramToken::leftJoin(
                        'users',
                        'telegram_tokens.user_id',
                        '=',
                        'users.user_id'
                    )
                    ->select(
                        'telegram_tokens.chat_id',
                        'telegram_tokens.lang_id',
                        'users.first_name'
                    )
                    ->where('telegram_tokens.user_id', $conference->mentor_id)
                    ->first();

                    if(isset($mentor_token)){
                        $selected_language = Language::find($mentor_token->lang_id);

                        if(isset($selected_language)){
                            app()->setLocale($selected_language->lang_tag);
                        }

                        SendTelegramMessage::dispatch(
                            $mentor_token->chat_id,
                            $this->message($conference, $mentor_token),
                            $this->keyboard($conference->uuid)
                        );
                    }

                    $conference->update([
                        'notification_sent' => 1
                    ]);
                }
            });

        return Command::SUCCESS;
    }

    protected function message($conference, $member): string
    {
        $selected_language = Language::find($member->lang_id);

        if(isset($selected_language)){
            app()->setLocale($selected_language->lang_tag);
        }

        return trans('app.bot.conference.reminder.now', [
            'name' => $member->first_name,
            'time' => env('CONFERENCE_BEFORE_MINUTES')
        ]);
    }

    protected function keyboard(string $uuid): array
    {
        return [
            [
                [
                    'text' => trans('app.bot.conference.link'),
                    'url' => env('FRONTEND_URL')."/dashboard/conference/".$uuid
                ],
            ],
        ];
    }
}
