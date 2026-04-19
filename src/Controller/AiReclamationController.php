<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiReclamationController extends AbstractController
{
    private $client;
    private $geminiApiKey;

    public function __construct(HttpClientInterface $client, string $geminiApiKey = null)
    {
        $this->client = $client;
        $this->geminiApiKey = $geminiApiKey;
    }

    #[Route('/api/reclamation/enhance', name: 'api_reclamation_enhance', methods: ['POST'])]
    public function enhanceText(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $originalText = $data['text'] ?? '';

        if (empty($originalText)) {
            return new JsonResponse(['error' => 'Texte vide'], 400);
        }

        $apiKey = $this->geminiApiKey;

        if (!$apiKey) {
            // Si pas de clé API configurée, on retourne une erreur spécifique 
            // pour que le Frontend utilise la simulation locale.
            return new JsonResponse(['error' => 'API_KEY_MISSING'], 503);
        }

        $systemPrompt = "Rôle : Tu es un expert en communication administrative et traducteur assermenté.
Mission : Ta tâche est de transformer des brouillons de réclamations clients en lettres formelles, professionnelles et structurées en Français.
Instructions cruciales :
- Détection de langue : Identifie si l'entrée est en Français, en Arabe classique ou en Darija tunisien (même écrit en caractères latins/franco-arabe comme '3andi', 'mchekel', 'tawa').
- Traduction & Style : Traduis et reformule le contenu pour qu'il respecte les codes de la correspondance administrative française (Vouvoiement, ton sérieux, clarté).
- Structure de la réponse : La lettre doit impérativement inclure : Une formule d'appel (Madame, Monsieur,), l'exposition claire du problème, une demande de solution ou d'intervention, une formule de politesse standard (Cordialement, ou Veuillez agréer...).
- Conservation des données : Garde précieusement les noms, les dates, les montants ou les références techniques mentionnés dans le brouillon.
- Format : Retourne uniquement le texte final de la lettre. Ne commence pas ta réponse par 'Voici la lettre' ou 'D'accord'.";

        try {
            $response = $this->client->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey, [
                'verify_peer' => false,
                'verify_host' => false,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt . "\n\nTexte du client à transformer :\n" . $originalText]
                            ]
                        ]
                    ]
                ]
            ]);

            $result = $response->toArray();
            
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $enhancedText = $result['candidates'][0]['content']['parts'][0]['text'];
                return new JsonResponse(['enhancedText' => trim($enhancedText)]);
            }

            return new JsonResponse(['error' => 'Erreur lors de la génération'], 500);
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
