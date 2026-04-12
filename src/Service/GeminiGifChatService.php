<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiGifChatService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
        private readonly string $groqModel,
        private readonly string $giphyApiKey
    ) {
    }

    public function generateReply(string $prompt): string
    {
        if ($this->groqApiKey === '') {
            return 'La cle Groq API est manquante. Ajoutez GROQ_API_KEY dans votre .env.local.';
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Tu es un assistant de conversation utile. Reponds clairement en francais. Limite stricte: moins de 1000 caracteres.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        $result = $this->callGroqChat($messages);
        if ($result !== null) {
            return $this->limitTo1000($result);
        }

        return 'Le service Groq est temporairement indisponible.';
    }

    public function generateConversationSummary(string $conversationText): string
    {
        if (trim($conversationText) === '') {
            return 'Aucun message a resumer dans cette conversation.';
        }
        if ($this->groqApiKey === '') {
            return 'La cle Groq API est manquante. Ajoutez GROQ_API_KEY dans votre .env.local.';
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Tu resumes des conversations. Donne: 1) resume court, 2) points cles en puces, 3) actions a faire. Reponse < 1000 caracteres.',
            ],
            [
                'role' => 'user',
                'content' => "Resume cette conversation:\n\n" . $conversationText,
            ],
        ];

        $result = $this->callGroqChat($messages);
        return $result !== null
            ? $this->limitTo1000($result)
            : 'Impossible de generer le resume pour le moment.';
    }

    public function generateLongMessage(string $instruction): string
    {
        $prompt = trim($instruction) !== ''
            ? $instruction
            : 'Ecris un message professionnel, poli et clair pour demander une mise a jour du dossier.';

        $result = $this->generateReply('Redige un long message pret a envoyer sur ce sujet: ' . $prompt);
        return $this->limitTo1000($result);
    }

    /**
     * @param array<int, array{role:string, content:string}> $messages
     */
    private function callGroqChat(array $messages): ?string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->groqModel,
                    'messages' => $messages,
                    'temperature' => 0.4,
                ],
                'timeout' => 25,
            ]);

            $data = $response->toArray(false);
            $text = $data['choices'][0]['message']['content'] ?? null;
            if (!is_string($text) || trim($text) === '') {
                return null;
            }

            return trim($text);
        } catch (\Throwable) {
            return null;
        }
    }

    private function limitTo1000(string $text): string
    {
        $trimmed = trim($text);
        if (mb_strlen($trimmed) <= 1000) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, 997)) . '...';
    }

    public function searchGifUrl(string $query): ?string
    {
        if ($this->giphyApiKey === '' || trim($query) === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.giphy.com/v1/gifs/search', [
                'query' => [
                    'api_key' => $this->giphyApiKey,
                    'q' => $query,
                    'limit' => 1,
                    'rating' => 'pg',
                    'lang' => 'en',
                ],
                'timeout' => 12,
            ]);

            $data = $response->toArray(false);
            return $data['data'][0]['images']['original']['url'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    public function searchGifUrls(string $query, int $limit = 12): array
    {
        if ($this->giphyApiKey === '') {
            return [];
        }

        $safeLimit = max(1, min($limit, 25));

        try {
            $response = $this->httpClient->request('GET', 'https://api.giphy.com/v1/gifs/search', [
                'query' => [
                    'api_key' => $this->giphyApiKey,
                    'q' => trim($query) !== '' ? $query : 'funny',
                    'limit' => $safeLimit,
                    'rating' => 'pg',
                    'lang' => 'en',
                ],
                'timeout' => 12,
            ]);

            $data = $response->toArray(false);
            $items = $data['data'] ?? [];
            $urls = [];
            foreach ($items as $item) {
                $url = $item['images']['fixed_width']['url'] ?? $item['images']['original']['url'] ?? null;
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }

            return $urls;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{path:string,name:string,mime:string,type:string}|null
     */
    public function downloadGifToLocal(string $gifUrl, string $baseUploadDir): ?array
    {
        if (!filter_var($gifUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $gifDir = rtrim($baseUploadDir, '/\\') . DIRECTORY_SEPARATOR . 'Gifs';
        if (!is_dir($gifDir) && !@mkdir($gifDir, 0775, true) && !is_dir($gifDir)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $gifUrl, ['timeout' => 20]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $contentType = strtolower((string) ($response->getHeaders(false)['content-type'][0] ?? 'image/gif'));
            $isGif = str_contains($contentType, 'gif') || str_ends_with(strtolower(parse_url($gifUrl, PHP_URL_PATH) ?? ''), '.gif');
            if (!$isGif) {
                return null;
            }

            $binary = $response->getContent();
            if ($binary === '') {
                return null;
            }

            $filename = 'gif_' . uniqid('', true) . '.gif';
            $target = $gifDir . DIRECTORY_SEPARATOR . $filename;
            if (@file_put_contents($target, $binary) === false) {
                return null;
            }

            return [
                'path' => '/uploads/Gifs/' . $filename,
                'name' => $filename,
                'mime' => 'image/gif',
                'type' => 'GIF',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
