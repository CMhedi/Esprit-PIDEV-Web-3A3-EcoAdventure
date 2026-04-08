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
        
        // Temporaire: Mock de l'utilisateur s'il n'y a pas de système de login encore
        if (!$user) {
            $user = $entityManager->getRepository(UserApp::class)->findOneBy([]);
            if (!$user) {
                $this->addFlash('error', 'Vous devez être connecté pour réserver.');
                return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
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

        $this->addFlash('success', 'Votre réservation a été confirmée !');

        return $this->redirectToRoute('app_event_front_show', ['id_evenement' => $evenement->getId_evenement()]);
    }
}
