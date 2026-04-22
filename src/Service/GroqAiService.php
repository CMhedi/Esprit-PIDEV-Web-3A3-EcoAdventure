<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqAiService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;

        // 🔥 TA CLÉ GROQ ICI
        $this->apiKey = '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';
    }

    // =========================
    // 🤖 BASE AI CALL
    // =========================
    public function ask(string $prompt): string
    {
        try {
            $response = $this->client->request(
                'POST',
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'model' => 'llama-3.1-8b-instant',
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'temperature' => 0.7
                    ]
                ]
            );

            $data = $response->toArray(false);

            return $data['choices'][0]['message']['content']
                ?? "Aucune réponse IA";

        } catch (\Throwable $e) {
            return "Erreur IA: " . $e->getMessage();
        }
    }

    // =========================
    // 📊 ANALYSE ACTIVITÉS
    // =========================
    public function analyzeActivities(array $data): string
    {
        if (empty($data)) {
            return "Aucune donnée disponible pour analyse.";
        }

        $prompt = "Analyse les activités suivantes et donne des recommandations business:\n";

        foreach ($data as $a) {
            $prompt .= "- {$a['nom']} : {$a['total']} réservations\n";
        }

        $prompt .= "\nDonne une analyse claire et actionnable.";

        return $this->ask($prompt);
    }

    // =========================
    // 📈 PRÉDICTION
    // =========================
    public function predict(array $data): string
    {
        if (empty($data)) {
            return "📊 Pas assez de données pour prédire.";
        }

        $prompt = "Voici les activités et leurs réservations:\n";

        foreach ($data as $a) {
            $prompt .= "- {$a['nom']} : {$a['total']}\n";
        }

        $prompt .= "\nPrédit les tendances (augmentation, stabilité ou baisse).";

        return $this->ask($prompt);
    }
}