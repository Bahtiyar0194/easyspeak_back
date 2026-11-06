<?php 

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Jobs\SendWhatsAppMessage;

class GreenApiService
{
    protected $instanceId;
    protected $apiToken;
    protected $baseUrl;

    public function __construct()
    {
        $this->instanceId = config('services.greenapi.instance_id');
        $this->apiToken = config('services.greenapi.api_token');
        $this->baseUrl = config('services.greenapi.api_url')."/waInstance{$this->instanceId}";
    }

    /**
     * Проверяет наличие WhatsApp на номере телефона.
     * 
     * @param string $phoneNumber Номер в международном формате (например, "77001112233" или "+7 (700) 111-22-33")
     * @return string|null Возвращает chatId (e.g. "77001112233@c.us"), если WhatsApp есть, иначе null
     */
    public function checkWhatsapp(string $phoneNumber): ?string
    {
        // 1. Очищаем номер от лишних символов (плюсы, скобки, пробелы, дефисы)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (empty($cleanPhone)) {
            return null;
        }

        $url = "{$this->baseUrl}/checkWhatsapp/{$this->apiToken}";

        try {
            $response = Http::timeout(10)->post($url, [
                'phoneNumber' => (int) $cleanPhone,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Green API возвращает boolean 'existsWhatsapp'
                if (!empty($data['existsWhatsapp'])) {
                    // Возвращаем валидный chatId для отправки сообщений
                    return $data['chatId'] ?? "{$cleanPhone}@c.us";
                }
            } else {
                Log::warning("Не удалось проверить WhatsApp для номера {$cleanPhone}", [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Исключение при проверке WhatsApp для {$cleanPhone}: " . $e->getMessage());
        }

        return null;
    }

    public function sendMessage($phone, $message)
    {
        // 1. Проверяем наличие WhatsApp
        $chatId = $this->checkWhatsapp($phone);

        if (!$chatId) {
            return response()->json([
                'success' => false,
                'message' => 'Номер не зарегистрирован в WhatsApp или некорректен.',
            ], 422);
        }

        // 2. Если номер существует, отправляем задачу в очередь
        SendWhatsAppMessage::dispatch($chatId, $message);

        return response()->json([
            'success' => true,
            'message' => 'Сообщение поставлено в очередь на отправку.',
            'chatId' => $chatId,
        ]);
    }
}