<?php
namespace App\Services;

use Twilio\Rest\Client;

class TwilioWhatsAppService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
    }

    public function sendMessage($template_name, $params, $phone, $language = 'ru')
    {
        try {
            $to = preg_replace('/[^\d+]/', '', $phone);

            return $this->twilio->messages->create(
                "whatsapp:$to", [
                    'from' => env('TWILIO_WHATSAPP_NUMBER'),
                    'template' => $template_name,
                    'templateLanguage' => $language, // Указываем язык шаблона
                    'templateParameters' => $params
                ]
            );
        } catch (\Twilio\Exceptions\RestException $e) {
            \Log::error('Ошибка Twilio: ' . $e->getMessage());
            return false;
        }
    }
}
?>