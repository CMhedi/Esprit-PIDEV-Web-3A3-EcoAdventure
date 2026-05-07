<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AiDashboardController extends AbstractController
{
    #[Route('/admin/ai-dashboard/preview', name: 'ai_dashboard_preview')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_ai_dashboard');
    }
}
