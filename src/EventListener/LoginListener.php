<?php

namespace App\EventListener;

use App\Entity\UserApp;
use App\Service\SecurityAlertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class LoginListener implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SecurityAlertService $securityAlertService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $exception = $event->getException();
        if ($exception instanceof \Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException &&
            $exception->getMessageKey() === 'Votre compte a été bloqué pour sécurité après 3 tentatives échouées.') {
            // Do not increment if already blocked
            return;
        }

        $passport = $event->getPassport();
        if (!$passport) {
            return;
        }

        $userBadge = $passport->getBadge(UserBadge::class);
        if (!$userBadge) {
            return;
        }

        $userIdentifier = $userBadge->getUserIdentifier();
        $user = $this->entityManager->getRepository(UserApp::class)->findOneBy(['email' => $userIdentifier]);

        if ($user instanceof UserApp) {
            if ($user->getFailedAttempts() < 3) {
                $user->incrementFailedAttempts();
                
                if ($user->getFailedAttempts() === 3) {
                    $this->securityAlertService->notifyAdminOfFailedAttempts($user, $event->getRequest());
                }

                $this->entityManager->flush();
            }
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof UserApp) {
            $user->resetFailedAttempts();
            $this->entityManager->flush();
        }
    }
}
