<?php

namespace App\Controller;

use App\Repository\ReservationActiviteRepository;
use App\Repository\LocalisationRepository;
use App\Service\RouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TransportController extends AbstractController
{
    #[Route('/transport/{id}', name: 'app_transport_show')]
    public function show(
        int $id,
        Request $request,
        ReservationActiviteRepository $resRepo,
        LocalisationRepository $locRepo,
        RouteService $routeService
    ): Response {

        $reservation = $resRepo->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException("Réservation introuvable");
        }

        // 🔵 INPUTS
        $gouvernorat = $request->query->get('gouvernorat');
        $delegation = $request->query->get('delegation');
        $mode = $request->query->get('mode', 'car');

        // 🔥 RESET logique propre
        if ($request->query->has('gouvernorat') && !$request->query->has('delegation')) {
            $delegation = null;
        }

        // 🔵 LISTE GOUVERNORATS
        $gouvernorats = $locRepo->createQueryBuilder('l')
            ->select('DISTINCT l.gouvernorat')
            ->getQuery()
            ->getResult();

        // 🔵 LISTE DÉLÉGATIONS filtrées
        $delegations = [];

        if ($gouvernorat) {
            $delegations = $locRepo->createQueryBuilder('l')
                ->select('DISTINCT l.delegation')
                ->where('l.gouvernorat = :gov')
                ->setParameter('gov', $gouvernorat)
                ->getQuery()
                ->getResult();
        }

        // 🔵 RESULTATS
        $distance = null;
        $hours = 0;
        $minutes = 0;
        $geoJson = null;
        $error = null;

        if ($request->query->get('submit') && $delegation) {

            $loc = $locRepo->findOneBy([
                'gouvernorat' => $gouvernorat,
                'delegation' => $delegation
            ]);

            if (!$loc) {
                $error = "Localisation introuvable";
            } else {

                $activite = $reservation->getActivite();

                $route = $routeService->getRoute(
                    (float) $activite->getLatitude(),
                    (float) $activite->getLongitude(),
                    (float) $loc->getLatitude(),
                    (float) $loc->getLongitude()
                );

                $segment = $route['features'][0]['properties']['segments'][0] ?? null;

                if ($segment) {

                    $distance = $segment['distance'] / 1000;
                    $time = $segment['duration'] / 60;

                    $time = match ($mode) {
                        'moto' => $time * 0.85,
                        'bus' => $time * 1.25,
                        default => $time
                    };

                    $hours = floor($time / 60);
                    $minutes = round($time % 60);

                    $geoJson = $route['features'][0]['geometry'] ?? null;

                } else {
                    $error = "Aucun itinéraire trouvé";
                }
            }
        }

        return $this->render('transport/show.html.twig', [
            'reservation' => $reservation,
            'gouvernorats' => $gouvernorats,
            'delegations' => $delegations,
            'gouvernorat' => $gouvernorat,
            'delegation' => $delegation,
            'mode' => $mode,
            'distance' => $distance,
            'hours' => $hours,
            'minutes' => $minutes,
            'geoJson' => $geoJson,
            'error' => $error
        ]);
    }
}