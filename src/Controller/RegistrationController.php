<?php

namespace App\Controller;
use App\Repository\ReclamationRepository;
use App\Entity\UserApp;
use App\Form\RegistrationFormType;
use App\Enum\RoleUser; // <--- Thabbet elli el namespace hadha s7i7 hasb dossier Enum mte3ek
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{

#[Route('/reclamation/admin-reply/{id_reclamation}', name: 'app_reclamation_admin_reply', methods: ['POST'])]
public function adminReply(Request $request, int $id_reclamation, EntityManagerInterface $entityManager, ReclamationRepository $repo): Response
{
    $reclamation = $repo->find($id_reclamation);
    
    if ($reclamation && $this->isGranted('ROLE_ADMIN')) {
        $reponse = $request->request->get('reponse');
        $statutSelect = $request->request->get('statut'); // "TRAITEE" walla "REJETEE"

        // LOGIQUE: 
        // 1. Ken l'admin kteb reponse -> twalli TRAITEE dima
        if (!empty(trim($reponse))) {
            $reclamation->setReponse($reponse);
            $reclamation->setStatut(\App\Enum\StatutReclamation::TRAITEE);
        } 
        // 2. Ken l'admin ma kthebch reponse ama ekhtar REJETEE m'el dropdown
        elseif ($statutSelect === 'REJETEE') {
            $reclamation->setStatut(\App\Enum\StatutReclamation::REJETEE);
            $reclamation->setReponse("Réclamation rejetée par l'administration.");
        }

        $entityManager->flush();
        $this->addFlash('success', 'Mise à jour effectuée !');
    }

    return $this->redirectToRoute('app_reclamation_index');
}
#[Route('/register', name: 'app_register')]
public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
{
    $user = new UserApp();
    $form = $this->createForm(RegistrationFormType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // 1. Hash el password
        $user->setMot_de_passe(
            $userPasswordHasher->hashPassword(
                $user,
                $form->get('motdepasse')->getData()
            )
        );
        $selectedRole = $form->get('role')->getData(); 
        $user->setRole($selectedRole);
        // 2. Date creation
        $user->setDate_creation(new \DateTime());

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('app_home');
    }

    return $this->render('registration/register.html.twig', [
        'registrationForm' => $form,
    ]);
}}