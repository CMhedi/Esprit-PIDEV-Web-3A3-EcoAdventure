<?php

namespace App\Repository;

use App\Entity\Inscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inscription>
 */
class InscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscription::class);
    }

    public function findForAdmin(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.pack', 'p')
            ->leftJoin('i.userApp', 'u')
            ->addSelect('p')
            ->addSelect('u')
            ->orderBy('i.date_inscription', 'DESC');

        if (!empty($search)) {
            $qb->andWhere('
                LOWER(i.nom_user) LIKE :search
                OR LOWER(u.nom) LIKE :search
                OR LOWER(u.prenom) LIKE :search
                OR LOWER(u.email) LIKE :search
                OR LOWER(i.nom_pack) LIKE :search
                OR LOWER(i.statut_inscr) LIKE :search
                OR LOWER(p.nom) LIKE :search
            ')
            ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAllInscriptions(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id_inscription)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
