<?php

namespace App\AiBundle\Controller;

use App\AiBundle\Service\AiAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AiController extends AbstractController
{
    #[Route('/ai/local-dashboard', name: 'ai_local_dashboard')]
    public function index(EntityManagerInterface $em, AiAnalyzer $ai): Response
    {
        $conn = $em->getConnection();

        $data = $conn->executeQuery("
            SELECT a.nom, COUNT(r.id_res_act) AS total
            FROM activite a
            LEFT JOIN reservation_activite r 
            ON a.id_activite = r.id_activite
            GROUP BY a.id_activite, a.nom
        ")->fetchAllAssociative();

        return $this->render('@Ai/dashboard.html.twig', [
            'analysis' => $ai->analyze($data),
            'global' => $ai->globalInsight($data),
            'topActivities' => $data
        ]);
    }
}