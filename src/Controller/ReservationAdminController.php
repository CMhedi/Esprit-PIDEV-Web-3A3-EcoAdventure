<?php

namespace App\Controller\Admin;

use App\Entity\ReservationActivite;
use App\Entity\Activite;
use App\Enum\StatutReservationActivite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reservations')]
class ReservationAdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_reservations')]
    public function index(EntityManagerInterface $em): Response
    {
        $reservations = $em->getRepository(ReservationActivite::class)->findAll();

        return $this->render('admin/reservationadmin.html.twig', [
            'reservations' => $reservations
        ]);
    }

    #[Route('/delete/{id}', name: 'app_admin_reservation_delete', methods: ['POST'])]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        $reservation = $em->getRepository(ReservationActivite::class)->find($id);
        if ($reservation) {
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation supprimée avec succès !');
        }
        return $this->redirectToRoute('app_admin_reservations');
    }

    #[Route('/edit/{id}', name: 'app_admin_reservation_edit', methods: ['GET','POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $reservation = $em->getRepository(ReservationActivite::class)->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée');
        }

        $activites = $em->getRepository(Activite::class)->findAll();

        if ($request->isMethod('POST')) {
            $reservation->setActivite($em->getReference(Activite::class, $request->request->get('activite')));
            $reservation->setDateReservation(new \DateTime($request->request->get('date')));
            $reservation->setStatutRes(StatutReservationActivite::from($request->request->get('statut')));
            $reservation->setNbPersonnes((int)$request->request->get('nbPersonnes'));

            $em->flush();
            $this->addFlash('success', 'Réservation modifiée avec succès !');

            return $this->redirectToRoute('app_admin_reservations');
        }

        return $this->render('admin/reservation_edit_modal.html.twig', [
            'reservation' => $reservation,
            'activites' => $activites
        ]);
    }
}