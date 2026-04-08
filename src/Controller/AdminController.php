<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        $stats = [
            'users' => 576,
            'events' => 48,
            'packs' => 16,
            'inscriptions' => 1235
        ];

        $recentEvents = [
            [
                'title' => 'Randonnée en Montagne',
                'location' => 'Ain Draham',
                'date' => '12/04/2026',
                'participants' => 75,
                'status' => 'Active'
            ],
            [
                'title' => 'Kayak Adventure',
                'location' => 'Bizerte',
                'date' => '20/04/2026',
                'participants' => 53,
                'status' => 'À venir'
            ],
            [
                'title' => 'Escalade en Pleine Nature',
                'location' => 'Zaghouan',
                'date' => '28/04/2026',
                'participants' => 34,
                'status' => 'Clôturée'
            ]
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recentEvents' => $recentEvents
        ]);
    }
}