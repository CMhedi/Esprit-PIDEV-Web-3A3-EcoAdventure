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
use App\Service\EventDocumentService;
use App\Service\EventWorkflowService;
use App\Service\ReservationManager;
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
    public function downloadAllTickets(EntityManagerInterface $entityManager, EventDocumentService $documentService, ReservationPricingService $pricingService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserApp) return $this->redirectToRoute('app_login');

        $allReservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'statut_res' => StatutReservationEvenement::CONFIRMEE
        ]);

        if (empty($allReservations)) {
            $this->addFlash('info', 'Vous n\'avez aucun ticket à télécharger.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        $groupedTickets = [];
        foreach ($allReservations as $res) {
            $eventId = $res->getEvenement()->getId_evenement();
            if (!isset($groupedTickets[$eventId])) {
                $groupedTickets[$eventId] = ['evenement' => $res->getEvenement(), 'total_billets' => 0];
            }
            $groupedTickets[$eventId]['total_billets'] += $res->getNb_billets();
        }

        $ticketsData = [];
        foreach ($groupedTickets as $eventId => $data) {
            $ticketsData[] = [
                'evenement' => $data['evenement'],
                'totalBillets' => $data['total_billets'],
                'qrCode' => $documentService->generateQrCode(sprintf('EVENT:%d|USER:%d|TICKETS:%d', $eventId, $user->getId_user(), $data['total_billets'])),
                'reference' => 'EVT-' . $eventId . '-' . $user->getId_user(),
                'pricing' => $pricingService->calculatePricing($data['evenement'], $data['total_billets'])
            ];
        }

        $html = $this->renderView('front/event/all_tickets_pdf.html.twig', ['tickets' => $ticketsData, 'user' => $user]);
        
        $options = new Options(); $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html); $dompdf->setPaper('A4', 'portrait'); $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="tous-mes-tickets.pdf"',
        ]);
    }

    #[Route('/invoice-event/{id_evenement}', name: 'app_reservation_event_invoice', methods: ['GET'])]
    public function generateEventInvoice(Evenement $evenement, EntityManagerInterface $entityManager, EventDocumentService $documentService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserApp) return $this->redirectToRoute('app_login');

        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user, 'evenement' => $evenement, 'statut_res' => StatutReservationEvenement::CONFIRMEE
        ]);

        if (empty($reservations)) {
            $this->addFlash('error', 'Aucune réservation confirmée trouvée.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        $totalBillets = array_reduce($reservations, fn($carry, $res) => $carry + $res->getNb_billets(), 0);
        $pdfOutput = $documentService->generateInvoicePdf($evenement, $user, $totalBillets);

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="facture-%d.pdf"', $user->getId_user()),
        ]);
    }

    #[Route('/ticket-event/{id_evenement}', name: 'app_reservation_event_ticket', methods: ['GET'])]
    public function generateEventTicket(Evenement $evenement, EntityManagerInterface $entityManager, EventDocumentService $documentService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserApp) return $this->redirectToRoute('app_login');

        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user, 'evenement' => $evenement, 'statut_res' => StatutReservationEvenement::CONFIRMEE
        ]);

        if (empty($reservations)) {
            $this->addFlash('error', 'Aucune réservation confirmée trouvée.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        $totalBillets = array_reduce($reservations, fn($carry, $res) => $carry + $res->getNb_billets(), 0);
        $pdfOutput = $documentService->generateTicketPdf($evenement, $user, $totalBillets);

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ticket.pdf"',
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
    public function reserver(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, \App\Service\AiEventOptimizerService $aiOptimizer, ReservationManager $reservationManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof UserApp) {
            $this->addFlash('error', 'Vous devez être connecté pour réserver.');
            return $this->redirectToRoute('app_login');
        }

        $nbBillets = (int) $request->request->get('nb_billets', 1);

        try {
            $reservationManager->validateReservationDemande($evenement, $nbBillets);
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
        }

        $placesRestantes = $evenement->getPlacesRestantes();

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
                $this->addFlash('error', sprintf('Désolé, liste d\'attente fermée. Notre Agent IA a calculé une limite maximale optimale de %d place(s) afin d\'éviter de vous frustrer.', $dynamicWaitlistLimit));
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
                $this->addFlash('warning', 'Plus de places disponibles. Vous avez été ajouté à la liste d\'attente par notre Agent IA, vous serez notifié si une place se libère.');
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

        $user->addLoyaltyPoints($nbBillets * 10);
        $entityManager->persist($user);

        $entityManager->flush();

        // 🤖 4) YIELD MANAGEMENT (TARIFICATION DYNAMIQUE)
        $yieldData = $aiOptimizer->analyzeYieldManagement($evenement, 1.5);
        if (str_contains($yieldData['admin_alert'], 'FORTE')) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));
            $notifYield = new Notification();
            $notifYield->setTitle('⚡ PRIX DYNAMIQUE AGENT IA')
                ->setCreatedAt($now)
                ->setMessage(sprintf(
                    "L'événement '%s' est ultra-populaire ! L'Agent IA suggère d'augmenter le prix à %s DT (Actuel: %s DT) pour les prochaines éditions.", 
                    $evenement->getTitre(), 
                    number_format($yieldData['suggested_price'], 2, ',', ' '), 
                    number_format($evenement->getPrix(), 2, ',', ' ')
                ))
                ->setType('system');
            $entityManager->persist($notifYield);
            $entityManager->flush();
        }

        $this->addFlash('success', 'Votre réservation a été pré-enregistrée. Procédons au paiement pour la confirmer définitivement !');

        // Redirect directly to the payment tunnel instead of the dashboard
        return $this->redirectToRoute('app_reservation_confirm_event', ['id_evenement' => $evenement->getId_evenement()]);
    }

    #[Route('/annuler-event/{id_evenement}', name: 'app_reservation_cancel_event', methods: ['POST'])]
    public function annulerEvent(Evenement $evenement, EventWorkflowService $workflowService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserApp) return $this->redirectToRoute('app_login');

        $workflowService->cancelReservations($user, $evenement);

        $this->addFlash('success', sprintf('Votre réservation pour l\'événement "%s" a été supprimée.', $evenement->getTitre()));
        return $this->redirectToRoute('app_mes_reservations');
    }

    #[Route('/confirmer-event/{id_evenement}', name: 'app_reservation_confirm_event', methods: ['GET', 'POST'])]
    public function confirmerEvent(Evenement $evenement, EntityManagerInterface $entityManager, \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator, ReservationPricingService $pricingService): Response
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

        $totalBillets = 0;
        foreach ($reservations as $res) {
            $totalBillets += $res->getNb_billets();
        }

        $pricing = $pricingService->calculatePricing($evenement, $totalBillets);
        $totalFinal = $pricing['totalFinal'];

        // Tentative d'utilisation de Stripe (Mode Test)
        try {
            // Remplacez par votre VRAIE clé secrète Stripe de test (sk_test_...)
            \Stripe\Stripe::setApiKey('sk_test_fake_key_for_pidev_demo');

            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur', // Stripe utilise EUR/USD plus facilement que DT
                        'product_data' => [
                            'name' => 'Billet: ' . $evenement->getTitre(),
                            'images' => ['https://img.freepik.com/vecteurs-libre/billet-evenement-isole-realiste_1284-47473.jpg'],
                        ],
                        'unit_amount' => (int)($totalFinal * 100), // En centimes
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $urlGenerator->generate('app_reservation_payment_success', ['id_evenement' => $evenement->getId_evenement()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url' => $urlGenerator->generate('app_mes_reservations', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

            return $this->redirect($session->url, 303);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            // FALLBACK FACTICE (Si Stripe n'est pas configuré, on redirige vers notre tunnel interne)
            return $this->redirectToRoute('app_fake_payment_tunnel', [
                'id_evenement' => $evenement->getId_evenement(),
                'montant' => $totalFinal
            ]);
        }
    }

    #[Route('/fake-payment/{id_evenement}/{montant}', name: 'app_fake_payment_tunnel', methods: ['GET'])]
    public function fakePaymentTunnel(Evenement $evenement, float $montant): Response
    {
        return $this->render('front/event/fake_payment.html.twig', [
            'evenement' => $evenement,
            'montant' => $montant
        ]);
    }

    #[Route('/payment-success/{id_evenement}', name: 'app_reservation_payment_success', methods: ['GET', 'POST'])]
    public function paymentSuccess(Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'evenement' => $evenement,
            'statut_res' => StatutReservationEvenement::EN_ATTENTE
        ]);

        foreach ($reservations as $res) {
            $res->setStatut_res(StatutReservationEvenement::CONFIRMEE);
            $res->setIsNotifiedAvailability(false);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Paiement réussi ! Votre réservation pour "' . $evenement->getTitre() . '" est officiellement CONFIRMÉE.');
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
