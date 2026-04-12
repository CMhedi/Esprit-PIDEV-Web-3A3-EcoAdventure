<?php

namespace App\Controller;

use App\Entity\UserApp;
use App\Enum\RoleUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            return $this->redirectAfterLogin($authenticatedUser);
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the logout firewall.');
    }

    private function redirectAfterLogin(UserApp $user): Response
    {
        return $user->getRole() === RoleUser::ADMIN
            ? $this->redirectToRoute('admin_dashboard')
            : $this->redirectToRoute('app_messagerie_root');
    }
}
