<?php

namespace App\Repository;

use App\Entity\UserApp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\RoleUser;
/**
 * @extends ServiceEntityRepository<UserApp>
 */
class UserAppRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserApp::class);
    }


public function findCoaches()
{
    return $this->createQueryBuilder('u')
        ->where('u.role = :role')
        ->setParameter('role', RoleUser::COACH)
        ->getQuery()
        ->getResult();
}
}
