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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    #[Route('/download-all-tickets', name: 'app_reservation_all_tickets', methods: ['GET'])]
    public function downloadAllTickets(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $user = $entityManager->getRepository(UserApp::class)->findOneBy(['email' => 'guest@ecoadventure.com']);
        }

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_mes_reservations');
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
                'reference' => $ref
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

    #[Route('/ticket-event/{id_evenement}', name: 'app_reservation_event_ticket', methods: ['GET'])]
    public function generateEventTicket(Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $user = $entityManager->getRepository(UserApp::class)->findOneBy(['email' => 'guest@ecoadventure.com']);
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
        $qrContent = sprintf('EVENT:%d|USER:%d|TICKETS:%d', 
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

        $html = $this->renderView('front/event/ticket_pdf.html.twig', [
            'evenement' => $evenement,
            'user' => $user,
            'totalBillets' => $totalBillets,
            'qrCode' => $qrCodeBase64,
            'reference' => 'EVT-' . $evenement->getId_evenement() . '-' . $user->getId_user()
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
    public function generateTicket(ReservationEvenement $reservation): Response
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

        $html = $this->renderView('front/event/ticket_pdf.html.twig', [
            'reservation' => $reservation,
            'qrCode' => $qrCodeBase64,
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
    public function reserver(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        // 1. Check if user is connected
        $user = $this->getUser();
        
        // Temporaire: Génération d'un "Guest" si l'utilisateur n'est pas connecté
        if (!$user) {
            $user = $entityManager->getRepository(UserApp::class)->findOneBy(['email' => 'guest@ecoadventure.com']);
            if (!$user) {
                // Création d'un profil invité pour ne pas bloquer les tests de réservation
                $user = new UserApp();
                $user->setNom('Visiteur');
                $user->setPrenom('Anonyme');
                $user->setEmail('guest@ecoadventure.com');
                $user->setMot_de_passe('dummy_password'); // mot de passe factice
                $user->setRole(\App\Enum\RoleUser::USER_SIMPLE);
                $user->setDate_creation(new \DateTime());
                
                $entityManager->persist($user);
                $entityManager->flush();
            }
        }

        $nbBillets = (int) $request->request->get('nb_billets', 1);

        // 2. Gestion intelligente des places & Validation
        $nbReservationsExistantes = 0;
        foreach ($evenement->getReservationEvenements() as $res) {
            if ($res->getStatut_res() !== StatutReservationEvenement::ANNULEE) {
                $nbReservationsExistantes += $res->getNb_billets();
            }
        }
        $placesRestantes = $evenement->getNb_places() - $nbReservationsExistantes;

        if ($placesRestantes <= 0) {
            $this->addFlash('error', 'Désolé, cet événement est Complet.');
            return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
        }

        if ($nbBillets > $placesRestantes) {
            $this->addFlash('error', "Il ne reste que $placesRestantes place(s).");
            return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
        }

        // 3. Create Reservation
        $reservation = new ReservationEvenement();
        $reservation->setEvenement($evenement);
        $reservation->setUserApp($user);
        $reservation->setDate_reservation(new \DateTime());
        $reservation->setStatut_res(StatutReservationEvenement::EN_ATTENTE);
        $reservation->setNb_billets($nbBillets);

        $entityManager->persist($reservation);
        
        // Advanced Business Logic: Loyalty Points
        if ($user instanceof UserApp) {
            $user->addLoyaltyPoints($nbBillets * 10);
            $entityManager->persist($user);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Votre réservation a été enregistrée en attente de confirmation !');

        return $this->redirectToRoute('app_mes_reservations');
    }

    #[Route('/annuler-event/{id_evenement}', name: 'app_reservation_cancel_event', methods: ['POST'])]
    public function annulerEvent(Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $user = $entityManager->getRepository(UserApp::class)->findOneBy(['email' => 'guest@ecoadventure.com']);
        }

        // Find all reservations for this event and user
        $reservations = $entityManager->getRepository(ReservationEvenement::class)->findBy([
            'userApp' => $user,
            'evenement' => $evenement
        ]);

        if (empty($reservations)) {
            $this->addFlash('error', 'Aucune réservation trouvée pour cet événement.');
            return $this->redirectToRoute('app_mes_reservations');
        }

        foreach ($reservations as $res) {
            // Hard delete as requested "supprimé"
            $entityManager->remove($res);
        }
        
        $entityManager->flush();

        $this->addFlash('success', 'Votre réservation pour l\'événement "' . $evenement->getTitre() . '" a été supprimée.');
        return $this->redirectToRoute('app_mes_reservations');
    }

    #[Route('/confirmer-event/{id_evenement}', name: 'app_reservation_confirm_event', methods: ['POST'])]
    public function confirmerEvent(Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $user = $entityManager->getRepository(UserApp::class)->findOneBy(['email' => 'guest@ecoadventure.com']);
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
        }
        
        $entityManager->flush();

        $this->addFlash('success', 'Votre réservation pour l\'événement "' . $evenement->getTitre() . '" est maintenant confirmée !');
        return $this->redirectToRoute('app_mes_reservations');
    }

    #[Route('/mes-reservations', name: 'app_mes_reservations', methods: ['GET'])]
    public function mesReservations(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        // Mock si pas connecté
        if (!$user) {
            $user = $entityManager->getRepository(UserApp::class)->findOneBy(['email' => 'guest@ecoadventure.com']);
        }

        if (!$user) {
            $this->addFlash('warning', 'Vous n\'avez aucune réservation ou n\'êtes pas connecté.');
            return $this->redirectToRoute('app_event_front_index');
        }

        $allReservations = $entityManager->getRepository(ReservationEvenement::class)->findBy(
            ['userApp' => $user],
            ['date_reservation' => 'DESC']
        );

        $groupedReservations = [];
        foreach ($allReservations as $res) {
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
                ];
            }
            $groupedReservations[$eventId]['total_billets'] += $res->getNb_billets();
            $groupedReservations[$eventId]['ids'][] = $res->getId_res_evt();
        }

        return $this->render('front/event/mes_reservations.html.twig', [
            'reservations' => $groupedReservations,
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
