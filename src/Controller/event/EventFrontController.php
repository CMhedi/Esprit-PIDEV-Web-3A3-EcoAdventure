<?php

namespace App\Controller\event;

use App\Entity\Evenement;
use App\Entity\EventRating;
use App\Entity\UserApp;
use App\Repository\EvenementRepository;
use App\Service\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/events')]
class EventFrontController extends AbstractController
{
    #[Route('/rate/{id_evenement}', name: 'app_event_rate', methods: ['POST'])]
    public function rate(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $user = $entityManager->getRepository(UserApp::class)->findOneBy(['email' => 'guest@ecoadventure.com']);
        }

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour noter un événement.');
            return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
        }

        $note = (int) $request->request->get('note', 5);
        if ($note < 1 || $note > 5) {
            $this->addFlash('error', 'La note doit être entre 1 et 5.');
            return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
        }

        // Check if already rated
        $existingRating = $entityManager->getRepository(EventRating::class)->findOneBy([
            'user' => $user,
            'evenement' => $evenement
        ]);

        if ($existingRating) {
            $existingRating->setNote($note);
            $existingRating->setCreatedAt(new \DateTime());
        } else {
            $rating = new EventRating();
            $rating->setUser($user);
            $rating->setEvenement($evenement);
            $rating->setNote($note);
            $entityManager->persist($rating);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Merci pour votre note !');
        return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
    }

    #[Route('/', name: 'app_event_front_index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenementRepository): Response
    {
        $search = $request->query->get('search');
        $categorie = $request->query->get('categorie');
        $lieu = $request->query->get('lieu');
        $sortBy = $request->query->get('sortBy', 'date_desc');

        // Handling pagination & sorting can go here, but for now we use the repository filters
        $events = $evenementRepository->findByFilters($search, $categorie, $lieu, $sortBy);

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
            'selected_lieu' => $lieu,
            'selected_sort' => $sortBy
        ]);
    }

    #[Route('/{id_evenement}', name: 'app_event_front_show', methods: ['GET'])]
    public function show(Evenement $evenement, WeatherService $weatherService): Response
    {
        // Compute available places properly
        $nbReservationsExistantes = 0;
        foreach ($evenement->getReservationEvenements() as $res) {
            if ($res->getStatut_res() !== \App\Enum\StatutReservationEvenement::ANNULEE) {
                $nbReservationsExistantes += $res->getNb_billets();
            }
        }
        $placesDispo = $evenement->getNb_places() - $nbReservationsExistantes;

        // Fetch Weather
        $weather = $weatherService->getWeather($evenement->getLieu());

        return $this->render('front/event/show.html.twig', [
            'evenement' => $evenement,
            'places_dispo' => $placesDispo,
            'weather' => $weather
        ]);
    }
}
