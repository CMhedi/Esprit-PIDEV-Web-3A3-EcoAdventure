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
public function getUserFeatures(int $userId): array
{
    $conn = $this->getEntityManager()->getConnection();

    $userId = (int) $userId; // sécurité

    $sql = "
    SELECT 
        u.id_user,

        COUNT(CASE 
            WHEN s.date_seance >= NOW() - INTERVAL 7 DAY THEN r.id_reservation 
        END) AS nb_reservations_7j,

        COUNT(CASE 
            WHEN r.statut_presence = 'ABSENT' 
            AND s.date_seance >= NOW() - INTERVAL 30 DAY 
            THEN 1 END
        ) AS nb_absences_30j,

        (
            COUNT(CASE 
                WHEN r.statut_presence = 'ABSENT' 
                AND s.date_seance >= NOW() - INTERVAL 30 DAY 
                THEN 1 END
            ) / NULLIF(COUNT(CASE 
                WHEN s.date_seance >= NOW() - INTERVAL 30 DAY THEN 1 END
            ), 0)
        ) AS taux_absence,

        COUNT(DISTINCT CASE 
            WHEN s.date_seance >= NOW() - INTERVAL 30 DAY 
            THEN DATE(s.date_seance)
        END) AS nb_jours_actifs_30j,

        (
            COUNT(CASE WHEN s.date_seance >= NOW() - INTERVAL 7 DAY THEN 1 END)
            -
            COUNT(CASE 
                WHEN s.date_seance BETWEEN NOW() - INTERVAL 30 DAY 
                AND NOW() - INTERVAL 7 DAY THEN 1 END)
        ) AS trend_reservations,

        (
            COUNT(CASE 
                WHEN r.statut_presence = 'ABSENT' 
                AND s.date_seance >= NOW() - INTERVAL 7 DAY THEN 1 END)
            -
            COUNT(CASE 
                WHEN r.statut_presence = 'ABSENT' 
                AND s.date_seance BETWEEN NOW() - INTERVAL 30 DAY 
                AND NOW() - INTERVAL 7 DAY THEN 1 END)
        ) AS trend_absence,

        (
            SELECT AVG(n.calories) 
            FROM nutrition_log n 
            WHERE n.user_id = u.id_user 
            AND n.log_date >= NOW() - INTERVAL 7 DAY
        ) AS avg_calories_7j,

        (
            SELECT AVG(n.protein) 
            FROM nutrition_log n 
            WHERE n.user_id = u.id_user 
            AND n.log_date >= NOW() - INTERVAL 7 DAY
        ) AS avg_protein_7j,

        (
            SELECT AVG(n.carbs) 
            FROM nutrition_log n 
            WHERE n.user_id = u.id_user 
            AND n.log_date >= NOW() - INTERVAL 7 DAY
        ) AS avg_carbs_7j,

        DATEDIFF(NOW(), u.date_creation) AS days_since_signup

    FROM user_app u
    LEFT JOIN reservation_seance r ON r.id_user = u.id_user
    LEFT JOIN seance s ON s.id_seance = r.id_seance

    WHERE u.id_user = $userId -- 🔥 FIX ICI
    GROUP BY u.id_user
    ";

    return $conn->executeQuery($sql)->fetchAssociative() ?: [];
}
public function getAllUsersFeatures(): array
{
    $conn = $this->getEntityManager()->getConnection();

    $sql = "
    SELECT 
        u.id_user,

        COUNT(CASE WHEN s.date_seance >= NOW() - INTERVAL 7 DAY THEN 1 END) AS nb_reservations_7j,

        COUNT(CASE 
            WHEN r.statut_presence = 'ABSENT' 
            AND s.date_seance >= NOW() - INTERVAL 30 DAY 
            THEN 1 END
        ) AS nb_absences_30j,

        (
            COUNT(CASE 
                WHEN r.statut_presence = 'ABSENT' 
                AND s.date_seance >= NOW() - INTERVAL 30 DAY 
                THEN 1 END
            ) / NULLIF(COUNT(CASE 
                WHEN s.date_seance >= NOW() - INTERVAL 30 DAY THEN 1 END
            ), 0)
        ) AS taux_absence,

        COUNT(DISTINCT DATE(s.date_seance)) AS nb_jours_actifs_30j,

        0 AS trend_reservations,
        0 AS trend_absence,

        COALESCE(AVG(n.calories), 0) AS avg_calories_7j,
        COALESCE(AVG(n.protein), 0) AS avg_protein_7j,
        COALESCE(AVG(n.carbs), 0) AS avg_carbs_7j,

        DATEDIFF(NOW(), u.date_creation) AS days_since_signup

    FROM user_app u
    LEFT JOIN reservation_seance r ON r.id_user = u.id_user
    LEFT JOIN seance s ON s.id_seance = r.id_seance
    LEFT JOIN nutrition_log n ON n.user_id = u.id_user

    GROUP BY u.id_user
    ";

    return $conn->executeQuery($sql)->fetchAllAssociative();
}
}
