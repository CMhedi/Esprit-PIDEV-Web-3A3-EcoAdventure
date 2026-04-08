<?php

namespace App\Controller\event;

use App\Entity\Evenement;
use App\Entity\ReservationEvenement;
use App\Entity\UserApp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Enum\StatutReservationEvenement;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
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
        $reservation->setStatut_res(StatutReservationEvenement::CONFIRMEE);
        $reservation->setNb_billets($nbBillets);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Votre réservation a été confirmée ! Retrouvez-la dans vos réservations.');

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
            $eventId = $res->getEvenement()->getId_evenement();
            if (!isset($groupedReservations[$eventId])) {
                $groupedReservations[$eventId] = [
                    'evenement' => $res->getEvenement(),
                    'total_billets' => 0,
                    'latest_date' => $res->getDate_reservation(),
                    'statut' => $res->getStatut_res(),
                    'is_new' => false,
                ];
            }
            $groupedReservations[$eventId]['total_billets'] += $res->getNb_billets();
            
            // Check if any reservation in this group is "new" (e.g., less than 24 hours old)
             $oneDayAgo = new \DateTime('-24 hours');
             if ($res->getDate_reservation() > $oneDayAgo) {
                 $groupedReservations[$eventId]['is_new'] = true;
             }
        }

        return $this->render('front/event/mes_reservations.html.twig', [
            'reservations' => $groupedReservations,
        ]);
    }
}
