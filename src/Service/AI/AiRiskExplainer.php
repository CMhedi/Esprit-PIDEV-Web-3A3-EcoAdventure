<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiRiskExplainer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
        private readonly string $model = 'gpt-4o-mini',
    ) {
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function summarizeDashboard(array $snapshot): string
    {
        $fallback = $this->buildFallbackSummary($snapshot);
        $payload = $this->normalizeSnapshot($snapshot);

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
                    'temperature' => 0.2,
                    'input' => [[
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'Tu es un analyste risque pour un back-office Symfony. Resume en francais, maximum 90 mots, avec un ton professionnel. Donne la situation globale et 2 actions concretes.',
                        ]],
                    ], [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => json_encode($payload, JSON_THROW_ON_ERROR),
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
     * @param array<string, mixed> $snapshot
     */
    private function buildFallbackSummary(array $snapshot): string
    {
        $topPack = $snapshot['top_risky_packs'][0] ?? null;
        $topInscription = $snapshot['top_risky_inscriptions'][0] ?? null;

        return sprintf(
            'Le risque global est de %s/100. %d pack(s) sont critiques et %d inscription(s) demandent une priorisation immediate. Le pack le plus expose est %s. Le dossier le plus sensible concerne %s. Action attendue: %s.',
            number_format((float) ($snapshot['global_average'] ?? 0.0), 1, '.', ' '),
            (int) ($snapshot['critical_packs'] ?? 0),
            (int) ($snapshot['priority_inscriptions'] ?? 0),
            $topPack ? $topPack->getPack()->getNom() : 'aucun pack',
            $topInscription
                ? ($topInscription->getInscription()->getNomPack() ?: ($topInscription->getInscription()->getPack()?->getNom() ?? 'aucune inscription'))
                : 'aucune inscription',
            $snapshot['recommended_actions'][0] ?? 'surveiller le portefeuille pack'
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function normalizeSnapshot(array $snapshot): array
    {
        return [
            'pack_average' => $snapshot['pack_average'] ?? 0.0,
            'inscription_average' => $snapshot['inscription_average'] ?? 0.0,
            'global_average' => $snapshot['global_average'] ?? 0.0,
            'critical_packs' => $snapshot['critical_packs'] ?? 0,
            'priority_inscriptions' => $snapshot['priority_inscriptions'] ?? 0,
            'pack_distribution' => $snapshot['pack_distribution'] ?? [],
            'inscription_distribution' => $snapshot['inscription_distribution'] ?? [],
            'top_risky_packs' => array_map(
                static fn ($view): array => [
                    'name' => $view->getPack()->getNom(),
                    'risk_score' => $view->getRiskScore(),
                    'risk_level' => $view->getRiskLevel(),
                    'profile' => $view->getRiskProfile(),
                ],
                array_slice($snapshot['top_risky_packs'] ?? [], 0, 3)
            ),
            'top_risky_inscriptions' => array_map(
                static fn ($view): array => [
                    'user' => $view->getInscription()->getNomUser() ?: ($view->getInscription()->getUserApp()?->getEmail() ?? 'Utilisateur'),
                    'pack' => $view->getInscription()->getNomPack() ?: ($view->getInscription()->getPack()?->getNom() ?? 'Pack'),
                    'risk_score' => $view->getRiskScore(),
                    'risk_level' => $view->getRiskLevel(),
                ],
                array_slice($snapshot['top_risky_inscriptions'] ?? [], 0, 3)
            ),
            'recommended_actions' => $snapshot['recommended_actions'] ?? [],
        ];
    }
}
