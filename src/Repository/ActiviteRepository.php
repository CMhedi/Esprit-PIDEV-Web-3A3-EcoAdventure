<?php

namespace App\Repository;

use App\Entity\Activite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActiviteRepository extends ServiceEntityRepository
{
    private const SORT_FIELDS = [
        'nom' => 'a.nom',
        'prix' => 'a.prix',
        'type' => 'a.type_activite',
        'categorie' => 'a.categorie_act',
        'niveau' => 'a.niveau_act',
        'statut' => 'a.statut',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    public function findBySearchAndSort(
        string $search = '',
        string $sortBy = 'prix',
        string $direction = 'asc'
    ): array {
        $queryBuilder = $this->createQueryBuilder('a');
        $search = trim($search);
        $sortField = self::SORT_FIELDS[$sortBy] ?? self::SORT_FIELDS['prix'];
        $sortDirection = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(a.nom) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }

        return $queryBuilder
            ->orderBy($sortField, $sortDirection)
            ->addOrderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
