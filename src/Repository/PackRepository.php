<?php

namespace App\Repository;

use App\Entity\Pack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pack::class);
    }

    public function findForAdmin(?string $search = null, ?string $sort = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.inscriptions', 'i')
            ->addSelect('COUNT(i.id_inscription) AS HIDDEN inscriptionsCount')
            ->groupBy('p.id_pack');

        if (!empty($search)) {
            $qb->andWhere('
                LOWER(p.nom) LIKE :search
                OR LOWER(p.type_pack) LIKE :search
                OR LOWER(p.statut_pack) LIKE :search
            ')
            ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        switch ($sort) {
            case 'nom_desc':
                $qb->orderBy('p.nom', 'DESC');
                break;

            case 'prix_asc':
                $qb->orderBy('p.prix_base', 'ASC');
                break;

            case 'prix_desc':
                $qb->orderBy('p.prix_base', 'DESC');
                break;

            case 'reduction_desc':
                $qb->orderBy('p.reduction', 'DESC');
                break;

            case 'activites_desc':
                $qb->orderBy('p.nb_activites_max', 'DESC');
                break;

            case 'statut_asc':
                $qb->orderBy('p.statut_pack', 'ASC');
                break;

            case 'popular_desc':
                $qb->orderBy('inscriptionsCount', 'DESC');
                break;

            default:
                $qb->orderBy('p.id_pack', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function findForFront(
        ?string $search = null,
        ?string $type = null,
        ?string $statut = null,
        ?string $sort = null
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.inscriptions', 'i')
            ->addSelect('COUNT(i.id_inscription) AS HIDDEN inscriptionsCount')
            ->groupBy('p.id_pack');

        if (!empty($search)) {
            $qb->andWhere('
                LOWER(p.nom) LIKE :search
                OR LOWER(p.type_pack) LIKE :search
            ')
            ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        if (!empty($type)) {
            $qb->andWhere('p.type_pack = :type')
               ->setParameter('type', trim($type));
        }

        if (!empty($statut)) {
            $qb->andWhere('p.statut_pack = :statut')
               ->setParameter('statut', trim($statut));
        }

        switch ($sort) {
            case 'nom_desc':
                $qb->orderBy('p.nom', 'DESC');
                break;

            case 'prix_asc':
                $qb->orderBy('p.prix_base', 'ASC');
                break;

            case 'prix_desc':
                $qb->orderBy('p.prix_base', 'DESC');
                break;

            case 'reduction_desc':
                $qb->orderBy('p.reduction', 'DESC');
                break;

            case 'activites_desc':
                $qb->orderBy('p.nb_activites_max', 'DESC');
                break;

            case 'popular_desc':
                $qb->orderBy('inscriptionsCount', 'DESC');
                break;

            default:
                $qb->orderBy('p.nom', 'ASC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function findDistinctTypes(): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('DISTINCT p.type_pack AS type_pack')
            ->orderBy('p.type_pack', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($results, 'type_pack');
    }

    public function findDistinctStatuts(): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('DISTINCT p.statut_pack AS statut_pack')
            ->orderBy('p.statut_pack', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($results, 'statut_pack');
    }

    public function countAllPacks(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id_pack)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllForPdf(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}