<?php

namespace App\EventListener;

use App\Repository\LoginRequestRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TwoFactorGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private LoginRequestRepository $loginRequestRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 0]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // If no pending 2FA in session, nothing to do
        $token = $session->get('_2fa_pending_token');
        if (!$token) {
            return;
        }

        // Allow these routes to avoid infinite loop
        $allowedRoutes = ['app_2fa_waiting', 'app_2fa_status', 'app_logout', 'app_telegram_webhook'];
        if (in_array($request->attributes->get('_route'), $allowedRoutes)) {
            return;
        }

        // Check if the token is still pending in DB
        $loginRequest = $this->loginRequestRepository->findOneBy(['token' => $token]);
        
        if ($loginRequest && $loginRequest->getStatus() === 'approved') {
            // Success! Clear the session flag and allow access
            $session->remove('_2fa_pending_token');
            return;
        }

        if ($loginRequest && $loginRequest->getStatus() === 'rejected') {
            // Rejected! Log out and clear session
            $session->remove('_2fa_pending_token');
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_logout')));
            return;
        }

        // Still pending or token invalid, force redirect to waiting page
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_2fa_waiting', ['token' => $token])));
    }
}
