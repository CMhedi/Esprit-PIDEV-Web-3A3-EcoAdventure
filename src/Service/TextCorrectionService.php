<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TextCorrectionService
{
    private const DEFAULT_ENDPOINT = 'https://api.languagetool.org/v2/check';

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @return array{success:bool, corrected_text:string, changed:bool, matches:int, message?:string}
     */
    public function correctFrenchText(string $text): array
    {
        $normalized = trim($text);
        if ($normalized === '') {
            return [
                'success' => false,
                'corrected_text' => '',
                'changed' => false,
                'matches' => 0,
                'message' => 'Le texte est vide.',
            ];
        }

        try {
            $response = $this->httpClient->request('POST', self::DEFAULT_ENDPOINT, [
                'body' => [
                    'text' => $normalized,
                    'language' => 'fr',
                ],
                'timeout' => 12,
            ]);

            $data = $response->toArray(false);
            $matches = is_array($data['matches'] ?? null) ? $data['matches'] : [];
            $correctedText = $this->applyMatches($normalized, $matches);

            return [
                'success' => true,
                'corrected_text' => $correctedText,
                'changed' => $correctedText !== $normalized,
                'matches' => count($matches),
            ];
        } catch (\Throwable) {
            return [
                'success' => false,
                'corrected_text' => $normalized,
                'changed' => false,
                'matches' => 0,
                'message' => 'Le service de correction est temporairement indisponible.',
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $matches
     */
    private function applyMatches(string $text, array $matches): string
    {
        if ($matches === []) {
            return $text;
        }

        usort($matches, static function (array $left, array $right): int {
            return ((int) ($right['offset'] ?? 0)) <=> ((int) ($left['offset'] ?? 0));
        });

        $corrected = $text;
        foreach ($matches as $match) {
            $offset = (int) ($match['offset'] ?? -1);
            $length = (int) ($match['length'] ?? 0);
            $replacement = $match['replacements'][0]['value'] ?? null;

            if ($offset < 0 || $length < 0 || !is_string($replacement) || $replacement === '') {
                continue;
            }

            $corrected = mb_substr($corrected, 0, $offset)
                . $replacement
                . mb_substr($corrected, $offset + $length);
        }

        return $corrected;
    }
}
