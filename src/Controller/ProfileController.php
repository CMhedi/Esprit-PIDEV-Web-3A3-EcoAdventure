<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile_index')]
    public function index(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || !($user instanceof \App\Entity\UserApp)) {
            return $this->redirectToRoute('app_login');
        }

        // Ken el user nzal 3la "Activer", n'generiw el Token
        if ($request->query->get('setup2fa')) {
            $user->setTelegramToken(bin2hex(random_bytes(10)));
            $em->flush();
            return $this->redirectToRoute('app_profile_index', ['showModal' => 1]);
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'showModal' => $request->query->get('showModal'),
        ]);
    }
}
