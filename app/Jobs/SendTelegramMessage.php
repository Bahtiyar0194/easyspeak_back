<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chatId;
    protected $text;
    protected $keyboard;

    public function __construct(int $chatId, string $text, ?array $keyboard = null)
    {
        $this->chatId = $chatId;
        $this->text = $text;
        $this->keyboard = $keyboard;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $bot = new BotApi(config('services.telegram.token'));

        $replyMarkup = null;

        if ($this->keyboard) {
            $replyMarkup = new InlineKeyboardMarkup($this->keyboard);
        }

        $bot->sendMessage(
            $this->chatId,
            $this->text,
            null,
            false,
            null,
            $replyMarkup
        );
    }
}
