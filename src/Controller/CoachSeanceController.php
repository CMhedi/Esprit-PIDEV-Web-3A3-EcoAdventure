<?php

namespace App\Controller;

use App\Entity\Seance;
use App\Entity\ReservationSeance;
use App\Repository\SeanceRepository;
use App\Repository\ReservationSeanceRepository;
use App\Repository\UserAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
#[Route('/coach')]
class CoachSeanceController extends AbstractController
{
    private LoggerInterface $logger;
    private SeanceRepository $seanceRepo;
    private ReservationSeanceRepository $reservationRepo;
    private EntityManagerInterface $entityManager;
    private UserAppRepository $userRepo;

    public function __construct(
        LoggerInterface $logger,
        SeanceRepository $seanceRepo,
        ReservationSeanceRepository $reservationRepo,
        EntityManagerInterface $entityManager,
        UserAppRepository $userRepo
    ) {
        $this->logger = $logger;
        $this->seanceRepo = $seanceRepo;
        $this->reservationRepo = $reservationRepo;
        $this->entityManager = $entityManager;
        $this->userRepo = $userRepo;
    }

  #[Route('/seances', name: 'app_coach_seances', methods: ['GET'])]
    public function seances(): Response
    {
        try {
            // 🔥 COACH STATIQUE (temporaire)
           $coach = $this->getUser();
           if (!$coach) {
    $this->addFlash('error', 'Utilisateur non connecté');
    return $this->redirectToRoute('app_coach_seances');
} // À adapter

            if (!$coach) {
                $this->addFlash('error', 'Coach introuvable');
                return $this->redirectToRoute('app_coach_seances');
            }

            // Récupérer toutes les séances du coach
            $seances = $this->seanceRepo->findByCoach($coach);

            // Calculer les stats
            $stats = $this->calculateStats($seances);

            $this->logger->info('Page des séances du coach consultée', [
                'coach_id' => $coach->getId_user(),
                'total_seances' => count($seances),
            ]);

            return $this->render('front/coach_seances.html.twig', [
                'seances' => $seances,
                'stats' => $stats,
                'coach' => $coach,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du chargement des séances', [
                'error' => $e->getMessage(),
            ]);

            $this->addFlash('error', 'Erreur lors du chargement des séances');
            return $this->redirectToRoute('app_coach_seances');
        }
    }

#[Route('/seance/{id}', name: 'app_coach_seance_detail', methods: ['GET'])]
public function detail(int $id): Response
{
    try {
        $seance = $this->seanceRepo->find($id);

        if (!$seance) {
            $this->addFlash('error', 'Séance introuvable');
            return $this->redirectToRoute('app_coach_seances');
        }

        $coach = $this->getUser();
        if (!$coach) {
            $this->addFlash('error', 'Utilisateur non connecté');
            return $this->redirectToRoute('app_coach_seances');
        }

        if ($seance->getCoach()->getId_user() !== $coach->getId_user()) {
            $this->addFlash('error', 'Accès refusé');
            return $this->redirectToRoute('app_coach_seances');
        }

        $participants = $this->reservationRepo->findSeanceReservations($seance);

        $seanceStats = [
            'total_places' => $seance->getCapacite(),
            'places_reservees' => count($participants),
            'places_restantes' => $seance->getCapacite() - count($participants),
            'taux_occupation' => round((count($participants) / $seance->getCapacite()) * 100),
            'presents' => count(array_filter($participants, fn($r) => $r->getStatut_presence()->value === 'PRESENT')),
            'absents' => count(array_filter($participants, fn($r) => $r->getStatut_presence()->value === 'ABSENT')),
        ];

        return $this->render('front/coach_seance_detail.html.twig', [
            'seance' => $seance,
            'participants' => $participants,
            'seanceStats' => $seanceStats,
        ]);

    } catch (\Exception $e) {
        dd($e->getMessage()); // 🔥 DEBUG TEMPORAIRE
    }
}

#[Route('/seance/participant/{id}/presence', name: 'app_coach_update_presence', methods: ['POST'])]
public function updatePresence(int $id): Response
{
    try {
        $reservation = $this->reservationRepo->find($id);

        if (!$reservation) {
            return new JsonResponse(['error' => 'Réservation introuvable'], 404);
        }

       $coach = $this->getUser();
      

if (!$coach) {
    $this->addFlash('error', 'Utilisateur non connecté');
    return $this->redirectToRoute('app_coach_seances');
}

        if ($reservation->getSeance()->getCoach()->getId_user() !== $coach->getId_user()) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        // 🔥 SIMPLE ET PROPRE (pas besoin de reflection)
        $currentStatus = $reservation->getStatut_presence()->value;

        $newStatus = $currentStatus === 'PRESENT'
            ? \App\Enum\StatutPresence::ABSENT
            : \App\Enum\StatutPresence::PRESENT;

        $reservation->setStatut_presence($newStatus);

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => "Statut mis à jour",
            'status' => $newStatus->value,
        ]);

    } catch (\Exception $e) {

        dd($e->getMessage()); // 🔥 pour voir l'erreur exacte

        return new JsonResponse([
            'error' => 'Erreur lors de la mise à jour',
        ], 500);
    }
}



#[Route('/export-pdf', name: 'app_coach_export_pdf', methods: ['GET'])]
public function exportPdf(): Response
{
    try {
        // 🔥 COACH STATIQUE
       $coach = $this->getUser();
       

if (!$coach) {
    $this->addFlash('error', 'Utilisateur non connecté');
    return $this->redirectToRoute('app_coach_seances');
}

        if (!$coach) {
            $this->addFlash('error', 'Coach introuvable');
            return $this->redirectToRoute('app_coach_seances');
        }

        $seances = $this->seanceRepo->findByCoach($coach);
        $stats = $this->calculateStats($seances);

        // Vérifier s'il y a des séances
        if (empty($seances)) {
            $this->addFlash('warning', 'Aucune séance à exporter');
            return $this->redirectToRoute('app_coach_seances');
        }

        // Générer HTML depuis Twig
        $html = $this->renderView('pdf/coach_seances_pdf.html.twig', [
            'seances' => $seances,
            'stats' => $stats,
            'coach' => $coach,
            'generated_at' => new \DateTime(),
        ]);

        // Configuration Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('fontDir', $this->getParameter('kernel.project_dir') . '/public/fonts');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nom du fichier
        $fileName = 'seances_' . $coach->getNom() . '_' . date('Y-m-d_H-i-s') . '.pdf';

        // Logger l'export
        $this->logger->info('Export PDF généré', [
            'coach_id' => $coach->getId_user(),
            'total_seances' => count($seances),
            'file_name' => $fileName,
        ]);

        // ✅ CORRECTION ICI UNIQUEMENT
        $pdfContent = $dompdf->output();

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]
        );

    } catch (\Exception $e) {
        $this->logger->error('Erreur lors de la génération du PDF', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->addFlash('error', 'Erreur lors de la génération du PDF: ' . $e->getMessage());
        return $this->redirectToRoute('app_coach_seances');
    }
}
    /**
     * Calcule les statistiques des séances du coach
     */
    private function calculateStats(array $seances): array
    {
       $maxReservations = 0;
$seancePopulaire = null;

foreach ($seances as $seance) {
    $count = count($seance->getReservationSeances());

    if ($count > $maxReservations) {
        $maxReservations = $count;
        $seancePopulaire = $seance;
    }
}

$stats['seance_populaire'] = $seancePopulaire;
$stats['nb_reservations_max'] = $maxReservations;
        
        $totalSeances = count($seances);
        $totalPlaces = 0;
        $totalParticipants = 0;
        $seancesAVenir = 0;
        $seancesTerminees = 0;
        $prochainerSeance = null;

        $now = new \DateTime();

        foreach ($seances as $seance) {
            $totalPlaces += $seance->getCapacite();
            $participants = $this->reservationRepo->findSeanceReservations($seance);
            $totalParticipants += count($participants);

            if ($seance->getStatutSeance()->value === 'PLANIFIEE') {
                $seancesAVenir++;
                if ($prochainerSeance === null || $seance->getDateSeance() < $prochainerSeance->getDateSeance()) {
                    $prochainerSeance = $seance;
                }
            } elseif ($seance->getStatutSeance()->value === 'TERMINEE') {
                $seancesTerminees++;
            }
        }

        $tauxParticipation = $totalPlaces > 0 ? round(($totalParticipants / $totalPlaces) * 100) : 0;
        $revenusPatentiels = $totalParticipants * 25; // À adapter au prix

        return [
            'seance_populaire' => $stats['seance_populaire'],
            'nb_reservations_max' => $stats['nb_reservations_max'],
            'total_seances' => $totalSeances,
            'total_places' => $totalPlaces,
            'total_participants' => $totalParticipants,
            'taux_participation' => $tauxParticipation,
            'seances_a_venir' => $seancesAVenir,
            'seances_terminees' => $seancesTerminees,
            'prochaine_seance' => $prochainerSeance,
            'revenus_potentiels' => $revenusPatentiels,
            'semaine_actuelle' => $this->getSeancesThisWeek($seances),
        ];
    }

    /**
     * Compte les séances de cette semaine
     */
    private function getSeancesThisWeek(array $seances): int
    {
        $count = 0;
        $now = new \DateTime();
        $startOfWeek = (clone $now)->modify('Monday this week');
        $endOfWeek = (clone $startOfWeek)->modify('+6 days');

        foreach ($seances as $seance) {
            if ($seance->getDateSeance() >= $startOfWeek && $seance->getDateSeance() <= $endOfWeek) {
                $count++;
            }
        }

        return $count;
    }
}