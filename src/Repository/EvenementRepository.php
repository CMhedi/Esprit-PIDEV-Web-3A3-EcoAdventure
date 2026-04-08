<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    // 🔍 FILTRE
    public function findByFilters(?string $search, ?string $categorie, ?string $lieu, ?string $sortBy = 'date_desc')
    {
        $qb = $this->createQueryBuilder('e');

        if ($search) {
            $qb->andWhere('e.titre LIKE :search OR e.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($categorie) {
            $qb->andWhere('e.categorie_evt = :cat')
               ->setParameter('cat', $categorie);
        }

        if ($lieu) {
            $qb->andWhere('e.lieu = :lieu')
               ->setParameter('lieu', $lieu);
        }

        switch ($sortBy) {
            case 'date_asc':
                $qb->orderBy('e.date_event', 'ASC');
                break;
            case 'title_asc':
                $qb->orderBy('e.titre', 'ASC');
                break;
            case 'title_desc':
                $qb->orderBy('e.titre', 'DESC');
                break;
            case 'date_desc':
            default:
                $qb->orderBy('e.date_event', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    // 📍 LIEUX DISTINCTS
    public function findDistinctLieux(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.lieu')
            ->getQuery()
            ->getSingleColumnResult();
    }
}