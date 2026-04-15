<?php

namespace App\Service;

use App\Model\UserProfile;
use App\Model\PreferredTime;
use App\Repository\ReservationSeanceRepository;
use Psr\Log\LoggerInterface;

class UserAnalyticsService
{
    public function __construct(
        private ReservationSeanceRepository $reservationRepo,
        private LoggerInterface $logger
    ) {}

    /**
     * Analyse le profil d'un utilisateur
     * Détermine: coach préféré, horaire préféré, niveau d'activité
     *
     * @param int $userId ID de l'utilisateur
     * @return UserProfile Profil analysé
     */
    public function analyzeUser(int $userId): UserProfile
    {
        try {
            $this->logger->info("Analyse du profil utilisateur: $userId");

            // ===== ÉTAPE 1: RÉCUPÉRER LES RÉSERVATIONS =====
            $reservations = $this->reservationRepo->findBy([
                'userApp' => $userId
            ]);

            if (empty($reservations)) {
                $this->logger->info("Utilisateur nouveau (aucune réservation)");
                return new UserProfile($userId, null, null);
            }

            // ===== ÉTAPE 2: ANALYSER LES DONNÉES =====
            $coachCount = [];
            $morning = 0;
            $afternoon = 0;
            $evening = 0;
            $dayOfWeek = [];

            foreach ($reservations as $reservation) {
                try {
                    $seance = $reservation->getSeance();

                    if (!$seance) {
                        $this->logger->warning("Séance null dans réservation");
                        continue;
                    }

                    // --- Analyse du Coach ---
                    $coach = $seance->getCoach();
                    if ($coach) {
                        $coachId = $coach->getId();
                        if ($coachId) {
                            $coachCount[$coachId] = ($coachCount[$coachId] ?? 0) + 1;
                        }
                    }

                    // --- Analyse de l'Horaire ---
                    try {
                        $heureDebut = $seance->getHeureDebut();
                        if ($heureDebut instanceof \DateTimeInterface) {
                            $hour = (int)$heureDebut->format('H');

                            if ($hour < 12) {
                                $morning++;
                            } elseif ($hour < 18) {
                                $afternoon++;
                            } else {
                                $evening++;
                            }

                            // Jour de la semaine
                            $dayName = $heureDebut->format('l');
                            $dayOfWeek[$dayName] = ($dayOfWeek[$dayName] ?? 0) + 1;
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning("Erreur parsing heure: " . $e->getMessage());
                    }

                } catch (\Exception $e) {
                    $this->logger->error("Erreur analyse réservation: " . $e->getMessage());
                    continue;
                }
            }

            // ===== ÉTAPE 3: DÉTERMINER LES PRÉFÉRENCES =====

            // Coach préféré
            arsort($coachCount);
            $favoriteCoach = !empty($coachCount) ? array_key_first($coachCount) : null;

            // Horaire préféré
            $preferredTime = $this->determinePreferredTime($morning, $afternoon, $evening);

            // Jour préféré
            $preferredDay = !empty($dayOfWeek) ? array_key_first(array_filter($dayOfWeek, fn($count) => $count === max($dayOfWeek))) : null;

            // Activité
            $activityLevel = $this->calculateActivityLevel(count($reservations));

            $this->logger->info(
                "Profil utilisateur analysé",
                [
                    'user_id' => $userId,
                    'reservations_count' => count($reservations),
                    'favorite_coach' => $favoriteCoach,
                    'preferred_time' => $preferredTime,
                    'preferred_day' => $preferredDay,
                    'activity_level' => $activityLevel,
                    'coach_breakdown' => array_slice($coachCount, 0, 3),
                ]
            );

            return new UserProfile(
                $userId,
                $favoriteCoach,
                $preferredTime,
                $preferredDay,
                $activityLevel
            );

        } catch (\Exception $e) {
            $this->logger->error("Erreur analyzeUser: " . $e->getMessage());
            return new UserProfile($userId, null, null);
        }
    }

    /**
     * Détermine l'horaire préféré de l'utilisateur
     */
    private function determinePreferredTime(int $morning, int $afternoon, int $evening): ?PreferredTime
    {
        if ($morning === 0 && $afternoon === 0 && $evening === 0) {
            return null;
        }

        $max = max($morning, $afternoon, $evening);

        return match ($max) {
            $morning => PreferredTime::MORNING,
            $afternoon => PreferredTime::AFTERNOON,
            $evening => PreferredTime::EVENING,
            default => null
        };
    }

    /**
     * Calcule le niveau d'activité de l'utilisateur
     */
    private function calculateActivityLevel(int $reservationCount): string
    {
        return match (true) {
            $reservationCount >= 20 => 'very_active',
            $reservationCount >= 10 => 'active',
            $reservationCount >= 5 => 'moderate',
            $reservationCount >= 2 => 'low',
            default => 'new'
        };
    }

    /**
     * Récupère les statistiques de l'utilisateur
     *
     * @param int $userId ID de l'utilisateur
     * @return array Statistiques
     */
    public function getUserStatistics(int $userId): array
    {
        try {
            $reservations = $this->reservationRepo->findBy([
                'userApp' => $userId
            ]);

            if (empty($reservations)) {
                return [
                    'total_reservations' => 0,
                    'first_reservation' => null,
                    'last_reservation' => null,
                    'favorite_coach' => null,
                    'average_per_month' => 0,
                ];
            }

            $dates = array_map(fn($r) => $r->getSeance()?->getDateSeance(), $reservations);
            $dates = array_filter($dates, fn($d) => $d !== null);

            $firstDate = min($dates);
            $lastDate = max($dates);

            $monthsDiff = $firstDate->diff($lastDate)->m + ($firstDate->diff($lastDate)->y * 12);
            $avgPerMonth = $monthsDiff > 0 ? round(count($reservations) / $monthsDiff, 2) : count($reservations);

            return [
                'total_reservations' => count($reservations),
                'first_reservation' => $firstDate->format('Y-m-d'),
                'last_reservation' => $lastDate->format('Y-m-d'),
                'months_active' => $monthsDiff,
                'average_per_month' => $avgPerMonth,
            ];

        } catch (\Exception $e) {
            $this->logger->error("Erreur getUserStatistics: " . $e->getMessage());
            return [];
        }
    }
}