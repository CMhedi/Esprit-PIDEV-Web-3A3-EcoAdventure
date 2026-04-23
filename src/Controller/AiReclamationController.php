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
        $this->geminiApiKey = $geminiApiKey ? trim($geminiApiKey) : null;
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

        $systemPrompt = "INSTRUCTION CRITIQUE : Tu es un traducteur de haut niveau. 
Ta tâche est de prendre le texte suivant (souvent en Arabe ou Darija Tunisien) et de le TRADUIRE et RÉDIGER en une lettre de réclamation FORMELLE, POLIE et PROFESSIONNELLE en FRANÇAIS.

SORTIE ATTENDUE (JSON UNIQUEMENT) :
{
  \"enhancedText\": \"(La lettre complète et bien rédigée en FRANÇAIS)\",
  \"sentiment\": \"COLERE/NEUTRE/POSITIF\",
  \"category\": \"Mot de passe/Séance/Technique/Paiement/Autre\",
  \"urgency\": \"HAUTE/MOYENNE/BASSE\",
  \"shortDescription\": \"(Bref résumé en 3-5 mots en Français)\"
}

REMARQUE : Même si le texte original est une seule phrase, transforme-la en un paragraphe structuré et formel en Français.";

        try {
            // Utilisation de gemini-2.0-flash (v1beta) - Seul modèle qui répond sans 404
            $response = $this->client->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey, [


                'verify_peer' => false,
                'verify_host' => false,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt . "\n\nTexte du client à analyser :\n" . $originalText]
                            ]
                        ]
                    ]
                ]
            ]);


            $result = $response->toArray();
            
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $rawText = $result['candidates'][0]['content']['parts'][0]['text'];
                
                // Nettoyage des backticks markdown si présents
                $cleanJson = preg_replace('/^```json\s*|```$/m', '', $rawText);
                $analysis = json_decode(trim($cleanJson), true);


                if ($analysis && isset($analysis['enhancedText'])) {
                    return new JsonResponse($analysis);
                }

                return new JsonResponse([
                    'enhancedText' => trim($rawText),
                    'sentiment' => 'NEUTRE',
                    'category' => 'Autre',
                    'urgency' => 'BASSE',
                    'shortDescription' => 'Analyse simplifiée'
                ]);
            }

            return new JsonResponse(['error' => 'Réponse vide de l\'IA'], 500);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $statusCode = 500;
            
            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);
                
                if ($statusCode === 429) {
                    // Simulation d'analyse pour ne pas bloquer l'utilisateur quand le quota est atteint
                    return new JsonResponse([
                        'enhancedText' => $originalText,
                        'sentiment' => 'NEUTRE',
                        'category' => 'Autre',
                        'urgency' => 'MOYENNE',
                        'shortDescription' => 'Quota IA atteint',
                        'isFallback' => true
                    ]);
                }
                
                $errorMessage = "API Error: " . $content;
            }
            
            return new JsonResponse([
                'error' => $errorMessage,
                'details' => 'Problème lors de la communication avec l\'IA (Code: ' . $statusCode . ').'
            ], $statusCode);
        }



    }
}
