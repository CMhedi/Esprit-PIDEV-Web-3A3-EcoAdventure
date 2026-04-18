<?php

namespace App\Controller\event;

use App\Entity\Evenement;
use App\Entity\ReservationEvenement;
use App\Entity\UserApp;
use App\Enum\StatutReservationEvenement;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use App\Entity\Notification;
use App\Service\ReservationPricingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
#[IsGranted('ROLE_USER')]
class ReservationController extends AbstractController
{
    #[Route('/download-all-tickets', name: 'app_reservation_all_tickets', methods: ['GET'])]
    public function downloadAllTickets(EntityManagerInterface $entityManager, ReservationPricingService $pricingService): Response
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $allReservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'statut_res' => StatutReservationEvenement::CONFIRMEE
        ]);

        if (empty($allReservations)) {
            $this->addFlash('info', 'Vous n\'avez aucun ticket à télécharger.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        // Group tickets by event to avoid duplicate pages for the same event
        $groupedTickets = [];
        foreach ($allReservations as $res) {
            $eventId = $res->getEvenement()->getId_evenement();
            if (!isset($groupedTickets[$eventId])) {
                $groupedTickets[$eventId] = [
                    'evenement' => $res->getEvenement(),
                    'total_billets' => 0
                ];
            }
            $groupedTickets[$eventId]['total_billets'] += $res->getNb_billets();
        }

        $ticketsData = [];
        $writer = new SvgWriter();
        foreach ($groupedTickets as $eventId => $data) {
            $ref = 'EVT-' . $eventId . '-' . $user->getId_user();
            $qrContent = sprintf('EVENT:%d|USER:%d|TICKETS:%d', $eventId, $user->getId_user(), $data['total_billets']);

            $qrCode = new QrCode(
                data: $qrContent,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: 200,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255)
            );

            $result = $writer->write($qrCode);

            $ticketsData[] = [
                'evenement' => $data['evenement'],
                'totalBillets' => $data['total_billets'],
                'qrCode' => base64_encode($result->getString()),
                'reference' => $ref,
                'pricing' => $pricingService->calculatePricing($data['evenement'], $data['total_billets'])
            ];
        }

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $html = $this->renderView('front/event/all_tickets_pdf.html.twig', [
            'tickets' => $ticketsData,
            'user' => $user
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="tous-mes-tickets.pdf"',
        ]);
    }

    #[Route('/invoice-event/{id_evenement}', name: 'app_reservation_event_invoice', methods: ['GET'])]
    public function generateEventInvoice(Evenement $evenement, EntityManagerInterface $entityManager, ReservationPricingService $pricingService): Response
    {
        /** @var UserApp $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'evenement' => $evenement,
            'statut_res' => StatutReservationEvenement::CONFIRMEE
        ]);

        if (empty($reservations)) {
            $this->addFlash('error', 'Aucune réservation confirmée trouvée pour cet événement.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        $totalBillets = 0;
        foreach ($reservations as $res) {
            $totalBillets += $res->getNb_billets();
        }

        // Prepare PDF Content
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $pricing = $pricingService->calculatePricing($evenement, $totalBillets);

        $html = $this->renderView('front/event/invoice_pdf.html.twig', [
            'evenement' => $evenement,
            'user' => $user,
            'totalBillets' => $totalBillets,
            'pricing' => $pricing,
            'reference' => 'FAC-' . strtoupper(substr($evenement->getTitre(), 0, 3)) . '-' . $evenement->getId_evenement() . '-' . $user->getId_user()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        $filename = sprintf('facture-%s-%d.pdf', str_replace(' ', '-', $evenement->getTitre()), $user->getId_user());

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/ticket-event/{id_evenement}', name: 'app_reservation_event_ticket', methods: ['GET'])]
    public function generateEventTicket(Evenement $evenement, EntityManagerInterface $entityManager, ReservationPricingService $pricingService): Response
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'evenement' => $evenement,
            'statut_res' => StatutReservationEvenement::CONFIRMEE
        ]);

        if (empty($reservations)) {
            $this->addFlash('error', 'Aucune réservation confirmée trouvée pour cet événement.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        $totalBillets = 0;
        $firstReservation = $reservations[0];
        foreach ($reservations as $res) {
            $totalBillets += $res->getNb_billets();
        }

        // 1. Generate QR Code with unique reference (EventID + UserID)
        $writer = new SvgWriter();
        $qrContent = sprintf(
            'EVENT:%d|USER:%d|TICKETS:%d',
            $evenement->getId_evenement(),
            $user->getId_user(),
            $totalBillets
        );

        $qrCode = new QrCode(
            data: $qrContent,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 200,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $result = $writer->write($qrCode);
        $qrCodeBase64 = base64_encode($result->getString());

        // 2. Prepare PDF Content
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $pricing = $pricingService->calculatePricing($evenement, $totalBillets);

        $html = $this->renderView('front/event/ticket_pdf.html.twig', [
            'evenement' => $evenement,
            'user' => $user,
            'totalBillets' => $totalBillets,
            'qrCode' => $qrCodeBase64,
            'reference' => 'EVT-' . $evenement->getId_evenement() . '-' . $user->getId_user(),
            'pricing' => $pricing
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        $filename = sprintf('ticket-%s.pdf', str_replace(' ', '-', $evenement->getTitre()));

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/ticket/{id_res_evt}', name: 'app_reservation_ticket', methods: ['GET'])]
    public function generateTicket(ReservationEvenement $reservation, ReservationPricingService $pricingService): Response
    {
        // 1. Generate QR Code
        $writer = new SvgWriter();
        $qrCode = new QrCode(
            data: 'Reservation ID: ' . $reservation->getId_res_evt(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 200,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $result = $writer->write($qrCode);
        $qrCodeBase64 = base64_encode($result->getString());

        // 2. Prepare PDF Content
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $pricing = $pricingService->calculatePricing($reservation->getEvenement(), $reservation->getNb_billets());

        $html = $this->renderView('front/event/ticket_pdf.html.twig', [
            'reservation' => $reservation,
            'evenement' => $reservation->getEvenement(),
            'user' => $reservation->getUserApp(),
            'totalBillets' => $reservation->getNb_billets(),
            'qrCode' => $qrCodeBase64,
            'reference' => 'EVT-' . $reservation->getEvenement()->getId_evenement() . '-' . $reservation->getUserApp()->getId_user(),
            'pricing' => $pricing,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();
        $filename = sprintf('ticket-event-%s.pdf', $reservation->getId_res_evt());

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/evenement/{id_evenement}', name: 'app_reservation_event', methods: ['POST'])]
    public function reserver(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, \App\Service\AiEventOptimizerService $aiOptimizer): Response
    {
        /** @var UserApp $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour réserver.');
            return $this->redirectToRoute('app_login');
        }

        $nbBillets = (int) $request->request->get('nb_billets', 1);
        $placesRestantes = $evenement->getPlacesRestantes();



        if ($placesRestantes > 0 && $nbBillets > $placesRestantes) {
            $this->addFlash('error', sprintf('Il ne reste que %d place(s). Vous ne pouvez pas réserver plus que ce qui est disponible tant que l\'événement n\'est pas complet.', $placesRestantes));
            return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
        }

            if ($placesRestantes <= 0) {
            $reservationsAttente = $entityManager->getRepository(ReservationEvenement::class)->findBy([
                'evenement' => $evenement,
                'statut_res' => StatutReservationEvenement::LISTE_ATTENTE
            ]);
            $placesEnAttente = 0;
            foreach ($reservationsAttente as $res) {
                $placesEnAttente += $res->getNb_billets();
            }

            // 🤖 2) EXPANSION DYNAMIQUE PAR L'IA
            $dynamicWaitlistLimit = $aiOptimizer->optimizeWaitlistLimit($evenement);

            if ($placesEnAttente + $nbBillets > $dynamicWaitlistLimit) {
                $this->addFlash('error', sprintf('Désolé, liste d\'attente fermée. Notre IA a calculé une limite maximale optimale de %d place(s) afin d\'éviter de vous frustrer.', $dynamicWaitlistLimit));
                return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
            }

            $reservation = new ReservationEvenement();
            $reservation->setEvenement($evenement)
                ->setUserApp($user)
                ->setDate_reservation(new \DateTime())
                ->setStatut_res(StatutReservationEvenement::LISTE_ATTENTE)
                ->setNb_billets($nbBillets);

            $entityManager->persist($reservation);
            $entityManager->flush();

            // 🤖 3) MOTEUR DE RECOMMANDATION PRÉVENTIVE (Avec alternative proposée)
            $aiData = $aiOptimizer->getAiRecommendationMessage($evenement, $placesEnAttente + 1);
            if ($aiData) {
                $fullMessage = $aiData['message'] . " 👉 " . $aiData['event']->getTitre();
                $this->addFlash('info', $fullMessage);
            } else {
                $this->addFlash('warning', 'Plus de places disponibles. Vous avez été ajouté à la liste d\'attente, vous serez notifié si une place se libère.');
            }
            
            return $this->redirectToRoute('app_mes_reservations');
        }

        $reservation = new ReservationEvenement();
        $reservation->setEvenement($evenement)
            ->setUserApp($user)
            ->setDate_reservation(new \DateTime())
            ->setStatut_res(StatutReservationEvenement::EN_ATTENTE)
            ->setNb_billets($nbBillets);

        $entityManager->persist($reservation);

        if ($user instanceof UserApp) {
            $user->addLoyaltyPoints($nbBillets * 10);
            $entityManager->persist($user);
        }

        $entityManager->flush();

        // 🤖 4) YIELD MANAGEMENT (TARIFICATION DYNAMIQUE)
        $yieldData = $aiOptimizer->analyzeYieldManagement($evenement, 1.5);
        if (isset($yieldData['admin_alert']) && strpos($yieldData['admin_alert'], 'FORTE') !== false) {
            $notifYield = new Notification();
            $notifYield->setTitle('⚡ PRIX DYNAMIQUE IA')
                ->setMessage(sprintf(
                    "L'événement '%s' est ultra-populaire ! L'IA suggère d'augmenter le prix à %s DT (Actuel: %s DT) pour les prochaines éditions.", 
                    $evenement->getTitre(), 
                    number_format($yieldData['suggested_price'], 2, ',', ' '), 
                    number_format($evenement->getPrix(), 2, ',', ' ')
                ))
                ->setType('system');
            $entityManager->persist($notifYield);
            $entityManager->flush();
        }

        $this->addFlash('success', 'Votre réservation a été enregistrée en attente de confirmation !');

        return $this->redirectToRoute('app_mes_reservations');
    }

    #[Route('/annuler-event/{id_evenement}', name: 'app_reservation_cancel_event', methods: ['POST'])]
    public function annulerEvent(
        Evenement $evenement,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var UserApp $user */
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'evenement' => $evenement
        ]);

        if (empty($reservations)) {
            $this->addFlash('error', 'Aucune réservation trouvée pour cet événement.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        foreach ($reservations as $reservation) {
            $entityManager->remove($reservation);
        }

        // Création d'une notification pour l'admin
        $notif = new Notification();
        $notif->setTitle('Annulation de Réservation')
            ->setMessage(sprintf('Le client %s %s a annulé sa participation à l\'événement %s.', $user->getNom(), $user->getPrenom(), $evenement->getTitre()))
            ->setType('cancellation');
        $entityManager->persist($notif);

        $entityManager->flush();

        // Check if we can accommodate someone from the waiting list
        $reservationsAttente = $entityManager->getRepository(ReservationEvenement::class)->findBy(
            ['evenement' => $evenement, 'statut_res' => StatutReservationEvenement::LISTE_ATTENTE],
            ['date_reservation' => 'ASC']
        );

        $placesRestantes = $evenement->getPlacesRestantes();
        foreach ($reservationsAttente as $resAttente) {
            if ($resAttente->getNb_billets() <= $placesRestantes) {
                $resAttente->setStatut_res(StatutReservationEvenement::EN_ATTENTE);
                $resAttente->setIsNotifiedAvailability(true);
                $placesRestantes -= $resAttente->getNb_billets();

                // Add an admin notification as well to log that someone was moved from waitlist
                $notifWaitlist = new Notification();
                $notifWaitlist->setTitle('Priorité liste d\'attente')
                    ->setMessage(sprintf(
                        'La réservation du client %s %s pour l\'événement %s est passée de la liste d\'attente à En Attente (disponible).',
                        $resAttente->getUserApp()->getNom(),
                        $resAttente->getUserApp()->getPrenom(),
                        $evenement->getTitre()
                    ))
                    ->setType('availability');
                $entityManager->persist($notifWaitlist);

                // TODO: Here ideally we send an EMAIL to $resAttente->getUserApp()
            }
        }
        $entityManager->flush();

        $this->addFlash('success', sprintf('Votre réservation pour l\'événement "%s" a été supprimée.', $evenement->getTitre()));

        return $this->redirectToRoute('app_mes_reservations');
    }

    #[Route('/confirmer-event/{id_evenement}', name: 'app_reservation_confirm_event', methods: ['POST'])]
    public function confirmerEvent(Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'evenement' => $evenement,
            'statut_res' => StatutReservationEvenement::EN_ATTENTE
        ]);

        if (empty($reservations)) {
            $this->addFlash('error', 'Aucune réservation en attente trouvée pour cet événement.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        foreach ($reservations as $res) {
            $res->setStatut_res(StatutReservationEvenement::CONFIRMEE);
            $res->setIsNotifiedAvailability(false);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Votre réservation pour l\'événement "' . $evenement->getTitre() . '" est maintenant confirmée !');
        return $this->redirectToRoute('app_mes_reservations');
    }

    #[Route('/mes-reservations', name: 'app_mes_reservations', methods: ['GET'])]
    public function mesReservations(Request $request, EntityManagerInterface $entityManager, ReservationPricingService $pricingService): Response
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('warning', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $allReservations = $entityManager->getRepository(ReservationEvenement::class)->findBy(
            ['userApp' => $user],
            ['date_reservation' => 'DESC']
        );

        $groupedReservations = [];
        $hasUpdates = false;

        foreach ($allReservations as $res) {
            // Mark notifications as read if they were notified
            if ($res->isNotifiedAvailability()) {
                $res->setIsNotifiedAvailability(false);
                $hasUpdates = true;
            }

            // Only process non-cancelled reservations (though we delete them now, safety first)
            if ($res->getStatut_res() === StatutReservationEvenement::ANNULEE) {
                continue;
            }

            $eventId = $res->getEvenement()->getId_evenement();
            if (!isset($groupedReservations[$eventId])) {
                $groupedReservations[$eventId] = [
                    'evenement' => $res->getEvenement(),
                    'total_billets' => 0,
                    'latest_date' => $res->getDate_reservation(),
                    'statut' => $res->getStatut_res(),
                    'ids' => [], // Store all reservation IDs for this event
                    'pricing' => null
                ];
            }
            $groupedReservations[$eventId]['total_billets'] += $res->getNb_billets();
            $groupedReservations[$eventId]['ids'][] = $res->getId_res_evt();
        }

        foreach ($groupedReservations as $eventId => $data) {
            $groupedReservations[$eventId]['pricing'] = $pricingService->calculatePricing($data['evenement'], $data['total_billets']);
        }

        if ($hasUpdates) {
            $entityManager->flush();
        }

        $groupedReservations = array_values($groupedReservations); // Reindex array
        $page = $request->query->getInt('page', 1);
        $limit = 5; // Nombre d'événements affichés par page
        $totalItems = count($groupedReservations);
        $totalPages = max(1, ceil($totalItems / $limit));

        if ($page < 1)
            $page = 1;
        if ($page > $totalPages && $totalPages > 0)
            $page = $totalPages;

        $paginatedReservations = array_slice($groupedReservations, ($page - 1) * $limit, $limit);

        return $this->render('front/event/mes_reservations.html.twig', [
            'reservations' => $paginatedReservations,
            'current_page' => $page,
            'total_pages' => $totalPages
        ]);
    }

    #[Route('/api/verify-ticket/{reference}', name: 'app_api_verify_ticket', methods: ['GET'])]
    public function verifyTicket(string $reference, EntityManagerInterface $entityManager): Response
    {
        // Reference format: EVT-EventID-UserID
        $parts = explode('-', $reference);
        if (count($parts) !== 3) {
            return $this->json(['status' => 'error', 'message' => 'Format de référence invalide.'], 400);
        }

        $eventId = $parts[1];
        $userId = $parts[2];

        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $userId,
            'evenement' => $eventId,
            'statut_res' => StatutReservationEvenement::CONFIRMEE
        ]);

        if (empty($reservations)) {
            return $this->json([
                'status' => 'invalid',
                'message' => 'Aucune réservation valide trouvée.'
            ]);
        }

        $totalBillets = 0;
        foreach ($reservations as $res) {
            $totalBillets += $res->getNb_billets();
        }

        return $this->json([
            'status' => 'valid',
            'event' => $reservations[0]->getEvenement()->getTitre(),
            'client' => $reservations[0]->getUserApp()->getNom() . ' ' . $reservations[0]->getUserApp()->getPrenom(),
            'tickets' => $totalBillets,
            'message' => 'Ticket vérifié avec succès.'
        ]);
    }
}
