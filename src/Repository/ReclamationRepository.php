<?php

namespace App\Repository;

use App\Entity\Reclamation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reclamation>
 */
class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }
    public function countByStatus(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.statut, COUNT(r.id_reclamation) as total')
            ->groupBy('r.statut')
            ->getQuery()
            ->getResult();
    }

    public function findUrgentTickets(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.priorite = :p')
            ->andWhere('r.statut = :s')
            ->setParameter('p', 'HAUTE')
            ->setParameter('s', StatutReclamation::EN_ATTENTE)
            ->orderBy('r.date_creation', 'DESC')
            ->getQuery()
            ->getResult();
}

//    /**
//     * @return Reclamation[] Returns an array of Reclamation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Reclamation
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
