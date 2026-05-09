<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService
{
    private string $botToken;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $telegramBotToken // Injected from services.yaml
    ) {
        $this->botToken = $telegramBotToken;
    }

    public function sendLoginRequest(string $chatId, string $token): void
    {
        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Accepter', 'callback_data' => 'login_accept_' . $token],
                    ['text' => '❌ Refuser', 'callback_data' => 'login_refuse_' . $token]
                ]
            ]
        ];

        try {
            $this->httpClient->request('POST', $url, [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => "🔔 Nouvelle tentative de connexion détectée.\n\nVoulez-vous autoriser cette connexion ?",
                    'reply_markup' => $keyboard
                ]
            ]);
        } catch (\Exception $e) {
            // Log error
        }
    }

    public function sendMessage(int $chatId, string $text): void
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        try {
            $this->httpClient->request('POST', $url, [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text
                ]
            ]);
        } catch (\Exception $e) {
            // Log error
        }
    }
}
