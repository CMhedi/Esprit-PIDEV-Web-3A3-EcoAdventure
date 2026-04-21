<?php

namespace App\Controller;

use App\Entity\UserApp;
use App\Form\UserAppType;
use App\Repository\UserAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/user/app')]
final class UserAppController extends AbstractController
{
    #[Route(name: 'app_user_app_index', methods: ['GET'])]
    public function index(UserAppRepository $userAppRepository): Response
    {
        return $this->render('user_app/index.html.twig', [
            'user_apps' => $userAppRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_app_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $userApp = new UserApp();
        $form = $this->createForm(UserAppType::class, $userApp, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'is_coach' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $userApp->setMot_de_passe(
                    $userPasswordHasher->hashPassword($userApp, $plainPassword)
                );
            } else {
                // If no password is provided during creation, you might want to handle this error
                // or provide a default password. Since the DB requires it, we should probably
                // have made it required in the form.
            }

            $entityManager->persist($userApp);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur ' . $userApp->getNom() . ' ajouté avec succès !');

            return $this->redirectToRoute('app_user_app_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user_app/new.html.twig', [
            'user_app' => $userApp,
            'form' => $form,
        ]);
    }

    #[Route('/{id_user}', name: 'app_user_app_show', methods: ['GET'])]
    public function show(UserApp $userApp): Response
    {
        return $this->render('user_app/show.html.twig', [
            'user_app' => $userApp,
        ]);
    }

// src/Controller/UserAppController.php

#[Route('/{id_user}/edit', name: 'app_user_app_edit', methods: ['GET', 'POST'])]
public function edit(Request $request, UserApp $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, SluggerInterface $slugger): Response
{
    // Security check: Only Admin or the owner can edit
    if (!$this->isGranted('ROLE_ADMIN') && $this->getUser() !== $user) {
        throw $this->createAccessDeniedException("Vous n'avez pas le droit de modifier ce profil.");
    }

    $form = $this->createForm(UserAppType::class, $user, [
        'is_admin' => $this->isGranted('ROLE_ADMIN'),
        'is_coach' => ($user->getRole() && $user->getRole()->value === 'COACH'),
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // 1. Gérer le mot de passe s'il est rempli
        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword) {
            $user->setMot_de_passe(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );
        }

        // 2. Gérer l'upload de l'image de profil
        $imageFile = $form->get('imageFile')->getData();
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads',
                    $newFilename
                );
                $user->setImageUrl($newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
            }
        }

        $entityManager->flush();

        // REDIRECTION: Admin editing someone else -> List, otherwise -> Show Details
        if ($this->isGranted('ROLE_ADMIN') && $this->getUser() !== $user) {
            $this->addFlash('success', 'Le profil de ' . $user->getNom() . ' a été mis à jour.');
            return $this->redirectToRoute('app_user_app_index');
        }
        $this->addFlash('success', 'Votre profil a été mis à jour.');
        return $this->redirectToRoute('app_profile_index'); 
    } elseif ($form->isSubmitted() && !$form->isValid()) {
        // Collect all errors and flash them
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }
    }

    return $this->render('user_app/edit.html.twig', [
        'user' => $user,
        'form' => $form,
    ]);
}
    #[Route('/{id_user}', name: 'app_user_app_delete', methods: ['POST'])]
    public function delete(Request $request, UserApp $userApp, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$userApp->getId_user(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($userApp);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_app_index', [], Response::HTTP_SEE_OTHER);
    }
}
