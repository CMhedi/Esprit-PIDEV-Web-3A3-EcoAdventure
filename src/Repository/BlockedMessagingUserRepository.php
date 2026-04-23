<?php

namespace App\Repository;

use App\Entity\BlockedMessagingUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlockedMessagingUser>
 */
class BlockedMessagingUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedMessagingUser::class);
    }

    /**
     * @return int[]
     */
    public function findActiveBlockedUserIds(): array
    {
        $rows = $this->createQueryBuilder('bmu')
            ->select('IDENTITY(bmu.userApp) AS user_id')
            ->andWhere('bmu.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_unique(array_map(static fn (array $row): int => (int) ($row['user_id'] ?? 0), $rows)));
    }
}
