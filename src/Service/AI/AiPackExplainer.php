<?php

namespace App\Service\AI;

use App\Dto\PackInsightView;
use App\Entity\UserApp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiPackExplainer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
        private readonly string $model = 'gpt-4o-mini',
    ) {
    }

    /**
     * @param array<string, mixed> $holidayContext
     */
    public function explainChoice(
        PackInsightView $insight,
        ?UserApp $user = null,
        ?PackInsightView $alternative = null,
        array $holidayContext = [],
    ): string {
        $fallback = $this->buildFallbackExplanation($insight, $user, $alternative, $holidayContext);

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
                    'temperature' => 0.4,
                    'input' => [[
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'Rédige une explication business courte, précise et crédible pour un choix de pack EcoAdventure. Maximum 90 mots.',
                        ]],
                    ], [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $this->buildPrompt($insight, $user, $alternative, $holidayContext),
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
     * @param array<string, mixed> $holidayContext
     */
    private function buildPrompt(
        PackInsightView $insight,
        ?UserApp $user,
        ?PackInsightView $alternative,
        array $holidayContext,
    ): string {
        $metrics = $insight->getMetrics();
        $parts = [
            'Pack: ' . $insight->getPack()->getNom(),
            'Type: ' . $insight->getPack()->getTypePack(),
            'Score: ' . round($insight->getScore(), 1) . '/100',
            'Badges: ' . implode(', ', $insight->getBadges()),
            'Raisons: ' . implode(' | ', $insight->getReasons()),
            'Prix final: ' . ($metrics['final_price'] ?? 0) . ' DT',
            'Inscriptions: ' . ($metrics['inscriptions_total'] ?? 0),
            'Vues récentes: ' . ($metrics['views_30d'] ?? 0),
        ];

        if ($user instanceof UserApp) {
            $parts[] = 'Utilisateur: ' . trim((string) $user->getPrenom() . ' ' . (string) $user->getNom());
        }

        if ($alternative instanceof PackInsightView) {
            $parts[] = 'Alternative: ' . $alternative->getPack()->getNom() . ' (' . round($alternative->getRecommendationScore(), 1) . ')';
        }

        if (($holidayContext['available'] ?? false) === true) {
            $parts[] = 'Contexte jour férié: ' . $holidayContext['message'];
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string, mixed> $holidayContext
     */
    private function buildFallbackExplanation(
        PackInsightView $insight,
        ?UserApp $user,
        ?PackInsightView $alternative,
        array $holidayContext,
    ): string {
        $bits = [];
        $name = $insight->getPack()->getNom() ?? 'ce pack';
        $bits[] = sprintf(
            '%s ressort avec un score de %.1f/100 grâce à %s.',
            $name,
            $insight->getScore(),
            strtolower(implode(', ', array_slice($insight->getReasons(), 0, 2)))
        );

        if ($user instanceof UserApp && $user->getInscriptions()->count() > 0) {
            $bits[] = 'Le moteur tient compte de votre historique d’inscriptions pour éviter une recommandation purement générique.';
        }

        if (($holidayContext['available'] ?? false) === true && isset($holidayContext['message'])) {
            $bits[] = (string) $holidayContext['message'];
        }

        if ($alternative instanceof PackInsightView) {
            $bits[] = sprintf(
                'Si vous cherchez une variante plus pertinente, %s constitue l’alternative principale.',
                $alternative->getPack()->getNom()
            );
        }

        return implode(' ', $bits);
    }
}
