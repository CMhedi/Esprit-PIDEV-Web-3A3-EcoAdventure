<?php

namespace App\Controller;

use App\Service\GroqAiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AiDashboardController extends AbstractController
{
    #[Route('/admin/ai-dashboard', name: 'ai_dashboard')]
    public function index(
        EntityManagerInterface $em,
        GroqAiService $ai
    ): Response {

        $conn = $em->getConnection();

        // 🔥 TOP ACTIVITIES SQL
        $topActivities = $conn->executeQuery("
            SELECT a.nom, COUNT(r.id_res_act) AS total
            FROM activite a
            LEFT JOIN reservation_activite r 
                ON a.id_activite = r.id_activite
            GROUP BY a.id_activite, a.nom
            ORDER BY total DESC
            LIMIT 5
        ")->fetchAllAssociative();

        // 🧠 IA ANALYSIS
        $analysis = $ai->analyzeActivities($topActivities);

        // 📈 IA PREDICTION
        $prediction = $ai->predict($topActivities);

        return $this->render('admin/ai_dashboard.html.twig', [
            'topActivities' => $topActivities,
            'analysis' => $analysis,
            'prediction' => $prediction
        ]);
    }
}