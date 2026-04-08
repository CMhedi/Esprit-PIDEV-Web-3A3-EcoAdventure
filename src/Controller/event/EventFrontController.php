<?php

namespace App\Controller\event;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/events')]
class EventFrontController extends AbstractController
{
    #[Route('/', name: 'app_event_front_index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenementRepository): Response
    {
        $search = $request->query->get('search');
        $categorie = $request->query->get('categorie');
        $lieu = $request->query->get('lieu');

        // Handling pagination & sorting can go here, but for now we use the repository filters
        $events = $evenementRepository->findByFilters($search, $categorie, $lieu);

        // Fetch distinct locations for filter dropdown
        $lieux = $evenementRepository->findDistinctLieux();

        // Check if AJAX request for real-time search
        if ($request->isXmlHttpRequest()) {
            return $this->render('front/event/_list.html.twig', [
                'events' => $events,
            ]);
        }

        return $this->render('front/event/events.html.twig', [
            'events' => $events,
            'lieux'  => $lieux,
            'search' => $search,
            'selected_cat' => $categorie,
            'selected_lieu' => $lieu
        ]);
    }

    #[Route('/{id_evenement}', name: 'app_event_front_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        // Compute available places properly (сум of nb_billets, not just row count)
        $nbReservationsExistantes = 0;
        foreach ($evenement->getReservationEvenements() as $res) {
            if ($res->getStatut_res() !== \App\Enum\StatutReservationEvenement::ANNULEE) {
                $nbReservationsExistantes += $res->getNb_billets();
            }
        }
        $placesDispo = $evenement->getNb_places() - $nbReservationsExistantes;

        return $this->render('front/event/show.html.twig', [
            'evenement' => $evenement,
            'places_dispo' => $placesDispo,
        ]);
    }
}
