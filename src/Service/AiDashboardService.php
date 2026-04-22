<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class AiDashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GroqAiService $ai
    ) {
    }

    public function getTopActivities(): array
    {
        $conn = $this->em->getConnection();

        $sql = "
            SELECT a.nom AS nom, COUNT(r.id_res_act) AS total
            FROM activite a
            LEFT JOIN reservation_activite r
                ON r.id_activite = a.id_activite
            GROUP BY a.id_activite, a.nom
            ORDER BY total DESC
            LIMIT 5
        ";

        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    public function generateAIInsight(array $topActivities = []): string
    {
        if ($topActivities === []) {
            $topActivities = $this->getTopActivities();
        }

        if ($topActivities === []) {
            return 'Aucune donnee disponible.';
        }

        return $this->ai->analyzeActivities($topActivities);
    }

    public function generatePrediction(array $topActivities = []): string
    {
        if ($topActivities === []) {
            $topActivities = $this->getTopActivities();
        }

        if ($topActivities === []) {
            return 'Pas assez de donnees pour predire.';
        }

        return $this->ai->predict($topActivities);
    }
}
