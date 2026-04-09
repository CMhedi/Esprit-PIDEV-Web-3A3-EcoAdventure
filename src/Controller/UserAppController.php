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
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $userApp = new UserApp();
        $form = $this->createForm(UserAppType::class, $userApp);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($userApp);
            $entityManager->flush();

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
public function edit(Request $request, UserApp $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
{
    $form = $this->createForm(UserAppType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // 1. Gérer le mot de passe s'il est rempli
        $plainPassword = $form->get('plainPassword')->getData();
        if ($plainPassword) {
            $user->setMot_de_passe(
                $userPasswordHasher->hashPassword($user, $plainPassword)
            );
        }

        $entityManager->flush();

        // REDIRECTION LEL PROFIL (Mouch lel index/list)
        return $this->redirectToRoute('app_profile_index'); 
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
