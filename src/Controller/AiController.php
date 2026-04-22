<?php

namespace App\Controller;

use App\Service\AiDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AiController extends AbstractController
{
    #[Route('/admin/ai-dashboard', name: 'admin_ai_dashboard')]
    public function index(AiDashboardService $dashboard): Response
    {
        $topActivities = $dashboard->getTopActivities();

        return $this->render('admin/ai_dashboard.html.twig', [
            'topActivities' => $topActivities,
            'analysis' => $dashboard->generateAIInsight($topActivities),
            'prediction' => $dashboard->generatePrediction($topActivities),
        ]);
    }
}
