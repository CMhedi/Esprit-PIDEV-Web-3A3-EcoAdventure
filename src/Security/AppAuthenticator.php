<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(private RouterInterface $router)
    {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && $request->get('_login_form') === '1' && $this->getLoginUrl($request);
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_email');
        $password = $request->request->get('_password');
        $request->getSession()->set('_security.last_username', $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new JsonResponse(['redirect' => $targetPath]);
        }

        // Redirect based on user role
        $user = $token->getUser();
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            return new JsonResponse(['redirect' => $this->router->generate('admin_dashboard')]);
        } else {
            return new JsonResponse(['redirect' => $this->router->generate('app_messagerie')]);
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = 'Invalid credentials.';
        if ($exception instanceof BadCredentialsException) {
            $message = 'Invalid email or password.';
        }

        return new JsonResponse(['error' => $message], Response::HTTP_UNAUTHORIZED);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate('app_login');
    }
}
