<?php

namespace App\Service;

use App\Model\UserProfile;
use App\Model\PreferredTime;
use App\Repository\SeanceRepository;
use Psr\Log\LoggerInterface;

class RecommendationService
{
    private const MAX_RECOMMENDATIONS = 5;
    private const MIN_SCORE = 0.1;

    public function __construct(
        private SeanceRepository $seanceRepo,
        private ReservationService $reservationService,
        private UserAnalyticsService $analyticsService,
        private LoggerInterface $logger
    ) {}

    /**
     * Recommande des séances personnalisées pour un utilisateur
     *
     * @param int $userId ID de l'utilisateur
     * @return array Tableau des séances recommandées avec score et raison
     */
    public function recommendForUser(int $userId): array
    {
        try {
            $this->logger->info("Génération recommandations pour utilisateur: $userId");

            // ===== ÉTAPE 1: RÉCUPÉRER LES DONNÉES =====
            $seances = $this->seanceRepo->findAvailable();
            $userMap = $this->reservationService->getUserReservationsMap();
            $profile = $this->analyticsService->analyzeUser($userId);

            $userSeances = $userMap[$userId] ?? [];

            if (empty($seances)) {
                $this->logger->warning("Aucune séance disponible pour recommandation");
                return [];
            }

            // ===== ÉTAPE 2: CALCULER LES SCORES =====
            $results = [];

foreach ($seances as $seance) {

    // ✅ AJOUTER ICI
    $now = new \DateTime();

    $seanceDateTime = new \DateTime(
        $seance->getDateSeance()->format('Y-m-d') . ' ' .
        $seance->getHeureDebut()->format('H:i:s')
    );

    if ($seanceDateTime <= $now) {
        continue;
    }

    // Exclure les séances déjà réservées
    if (in_array($seance->getIdSeance(), $userSeances)) {
        continue;
    }

    // Exclure les séances complètes
    if ($this->isSeanceFull($seance)) {
        continue;
    }

                // Calculer les scores
                $sim = $this->similarityScore($userId, $seance->getIdSeance(), $userMap);
                $pop = $this->popularityScore($seance->getIdSeance());
                $coach = $this->coachScore($seance->getCoach()->getId(), $profile);
                $time = $this->timeScore($seance->getHeureDebut(), $profile);
                $rec = $this->recencyScore($userId);
                $capacity = $this->capacityScore($seance);

                // Score pondéré
                $score = $this->calculateWeightedScore($sim, $pop, $coach, $time, $rec, $capacity);

                if ($score < self::MIN_SCORE) {
                    continue;
                }

                $results[] = [
                    'seance' => $seance,
                    'score' => round($score, 3),
                    'reason' => $this->buildReason($sim, $pop, $coach, $time, $capacity, $rec),
                    'scores' => [
                        'similarity' => round($sim, 2),
                        'popularity' => round($pop, 2),
                        'coach_match' => round($coach, 2),
                        'time_match' => round($time, 2),
                        'recency' => round($rec, 2),
                        'capacity' => round($capacity, 2),
                    ]
                ];
            }

            // ===== ÉTAPE 3: TRIER ET LIMITER =====
            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
            $final = array_slice($results, 0, self::MAX_RECOMMENDATIONS);

            $this->logger->info(
                "Recommandations générées",
                [
                    'user_id' => $userId,
                    'total_seances_disponibles' => count($seances),
                    'recommendations_count' => count($final),
                ]
            );

            return $final;

        } catch (\Exception $e) {
            $this->logger->error("Erreur recommandations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Score de similarité basé sur les utilisateurs avec goûts similaires
     */
    private function similarityScore(int $userId, int $seanceId, array $map): float
    {
        $target = $map[$userId] ?? [];

        if (empty($target)) {
            return 0;
        }

        $best = 0;

        foreach ($map as $otherId => $seances) {
            if ($otherId === $userId) {
                continue;
            }

            if (!in_array($seanceId, $seances)) {
                continue;
            }

            // Coefficient Jaccard
            $sim = $this->jaccard($target, $seances);
            $best = max($best, $sim);
        }

        return $best;
    }

    /**
     * Calcul de similarité Jaccard
     * Intersection / Union = mesure de similarité
     */
    private function jaccard(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0;
        }

        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union > 0 ? $intersection / $union : 0;
    }

    /**
     * Score de popularité basé sur le nombre de réservations
     */
    private function popularityScore(int $seanceId): float
    {
        $count = $this->reservationService->countReservations($seanceId);
        
        // Normalisé sur 10 réservations max
        return min($count / 10, 1.0);
    }

    /**
     * Score du coach (1 si coach préféré, 0 sinon)
     */
    private function coachScore(int $coachId, UserProfile $profile): float
    {
        return $profile->getFavoriteCoach() === $coachId ? 1.0 : 0.3;
    }

    /**
     * Score d'horaire adapté au profil utilisateur
     */
    private function timeScore(\DateTimeInterface $time, UserProfile $profile): float
    {
        if (!$profile->getPreferredTime()) {
            return 0.5; // Score neutre si pas de préférence
        }

        $hour = (int)$time->format('H');
        $isMorning = $hour < 12;
        $isAfternoon = $hour >= 12 && $hour < 18;
        $isEvening = $hour >= 18;

        return match ($profile->getPreferredTime()) {
            PreferredTime::MORNING => $isMorning ? 1.0 : 0.2,
            PreferredTime::AFTERNOON => $isAfternoon ? 1.0 : 0.2,
            PreferredTime::EVENING => $isEvening ? 1.0 : 0.2,
            default => 0.5
        };
    }

    /**
     * Score de récence (utilisateurs actifs obtiennent plus)
     */
    private function recencyScore(int $userId): float
    {
        try {
            $seances = $this->reservationService->getUserSeances($userId);

            if (empty($seances)) {
                return 0.3; // Nouvel utilisateur
            }

            $latest = max(array_map(fn($s) => $s->getDateSeance(), $seances));
            $days = (new \DateTime())->diff($latest)->days;

            return match (true) {
                $days <= 7 => 1.0,    // Actif cette semaine
                $days <= 14 => 0.8,   // Actif cette quinzaine
                $days <= 30 => 0.6,   // Actif ce mois
                $days <= 90 => 0.4,   // Actif ce trimestre
                default => 0.2        // Inactif longtemps
            };

        } catch (\Exception $e) {
            $this->logger->warning("Erreur récence score: " . $e->getMessage());
            return 0.3;
        }
    }

    /**
     * Score de capacité (bonus si places limitées)
     */
    private function capacityScore(object $seance): float
    {
        try {
            $total = $seance->getCapacite();
            $reserved = count($seance->getReservationSeances());
            $available = $total - $reserved;

            if ($available <= 0) {
                return 0;
            }

            // Urgence: moins il y a de places, plus haut le score
            $percentage = $available / $total;

            return match (true) {
                $percentage <= 0.1 => 0.8,  // Très urgent!
                $percentage <= 0.25 => 0.6, // Urgent
                $percentage <= 0.5 => 0.4,  // Limite
                default => 0.2              // Beaucoup de places
            };

        } catch (\Exception $e) {
            return 0.2;
        }
    }

    /**
     * Vérifier si la séance est complète
     */
    private function isSeanceFull(object $seance): bool
    {
        return count($seance->getReservationSeances()) >= $seance->getCapacite();
    }

    /**
     * Calcul du score pondéré
     */
    private function calculateWeightedScore(
        float $sim,
        float $pop,
        float $coach,
        float $time,
        float $rec,
        float $capacity
    ): float {
        return (
            0.30 * $sim +      // Similarité (30%)
            0.18 * $pop +      // Popularité (18%)
            0.18 * $coach +    // Coach (18%)
            0.14 * $time +     // Horaire (14%)
            0.12 * $rec +      // Récence (12%)
            0.08 * $capacity   // Capacité (8%)
        );
    }

    /**
     * Construire la raison de la recommandation
     */
    private function buildReason(
        float $sim,
        float $pop,
        float $coach,
        float $time,
        float $capacity,
        float $rec
    ): string {
        // Déterminer la raison dominante
        return match (true) {
            $capacity >= 0.7 => "📍 Seulement quelques places disponibles!",
            $sim >= 0.6 => "👥 Utilisateurs similaires l'adorent",
            $coach >= 0.9 => "⭐ Votre coach préféré",
            $time >= 0.9 => "🕐 Horaire parfait pour vous",
            $pop >= 0.8 => "🔥 Très populaire en ce moment",
            $rec >= 0.8 => "✅ Vous êtes actif, continuez!",
            default => "💡 Recommandé pour vous"
        };
    }
}