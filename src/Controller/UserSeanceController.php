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
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route('/seance')]
class UserSeanceController extends AbstractController
{
    private LoggerInterface $logger;
    private ReservationSeanceRepository $reservationRepo;
    private SeanceRepository $seanceRepo;
    private EntityManagerInterface $entityManager;
    private UserAppRepository $userRepo;

    public function __construct(
        LoggerInterface $logger,
        ReservationSeanceRepository $reservationRepo,
        SeanceRepository $seanceRepo,
        EntityManagerInterface $entityManager,
        UserAppRepository $userRepo
    ) {
        $this->logger = $logger;
        $this->reservationRepo = $reservationRepo;
        $this->seanceRepo = $seanceRepo;
        $this->entityManager = $entityManager;
        $this->userRepo = $userRepo;
    }

    /**
     * Liste des séances
     */
   #[Route('', name: 'app_user_seances', methods: ['GET'])]
public function index(): Response
{
    try {
        $seances = $this->seanceRepo->findAll();

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
        ]);

        return $this->render('front/seances.html.twig', [
            'seances' => $seances,
            'pourcentage' => $pourcentage,
            'disponibles' => $disponibles,
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
        $user = $this->userRepo->find(1);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable');
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
    #[Route('/mes-seances', name: 'app_mes_seances', methods: ['GET'])]
public function mesSeances(): Response
{
    // 🔥 utilisateur temporaire
    $user = $this->userRepo->find(1);

    if (!$user) {
        $this->addFlash('error', 'Utilisateur introuvable');
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
            $this->addFlash('error', 'Réservation introuvable');
            return $this->redirectToRoute('app_mes_seances');
        }

        $user = $this->userRepo->find(1);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable');
            return $this->redirectToRoute('app_mes_seances');
        }

        if ($reservation->getUserApp()->getId_user() !== $user->getId_user()) {
            $this->addFlash('error', 'Accès refusé');
            return $this->redirectToRoute('app_mes_seances');
        }

        $seanceName = $reservation->getSeance()->getNom();

        $this->entityManager->remove($reservation);
        $this->entityManager->flush();

        $this->addFlash('success', "Réservation pour '$seanceName' annulée");

    } catch (\Exception $e) {
        $this->addFlash('error', 'Erreur lors de l’annulation');
    }

    return $this->redirectToRoute('app_mes_seances');
}
}