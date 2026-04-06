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

    public function findForFront(
        ?string $search = null,
        ?string $sort = null,
        ?string $type = null,
        ?string $statut = null
    ): array {
        $qb = $this->createQueryBuilder('p');

        if (!empty($search)) {
            $qb->andWhere('LOWER(p.nom) LIKE :search OR LOWER(p.type_pack) LIKE :search')
               ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }

        if (!empty($type)) {
            $qb->andWhere('LOWER(p.type_pack) = :type')
               ->setParameter('type', mb_strtolower(trim($type)));
        }

        if (!empty($statut)) {
            $qb->andWhere('LOWER(p.statut_pack) = :statut')
               ->setParameter('statut', mb_strtolower(trim($statut)));
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

            default:
                $qb->orderBy('p.nom', 'ASC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function findDistinctTypes(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.type_pack AS type_pack')
            ->where('p.type_pack IS NOT NULL')
            ->andWhere('p.type_pack != :vide')
            ->setParameter('vide', '')
            ->orderBy('p.type_pack', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(fn ($row) => $row['type_pack'], $rows);
    }

    public function findDistinctStatuts(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.statut_pack AS statut_pack')
            ->where('p.statut_pack IS NOT NULL')
            ->andWhere('p.statut_pack != :vide')
            ->setParameter('vide', '')
            ->orderBy('p.statut_pack', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(fn ($row) => $row['statut_pack'], $rows);
    }
}