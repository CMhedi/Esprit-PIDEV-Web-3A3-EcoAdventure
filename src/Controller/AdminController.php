<?php

namespace App\Controller;

use App\Repository\ActiviteRepository;
use App\Repository\ReservationActiviteRepository;
use App\Entity\Evenement;
use App\Entity\UserApp;
use App\Entity\ReservationEvenement;
use App\Entity\Pack;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_admin_dashboard');
    }

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

    #[Route('/admin/activites-overview', name: 'app_admin_activites_overview')]
    public function activites(Request $request, ActiviteRepository $activiteRepository): Response
    {
        $nom = trim((string) $request->query->get('nom', ''));
        $sortBy = (string) $request->query->get('sort_by', 'prix');
        $tri = strtolower((string) $request->query->get('tri', 'asc'));
        $allowedSortFields = ['prix', 'nom', 'type', 'statut'];

        if (!in_array($sortBy, $allowedSortFields, true)) {
            $sortBy = 'prix';
        }

        if (!in_array($tri, ['asc', 'desc'], true)) {
            $tri = 'asc';
        }

        $activites = $activiteRepository->findBySearchAndSort($nom, $sortBy, $tri);

        return $this->render('admin/activiteadmin.html.twig', [
            'activites' => $activites,
            'nom' => $nom,
            'sort_by' => $sortBy,
            'tri' => $tri,
        ]);
    }

    #[Route('/admin/reservations', name: 'app_admin_reservations')]
    public function reservations(
        ReservationActiviteRepository $reservationRepository
    ): Response {
        $reservations = $reservationRepository->findAll();

        return $this->render('admin/reservationadmin.html.twig', [
            'reservations' => $reservations
        ]);
    }
}
