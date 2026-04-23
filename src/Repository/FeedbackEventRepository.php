<?php

namespace App\Repository;

use App\Entity\FeedbackEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeedbackEvent>
 */
class FeedbackEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeedbackEvent::class);
    }

    /**
     * @param int[] $packIds
     * @return array<int, array<string, int>>
     */
    public function getPackActionCounts(array $packIds, ?\DateTimeImmutable $since = null): array
    {
        if ($packIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.pack) AS pack_id, f.action AS action_name, COUNT(f.id) AS action_count')
            ->andWhere('IDENTITY(f.pack) IN (:packIds)')
            ->setParameter('packIds', $packIds)
            ->groupBy('f.pack, f.action');

        if ($since instanceof \DateTimeImmutable) {
            $qb->andWhere('f.created_at >= :since')
                ->setParameter('since', $since);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $matrix = [];

        foreach ($rows as $row) {
            $packId = (int) ($row['pack_id'] ?? 0);
            $actionName = (string) ($row['action_name'] ?? 'unknown');
            $matrix[$packId][$actionName] = (int) ($row['action_count'] ?? 0);
        }

        return $matrix;
    }
}
