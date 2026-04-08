<?php

namespace App\Controller\event;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/events')]
class EventAdminController extends AbstractController
{
    #[Route('/', name: 'app_event_admin_index', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        return $this->render('admin/event/index.html.twig', [
            'evenements' => $evenementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_event_admin_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image_url')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/events',
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
                $evenement->setImage_url('uploads/events/' . $newFilename);
            }

            // Automatiquement statut "Active" ou "À venir" selon la date par exemple
            $evenement->setStatut('Active'); // Ou le laisser null

            try {
                $entityManager->persist($evenement);
                $entityManager->flush();
                $this->addFlash('success', 'Événement créé avec succès.');
                return $this->redirectToRoute('app_event_admin_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur BDD: ' . $e->getMessage());
            }
        } else if ($form->isSubmitted()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getOrigin()->getName() . ': ' . $error->getMessage();
            }
            $this->addFlash('error', 'Le formulaire d\'ajout contient des erreurs: ' . implode(', ', $errors));
        }

        return $this->render('admin/event/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id_evenement}/edit', name: 'app_event_admin_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image_url')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/events',
                        $newFilename
                    );
                    $evenement->setImage_url('uploads/events/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Événement modifié avec succès.');
                return $this->redirectToRoute('app_event_admin_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur BDD: ' . $e->getMessage());
            }
        } else if ($form->isSubmitted()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getOrigin()->getName() . ': ' . $error->getMessage();
            }
            $this->addFlash('error', 'Le formulaire contient des erreurs de validation: ' . implode(', ', $errors));
        }

        return $this->render('admin/event/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id_evenement}', name: 'app_event_admin_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$evenement->getId_evenement(), $request->request->get('_token'))) {
            try {
                // Delete related reservations manually if necessary, or just rely on Exception
                $entityManager->remove($evenement);
                $entityManager->flush();
                $this->addFlash('success', 'Événement supprimé.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Impossible de supprimer cet événement car il est lié à d\'autres éléments (réservations, etc).');
            }
        } else {
            $this->addFlash('error', 'Action non autorisée (CSRF Invalide).');
        }

        return $this->redirectToRoute('app_event_admin_index', [], Response::HTTP_SEE_OTHER);
    }
}
