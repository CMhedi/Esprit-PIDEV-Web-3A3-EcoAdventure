<?php
// src/Repository/SeanceRepository.php

namespace App\Repository;

use App\Entity\Seance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\UserApp;
/**
 * @extends ServiceEntityRepository<Seance>
 */
class SeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seance::class);
    }

  /**
 * @return Seance[]
 */
    public function findByPlanning(int $planningId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.planning = :planning')
            ->setParameter('planning', $planningId)
            ->orderBy('s.dateSeance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // 🔥 équivalent hasSeanceInPlanning()
    public function hasSeances(int $planningId): bool
    {
        return (bool) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.planning = :planning')
            ->setParameter('planning', $planningId)
            ->getQuery()
            ->getSingleScalarResult();
    }
    public function save(Seance $entity, bool $flush = false): void
{
    $this->getEntityManager()->persist($entity);

    if ($flush) {
        $this->getEntityManager()->flush();
    }
}
public function remove(Seance $entity, bool $flush = false): void
{
    $this->getEntityManager()->remove($entity);

    if ($flush) {
        $this->getEntityManager()->flush();
    }
}
/**
 * @param int $planning
 * @param string|null $search
 * @param string|null $date
 * @param string|null $statut
 * @param int|null $coach
 * @param string|null $sort
 * 
 * @return Seance[]
 */
public function filter(
    int $planning,
    ?string $search,
    ?string $date,
    ?string $statut,
    ?int $coach,
    ?string $sort
): array
{
    $qb = $this->createQueryBuilder('s')
        ->where('s.planning = :planning')
        ->setParameter('planning', $planning);

    if ($search) {
        $qb->andWhere('s.nom LIKE :search')
           ->setParameter('search', '%'.$search.'%');
    }

    if ($date) {
        $qb->andWhere('s.dateSeance = :date')
           ->setParameter('date', new \DateTime($date));
    }

    if ($statut) {
        $qb->andWhere('s.statutSeance = :statut')
           ->setParameter('statut', $statut);
    }

    if ($coach) {
        $qb->andWhere('s.coach = :coach')
           ->setParameter('coach', $coach);
    }

    if ($sort === 'date') {
        $qb->orderBy('s.dateSeance', 'ASC');
    }

    if ($sort === 'capacite') {
        $qb->orderBy('s.capacite', 'DESC');
    }

   $result = $qb->getQuery()->getResult();

/** @var Seance[] $result */
return $result;
}

/**
 * @return Seance[]
 */
public function findAvailable(): array
{
    return $this->createQueryBuilder('s')
        ->where('s.statutSeance = :statut')
        ->andWhere('s.dateSeance >= :today')
        ->setParameter('statut', 'PLANIFIEE')
        ->setParameter('today', new \DateTime())
        ->orderBy('s.dateSeance', 'ASC')
        ->getQuery()
        ->getResult();
}
/**
 * @return Seance[]
 */
public function findByCoach(UserApp $coach): array
{
    return $this->createQueryBuilder('s')
        ->where('s.coach = :coach')
        ->setParameter('coach', $coach)
        ->orderBy('s.dateSeance', 'ASC')
        ->getQuery()
        ->getResult();
}
}