<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\ReservationActivite;
use App\Entity\UserApp;
use App\Enum\StatutReservationActivite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReservationController extends AbstractController
{
    #[Route('/reservationfront/{id}', name: 'app_reservation_front')]
    public function reservationFront(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);

        if (!$activite) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        return $this->render('front/reservationfront.html.twig', [
            'activite' => $activite
        ]);
    }

    #[Route('/reservation/create/{id}', name: 'app_reservation_create', methods: ['POST'])]
    public function createReservation(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $activite = $em->getRepository(Activite::class)->find($id);

        if (!$activite) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        // utilisateur fixe pour test
        $user = $em->getRepository(UserApp::class)->find(1);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur avec ID 1 non trouvé');
        }

        $reservation = new ReservationActivite();

        // date réservation
        $dateRes = new \DateTime($request->request->get('date_res'));
        $reservation->setDateRes($dateRes);

        // statut réservation
        $reservation->setStatutRes(
            StatutReservationActivite::from(
                $request->request->get('statut_res')
            )
        );

        // nombre de personnes
        $reservation->setNbPersonnes(
            (int) $request->request->get('nb_personnes')
        );

        // user fixe
        $reservation->setUserApp($user);

        // activité
        $reservation->setActivite($activite);

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Réservation effectuée avec succès');

        return $this->redirectToRoute('app_reservation_affichage', [
            'id' => $reservation->getIdResAct()
        ]);
    }

    #[Route('/reservation/affichage/{id}', name: 'app_reservation_affichage')]
    public function reservationAffichage(
        int $id,
        EntityManagerInterface $em
    ): Response {
        $reservation = $em->getRepository(ReservationActivite::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée');
        }

        return $this->render('front/reservationaffichage.html.twig', [
            'reservation' => $reservation
        ]);
    }
}