<?php

namespace App\Service;

use App\Repository\ReservationSeanceRepository;
use App\Repository\SeanceRepository;
use Psr\Log\LoggerInterface;

class ReservationService
{
    public function __construct(
        private ReservationSeanceRepository $repo,
        private SeanceRepository $seanceRepo,
        private LoggerInterface $logger
    ) {}

    /**
     * Récupère une map de toutes les réservations utilisateur-séance
     * Format: [userId => [seanceId1, seanceId2, ...]]
     *
     * @return array Map des réservations
     */
    public function getUserReservationsMap(): array
    {
        try {
            $data = [];
            $reservations = $this->repo->findAll();

            foreach ($reservations as $reservation) {
                try {
                    $userId = $reservation->getUserApp()?->getId();
                    $seanceId = $reservation->getSeance()?->getIdSeance();

                    // Validation des données
                    if (!$userId || !$seanceId) {
                        $this->logger->warning("Réservation avec données invalides");
                        continue;
                    }

                    if (!isset($data[$userId])) {
                        $data[$userId] = [];
                    }

                    $data[$userId][] = $seanceId;

                } catch (\Exception $e) {
                    $this->logger->error("Erreur traitement réservation: " . $e->getMessage());
                    continue;
                }
            }

            $this->logger->info(
                "Map des réservations créée",
                ['total_users' => count($data), 'total_reservations' => count($reservations)]
            );

            return $data;

        } catch (\Exception $e) {
            $this->logger->error("Erreur getUserReservationsMap: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compte le nombre de réservations pour une séance
     *
     * @param int $seanceId ID de la séance
     * @return int Nombre de réservations
     */
    public function countReservations(int $seanceId): int
    {
        try {
            $count = $this->repo->countBySeance($seanceId);
            return $count ?? 0;

        } catch (\Exception $e) {
            $this->logger->error("Erreur countReservations: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Récupère les séances d'un utilisateur
     *
     * @param int $userId ID de l'utilisateur
     * @return array Tableau des séances
     */
    public function getUserSeances(int $userId): array
    {
        try {
            $reservations = $this->repo->findBy([
                'userApp' => $userId
            ]);

            $seances = array_map(function ($reservation) {
                return $reservation->getSeance();
            }, $reservations);

            // Filtrer les null et réindexer
            $seances = array_filter($seances, fn($s) => $s !== null);

            $this->logger->info(
                "Séances utilisateur récupérées",
                ['user_id' => $userId, 'total' => count($seances)]
            );

            return array_values($seances);

        } catch (\Exception $e) {
            $this->logger->error("Erreur getUserSeances: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifie si un utilisateur a réservé une séance
     *
     * @param int $userId ID de l'utilisateur
     * @param int $seanceId ID de la séance
     * @return bool
     */
    public function hasUserReservedSeance(int $userId, int $seanceId): bool
    {
        try {
            $reservation = $this->repo->findOneBy([
                'userApp' => $userId,
                'seance' => $seanceId
            ]);

            return $reservation !== null;

        } catch (\Exception $e) {
            $this->logger->error("Erreur hasUserReservedSeance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les statistiques de réservation
     *
     * @return array Statistiques
     */
    public function getStatistics(): array
    {
        try {
            $totalReservations = count($this->repo->findAll());
            $seances = $this->seanceRepo->findAll();

            $totalCapacity = array_sum(array_map(fn($s) => $s->getCapacite(), $seances));

            return [
                'total_reservations' => $totalReservations,
                'total_capacity' => $totalCapacity,
                'fill_rate' => $totalCapacity > 0 ? round(($totalReservations / $totalCapacity) * 100, 2) : 0,
                'total_seances' => count($seances),
            ];

        } catch (\Exception $e) {
            $this->logger->error("Erreur getStatistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les utilisateurs les plus actifs
     *
     * @param int $limit Nombre de résultats
     * @return array Utilisateurs et leur nombre de réservations
     */
    public function getTopActiveUsers(int $limit = 10): array
    {
        try {
            $map = $this->getUserReservationsMap();

            // Trier par nombre de réservations
            uasort($map, fn($a, $b) => count($b) <=> count($a));

            $result = [];
            foreach (array_slice($map, 0, $limit, true) as $userId => $seances) {
                $result[] = [
                    'user_id' => $userId,
                    'reservation_count' => count($seances)
                ];
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error("Erreur getTopActiveUsers: " . $e->getMessage());
            return [];
        }
    }
}