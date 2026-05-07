<?php

namespace App\Controller\event;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\ReservationEvenementRepository;
use App\Form\EvenementType;
use Symfony\Component\HttpFoundation\Request;

#[Route('/admin/events')]
class EventAdminController extends AbstractController
{
    #[Route('/', name: 'app_event_admin_index')]
    public function index(EvenementRepository $repo): Response
    {
        $events = $repo->findAll();

        return $this->render('admin/event/index.html.twig', [
            'evenements' => $events
        ]);
    }

    #[Route('/reservations', name: 'admin_event_reservations')]
    public function reservations(ReservationEvenementRepository $repo): Response
    {
        return $this->render('admin/event/reservations.html.twig', [
            'reservations' => $repo->findAll()
        ]);
    }

    #[Route('/new', name: 'app_event_admin_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $event = new Evenement();
        $form = $this->createForm(EvenementType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($event);
            $em->flush();

            $this->addFlash('success', 'L\'événement a été créé avec succès.');
            return $this->redirectToRoute('app_event_admin_index');
        }

        return $this->render('admin/event/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id_evenement}/edit', name: 'app_event_admin_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $event, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EvenementType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'L\'événement a été mis à jour.');
            return $this->redirectToRoute('app_event_admin_index');
        }

        return $this->render('admin/event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id_evenement}', name: 'app_event_admin_delete', methods: ['POST'])]
    public function delete(Evenement $event, EntityManagerInterface $em): Response
    {
        $em->remove($event);
        $em->flush();

        return $this->redirectToRoute('app_event_admin_index');
    }
}