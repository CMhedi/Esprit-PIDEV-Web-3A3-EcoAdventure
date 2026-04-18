<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class AiEventOptimizerService
{
    private $client;
    private $logger;
    private $evenementRepository;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger, EvenementRepository $evenementRepository)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->evenementRepository = $evenementRepository;
    }

    /**
     * Cas 2 : L'Expansion Dynamique de la Liste d'Attente
     */
    public function optimizeWaitlistLimit(Evenement $evenement): int
    {
        try {
            $response = $this->client->request('POST', 'http://127.0.0.1:5000/api/ai/predict-waitlist-limit', [
                'json' => [
                    'categorie' => $evenement->getCategorie_evt()->value,
                    'places_total' => $evenement->getNb_places(),
                    'cancellation_rate' => 0.05
                ],
                'timeout' => 2 
            ]);

            $data = $response->toArray();
            return $data['suggested_limit'];

        } catch (\Exception $e) {
            $this->logger->warning('API Python IA injoignable pour la limite d\'attente. Fallback Mocked.');
            
            if ($evenement->getCategorie_evt()->value === 'NAUTIQUE') {
                return 2; 
            }
            return (int)($evenement->getNb_places() * 0.4); 
        }
    }

    /**
     * Cas 3 : Moteur de Recommandations Préventives
     */
    public function getAiRecommendationMessage(Evenement $evenement, int $waitlistPosition): ?array
    {
        try {
            $response = $this->client->request('POST', 'http://127.0.0.1:5000/api/ai/recommend-alternative', [
                'json' => [
                    'categorie_cible' => $evenement->getCategorie_evt()->value,
                    'waitlist_position' => $waitlistPosition,
                ],
                'timeout' => 2
            ]);

            $data = $response->toArray();
            if ($data['trigger_recommendation']) {
                $rec = $this->getSimilarAvailableEvent($evenement);
                if ($rec) {
                    return [
                        'message' => $data['ai_message'],
                        'event' => $rec
                    ];
                }
            }
            return null;

        } catch (\Exception $e) {
            $this->logger->warning('API Python IA injoignable pour les recommandations. Fallback Mocked.');
            
            if ($waitlistPosition >= 5) {
                $rec = $this->getSimilarAvailableEvent($evenement);
                if ($rec) {
                    return [
                        'message' => "🌟 L'IA ECOADVENTURE vous informe : Vos chances sont faibles d'avoir une place ici. Nous vous recommandons cet événement très similaire :",
                        'event' => $rec
                    ];
                }
            }
            return null;
        }
    }

    /**
     * Recherche d'un événement similaire disponible (Même Catégorie, places > 0, exclure l'actuel).
     */
    public function getSimilarAvailableEvent(Evenement $evenement): ?Evenement
    {
        $similarEvents = $this->evenementRepository->findByFilters(
            null, 
            $evenement->getCategorie_evt()->value, 
            null, 
            'date_asc', 
            true // ONLY AVAILABLE ! (Places restantes > 0)
        );

        foreach ($similarEvents as $sim) {
            if ($sim->getId_evenement() !== $evenement->getId_evenement()) {
                return $sim;
            }
        }
        return null;
    }

    /**
     * Cas 4 : Yield Management (Tarification Dynamique)
     */
    public function analyzeYieldManagement(Evenement $evenement, float $heuresRemplissage): array
    {
        try {
            $response = $this->client->request('POST', 'http://127.0.0.1:5000/api/ai/yield-management', [
                'json' => [
                    'fill_speed_hours' => $heuresRemplissage,
                    'current_price' => (float) $evenement->getPrix()
                ],
                'timeout' => 2
            ]);

            $data = $response->toArray();
            return [
                'suggested_price' => $data['suggested_price'],
                'admin_alert' => $data['admin_alert']
            ];

        } catch (\Exception $e) {
            $this->logger->warning('API Python IA injoignable pour le Yield Management. Fallback Mocked.');
            
            if ($heuresRemplissage < 2) {
                return [
                    'suggested_price' => round($evenement->getPrix() * 1.15, 2),
                    'admin_alert' => 'ALERTE DEMANDE FORTE : Ajustement algorithmique recommandé !'
                ];
            }
            return [
                'suggested_price' => $evenement->getPrix(),
                'admin_alert' => 'Demande standard.'
            ];
        }
    }
}
