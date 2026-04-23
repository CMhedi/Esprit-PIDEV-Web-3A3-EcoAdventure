<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqAiService
{
    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(HttpClientInterface $client, string $apiKey = '')
    {
        $this->client = $client;

        // Configure GROQ_API_KEY in .env.local.
        $this->apiKey = trim($apiKey);
    }

    // =========================
    // 🤖 BASE AI CALL
    // =========================
    public function ask(string $prompt): string
    {
        if ($this->apiKey === '') {
            return "La cle Groq API est manquante. Ajoutez GROQ_API_KEY dans votre .env.local.";
        }

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
