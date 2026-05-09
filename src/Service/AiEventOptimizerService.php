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
                        'message' => "🌟 L'Agent IA ECOADVENTURE vous informe : Vos chances sont faibles d'avoir une place ici. Nous vous recommandons cet événement très similaire :",
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
     * Cas 4 : notif Management (Tarification Dynamique)
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

    /**
     * Cas 5 : Widget Météo - Alerte automatique de l'IA (Maintenant pour toute condition météo)
     */
    public function getWeatherAiAlert(array $weather, Evenement $evenement): array
    {
        $icon = substr($weather['icon'], 0, 2);
        $temp = $weather['temp'];
        
        // Liste des codes icon OpenWeatherMap associés au "mauvais temps"
        $badIcons = ['09', '10', '11', '13', '50'];
        $isExtremeTemp = ($temp > 35 || $temp < 5);
        $isBadWeather = in_array($icon, $badIcons) || $isExtremeTemp;
        
        $type = $isBadWeather ? 'danger' : 'success';

        try {
            $response = $this->client->request('POST', 'http://127.0.0.1:5000/api/ai/weather-alert', [
                'json' => [
                    'weather_desc' => $weather['description'],
                    'temp' => $temp,
                    'is_bad_weather' => $isBadWeather,
                    'event_title' => $evenement->getTitre()
                ],
                'timeout' => 2
            ]);

            $data = $response->toArray();
            return ['type' => $type, 'message' => $data['alert_message']];

        } catch (\Exception $e) {
            $this->logger->warning('API Python IA injoignable pour Weather Alert. Fallback Mocked.');
            
            // Simulation d'une IA d'assistance sécuritaire (Good & Bad Weather)
            if ($temp > 35) {
                return ['type' => 'danger', 'message' => 'Alerte Canicule (' . $temp . '°C) détectée ! Pensez à prévoir minimum 2L d\'eau et une protection UV maximale pour "' . $evenement->getTitre() . '".'];
            } elseif ($temp < 5) {
                return ['type' => 'danger', 'message' => 'Températures glaciales prévues (' . $temp . '°C). Le système des 3 couches thermiques est impératif pour cette aventure.'];
            } elseif (in_array($icon, ['09', '10', '11'])) {
                return ['type' => 'danger', 'message' => 'Fortes précipitations (' . $weather['description'] . '). Un équipement 100% imperméable est obligatoire pour garantir votre confort et sécurité.'];
            } elseif ($icon === '13') {
                return ['type' => 'danger', 'message' => 'Risque d\'enneigement. Assurez-vous d\'avoir les bottes et crampons nécessaires avant le départ.'];
            } elseif (in_array($icon, ['01', '02', '03'])) {
                return ['type' => 'success', 'message' => 'Conditions météo idéales (' . $weather['description'] . ' à ' . $temp . '°C) ! Une météo parfaite pour profiter pleinement de "' . $evenement->getTitre() . '".'];
            } else {
                if ($isBadWeather) {
                    return ['type' => 'warning', 'message' => 'Conditions de visibilité réduites (' . $weather['description'] . '). Restez groupés avec votre guide EcoAdventure.'];
                } else {
                    return ['type' => 'success', 'message' => 'Conditions météo très favorables pour l\'activité en plein air. Bonne aventure avec EcoAdventure !'];
                }
            }
        }
    }

    /**
     * Cas 6 : Prédictions Financières Annuelles (Machine Learning Simulation)
     * Utilisé pour le rapport Excel détaillé.
     */
    public function predictFinancials(Evenement $evenement): array
    {
        try {
            $placesMax = $evenement->getNb_places();
            $tauxRemplissage = $placesMax > 0 ? (max(0, $placesMax - $evenement->getPlacesRestantes()) / $placesMax) : 0;

            $response = $this->client->request('POST', 'http://127.0.0.1:5000/api/ai/predict-financials', [
                'json' => [
                    'event_id' => $evenement->getId_evenement(),
                    'categorie' => $evenement->getCategorie_evt()->value,
                    'current_fill_rate' => $tauxRemplissage,
                    'unit_price' => (float)$evenement->getPrix()
                ],
                'timeout' => 2
            ]);

            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->warning('API Python IA injoignable pour Financial Predictions. Fallback Mocked.');
            
            // Logique de simulation ML "EcoAdventure"
            $scoreIA = 65; // Score de base
            $multiplier = 1.0;
            
            // Facteurs d'influence
            $cat = $evenement->getCategorie_evt()->value;
            if ($cat === 'NAUTIQUE' || $cat === 'CAMPING') {
                $scoreIA += 15;
                $multiplier = 1.2;
            }
            
            $placesMax = $evenement->getNb_places();
            $tauxRemplissage = $placesMax > 0 ? (max(0, $placesMax - $evenement->getPlacesRestantes()) / $placesMax) : 0;
            
            if ($tauxRemplissage > 0.8) {
                $scoreIA += 10;
                $multiplier += 0.2;
            }

            $scoreIA = min(98, $scoreIA + rand(-5, 5));
            
            // On prédit une augmentation basée sur la popularité (score IA)
            $predictedTickets = (int)($placesMax * $multiplier * (1 + ($scoreIA / 100)));
            $predictedCA = $predictedTickets * $evenement->getPrix();

            return [
                'score_ia' => $scoreIA,
                'predicted_tickets' => $predictedTickets,
                'predicted_ca' => round($predictedCA, 2)
            ];
        }
    }
}
