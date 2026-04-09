<?php

namespace App\Controller;

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