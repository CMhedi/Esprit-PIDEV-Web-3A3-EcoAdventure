<?php
// src/Repository/SeanceRepository.php

namespace App\Repository;

use App\Entity\Seance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seance::class);
    }

    // 🔥 équivalent getByPlanning()
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
public function filter($planning, $search, $date, $statut, $coach, $sort)
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

    return $qb->getQuery()->getResult();
}
}