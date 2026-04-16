<?php

namespace App\Repository;

use App\Entity\NutritionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NutritionLog>
 */
class NutritionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NutritionLog::class);
    }

    /**
     * Récupère tous les logs d'un utilisateur
     */
    public function getByUser(int $userId): array
    {
        try {
            return $this->createQueryBuilder('nl')
                ->where('nl.user = :userId')
                ->setParameter('userId', $userId)
                ->orderBy('nl.log_date', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log('getByUser error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère le total des calories du jour
     */
    public function getTodayTotal(int $userId): float
    {
        try {
            $start = new \DateTime('today');
            $end = new \DateTime('tomorrow');

            $result = $this->createQueryBuilder('nl')
                ->select('SUM(nl.calories) as total')
                ->where('nl.user = :userId')
                ->andWhere('nl.log_date >= :start')
                ->andWhere('nl.log_date < :end')
                ->setParameter('userId', $userId)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getOneOrNullResult();

            return (float)($result['total'] ?? 0);
        } catch (\Exception $e) {
            error_log('getTodayTotal error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Récupère les macros totales d'un jour/période
     */
    public function getTotalMacros(int $userId, ?\DateTime $date = null): array
    {
        try {
            $qb = $this->createQueryBuilder('nl')
                ->select('
                    SUM(nl.calories) as calories,
                    SUM(nl.protein) as protein,
                    SUM(nl.fat) as fat,
                    SUM(nl.carbs) as carbs
                ')
                ->where('nl.user = :userId')
                ->setParameter('userId', $userId);

            if ($date) {
                $start = (clone $date)->setTime(0, 0, 0);
                $end = (clone $date)->setTime(23, 59, 59);
                $qb->andWhere('nl.log_date BETWEEN :start AND :end')
                   ->setParameter('start', $start)
                   ->setParameter('end', $end);
            }

            $result = $qb->getQuery()->getOneOrNullResult();

            return [
                'calories' => (float)($result['calories'] ?? 0),
                'protein' => (float)($result['protein'] ?? 0),
                'fat' => (float)($result['fat'] ?? 0),
                'carbs' => (float)($result['carbs'] ?? 0),
            ];
        } catch (\Exception $e) {
            error_log('getTotalMacros error: ' . $e->getMessage());
            return ['calories' => 0, 'protein' => 0, 'fat' => 0, 'carbs' => 0];
        }
    }

    /**
     * Récupère les logs dans une plage de dates
     */
    public function findByDateRange(int $userId, \DateTime $start, \DateTime $end): array
    {
        try {
            return $this->createQueryBuilder('nl')
                ->where('nl.user = :userId')
                ->andWhere('nl.log_date >= :start')
                ->andWhere('nl.log_date < :end')
                ->setParameter('userId', $userId)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->orderBy('nl.log_date', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log('findByDateRange error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les logs de la semaine actuelle
     */
    public function findThisWeek(int $userId): array
    {
        try {
            $start = (new \DateTime())->modify('monday this week')->setTime(0, 0, 0);
            $end = (new \DateTime())->modify('sunday this week')->setTime(23, 59, 59);

            return $this->createQueryBuilder('nl')
                ->where('nl.user = :userId')
                ->andWhere('nl.log_date BETWEEN :start AND :end')
                ->setParameter('userId', $userId)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->orderBy('nl.log_date', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log('findThisWeek error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère la moyenne de calories par jour de la semaine
     */
    public function getWeeklyAverage(int $userId): float
    {
        try {
            $weekAgo = new \DateTime('-7 days');

            $result = $this->createQueryBuilder('nl')
                ->select('AVG(nl.calories) as average')
                ->where('nl.user = :userId')
                ->andWhere('nl.log_date >= :weekAgo')
                ->setParameter('userId', $userId)
                ->setParameter('weekAgo', $weekAgo)
                ->getQuery()
                ->getOneOrNullResult();

            return (float)($result['average'] ?? 0);
        } catch (\Exception $e) {
            error_log('getWeeklyAverage error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Récupère les logs du mois en cours
     */
    public function findThisMonth(int $userId): array
    {
        try {
            $start = (new \DateTime())->modify('first day of this month')->setTime(0, 0, 0);
            $end = (new \DateTime())->modify('last day of this month')->setTime(23, 59, 59);

            return $this->createQueryBuilder('nl')
                ->where('nl.user = :userId')
                ->andWhere('nl.log_date BETWEEN :start AND :end')
                ->setParameter('userId', $userId)
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->orderBy('nl.log_date', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log('findThisMonth error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les logs par nom d'aliment
     */
    public function findByFoodName(string $foodName): array
    {
        try {
            return $this->createQueryBuilder('nl')
                ->where('nl.food_name LIKE :foodName')
                ->setParameter('foodName', '%' . $foodName . '%')
                ->orderBy('nl.log_date', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log('findByFoodName error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ajouter un log
     */
    public function add(NutritionLog $log, bool $flush = false): void
    {
        $this->getEntityManager()->persist($log);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprimer un log
     */
    public function remove(NutritionLog $log, bool $flush = false): void
    {
        $this->getEntityManager()->remove($log);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}