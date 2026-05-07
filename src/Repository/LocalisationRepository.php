<?php

namespace App\Repository;

use App\Entity\Localisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Localisation>
 */
class LocalisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Localisation::class);
    }

    // Exemple de méthode utile (optionnel)
    public function findByVille(string $ville): ?Localisation
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.ville = :ville')
            ->setParameter('ville', $ville)
            ->getQuery()
            ->getOneOrNullResult();
    }
}