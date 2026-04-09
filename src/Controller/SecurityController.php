<?php

namespace App\Controller;

use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Repository\UserAppRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(
        Request $request,
        UserAppRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            return $this->redirectAfterLogin($authenticatedUser);
        }

        $session = $request->getSession();
        $sessionUserId = $session->get('current_user_id');

        if ($sessionUserId) {
            $sessionUser = $userRepository->find($sessionUserId);
            if ($sessionUser instanceof UserApp) {
                return $this->redirectAfterLogin($sessionUser);
            }

            $session->remove('current_user_id');
            $session->remove('current_user_name');
            $session->remove('current_user_role');
        }

        $error = null;
        $lastUsername = '';

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('_username', ''));
            $password = (string) $request->request->get('_password', '');
            $lastUsername = $email;

            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user instanceof UserApp && $passwordHasher->isPasswordValid($user, $password)) {
                $session->set('current_user_id', $user->getId_user());
                $session->set('current_user_name', trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')));
                $session->set('current_user_role', $user->getRole()?->value);

                return $this->redirectAfterLogin($user);
            }

            $error = 'Email ou mot de passe invalide.';
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->invalidate();
        return $this->redirectToRoute('app_home');
    }

    private function redirectAfterLogin(UserApp $user): Response
    {
        return $user->getRole() === RoleUser::ADMIN
            ? $this->redirectToRoute('admin_dashboard')
            : $this->redirectToRoute('app_messagerie_root');
    }
}
