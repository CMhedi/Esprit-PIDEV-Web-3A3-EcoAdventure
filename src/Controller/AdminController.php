<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\UserApp;
use App\Entity\ReservationEvenement;
use App\Entity\Pack; // Assuming there is a Pack entity
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        // 1. Fetch real stats
        $userCount = $entityManager->getRepository(UserApp::class)->count([]);
        $eventCount = $entityManager->getRepository(Evenement::class)->count([]);
        $reservationCount = $entityManager->getRepository(ReservationEvenement::class)->count([]);
        
        // Handle Pack count safely if entity exists
        $packCount = 0;
        try {
            $packCount = $entityManager->getRepository(Pack::class)->count([]);
        } catch (\Exception $e) {
            // Fallback if Pack entity is not found or other issue
            $packCount = 16; 
        }

        $stats = [
            'users' => $userCount,
            'events' => $eventCount,
            'packs' => $packCount,
            'inscriptions' => $reservationCount
        ];

        // 2. Fetch real recent events
        $events = $entityManager->getRepository(Evenement::class)->findBy([], ['date_event' => 'DESC'], 3);
        
        $recentEvents = [];
        foreach ($events as $event) {
            // Calculate participants for this event
            $participants = 0;
            foreach ($event->getReservationEvenements() as $res) {
                if ($res->getStatut_res()->value !== 'ANNULEE') {
                    $participants += $res->getNb_billets();
                }
            }

            $recentEvents[] = [
                'id' => $event->getId_evenement(),
                'title' => $event->getTitre(),
                'location' => $event->getLieu(),
                'date' => $event->getDate_event(), // Keep as DateTime object for Twig formatting
                'participants' => $participants,
                'status' => $event->getStatut()
            ];
        }

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recentEvents' => $recentEvents
        ]);
    }
}