<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    #[Route('', name: 'app_forgot_password_request')]
    #[Route('/check-email', name: 'app_check_email')]
    #[Route('/check-sms-code', name: 'app_check_sms_code')]
    #[Route('/reset-sms', name: 'app_reset_password_sms')]
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function unavailable(): Response
    {
        $this->addFlash('warning', 'La reinitialisation de mot de passe est desactivee sur cet environnement local.');

        return $this->redirectToRoute('app_login');
    }
}
