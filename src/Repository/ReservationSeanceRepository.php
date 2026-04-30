<?php

namespace App\Repository;

use App\Entity\ReservationSeance;
use App\Entity\Seance;
use App\Entity\UserApp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion des réservations de séances
 * 
 * @extends ServiceEntityRepository<ReservationSeance>
 */
class ReservationSeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationSeance::class);
    }

    /**
     * Vérifie si un utilisateur a déjà réservé une séance
     * Utilise COUNT pour optimiser les performances
     * 
     * @param UserApp $user L'utilisateur
     * @param Seance $seance La séance
     * @return bool True si l'utilisateur a réservé, false sinon
     */
    public function isUserReserved(UserApp $user, Seance $seance): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id_reservation)')
            ->andWhere('r.userApp = :user')
            ->andWhere('r.seance = :seance')
            ->setParameter('user', $user)
            ->setParameter('seance', $seance)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Compte le nombre de réservations pour une séance donnée
     * 
     * @param Seance $seance La séance
     * @return int Le nombre de réservations
     */
    public function countReservations(Seance $seance): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id_reservation)')
            ->andWhere('r.seance = :seance')
            ->setParameter('seance', $seance)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBySeance(int $seanceId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id_reservation)')
            ->join('r.seance', 's')
            ->andWhere('s.idSeance = :seanceId')
            ->setParameter('seanceId', $seanceId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère toutes les réservations d'un utilisateur
     * 
     * @param UserApp $user L'utilisateur
     * @return ReservationSeance[] Les réservations de l'utilisateur
     */
    public function findUserReservations(UserApp $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.userApp = :user')
            ->setParameter('user', $user)
            ->orderBy('r.date_reservation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les réservations d'une séance
     * 
     * @param Seance $seance La séance
     * @return ReservationSeance[] Les réservations de la séance
     */
    public function findSeanceReservations(Seance $seance): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.seance = :seance')
            ->setParameter('seance', $seance)
            ->orderBy('r.date_reservation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les réservations confirmées pour une séance
     * 
     * @param Seance $seance La séance
     * @return ReservationSeance[] Les réservations confirmées
     */
    public function findConfirmedReservations(Seance $seance): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.seance = :seance')
            ->andWhere('r.statut = :statut')
            ->setParameter('seance', $seance)
            ->setParameter('statut', 'CONFIRMEE')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a une réservation pour une séance donnée
     * Alias pour isUserReserved() - pour meilleure lisibilité
     * 
     * @param UserApp $user L'utilisateur
     * @param Seance $seance La séance
     * @return bool True si l'utilisateur a réservé, false sinon
     */
    public function exists(UserApp $user, Seance $seance): bool
    {
        return $this->isUserReserved($user, $seance);
    }

    /**
     * Récupère les places restantes pour une séance
     * 
     * @param Seance $seance La séance
     * @return int Le nombre de places restantes
     */
    public function getRemainingPlaces(Seance $seance): int
    {
        $reserved = $this->countReservations($seance);
        $remaining = $seance->getCapacite() - $reserved;
        
        return max(0, $remaining);
    }

    /**
     * Supprime une réservation pour permettre un annulation
     * 
     * @param ReservationSeance $reservation La réservation à annuler
     * @return void
     */
    public function cancelReservation(ReservationSeance $reservation): void
    {
        $this->getEntityManager()->remove($reservation);
        $this->getEntityManager()->flush();
    }
}
