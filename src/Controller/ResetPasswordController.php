<?php

namespace App\Controller;

use App\Entity\UserApp;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Service\SmsService;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private UserManager $userManager
    ) {
    }

    /**
     * Display & process form to request a password reset.
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator, SmsService $smsService): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $identifier */
            $identifier = $form->get('identifier')->getData();

            try {
                $this->userManager->validateResetIdentifier($identifier);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('reset_password_error', $e->getMessage());
                return $this->redirectToRoute('app_forgot_password_request');
            }

            $user = $this->entityManager->getRepository(UserApp::class)->createQueryBuilder('u')
                ->where('u.email = :identifier OR u.telephone = :identifier')
                ->setParameter('identifier', $identifier)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$user) {
                $this->addFlash('reset_password_error', 'Utilisateur non trouvé .');
                return $this->redirectToRoute('app_forgot_password_request');
            }

            if ($identifier === $user->getTelephone()) {
                // SMS logic using Twilio Verify
                try {
                    $request->getSession()->set('sms_reset_user_id', $user->getId());
                    
                    $smsService->sendVerificationCode($user->getTelephone());
                    
                    return $this->redirectToRoute('app_check_sms_code');
                } catch (\Exception $e) {
                    $this->addFlash('reset_password_error', 'Erreur lors de l\'envoi du SMS: ' . $e->getMessage());
                    return $this->redirectToRoute('app_forgot_password_request');
                }
            }

            return $this->processSendingPasswordResetEmail($user->getEmail(), $mailer, $translator);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/check-sms-code', name: 'app_check_sms_code')]
    public function checkSmsCode(Request $request, SmsService $smsService): Response
    {
        $userId = $request->getSession()->get('sms_reset_user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            
            $user = $this->entityManager->getRepository(UserApp::class)->find($userId);

            if ($code && $user) {
                try {
                    if ($smsService->checkVerificationCode($user->getTelephone(), $code)) {
                        $request->getSession()->set('sms_reset_verified', true);
                        return $this->redirectToRoute('app_reset_password_sms');
                    } else {
                        $this->addFlash('reset_password_error', 'Code SMS incorrect.');
                    }
                } catch (\Exception $e) {
                    $this->addFlash('reset_password_error', 'Erreur de vérification : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('reset_password_error', 'Code invalide.');
            }
        }

        return $this->render('reset_password/check_sms_code.html.twig');
    }

    #[Route('/reset-sms', name: 'app_reset_password_sms')]
    public function resetSms(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $userId = $request->getSession()->get('sms_reset_user_id');
        $isVerified = $request->getSession()->get('sms_reset_verified');

        if (!$userId || !$isVerified) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $user = $this->entityManager->getRepository(UserApp::class)->find($userId);
        if (!$user) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setMot_de_passe($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            $request->getSession()->remove('sms_reset_user_id');
            $request->getSession()->remove('sms_reset_code');
            $request->getSession()->remove('sms_reset_verified');

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    /**
     * Confirmation page after a user has requested a password reset.
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Generate a fake token if the user does not exist or if the user has already been sent an email
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, ?string $token = null): Response
    {
        if ($token) {
            // We store the token in session and redirect to the target entity to prevent
            // the URL from being exposed in logs or browser history.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();

        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            /** @var UserApp $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $this->resetPasswordHelper->removeResetRequest($token);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Encode(hash) the plain password, and set it.
            $user->setMot_de_passe($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            // The session is cleaned up after the password has been changed.
            $this->cleanSessionAfterReset();

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, TranslatorInterface $translator): RedirectResponse
    {
        $user = $this->entityManager->getRepository(UserApp::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        if (!$user) {
            $this->addFlash('reset_password_error', 'Utilisateur non trouvé .');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_HANDLE, [], 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $email = (new TemplatedEmail())
            ->from(new Address('salmalahmar5@gmail.com', 'EcoAdventure Support'))
            ->to((string) $user->getEmail())
            ->subject('Your password reset request')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ])
        ;

        $mailer->send($email);

        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}