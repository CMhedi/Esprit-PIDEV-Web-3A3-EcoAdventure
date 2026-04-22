<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class AiDashboardService
{
    private EntityManagerInterface $em;
    private AiService $ai;

    public function __construct(EntityManagerInterface $em, AiService $ai)
    {
        $this->em = $em;
        $this->ai = $ai;
    }

    // 🔥 TOP ACTIVITES
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

    // 🤖 ANALYSE IA
    public function generateAIInsight(): string
    {
        $top = $this->getTopActivities();

        if (empty($top)) {
            return "Aucune donnée disponible.";
        }

        $prompt = "Analyse ces activités et donne des recommandations:\n";

        foreach ($top as $a) {
            $prompt .= "- {$a['nom']} : {$a['total']} réservations\n";
        }

        return $this->ai->generateInsight($prompt);
    }
}