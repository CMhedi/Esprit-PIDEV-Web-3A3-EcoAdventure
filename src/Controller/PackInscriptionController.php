<?php

namespace App\Controller;

use App\Dto\PackInscriptionRequest;
use App\Dto\StripeCheckoutData;
use App\Dto\StripeCheckoutRequest;
use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\StatutInscription;
use App\Form\InscriptionPackType;
use App\Form\StripeCheckoutType;
use App\Repository\InscriptionRepository;
use App\Repository\PackRepository;
use App\Service\AI\AiPackExplainer;
use App\Service\Context\HolidayContextProvider;
use App\Service\Inscription\PackInscriptionReceiptBuilder;
use App\Service\Inscription\PackInscriptionTicketFactory;
use App\Service\Pack\PackInsightAssembler;
use App\Service\Pack\PackRecommendationEngine;
use App\Service\Payment\KonnectPaymentGateway;
use App\Service\Payment\PaymentGatewayConfigurationException;
use App\Service\Payment\PaymentGatewayException;
use App\Service\Payment\StripeCheckoutGateway;
use App\Service\Tracking\PackFeedbackTracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PackInscriptionController extends AbstractController
{
    #[Route('/packs/{id}/inscription', name: 'app_pack_inscription', methods: ['GET', 'POST'])]
    public function inscrire(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        PackRepository $packRepository,
        InscriptionRepository $inscriptionRepository,
        PackInsightAssembler $packInsightAssembler,
        PackRecommendationEngine $packRecommendationEngine,
        PackFeedbackTracker $packFeedbackTracker,
        HolidayContextProvider $holidayContextProvider,
        AiPackExplainer $aiPackExplainer,
        PackInscriptionTicketFactory $ticketFactory,
    ): Response {
        $user = $this->getAuthenticatedUser();
        $pack = $this->findPackOrFail($id, $entityManager);

        $allPacks = $packRepository->findForFront();
        $packInsights = $packInsightAssembler->buildInsights($allPacks);
        $currentInsight = $packInsights[$pack->getIdPack()] ?? null;
        $alternativePack = $packRecommendationEngine->recommendAlternative($pack, $allPacks, $packInsights, $user);
        $holidayContext = $holidayContextProvider->getContext();
        $packExplanation = $currentInsight
            ? $aiPackExplainer->explainChoice($currentInsight, $user, $alternativePack, $holidayContext)
            : null;

        $formData = new PackInscriptionRequest();
        $form = $this->createForm(InscriptionPackType::class, $formData);
        $form->handleRequest($request);

        $latestInscription = $inscriptionRepository->findLatestForUserAndPack($user->getId_user(), $pack->getIdPack());

        if ($request->isMethod('GET')) {
            $packFeedbackTracker->track($user, $pack, 'view_pack_detail', ['route' => 'app_pack_inscription']);
            $packFeedbackTracker->track($user, $pack, 'open_inscription', ['route' => 'app_pack_inscription']);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($latestInscription && !$latestInscription->isPaid()) {
                $this->addFlash('warning', 'Une inscription est deja en attente pour ce pack. Reprenez directement l etape de paiement.');

                return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $latestInscription->getIdInscription()]);
            }

            $inscription = $this->createPendingInscription($user, $pack);

            $entityManager->persist($inscription);
            $entityManager->flush();

            $packFeedbackTracker->track($user, $pack, 'inscription_created', [
                'inscription_id' => $inscription->getIdInscription(),
                'payment_status' => $inscription->getPaymentStatus(),
            ]);

            $this->addFlash('success', 'Inscription enregistree. Passez maintenant au paiement securise.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        return $this->render('front/hedisPackInscription/pack_inscription.html.twig', [
            'pack' => $pack,
            'form' => $form->createView(),
            'currentUser' => $user,
            'packInsight' => $currentInsight,
            'alternativePack' => $alternativePack,
            'packExplanation' => $packExplanation,
            'holidayContext' => $holidayContext,
            'latestInscription' => $latestInscription,
            'latestTicketUrl' => $this->isTicketAvailable($latestInscription) ? $ticketFactory->generatePublicTicketUrl($latestInscription) : null,
        ]);
    }

    #[Route('/inscriptions/{id}/payment', name: 'app_pack_inscription_payment', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function payment(
        int $id,
        InscriptionRepository $inscriptionRepository,
        StripeCheckoutGateway $stripeCheckoutGateway,
        PackInscriptionTicketFactory $ticketFactory,
    ): Response {
        $user = $this->getAuthenticatedUser();
        $inscription = $this->findInscriptionOrFail($id, $inscriptionRepository);
        $this->assertInscriptionOwnership($inscription, $user);

        $stripeData = new StripeCheckoutData();
        $stripeData->customerName = trim(sprintf('%s %s', $user->getPrenom(), $user->getNom()));
        $stripeData->customerEmail = (string) $user->getEmail();

        $stripeForm = $this->createForm(StripeCheckoutType::class, $stripeData, [
            'action' => $this->generateUrl('app_pack_inscription_payment_stripe', ['id' => $inscription->getIdInscription()]),
            'method' => 'POST',
        ]);

        return $this->render('front/hedisPackInscription/pack_payment.html.twig', [
            'inscription' => $inscription,
            'pack' => $inscription->getPack(),
            'currentUser' => $user,
            'stripeForm' => $stripeForm->createView(),
            'stripeConfigured' => $stripeCheckoutGateway->isConfigured(),
            'stripeMissingSettings' => $stripeCheckoutGateway->getMissingConfigurationFields(),
            'stripePublishableKey' => $stripeCheckoutGateway->getPublishableKey(),
            'stripeCurrency' => $stripeCheckoutGateway->getCurrency(),
            'demoCardPaymentEnabled' => $this->isDemoCardPaymentEnabled(),
            'cardOcrApiUrl' => $this->getParameter('card_ocr_api_url'),
            'ticketUrl' => $this->isTicketAvailable($inscription) ? $ticketFactory->generatePublicTicketUrl($inscription) : null,
        ]);
    }

    #[Route('/inscriptions/{id}/payment/stripe', name: 'app_pack_inscription_payment_stripe', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function stripePayment(
        int $id,
        Request $request,
        InscriptionRepository $inscriptionRepository,
        EntityManagerInterface $entityManager,
        StripeCheckoutGateway $stripeCheckoutGateway,
        PackFeedbackTracker $packFeedbackTracker,
    ): Response {
        $user = $this->getAuthenticatedUser();
        $inscription = $this->findInscriptionOrFail($id, $inscriptionRepository);
        $this->assertInscriptionOwnership($inscription, $user);

        if ($inscription->isPaid()) {
            $this->addFlash('success', 'Cette inscription est deja reglee.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        $stripeData = new StripeCheckoutData();
        $stripeData->customerName = trim(sprintf('%s %s', $user->getPrenom(), $user->getNom()));
        $stripeData->customerEmail = (string) $user->getEmail();

        $form = $this->createForm(StripeCheckoutType::class, $stripeData);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'Le formulaire Stripe est incomplet. Verifiez les confirmations demandees.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        try {
            $pack = $inscription->getPack();
            if (!$pack) {
                throw $this->createNotFoundException('Pack introuvable pour cette inscription.');
            }

            if (!$inscription->getPaymentOrderId()) {
                $inscription->setPaymentOrderId($this->buildPaymentOrderId($pack));
            }

            $checkoutSession = $stripeCheckoutGateway->createCheckoutSession(new StripeCheckoutRequest(
                $inscription->getPaymentOrderId() ?? (string) $inscription->getIdInscription(),
                (float) $inscription->getMontantTotal(),
                $stripeCheckoutGateway->getCurrency(),
                sprintf('Inscription EcoAdventure - %s', $pack->getNom()),
                $this->generateUrl('app_pack_inscription_payment_stripe_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
                $this->generateUrl('app_pack_inscription_payment_stripe_cancel', ['id' => $inscription->getIdInscription()], UrlGeneratorInterface::ABSOLUTE_URL),
                $stripeData->customerEmail,
                $stripeData->customerName,
                [
                    'inscription_id' => (string) $inscription->getIdInscription(),
                    'payment_order_id' => (string) $inscription->getPaymentOrderId(),
                ],
            ));
        } catch (PaymentGatewayConfigurationException $exception) {
            $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_STRIPE);
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_FAILED);
            $entityManager->flush();

            $this->addFlash('danger', 'Stripe n est pas encore configure. Ajoutez STRIPE_SECRET_KEY et STRIPE_PUBLISHABLE_KEY dans votre environnement.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        } catch (PaymentGatewayException $exception) {
            $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_STRIPE);
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_FAILED);
            $entityManager->flush();

            $this->addFlash('danger', 'Le service Stripe est indisponible pour le moment. Reessayez dans quelques minutes.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_STRIPE);
        $inscription->setPaymentReference($checkoutSession->getId());
        $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PENDING);
        $entityManager->flush();

        $packFeedbackTracker->track($user, $pack, 'inscription_payment_started', [
            'inscription_id' => $inscription->getIdInscription(),
            'payment_gateway' => $inscription->getPaymentGateway(),
            'payment_reference' => $inscription->getPaymentReference(),
        ]);

        if (!$checkoutSession->getUrl()) {
            $this->addFlash('danger', 'Stripe n a pas retourne d URL de paiement exploitable.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        return $this->redirect($checkoutSession->getUrl());
    }

    #[Route('/inscriptions/payment/stripe/success', name: 'app_pack_inscription_payment_stripe_success', methods: ['GET'])]
    public function stripeSuccess(
        Request $request,
        InscriptionRepository $inscriptionRepository,
        EntityManagerInterface $entityManager,
        StripeCheckoutGateway $stripeCheckoutGateway,
        PackFeedbackTracker $packFeedbackTracker,
    ): Response {
        $sessionId = trim((string) $request->query->get('session_id', ''));
        if ($sessionId === '') {
            return new Response('Session Stripe manquante.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $checkoutSession = $stripeCheckoutGateway->getCheckoutSession($sessionId);
        } catch (PaymentGatewayException|PaymentGatewayConfigurationException $exception) {
            return new Response('Verification Stripe indisponible.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $metadata = $checkoutSession->getMetadata();
        $inscriptionId = isset($metadata['inscription_id']) ? (int) $metadata['inscription_id'] : 0;
        if ($inscriptionId <= 0) {
            return new Response('Inscription Stripe introuvable.', Response::HTTP_NOT_FOUND);
        }

        $inscription = $inscriptionRepository->find($inscriptionId);
        if (!$inscription) {
            return new Response('Inscription Stripe introuvable.', Response::HTTP_NOT_FOUND);
        }

        $expectedAmount = $stripeCheckoutGateway->amountToSmallestUnit((float) $inscription->getMontantTotal());
        $orderMatches = !$checkoutSession->getClientReferenceId() || $checkoutSession->getClientReferenceId() === $inscription->getPaymentOrderId();
        $currencyMatches = strtolower($checkoutSession->getCurrency()) === strtolower($stripeCheckoutGateway->getCurrency());

        if ($checkoutSession->isPaid() && $checkoutSession->getAmountTotal() >= $expectedAmount && $orderMatches && $currencyMatches) {
            $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_STRIPE);
            $inscription->setPaymentReference($checkoutSession->getId());
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PAID);
            $inscription->setStatutInscr(StatutInscription::CONFIRMEE);
            $inscription->setPaidAt(new \DateTimeImmutable());
        } elseif ($checkoutSession->isPaid()) {
            $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_STRIPE);
            $inscription->setPaymentReference($checkoutSession->getId());
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_AMOUNT_MISMATCH);
        } else {
            $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_STRIPE);
            $inscription->setPaymentReference($checkoutSession->getId());
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PENDING);
        }

        $entityManager->flush();

        $pack = $inscription->getPack();
        $user = $inscription->getUserApp();
        if ($pack && $user instanceof UserApp) {
            $packFeedbackTracker->track($user, $pack, 'inscription_payment_callback', [
                'inscription_id' => $inscription->getIdInscription(),
                'payment_reference' => $checkoutSession->getId(),
                'payment_status' => $inscription->getPaymentStatus(),
            ]);
        }

        if ($this->getUser() instanceof UserApp) {
            if ($inscription->isPaid()) {
                $this->addFlash('success', 'Paiement Stripe confirme. Votre inscription au pack est maintenant validee.');
            } else {
                $this->addFlash('warning', 'Le paiement Stripe est encore en attente de confirmation.');
            }

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        return new Response(sprintf('Paiement %s.', $inscription->getPaymentStatus() ?? 'mis a jour'), Response::HTTP_OK);
    }

    #[Route('/inscriptions/{id}/payment/stripe/cancel', name: 'app_pack_inscription_payment_stripe_cancel', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function stripeCancel(
        int $id,
        InscriptionRepository $inscriptionRepository,
    ): Response {
        $user = $this->getAuthenticatedUser();
        $inscription = $this->findInscriptionOrFail($id, $inscriptionRepository);
        $this->assertInscriptionOwnership($inscription, $user);

        $this->addFlash('warning', 'Le paiement Stripe a ete annule. Vous pouvez reprendre plus tard.');

        return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
    }

    #[Route('/inscriptions/{id}/payment/card-demo', name: 'app_pack_inscription_card_demo', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function demoCardPayment(
        int $id,
        Request $request,
        InscriptionRepository $inscriptionRepository,
        EntityManagerInterface $entityManager,
        PackFeedbackTracker $packFeedbackTracker,
    ): Response {
        $user = $this->getAuthenticatedUser();
        $inscription = $this->findInscriptionOrFail($id, $inscriptionRepository);
        $this->assertInscriptionOwnership($inscription, $user);

        $pack = $inscription->getPack();
        if (!$pack) {
            throw $this->createNotFoundException('Pack introuvable.');
        }

        if ($inscription->isPaid()) {
            $this->addFlash('success', 'Cette inscription est deja reglee.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        if (!$this->isDemoCardPaymentEnabled()) {
            $this->addFlash('danger', 'Le paiement carte demo est desactive.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        if (!$this->isCsrfTokenValid('card_payment_' . $inscription->getIdInscription(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session de paiement invalide. Rechargez la page puis reessayez.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        $cardToken = trim((string) $request->request->get('card_token', ''));
        $cardNumber = preg_replace('/\D+/', '', (string) $request->request->get('card_number_demo', '')) ?? '';
        $cardExpiry = trim((string) $request->request->get('card_expiry_demo', ''));
        $cardCvc = preg_replace('/\D+/', '', (string) $request->request->get('card_cvc_demo', '')) ?? '';
        $cardLast4 = preg_replace('/\D+/', '', (string) $request->request->get('card_last4', '')) ?? '';
        $cardBrand = trim((string) $request->request->get('card_brand', 'Carte'));

        if ($cardLast4 === '' && $cardNumber !== '') {
            $cardLast4 = substr($cardNumber, -4);
        }

        if ($cardBrand === 'Carte' && $cardNumber !== '') {
            $cardBrand = $this->detectDemoCardBrand($cardNumber);
        }

        if ($cardToken === '' && $cardNumber !== '' && $cardExpiry !== '' && $cardCvc !== '') {
            $cardToken = 'demo_card_server_' . bin2hex(random_bytes(12));
        }

        if (!$this->isValidDemoCardPayload($cardToken, $cardNumber, $cardExpiry, $cardCvc, $cardLast4)) {
            $this->addFlash('danger', 'Les donnees carte demo sont incompletes ou invalides. Utilisez par exemple 4242 4242 4242 4242, une expiration au format MM/AA et CVC 123.');

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        $paymentReference = sprintf('DEMO-%s-%s', strtoupper($cardLast4), strtoupper(bin2hex(random_bytes(4))));

        $inscription->setNomUser(trim(sprintf('%s %s', $user->getPrenom(), $user->getNom())));
        $inscription->setNomPack($pack->getNom());
        $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_CARD_DEMO);
        $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PAID);
        $inscription->setPaymentReference($paymentReference);
        $inscription->setStatutInscr(StatutInscription::CONFIRMEE);
        $inscription->setPaidAt(new \DateTimeImmutable());

        if (!$inscription->getPaymentOrderId()) {
            $inscription->setPaymentOrderId($this->buildPaymentOrderId($pack));
        }

        $entityManager->flush();

        $packFeedbackTracker->track($user, $pack, 'inscription_card_demo_paid', [
            'inscription_id' => $inscription->getIdInscription(),
            'payment_gateway' => $inscription->getPaymentGateway(),
            'payment_reference' => $inscription->getPaymentReference(),
            'card_brand' => $cardBrand,
            'card_last4' => $cardLast4,
        ]);

        $this->addFlash('success', 'Paiement carte demo accepte. Votre inscription au pack est confirmee.');

        return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
    }

    #[Route('/packs/inscription/payment/konnect/callback', name: 'app_pack_inscription_payment_callback', methods: ['GET'])]
    public function paymentCallback(
        Request $request,
        InscriptionRepository $inscriptionRepository,
        EntityManagerInterface $entityManager,
        KonnectPaymentGateway $paymentGateway,
        PackFeedbackTracker $packFeedbackTracker,
    ): Response {
        $paymentReference = trim((string) $request->query->get('payment_ref', ''));

        if ($paymentReference === '') {
            return new Response('Reference paiement manquante.', Response::HTTP_BAD_REQUEST);
        }

        $inscription = $inscriptionRepository->findOneByPaymentReference($paymentReference);
        if (!$inscription) {
            return new Response('Inscription introuvable pour ce paiement.', Response::HTTP_NOT_FOUND);
        }

        try {
            $paymentDetails = $paymentGateway->getPaymentDetails($paymentReference);
        } catch (PaymentGatewayException|PaymentGatewayConfigurationException $exception) {
            return new Response('Verification paiement indisponible.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $expectedAmount = $paymentGateway->amountToSmallestUnit((float) $inscription->getMontantTotal());
        $orderMatches = !$paymentDetails->getOrderId() || $paymentDetails->getOrderId() === $inscription->getPaymentOrderId();
        $tokenMatches = $paymentDetails->getToken() === $paymentGateway->getToken();

        if ($paymentDetails->isCompleted() && $paymentDetails->coversAmount($expectedAmount) && $orderMatches && $tokenMatches) {
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PAID);
            $inscription->setStatutInscr(StatutInscription::CONFIRMEE);
            $inscription->setPaidAt(new \DateTimeImmutable());
        } elseif ($paymentDetails->isCompleted()) {
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_AMOUNT_MISMATCH);
        } else {
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PENDING);
        }

        $entityManager->flush();

        $pack = $inscription->getPack();
        $user = $inscription->getUserApp();
        if ($pack && $user instanceof UserApp) {
            $packFeedbackTracker->track($user, $pack, 'inscription_payment_callback', [
                'inscription_id' => $inscription->getIdInscription(),
                'payment_reference' => $paymentReference,
                'payment_status' => $inscription->getPaymentStatus(),
            ]);
        }

        if ($this->getUser() instanceof UserApp && $pack) {
            if ($inscription->isPaid()) {
                $this->addFlash('success', 'Paiement confirme. Votre inscription au pack est maintenant validee.');
            } else {
                $this->addFlash('warning', 'Le paiement est encore en attente de confirmation.');
            }

            return $this->redirectToRoute('app_pack_inscription_payment', ['id' => $inscription->getIdInscription()]);
        }

        return new Response(sprintf('Paiement %s.', $inscription->getPaymentStatus() ?? 'mis a jour'), Response::HTTP_OK);
    }

    #[Route('/inscriptions/{id}/receipt.pdf', name: 'app_pack_inscription_receipt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function receipt(
        int $id,
        InscriptionRepository $inscriptionRepository,
        PackInscriptionReceiptBuilder $receiptBuilder,
    ): Response {
        $user = $this->getAuthenticatedUser();

        $inscription = $inscriptionRepository->find($id);
        if (!$inscription) {
            throw $this->createNotFoundException('Inscription introuvable.');
        }

        $ownerId = $inscription->getUserApp()?->getId_user();
        if ($ownerId !== $user->getId_user() && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Acces refuse a ce recu.');
        }

        if (!$inscription->isPaid() && !in_array($inscription->getStatutInscr(), [StatutInscription::CONFIRMEE, StatutInscription::VALIDEE], true)) {
            throw $this->createAccessDeniedException('Le recu est disponible apres confirmation du paiement.');
        }

        $response = new Response($receiptBuilder->buildPdf($inscription));
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('inscription-pack-%d.pdf', $inscription->getIdInscription())
        );

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/inscriptions/{id}/ticket/{token}', name: 'app_pack_inscription_ticket', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function publicTicket(
        int $id,
        string $token,
        InscriptionRepository $inscriptionRepository,
        PackInscriptionTicketFactory $ticketFactory,
    ): Response {
        $inscription = $inscriptionRepository->find($id);
        if (!$inscription || !$this->isTicketAvailable($inscription)) {
            throw $this->createNotFoundException('Ticket introuvable.');
        }

        if (!$ticketFactory->isValidPublicToken($inscription, $token)) {
            throw $this->createAccessDeniedException('Ticket invalide.');
        }

        return $this->render('front/hedisPackInscription/ticket.html.twig', $ticketFactory->buildViewData($inscription));
    }

    private function getAuthenticatedUser(): UserApp
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof UserApp) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        return $user;
    }

    private function findPackOrFail(int $id, EntityManagerInterface $entityManager): Pack
    {
        $pack = $entityManager->getRepository(Pack::class)->find($id);
        if (!$pack) {
            throw $this->createNotFoundException('Pack introuvable.');
        }

        return $pack;
    }

    private function findInscriptionOrFail(int $id, InscriptionRepository $inscriptionRepository): Inscription
    {
        $inscription = $inscriptionRepository->find($id);
        if (!$inscription) {
            throw $this->createNotFoundException('Inscription introuvable.');
        }

        return $inscription;
    }

    private function assertInscriptionOwnership(Inscription $inscription, UserApp $user): void
    {
        $ownerId = $inscription->getUserApp()?->getId_user();
        if ($ownerId !== $user->getId_user() && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Acces refuse a cette inscription.');
        }
    }

    private function createPendingInscription(UserApp $user, Pack $pack): Inscription
    {
        $displayName = trim(sprintf('%s %s', $user->getPrenom(), $user->getNom()));

        $inscription = new Inscription();
        $inscription->setPack($pack);
        $inscription->setUserApp($user);
        $inscription->setNomUser($displayName);
        $inscription->setNomPack($pack->getNom());
        $inscription->setMontantTotal((string) $pack->getPrixFinal());
        $inscription->setDateInscription(new \DateTime());
        $inscription->setStatutInscr(StatutInscription::EN_ATTENTE);
        $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_INITIATED);
        $inscription->setPaymentOrderId($this->buildPaymentOrderId($pack));

        return $inscription;
    }

    private function buildPaymentOrderId(Pack $pack): string
    {
        return sprintf('PACK-%d-%s', $pack->getIdPack(), strtoupper(bin2hex(random_bytes(5))));
    }

    private function isDemoCardPaymentEnabled(): bool
    {
        return filter_var($this->getParameter('demo_card_payment_enabled'), FILTER_VALIDATE_BOOL);
    }

    private function isValidDemoCardPayload(string $cardToken, string $cardNumber, string $cardExpiry, string $cardCvc, string $cardLast4): bool
    {
        if (!str_starts_with($cardToken, 'demo_card_') || strlen($cardLast4) !== 4) {
            return false;
        }

        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return false;
        }

        if (!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*(\d{2}|\d{4})$/', $cardExpiry, $matches)) {
            return false;
        }

        $year = strlen($matches[2]) === 2 ? (int) ('20' . $matches[2]) : (int) $matches[2];
        $month = (int) $matches[1];
        $expiryDate = \DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-01', $year, $month));
        if (!$expiryDate) {
            return false;
        }

        return strlen($cardCvc) >= 3 && strlen($cardCvc) <= 4;
    }

    private function detectDemoCardBrand(string $cardNumber): string
    {
        if (str_starts_with($cardNumber, '4')) {
            return 'Visa';
        }

        $firstTwo = (int) substr($cardNumber, 0, 2);
        $firstFour = (int) substr($cardNumber, 0, 4);

        if (($firstTwo >= 51 && $firstTwo <= 55) || ($firstFour >= 2221 && $firstFour <= 2720)) {
            return 'Mastercard';
        }

        return 'Carte';
    }

    private function isTicketAvailable(?Inscription $inscription): bool
    {
        if (!$inscription) {
            return false;
        }

        return $inscription->isPaid() || in_array($inscription->getStatutInscr(), [StatutInscription::CONFIRMEE, StatutInscription::VALIDEE], true);
    }
}
