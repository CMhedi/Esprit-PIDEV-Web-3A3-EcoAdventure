<?php

namespace App\Repository;

use App\Entity\ReservationActivite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReservationActivite>
 */
class ReservationActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationActivite::class);
    }

    /**
     * Liste admin : ignore les réservations dont l’activité ou l’utilisateur
     * n’existe plus (FK orpheline), pour éviter EntityNotFoundException au rendu.
     *
     * @return ReservationActivite[]
     */
    public function findAllForAdminList(): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.activite', 'a')->addSelect('a')
            ->innerJoin('r.userApp', 'u')->addSelect('u')
            ->orderBy('r.dateRes', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Détail / édition admin : null si réservation ou relation obligatoire absente.
     */
    public function findOneForAdmin(int $id): ?ReservationActivite
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.activite', 'a')->addSelect('a')
            ->innerJoin('r.userApp', 'u')->addSelect('u')
            ->andWhere('r.id_res_act = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

//    /**
//     * @return ReservationActivite[] Returns an array of ReservationActivite objects
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

//    public function findOneBySomeField($value): ?ReservationActivite
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
