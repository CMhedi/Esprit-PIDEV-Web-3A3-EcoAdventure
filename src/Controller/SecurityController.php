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
            ? $this->redirectToRoute('app_admin_dashboard')
            : $this->redirectToRoute('app_messagerie_root');
    }

    #[Route('/reactivate-account/{id}/{token}', name: 'app_reactivate_account')]
    public function reactivateAccount(
        int $id,
        string $token,
        \Doctrine\ORM\EntityManagerInterface $entityManager
    ): Response {
        $user = $entityManager->getRepository(UserApp::class)->find($id);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_login');
        }

        // Generate the expected token based on current user data
        $expectedToken = md5($user->getId() . $user->getEmail() . $user->getPassword() . 'reactivate');
        
        if (!hash_equals($expectedToken, $token)) {
            $this->addFlash('error', 'Lien de réactivation invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        $user->resetFailedAttempts();
        $entityManager->flush();

        $this->addFlash('success', 'Votre compte a été réactivé avec succès. Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_login');
    }
}
