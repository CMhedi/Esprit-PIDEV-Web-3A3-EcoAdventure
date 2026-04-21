<?php

namespace App\Controller\event;

use App\Entity\Evenement;
use App\Entity\EventRating;
use App\Entity\UserApp;
use App\Repository\EvenementRepository;
use App\Service\WeatherService;
use App\Service\ReservationPricingService;
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
        $onlyAvailable = $request->query->getBoolean('disponible', false);
        $page = $request->query->getInt('page', 1);
        $limit = 6; // N events per page

        $eventsPaginator = $evenementRepository->findByFilters($search, $categorie, $lieu, $sortBy, $onlyAvailable, $page, $limit);
        $totalEvents = count($eventsPaginator);
        $totalPages = ceil($totalEvents / $limit);

        $lieux = $evenementRepository->findDistinctLieux();

        if ($request->isXmlHttpRequest()) {
            return $this->render('front/event/_list.html.twig', [
                'events' => $eventsPaginator,
                'current_page' => $page,
                'total_pages' => $totalPages
            ]);
        }

        return $this->render('front/event/events.html.twig', [
            'events' => $eventsPaginator,
            'lieux'  => $lieux,
            'search' => $search,
            'selected_cat' => $categorie,
            'selected_lieu' => $lieu,
            'selected_sort' => $sortBy,
            'only_available' => $onlyAvailable,
            'current_page' => $page,
            'total_pages' => $totalPages
        ]);
    }

    #[Route('/{id_evenement}', name: 'app_event_front_show', methods: ['GET'])]
    public function show(Evenement $evenement, WeatherService $weatherService, ReservationPricingService $pricingService, \App\Service\AiEventOptimizerService $aiOptimizer): Response
    {
        // Compute available places properly
        $placesDispo = $evenement->getPlacesRestantes();
        
        $reservationsAttente = 0;
        foreach ($evenement->getReservationEvenements() as $res) {
            if ($res->getStatut_res() === \App\Enum\StatutReservationEvenement::LISTE_ATTENTE) {
                $reservationsAttente += $res->getNb_billets();
            }
        }
        $attenteDispo = $evenement->getLimite_attente() - $reservationsAttente;

        // Fetch Weather
        $weather = $weatherService->getWeather($evenement->getLieu());
        
        // IA Alerte Météo
        $weatherAiAlert = null;
        if ($weather) {
            $weatherAiAlert = $aiOptimizer->getWeatherAiAlert($weather, $evenement);
        }

        // 🤖 Récupérer une recommandation IA si l'événement est complet
        $aiRecommendation = null;
        if ($placesDispo <= 0) {
            $aiRecommendation = $aiOptimizer->getSimilarAvailableEvent($evenement);
        }

        return $this->render('front/event/show.html.twig', [
            'evenement' => $evenement,
            'places_dispo' => $placesDispo,
            'attente_dispo' => $attenteDispo,
            'weather' => $weather,
            'weather_ai_alert' => $weatherAiAlert,
            'promo_threshold' => $pricingService->getPromoThreshold(),
            'promo_discount' => $pricingService->getPromoDiscount(),
            'ai_recommendation' => $aiRecommendation
        ]);
    }
}
