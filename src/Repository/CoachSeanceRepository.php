<?php

namespace App\Repository;

use App\Entity\Seance;
use App\Entity\UserApp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour la gestion des séances du coach
 * 
 * @extends ServiceEntityRepository<Seance>
 */
class CoachSeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seance::class);
    }

    /**
     * Récupère toutes les séances d'un coach
     * 
     * @param UserApp $coach Le coach
     * @return Seance[] Les séances du coach
     */
    public function findByCoach(UserApp $coach): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('s.dateSeance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les séances à venir d'un coach
     * 
     * @param UserApp $coach Le coach
     * @return Seance[] Les séances à venir
     */
    public function findUpcomingByCoach(UserApp $coach): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.coach = :coach')
            ->andWhere('s.statutSeance = :statut')
            ->andWhere('s.dateSeance >= :now')
            ->setParameter('coach', $coach)
            ->setParameter('statut', 'PLANIFIEE')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.dateSeance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les séances terminées d'un coach
     * 
     * @param UserApp $coach Le coach
     * @return Seance[] Les séances terminées
     */
    public function findCompletedByCoach(UserApp $coach): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.coach = :coach')
            ->andWhere('s.statutSeance = :statut')
            ->setParameter('coach', $coach)
            ->setParameter('statut', 'TERMINEE')
            ->orderBy('s.dateSeance', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les séances d'un coach cette semaine
     * 
     * @param UserApp $coach Le coach
     * @return int Nombre de séances cette semaine
     */
    public function countThisWeek(UserApp $coach): int
    {
        $startOfWeek = (new \DateTime())->modify('Monday this week');
        $endOfWeek = (clone $startOfWeek)->modify('+6 days');

        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.coach = :coach')
            ->andWhere('s.dateSeance >= :start')
            ->andWhere('s.dateSeance <= :end')
            ->setParameter('coach', $coach)
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les séances d'un coach avec un statut spécifique
     * 
     * @param UserApp $coach Le coach
     * @param string $statut Le statut
     * @return Seance[] Les séances filtrées
     */
    public function findByCoachAndStatus(UserApp $coach, string $statut): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.coach = :coach')
            ->andWhere('s.statutSeance = :statut')
            ->setParameter('coach', $coach)
            ->setParameter('statut', $statut)
            ->orderBy('s.dateSeance', 'DESC')
            ->getQuery()
            ->getResult();
    }
}