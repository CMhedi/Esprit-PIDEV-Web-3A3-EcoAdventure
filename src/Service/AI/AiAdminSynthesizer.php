<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiAdminSynthesizer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
        private readonly string $model = 'gpt-4o-mini',
    ) {
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function summarizeInscriptions(array $summary): string
    {
        $fallback = $this->buildFallbackSummary($summary);

        if ($this->apiKey === '') {
            return $fallback;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0.3,
                    'input' => [[
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'Rédige une synthèse analytique admin en français. Maximum 100 mots. Ton professionnel, concret, sans jargon inutile.',
                        ]],
                    ], [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => json_encode($summary, JSON_THROW_ON_ERROR),
                        ]],
                    ]],
                ],
            ]);

            $payload = $response->toArray(false);
            $text = trim((string) ($payload['output'][0]['content'][0]['text'] ?? ''));

            return $text !== '' ? $text : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function buildFallbackSummary(array $summary): string
    {
        return sprintf(
            '%d inscriptions dont %d en attente. Le chiffre d’affaires théorique atteint %s DT. Le pack le plus moteur est %s et %d dossier(s) demandent un traitement prioritaire.',
            (int) ($summary['total'] ?? 0),
            (int) ($summary['pending_count'] ?? 0),
            number_format((float) ($summary['revenue_total'] ?? 0), 2, '.', ' '),
            (string) ($summary['top_pack'] ?? 'aucun pack dominant'),
            (int) ($summary['high_priority_count'] ?? 0)
        );
    }
}
