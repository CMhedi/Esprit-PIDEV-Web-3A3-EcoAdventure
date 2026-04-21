<?php

namespace App\Service;

use App\Entity\UserApp;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\RoleUser;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SecurityAlertService
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function notifyAdminOfFailedAttempts(UserApp $user, Request $request = null): void
    {
        $ipAddress = $request ? $request->getClientIp() : 'Inconnue';
        $userAgent = $request ? $request->headers->get('User-Agent') : 'Inconnu';

        // Generate Reactivation Token and Link
        $token = md5($user->getId() . $user->getEmail() . $user->getPassword() . 'reactivate');
        $reactivationLink = $this->urlGenerator->generate('app_reactivate_account', [
            'id' => $user->getId(),
            'token' => $token
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // Find all admins to notify
        $admins = $this->entityManager->getRepository(UserApp::class)->findBy(['role' => RoleUser::ADMIN]);
        $adminEmails = [];
        foreach ($admins as $admin) {
            if ($admin->getEmail()) {
                $adminEmails[] = $admin->getEmail();
            }
        }
        
        if (empty($adminEmails)) {
            $adminEmails[] = 'admin@ecoadventure.com'; // Fallback if no admin found
        }

        $htmlContent = sprintf(
            '<div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h2 style="color: #d9534f;">Alerte de Sécurité</h2>
                <p>Bonjour,</p>
                <p>Une activité suspecte a été détectée sur le compte suivant :</p>
                <ul>
                    <li><strong>Utilisateur ciblé :</strong> %s %s</li>
                    <li><strong>Email :</strong> %s</li>
                    <li><strong>Tentatives échouées :</strong> %d</li>
                    <li><strong>Adresse IP de l\'attaquant :</strong> %s</li>
                    <li><strong>Navigateur / Appareil :</strong> %s</li>
                </ul>
                <p>Le mot de passe a été saisi de manière erronée 3 fois de suite.</p>
                <p>Par mesure de sécurité, ce compte a été <strong>bloqué</strong>.</p>
                <div style="margin: 30px 0; text-align: center;">
                    <a href="%s" style="background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                        Réactiver le compte maintenant
                    </a>
                </div>
                <p>Si vous êtes à l\'origine de ces tentatives, vous pouvez utiliser le bouton ci-dessus pour débloquer votre accès.</p>
                <br>
                <p>Cordialement,<br>Le système EcoAdventure</p>
            </div>',
            $user->getPrenom(),
            $user->getNom(),
            $user->getEmail(),
            $user->getFailedAttempts(),
            $ipAddress,
            $userAgent,
            $reactivationLink
        );

        // 1. Send to all Admins
        foreach ($adminEmails as $adminEmail) {
            $emailAdmin = (new Email())
                ->from('security@ecoadventure.com')
                ->to($adminEmail)
                ->subject('Alerte de Sécurité : Tentatives de connexion suspectes')
                ->html($htmlContent);
            $this->mailer->send($emailAdmin);
        }

        // 2. Send to the Targeted User
        $emailUser = (new Email())
            ->from('security@ecoadventure.com')
            ->to($user->getEmail())
            ->subject('Alerte de Sécurité : Tentatives de connexion suspectes sur votre compte')
            ->html($htmlContent);
        $this->mailer->send($emailUser);
    }
}
