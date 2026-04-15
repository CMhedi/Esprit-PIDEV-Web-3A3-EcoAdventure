<?php
namespace App\Repository;

use App\Entity\NutritionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NutritionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NutritionLog::class);
    }

    // ===== ADD =====
    public function add(NutritionLog $log, bool $flush = false): void
    {
        $this->getEntityManager()->persist($log);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ===== DELETE =====
    public function remove(NutritionLog $log, bool $flush = false): void
    {
        $this->getEntityManager()->remove($log);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ===== GET BY USER =====
    public function getByUser(int $userId): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('IDENTITY(nl.user) = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('nl.log_Date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ===== TOTAL TODAY =====
    public function getTodayTotal(int $userId): float
    {
        $start = new \DateTime('today');
        $end = new \DateTime('tomorrow');

        $result = $this->createQueryBuilder('nl')
            ->select('SUM(nl.calories) as total')
            ->andWhere('IDENTITY(nl.user) = :userId')
            ->andWhere('nl.log_date >= :start')
            ->andWhere('nl.log_date < :end')
            ->setParameter('userId', $userId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getOneOrNullResult();

        return (float)($result['total'] ?? 0);
    }

    // ===== FIND BY DATE RANGE =====
    public function findByDateRange(int $userId, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('IDENTITY(nl.user) = :userId')
            ->andWhere('nl.log_date BETWEEN :start AND :end')
            ->setParameter('userId', $userId)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('nl.log_date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ===== THIS WEEK =====
    public function findThisWeek(int $userId): array
    {
        $start = (new \DateTime())->modify('monday this week')->setTime(0,0,0);
        $end = (new \DateTime())->modify('sunday this week')->setTime(23,59,59);

        return $this->findByDateRange($userId, $start, $end);
    }

    // ===== THIS MONTH =====
    public function findThisMonth(int $userId): array
    {
        $start = new \DateTime('first day of this month 00:00:00');
        $end = new \DateTime('last day of this month 23:59:59');

        return $this->findByDateRange($userId, $start, $end);
    }

    // ===== WEEKLY AVERAGE =====
    public function getWeeklyAverage(int $userId): float
    {
        $weekAgo = new \DateTime('-7 days');

        $result = $this->createQueryBuilder('nl')
            ->select('AVG(nl.calories) as average')
            ->andWhere('IDENTITY(nl.user) = :userId')
            ->andWhere('nl.log_date >= :weekAgo')
            ->setParameter('userId', $userId)
            ->setParameter('weekAgo', $weekAgo)
            ->getQuery()
            ->getOneOrNullResult();

        return (float)($result['average'] ?? 0);
    }

    // ===== TOTAL MACROS =====
    public function getTotalMacros(int $userId, ?\DateTime $date = null): array
    {
        $qb = $this->createQueryBuilder('nl')
            ->select('
                SUM(nl.calories) as calories,
                SUM(nl.protein) as protein,
                SUM(nl.fat) as fat,
                SUM(nl.carbs) as carbs
            ')
            ->andWhere('IDENTITY(nl.user) = :userId')
            ->setParameter('userId', $userId);

        if ($date) {
            $start = (clone $date)->setTime(0,0,0);
            $end = (clone $date)->setTime(23,59,59);

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
    }

    // ===== SEARCH BY FOOD =====
    public function findByFoodName(string $foodName): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('LOWER(nl.food_name) LIKE LOWER(:name)')
            ->setParameter('name', '%' . $foodName . '%')
            ->orderBy('nl.log_date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ===== COUNT =====
    public function countByUser(int $userId): int
    {
        return (int)$this->createQueryBuilder('nl')
            ->select('COUNT(nl.id)')
            ->andWhere('IDENTITY(nl.user) = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ===== DELETE OLD =====
    public function deleteOldLogs(int $days = 90): int
    {
        $date = new \DateTime("-$days days");

        return $this->createQueryBuilder('nl')
            ->delete()
            ->andWhere('nl.log_date < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}