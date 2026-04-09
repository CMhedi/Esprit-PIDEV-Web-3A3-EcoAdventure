<?php

namespace App\Repository;

use App\Entity\Planning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PlanningRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Planning::class);
    }

    // =========================
    // 📋 GET ALL (trié comme Java)
    // =========================
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // =========================
    // 🔥 GET ACTIFS
    // =========================
    public function findActifs(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut = :statut')
            ->setParameter('statut', 'ACTIF')
            ->orderBy('p.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // =========================
    // 🔍 SEARCH (comme JavaFX)
    // =========================
    public function search(?string $keyword): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($keyword) {
            $qb->where('LOWER(p.titre) LIKE :k OR LOWER(p.description) LIKE :k')
               ->setParameter('k', '%' . strtolower($keyword) . '%');
        }

        return $qb->orderBy('p.dateDebut', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    // =========================
    // 📅 FILTER BY YEAR
    // =========================
    public function filterByYear(?int $year): array
    {
        if (!$year) {
            return $this->findAllOrdered();
        }

        return $this->createQueryBuilder('p')
            ->where('YEAR(p.dateDebut) = :year')
            ->setParameter('year', $year)
            ->orderBy('p.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // =========================
    // 📌 FILTER BY STATUT
    // =========================
    public function filterByStatut(?string $statut): array
    {
        if (!$statut || $statut === 'TOUS') {
            return $this->findAllOrdered();
        }

        return $this->createQueryBuilder('p')
            ->where('p.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('p.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // =========================
    // 💾 SAVE (add + update)
    // =========================
    public function save(Planning $planning, bool $flush = true): void
    {
        $this->getEntityManager()->persist($planning);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // =========================
    // ❌ DELETE
    // =========================
    public function remove(Planning $planning, bool $flush = true): void
    {
        $this->getEntityManager()->remove($planning);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
 public function filter($search, $annee, $statut)
{
    $qb = $this->createQueryBuilder('p');

    if ($search) {
        $qb->andWhere('p.titre LIKE :search')
           ->setParameter('search', '%'.$search.'%');
    }

    // ✅ CORRECTION ICI
    if ($annee) {
        $dateStart = new \DateTime($annee . '-01-01');
        $dateEnd   = new \DateTime($annee . '-12-31');

        $qb->andWhere('p.date_debut BETWEEN :start AND :end')
           ->setParameter('start', $dateStart)
           ->setParameter('end', $dateEnd);
    }

    if ($statut) {
        $qb->andWhere('p.statut = :statut')
           ->setParameter('statut', $statut);
    }

    return $qb->getQuery()->getResult();
}
}