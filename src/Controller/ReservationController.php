<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\ReservationActivite;
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

        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour réserver');
            return $this->redirectToRoute('app_login');
        }

        $reservation = new ReservationActivite();

        // date auto
        $reservation->setDateReservation(new \DateTime());

        // enum
        $reservation->setStatutRes(
            StatutReservationActivite::from(
                $request->request->get('statut_res')
            )
        );

        // nb personnes
        $reservation->setNbPersonnes(
            (int) $request->request->get('nb_personnes')
        );

        // current user
        $reservation->setUserApp($user);

        // activité
        $reservation->setActivite($activite);

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Réservation effectuée avec succès');

        return $this->redirectToRoute('app_activite_affichage', [
            'id' => $activite->getIdActivite()
        ]);
    }
}