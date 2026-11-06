<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chatId;
    protected $message;

    /**
     * Количество попыток выполнения задачи
     */
    public function __construct(string $chatId, string $message) {
        $this->chatId = $chatId;
        $this->message = $message;
    }

    public function handle(): void
    {
        // 1. Имитация человеческой паузы перед отправкой (от 3 до 8 секунд)
        sleep(rand(3, 8));

        $instanceId = config('services.greenapi.instance_id');
        $apiToken = config('services.greenapi.api_token');
        $apiUrl = config('services.greenapi.api_url');

        $response = Http::post("{$apiUrl}/waInstance{$instanceId}/sendMessage/{$apiToken}", [
            'chatId' => $this->chatId,
            'message' => $this->message,
            'typingTime' => rand(1000, 4000), // Имитация набора текста (1-3 сек)
            'linkPreview' => false,
        ]);

        if ($response->failed()) {
            Log::error("Ошибка отправки WhatsApp для {$this->chatId}", [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            // Если получил ошибку 429 (Too Many Requests), возвращаем задачу в очередь
            if ($response->status() === 429) {
                $this->release(60); // Повторить через 60 секунд
            }
        }
    }
}