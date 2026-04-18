<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiGifChatService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
        private readonly string $groqModel,
        private readonly string $giphyApiKey,
        private readonly string $groqVisionModel,
        private readonly string $groqAudioModel
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
     * @param array<int, array<string, mixed>> $attachments
     */
    public function generateReplyForAttachments(string $prompt, array $attachments, string $baseUploadDir): string
    {
        if ($this->groqApiKey === '') {
            return 'La cle Groq API est manquante. Ajoutez GROQ_API_KEY dans votre .env.local.';
        }

        $normalizedPrompt = trim($prompt);
        $descriptions = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $description = $this->describeAttachment($attachment, $baseUploadDir, $normalizedPrompt);
            if ($description !== '') {
                $descriptions[] = $description;
            }
        }

        if ($descriptions === []) {
            return $this->generateReply($normalizedPrompt !== ''
                ? $normalizedPrompt
                : 'Je ne trouve pas de contenu exploitable dans la piece jointe.');
        }

        $userInstruction = $normalizedPrompt !== ''
            ? $normalizedPrompt
            : 'Decris clairement les pieces jointes en francais simple.';

        $finalPrompt = $userInstruction
            . "\n\nAnalyse des pieces jointes:\n"
            . implode("\n\n", $descriptions)
            . "\n\nDonne une reponse utile, concrete et courte.";

        return $this->generateReply($finalPrompt);
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

    /**
     * @param array<string, mixed> $attachment
     */
    private function describeAttachment(array $attachment, string $baseUploadDir, string $prompt): string
    {
        $path = (string) ($attachment['path'] ?? '');
        $name = (string) ($attachment['name'] ?? 'fichier');
        $mime = strtolower((string) ($attachment['mime'] ?? 'application/octet-stream'));
        $type = strtoupper((string) ($attachment['type'] ?? ''));

        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return sprintf('Fichier "%s" (%s): analyse locale impossible.', $name, $mime);
        }

        $absolutePath = $this->resolveLocalUploadPath($path, $baseUploadDir);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return sprintf('Fichier "%s" (%s): introuvable sur le serveur.', $name, $mime);
        }

        if (str_starts_with($mime, 'audio/') || $type === 'VOCALE' || $type === 'AUDIO') {
            $transcription = $this->transcribeAudioFile($absolutePath);
            if ($transcription === null || trim($transcription) === '') {
                return sprintf('Vocal "%s": transcription indisponible.', $name);
            }

            return sprintf('Vocal "%s" transcrit: %s', $name, $this->limitTo1000($transcription));
        }

        if (str_starts_with($mime, 'image/')) {
            $vision = $this->describeImageFile($absolutePath, $mime, $prompt);
            if ($vision === null || trim($vision) === '') {
                return sprintf('Image "%s": description indisponible.', $name);
            }

            return sprintf('Image "%s": %s', $name, $this->limitTo1000($vision));
        }

        if ($mime === 'application/pdf' || str_ends_with(strtolower($name), '.pdf') || $type === 'PDF') {
            $snippet = $this->extractPdfTextSnippet($absolutePath, 5000);
            if ($snippet === '') {
                return sprintf('PDF "%s": aucun texte extractible detecte.', $name);
            }

            $summary = $this->summarizeDocumentSnippet('PDF', $name, $snippet, $prompt);
            return sprintf('PDF "%s": %s', $name, $this->limitTo1000($summary));
        }

        if ($this->isTextLikeFile($mime, $name)) {
            $snippet = $this->extractTextFileSnippet($absolutePath, 5000);
            if ($snippet === '') {
                return sprintf('Fichier "%s": aucun texte lisible detecte.', $name);
            }

            $summary = $this->summarizeDocumentSnippet('FICHIER', $name, $snippet, $prompt);
            return sprintf('Fichier "%s": %s', $name, $this->limitTo1000($summary));
        }

        $size = @filesize($absolutePath);
        $sizeLabel = is_int($size) ? $size . ' octets' : 'taille inconnue';
        return sprintf('Fichier "%s": type %s, %s. Description detaillee non supportee pour ce format.', $name, $mime, $sizeLabel);
    }

    private function resolveLocalUploadPath(string $path, string $baseUploadDir): ?string
    {
        $normalizedBase = rtrim(str_replace('\\', '/', $baseUploadDir), '/');
        if ($normalizedBase === '') {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $relative = str_starts_with($normalizedPath, '/uploads/')
            ? ltrim(substr($normalizedPath, 9), '/')
            : ltrim($normalizedPath, '/');

        if ($relative === '') {
            return null;
        }

        return $normalizedBase . '/' . $relative;
    }

    private function transcribeAudioFile(string $absolutePath): ?string
    {
        if ($this->groqApiKey === '') {
            return null;
        }

        try {
            $stream = @fopen($absolutePath, 'r');
            if ($stream === false) {
                return null;
            }

            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/audio/transcriptions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                ],
                'body' => [
                    'model' => $this->groqAudioModel !== '' ? $this->groqAudioModel : 'whisper-large-v3',
                    'language' => 'fr',
                    'file' => $stream,
                ],
                'timeout' => 45,
            ]);

            $data = $response->toArray(false);
            $text = $data['text'] ?? null;

            return is_string($text) ? trim($text) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function describeImageFile(string $absolutePath, string $mimeType, string $prompt): ?string
    {
        if ($this->groqApiKey === '') {
            return null;
        }

        $imageSize = @filesize($absolutePath);
        if (is_int($imageSize) && $imageSize > 4 * 1024 * 1024) {
            return 'Image trop lourde pour analyse automatique (max environ 4MB).';
        }

        $binary = @file_get_contents($absolutePath);
        if (!is_string($binary) || $binary === '') {
            return null;
        }

        $base64 = base64_encode($binary);
        $userPrompt = trim($prompt) !== ''
            ? $prompt
            : 'Decris cette image en francais avec les elements importants.';

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->groqVisionModel !== '' ? $this->groqVisionModel : 'llama-3.2-11b-vision-preview',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu decris des images en francais. Sois clair et utile.',
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $userPrompt,
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => 'data:' . ($mimeType !== '' ? $mimeType : 'image/jpeg') . ';base64,' . $base64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'temperature' => 0.2,
                ],
                'timeout' => 35,
            ]);

            $data = $response->toArray(false);
            $text = $data['choices'][0]['message']['content'] ?? null;

            return is_string($text) ? trim($text) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function summarizeDocumentSnippet(string $kind, string $name, string $snippet, string $prompt): string
    {
        $baseInstruction = trim($prompt) !== ''
            ? $prompt
            : 'Donne une description claire du document.';

        $summaryPrompt = $baseInstruction
            . "\n\nType: {$kind}\nNom: {$name}\n\n"
            . "Extrait texte du document:\n"
            . $snippet
            . "\n\nResume le contenu important en francais.";

        return $this->generateReply($summaryPrompt);
    }

    private function extractPdfTextSnippet(string $absolutePath, int $maxChars = 5000): string
    {
        $raw = @file_get_contents($absolutePath, false, null, 0, 1_500_000);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $matches = [];
        preg_match_all('/\(([^\)]{2,})\)/', $raw, $matches);
        $chunks = $matches[1] ?? [];
        if (!is_array($chunks) || $chunks === []) {
            return '';
        }

        $text = implode(' ', array_slice($chunks, 0, 300));
        $text = str_replace(['\\n', '\\r', '\\t'], ' ', $text);
        $text = preg_replace('/[^\x20-\x7E]+/', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return $text === '' ? '' : mb_substr($text, 0, $maxChars);
    }

    private function extractTextFileSnippet(string $absolutePath, int $maxChars = 5000): string
    {
        $raw = @file_get_contents($absolutePath, false, null, 0, 500_000);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $raw = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]+/', ' ', $raw) ?? '';
        $normalized = trim(preg_replace('/\s+/', ' ', $raw) ?? '');

        return $normalized === '' ? '' : mb_substr($normalized, 0, $maxChars);
    }

    private function isTextLikeFile(string $mime, string $name): bool
    {
        if (str_starts_with($mime, 'text/')) {
            return true;
        }

        $lower = strtolower($name);
        foreach (['.txt', '.md', '.csv', '.json', '.xml', '.log'] as $extension) {
            if (str_ends_with($lower, $extension)) {
                return true;
            }
        }

        return false;
    }
}
