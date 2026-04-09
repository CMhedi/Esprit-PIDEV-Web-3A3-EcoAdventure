<?php

namespace App\Controller;

use App\Repository\ActiviteRepository;
use App\Repository\ReservationActiviteRepository;
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
