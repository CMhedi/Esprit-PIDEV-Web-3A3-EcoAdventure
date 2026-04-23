<?php

namespace App\Controller;

use App\Entity\UserApp;
use App\Enum\RoleUser; 
use App\Form\RegistrationFormType;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
// Hna nasta3mlou el Authenticator elli b3aththouly enti
use App\Security\LoginManagerAuthenticator; 

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $entityManager,
        UserAuthenticatorInterface $userAuthenticator, 
        LoginManagerAuthenticator $authenticator // <--- Ismou s7i7 hna
    ): Response {
        $user = new UserApp();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // 1. Hashage
        $clearPassword = $form->get('motdepasse')->getData();
        $user->setMot_de_passe(
            $userPasswordHasher->hashPassword($user, $clearPassword)
        );

        // 2. Initialisation
        // Si le formulaire n'a pas de champ rôle (ex: inscription simple), 
        // on peut forcer un rôle par défaut ici.
        if (!$user->getRole()) {
            $user->setRole(\App\Enum\RoleUser::USER_SIMPLE);
        }
        
        $user->setDate_creation(new \DateTime());

        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Bienvenue dans l\'aventure EcoAdventure !');

        return $userAuthenticator->authenticateUser(
            $user,
            $authenticator,
            $request
        );
    }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/reclamation/admin-reply/{id_reclamation}', name: 'app_reclamation_admin_reply', methods: ['POST'])]
    public function adminReply(Request $request, int $id_reclamation, EntityManagerInterface $entityManager, ReclamationRepository $repo): Response
    {
        $reclamation = $repo->find($id_reclamation);
        
        if ($reclamation && $this->isGranted('ROLE_ADMIN')) {
            $reponse = $request->request->get('reponse');
            $statutSelect = $request->request->get('statut');

            if (!empty(trim($reponse))) {
                $reclamation->setReponse($reponse);
                $reclamation->setStatut(\App\Enum\StatutReclamation::TRAITEE);
            } elseif ($statutSelect === 'REJETEE') {
                $reclamation->setStatut(\App\Enum\StatutReclamation::REJETEE);
                $reclamation->setReponse("Réclamation rejetée par l'administration.");
            }

            $entityManager->flush();
            $this->addFlash('success', 'Mise à jour effectuée !');
        }

        return $this->redirectToRoute('app_reclamation_index');
    }
}
