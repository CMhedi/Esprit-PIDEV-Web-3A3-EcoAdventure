<?php

namespace App\Controller;
use App\Service\ReclamationProcessor;
use App\Entity\Reclamation;
use App\Entity\UserApp;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Enum\StatutReclamation;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[Route('/reclamation')]
#[IsGranted('ROLE_USER')]
final class ReclamationController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_reclamation_index', methods: ['GET'])]
    public function adminIndex(ReclamationRepository $reclamationRepository): Response
    {
        return $this->render('reclamation/index.html.twig', [
            'reclamations' => $reclamationRepository->findAll(),
            'admin_mode' => true,
        ]);
    }

    #[Route(name: 'app_reclamation_index', methods: ['GET'])]
    public function index(ReclamationRepository $reclamationRepository): Response
    {
        // Kenou Admin, i-chouf kol chay
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->render('reclamation/index.html.twig', [
                'reclamations' => $reclamationRepository->findAll(),
                'admin_mode' => true,
            ]);
        }

        // Kenou User 3adi, i-chouf ken mte3ou (ken 3andek relation m3a el User)
        return $this->render('reclamation/index.html.twig', [
            'reclamations' => $reclamationRepository->findBy(['userApp' => $this->getUser()]),
            'admin_mode' => false,
        ]);
    }


#[Route('/new', name: 'app_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ReclamationProcessor $processor): Response
    {
        $reclamation = new Reclamation();
        $reclamation->setStatut(StatutReclamation::EN_ATTENTE);
        $user = $this->getUser();
        if ($user instanceof UserApp) {
            $reclamation->setUserApp($user);
        }

        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $type = $reclamation->getType();

            // Logique de priorité
            if ($type === 'Séance' || $type === 'Paiement') {
                $reclamation->setPriorite('HAUTE');
            } elseif ($type === 'Technique') {
                $reclamation->setPriorite('MOYENNE');
            } else {
                $reclamation->setPriorite('BASSE');
            }

            $entityManager->persist($reclamation);
            $entityManager->flush();
            
            return $this->redirectToRoute('app_reclamation_index'); 
        }

        return $this->render('reclamation/new.html.twig', [
            'reclamation' => $reclamation, 
            'form' => $form->createView(),
        ]);
    }
    #[Route('/{id_reclamation}', name: 'app_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id_reclamation}/edit', name: 'app_reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form,
        ]);
    }

    #[Route('/{id_reclamation}', name: 'app_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reclamation->getId_reclamation(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reclamation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_reclamation_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/admin-reply/{id_reclamation}', name: 'app_reclamation_admin_reply', methods: ['POST'])]
    public function adminReply(Request $request, int $id_reclamation, EntityManagerInterface $entityManager, ReclamationRepository $repo): Response
    {
        $reclamation = $repo->find($id_reclamation);
        
        if ($reclamation && $this->isGranted('ROLE_ADMIN')) {
            $reponse = $request->request->get('reponse');
            $statut = $request->request->get('statut');

            $reclamation->setReponse($reponse);
            
            // Houni thabbet f'el Enum mte3ek (TRAITEE / REJETEE)
            // Par exemple: $reclamation->setStatut(\App\Enum\StatutReclamation::from($statut));
            
            $entityManager->flush();
            $this->addFlash('success', 'Réponse enregistrée avec succès !');
        }

        return $this->redirectToRoute('app_reclamation_index');
    }
}
