<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    // 🔍 FILTRE
    /**
     * @return mixed
     */
    public function findByFilters(?string $search, ?string $categorie, ?string $lieu, ?string $sortBy = 'date_desc', bool $onlyAvailable = false, int $page = 1, int $limit = 6)
    {
        $qb = $this->createQueryBuilder('e');

        // Toujours n'afficher que les événements futurs pour le front par défaut
        $qb->andWhere('e.date_event >= :now')
           ->setParameter('now', new \DateTime());

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

        if ($onlyAvailable) {
            // Join with reservations and check places
            $qb->leftJoin('e.reservationEvenements', 'r')
               ->groupBy('e.id_evenement')
               ->having('(e.nb_places - COALESCE(SUM(CASE WHEN r.statut_res != :annule THEN r.nb_billets ELSE 0 END), 0)) > 0')
               ->setParameter('annule', \App\Enum\StatutReservationEvenement::ANNULEE);
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

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return new \Doctrine\ORM\Tools\Pagination\Paginator($qb);
    }

    // 📍 LIEUX DISTINCTS
    /**
     * @return array<int, string>
     */
    public function findDistinctLieux(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.lieu')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
