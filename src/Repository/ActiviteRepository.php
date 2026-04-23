<?php

namespace App\Repository;

use App\Entity\Activite;
use App\Enum\CategorieAct;
use App\Enum\NiveauAct;
use App\Enum\Statut;
use App\Enum\TypeActivite;
use BackedEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function findAllValid(): array
    {
        return $this->createValidQueryBuilder()
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySearchAndSort(
        string $search = '',
        string $sortBy = 'prix',
        string $direction = 'asc'
    ): array {
        $queryBuilder = $this->createValidQueryBuilder();
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

    public function findTrendingValid(int $limit = 10): array
    {
        return $this->createValidQueryBuilder()
            ->leftJoin('a.reservationActivites', 'r')
            ->addSelect('COUNT(r.id_res_act) AS HIDDEN total')
            ->groupBy('a.id_activite')
            ->orderBy('total', 'DESC')
            ->addOrderBy('a.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function createValidQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('a');
        $this->applyValidEnumFilters($queryBuilder, 'a');

        return $queryBuilder;
    }

    private function applyValidEnumFilters(QueryBuilder $queryBuilder, string $alias): void
    {
        $queryBuilder
            ->andWhere(sprintf('%s.type_activite IN (:validTypeActivites)', $alias))
            ->andWhere(sprintf('%s.categorie_act IN (:validCategories)', $alias))
            ->andWhere(sprintf('%s.niveau_act IN (:validNiveaux)', $alias))
            ->andWhere(sprintf('%s.statut IN (:validStatuts)', $alias))
            ->setParameter('validTypeActivites', $this->enumValues(TypeActivite::cases()))
            ->setParameter('validCategories', $this->enumValues(CategorieAct::cases()))
            ->setParameter('validNiveaux', $this->enumValues(NiveauAct::cases()))
            ->setParameter('validStatuts', $this->enumValues(Statut::cases()));
    }

    /**
     * @param BackedEnum[] $cases
     */
    private function enumValues(array $cases): array
    {
        return array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            $cases
        );
    }
}
