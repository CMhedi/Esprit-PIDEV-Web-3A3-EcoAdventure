<?php

namespace App\Controller;

use App\Entity\ReservationSeance;
use App\Entity\Seance;
use App\Enum\StatutReservation;
use App\Enum\StatutPresence;
use App\Repository\ReservationSeanceRepository;
use App\Repository\SeanceRepository;
use App\Repository\UserAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\RecommendationService;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\GoogleCalendarService;

#[Route('/seance')]
class UserSeanceController extends AbstractController
{
    private LoggerInterface $logger;
    private ReservationSeanceRepository $reservationRepo;
    private SeanceRepository $seanceRepo;
    private EntityManagerInterface $entityManager;
    private UserAppRepository $userRepo;
    private RecommendationService $recommendationService;
    private GoogleCalendarService $googleService;

    public function __construct(
        LoggerInterface $logger,
        ReservationSeanceRepository $reservationRepo,
        SeanceRepository $seanceRepo,
        EntityManagerInterface $entityManager,
        UserAppRepository $userRepo,
        RecommendationService $recommendationService,
        GoogleCalendarService $googleService
    ) {
        $this->logger = $logger;
        $this->reservationRepo = $reservationRepo;
        $this->seanceRepo = $seanceRepo;
        $this->entityManager = $entityManager;
        $this->userRepo = $userRepo;
        $this->recommendationService = $recommendationService;
        $this->googleService = $googleService;
    }

    /**
     * Liste des séances
     */
    #[Route('', name: 'app_user_seances', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        try {
            $query = $this->seanceRepo->createQueryBuilder('s')
                ->orderBy('s.idSeance', 'DESC')
                ->getQuery();

            $seances = $paginator->paginate(
                $query,
                $request->query->getInt('page', 1),
                5
            );

            // 🔥 IMPORTANT : récupérer toutes les séances pour stats
            $allSeances = $this->seanceRepo->findAll();

            // 🔥 STATS GLOBALES
            $totalCapacite = 0;
            $totalReserve = 0;
            $disponibles = 0;

            foreach ($seances as $s) {
                $capacite = $s->getCapacite();
                $nbReservations = count($s->getReservationSeances());

                $totalCapacite += $capacite;
                $totalReserve += $nbReservations;

                if ($nbReservations < $capacite) {
                    $disponibles++;
                }
            }

            // 🔥 POURCENTAGE GLOBAL
            $pourcentage = $totalCapacite > 0
                ? round(($totalReserve / $totalCapacite) * 100)
                : 0;

            $this->logger->info('Page des séances consultée', [
                'total_seances' => count($seances),
                'pourcentage' => $pourcentage,
                'disponibles' => $disponibles,
                'allSeances' => $allSeances 
            ]);

            $user = $this->getUser();
            $recommendations = [];

            if ($user) {
                try {
                    $recommendations = $this->recommendationService
                        ->recommendForUser($user->getId_user());
                } catch (\Exception $e) {
                    $this->logger->error('Erreur recommandation', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $this->render('front/seances.html.twig', [
                'seances' => $seances,
                'pourcentage' => $pourcentage,
                'disponibles' => $disponibles,
                'recommendations' => $recommendations,
                'allSeances' => $allSeances 
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du chargement des séances', [
                'error' => $e->getMessage(),
            ]);

            $this->addFlash('error', 'Erreur lors du chargement des séances');
            return $this->redirectToRoute('app_user_seances');
        }
    }

    /**
     * Réserver une séance
     */
    #[Route('/{id}/reserver', name: 'app_reserver', methods: ['GET'])]
    public function reserver(Seance $seance): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                $this->addFlash('error', 'Utilisateur non connecté');
                return $this->redirectToRoute('app_user_seances');
            }

            // 🔍 DEBUG INFOS
            $alreadyReserved = $this->reservationRepo->isUserReserved($user, $seance);
            $capacityOk = $this->validateCapacity($seance);
            $statusOk = $this->validateSeanceStatus($seance);

            // 🔥 LOG DEBUG (très important)
            $this->logger->info('DEBUG RESERVATION', [
                'user_id' => $user->getId_user(),
                'seance_id' => $seance->getIdSeance(),
                'already_reserved' => $alreadyReserved,
                'capacity_ok' => $capacityOk,
                'status_ok' => $statusOk,
            ]);

            // 🔥 DEBUG VISUEL (affiché dans la page)
            $this->addFlash('info', 
                'DEBUG → reserved: ' . ($alreadyReserved ? 'YES' : 'NO') .
                ' | capacity: ' . ($capacityOk ? 'OK' : 'FULL') .
                ' | status: ' . ($statusOk ? 'OK' : 'NOT OK')
            );

            // ===== VALIDATIONS =====

            if ($alreadyReserved) {
                $this->addFlash('warning', '⚠️ Déjà réservé');
                return $this->redirectToRoute('app_user_seances');
            }

            if (!$capacityOk) {
                $this->addFlash('error', '❌ Séance complète');
                return $this->redirectToRoute('app_user_seances');
            }

            if (!$statusOk) {
                $this->addFlash('error', '❌ Séance non disponible');
                return $this->redirectToRoute('app_user_seances');
            }

            // ===== INSERTION =====

            $reservation = $this->createReservation($user, $seance);

            // 🔍 DEBUG OBJET
            $this->logger->info('DEBUG OBJECT', [
                'reservation' => $reservation
            ]);

            $this->entityManager->persist($reservation);
            $this->entityManager->flush();

            $this->addFlash('success', '✅ Réservation confirmée');

            return $this->redirectToRoute('app_user_seances');

        } catch (\Exception $e) {
            // 🔥 ERREUR EXACTE
            $this->logger->error('ERREUR COMPLETE', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 🔥 AFFICHAGE À L'ÉCRAN
            $this->addFlash('error', '💥 ERREUR: ' . $e->getMessage());

            return $this->redirectToRoute('app_user_seances');
        }
    }

    #[Route('/mes-seances', name: 'app_mes_seances', methods: ['GET'])]
    public function mesSeances(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Utilisateur non connecté');
            return $this->redirectToRoute('app_user_seances');
        }

        // 🔥 récupérer les réservations de l'utilisateur
        $reservations = $this->reservationRepo->findBy([
            'userApp' => $user
        ]);

        return $this->render('front/mes_seances.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('/reservation/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancelReservation(int $id): Response
    {
        try {
            $reservation = $this->reservationRepo->find($id);

            if (!$reservation) {
                return $this->handleError('Réservation introuvable');
            }

            $user = $this->getUser();

            if (!$user) {
                return $this->handleError('Utilisateur non connecté');
            }

            // 🔒 sécurité
            if ($reservation->getUserApp()->getId_user() !== $user->getId_user()) {
                return $this->handleError('Accès refusé', 'warning');
            }

            $seanceName = $reservation->getSeance()->getNom();

            // 🔥 SUPPRESSION GOOGLE CALENDAR
            if ($reservation->getGoogle_event_id()) {
                $token = json_decode($user->getGoogleToken(), true);

                if ($token) {
                    try {
                        $this->googleService->deleteEvent(
                            $token,
                            $reservation->getGoogle_event_id()
                        );
                    } catch (\Exception $e) {
                        // 🔥 ne bloque pas toute la fonction
                        $this->logger->error('Erreur suppression Google', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // 🗑️ suppression DB
            $this->entityManager->remove($reservation);
            $this->entityManager->flush();

            $this->addFlash('success', "Réservation pour '$seanceName' annulée");

        } catch (\Exception $e) {
            // 🔥 ERREUR EXACTE
            $this->logger->error('Erreur lors de l’annulation', [
                'error' => $e->getMessage()
            ]);

            return $this->handleError('Erreur lors de l’annulation');
        }

        return $this->redirectToRoute('app_mes_seances');
    }

    #[Route('/reservation/{id}/add-google', name: 'app_add_google')]
    public function addToGoogle(int $id, Request $request): Response
    {
        try {
            $reservation = $this->reservationRepo->find($id);

            if (!$reservation) {
                $this->addFlash('error', 'Réservation introuvable');
                return $this->redirectToRoute('app_mes_seances');
            }

            $user = $this->getUser();

            if (!$user) {
                $this->addFlash('error', 'Utilisateur non connecté');
                return $this->redirectToRoute('app_user_seances');
            }

            // 🔒 sécurité
            if ($reservation->getUserApp()->getId_user() !== $user->getId_user()) {
                $this->addFlash('error', 'Accès refusé');
                return $this->redirectToRoute('app_mes_seances');
            }

            // ⚠️ déjà ajouté ?
            if ($reservation->getGoogle_event_id()) {
                $this->addFlash('info', 'Déjà ajouté à Google Calendar');
                return $this->redirectToRoute('app_mes_seances');
            }

            // 🔑 token
            $token = json_decode($user->getGoogleToken(), true);

            // ❌ pas connecté → redirection Google avec state
            if (!$token) {
                return $this->redirectToRoute('google_connect', [
                    'reservationId' => $id,
                    'userId' => $user->getId_user()
                ]);
            }

            $seance = $reservation->getSeance();

            $timezone = new \DateTimeZone('Africa/Tunis');

            $start = new \DateTime(
                $seance->getDateSeance()->format('Y-m-d') . ' ' .
                $seance->getHeureDebut()->format('H:i:s'),
                $timezone
            );

            $end = new \DateTime(
                $seance->getDateSeance()->format('Y-m-d') . ' ' .
                $seance->getHeureFin()->format('H:i:s'),
                $timezone
            );

            // 📅 création event
            $result = $this->googleService->addEvent(
                $token,
                "Séance : " . $seance->getNom(),
                "EcoAdventure",
                $start,
                $end
            );

            // 💾 sauvegarde
            $reservation->setGoogle_event_id($result['id']);
            $reservation->setGoogle_event_link($result['htmlLink']);

            $this->entityManager->flush();

            $this->addFlash('success', 'Ajouté à Google Calendar');

        } catch (\Exception $e) {
            $this->logger->error('Erreur Google Calendar', [
                'error' => $e->getMessage()
            ]);

            $this->addFlash('error', 'Erreur Google Calendar');
        }

        return $this->redirectToRoute('app_mes_seances');
    }

    #[Route('/google/connect', name: 'google_connect')]
    public function connect(Request $request): Response
    {
        $client = $this->googleService->getClient();

        $reservationId = $request->query->get('reservationId');
        $userId = $request->query->get('userId');

        $state = json_encode([
            'reservationId' => $reservationId,
            'userId' => $userId
        ]);

        $client->setState($state);

        return $this->redirect($client->createAuthUrl());
    }

    #[Route('/oauth/callback', name: 'google_callback')]
    public function callback(Request $request): Response
    {
        $client = $this->googleService->getClient();

        $token = $client->fetchAccessTokenWithAuthCode(
            $request->get('code')
        );

        // 🔥 récupération state
        $state = json_decode($request->get('state'), true);

        if (!$state || !isset($state['userId'], $state['reservationId'])) {
            return new Response('State invalide');
        }

        $user = $this->userRepo->find($state['userId']);

        if (!$user) {
            return new Response('Utilisateur introuvable');
        }

        // 💾 sauvegarde token
        $user->setGoogleToken(json_encode($token));
        $this->entityManager->flush();

        // 🔁 relancer ajout automatique
        return $this->redirectToRoute('app_add_google', [
            'id' => $state['reservationId']
        ]);
    }

    // ========== PRIVATE METHODS ==========

    private function validateUser($user): bool
    {
        return $user !== null;
    }

    private function validateNotAlreadyReserved($user, Seance $seance): bool
    {
        return !$this->reservationRepo->isUserReserved($user, $seance);
    }

    private function validateCapacity(Seance $seance): bool
    {
        $reservedCount = $this->reservationRepo->countReservations($seance);
        return $reservedCount < $seance->getCapacite();
    }

    private function validateSeanceStatus(Seance $seance): bool
    {
        return $seance->getStatutSeance()->value === 'PLANIFIEE';
    }

    private function createReservation($user, Seance $seance): ReservationSeance
    {
        $reservation = new ReservationSeance();

        $reservation->setUserApp($user);
        $reservation->setSeance($seance);
        $reservation->setDate_reservation(new \DateTime());
        $reservation->setStatut(StatutReservation::CONFIRMEE);
        $reservation->setStatut_presence(StatutPresence::ABSENT);

        return $reservation;
    }

    private function handleError(string $message, string $type = 'error'): Response
    {
        $this->logger->warning($message);
        $this->addFlash($type, $message);

        return $this->redirectToRoute('app_user_seances');
    }
}