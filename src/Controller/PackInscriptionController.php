<?php

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\StatutInscription;
use App\Form\InscriptionPackType;
use App\Repository\InscriptionRepository;
use App\Repository\PackRepository;
use App\Service\AI\AiPackExplainer;
use App\Service\Context\HolidayContextProvider;
use App\Service\Inscription\PackInscriptionReceiptBuilder;
use App\Service\Pack\PackInsightAssembler;
use App\Service\Pack\PackRecommendationEngine;
use App\Service\Payment\KonnectPaymentGateway;
use App\Service\Payment\KonnectPaymentRequest;
use App\Service\Payment\PaymentGatewayConfigurationException;
use App\Service\Payment\PaymentGatewayException;
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
        KonnectPaymentGateway $paymentGateway,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof UserApp) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $pack = $entityManager->getRepository(Pack::class)->find($id);
        if (!$pack) {
            throw $this->createNotFoundException('Pack introuvable.');
        }

        $allPacks = $packRepository->findForFront();
        $packInsights = $packInsightAssembler->buildInsights($allPacks);
        $currentInsight = $packInsights[$pack->getIdPack()] ?? null;
        $alternativePack = $packRecommendationEngine->recommendAlternative($pack, $allPacks, $packInsights, $user);
        $holidayContext = $holidayContextProvider->getContext();
        $packExplanation = $currentInsight
            ? $aiPackExplainer->explainChoice($currentInsight, $user, $alternativePack, $holidayContext)
            : null;

        $displayName = trim(sprintf('%s %s', $user->getPrenom(), $user->getNom()));

        $inscription = new Inscription();
        $inscription->setPack($pack);
        $inscription->setUserApp($user);
        $inscription->setNomUser($displayName);
        $inscription->setNomPack($pack->getNom());
        $inscription->setMontantTotal((string) $pack->getPrixFinal());
        $inscription->setDateInscription(new \DateTime());
        $inscription->setStatutInscr(StatutInscription::EN_ATTENTE);

        $form = $this->createForm(InscriptionPackType::class, $inscription);
        $form->handleRequest($request);

        if ($request->isMethod('GET')) {
            $packFeedbackTracker->track($user, $pack, 'view_pack_detail', ['route' => 'app_pack_inscription']);
            $packFeedbackTracker->track($user, $pack, 'open_inscription', ['route' => 'app_pack_inscription']);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $inscription->setUserApp($user);
            $inscription->setNomUser($displayName);
            $inscription->setNomPack($pack->getNom());
            $inscription->setMontantTotal((string) $pack->getPrixFinal());
            $inscription->setDateInscription(new \DateTime());
            $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_KONNECT);
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_INITIATED);
            $inscription->setPaymentOrderId($this->buildPaymentOrderId($pack));

            $entityManager->persist($inscription);
            $entityManager->flush();

            try {
                $paymentSession = $paymentGateway->initiatePayment(new KonnectPaymentRequest(
                    $inscription->getPaymentOrderId() ?? (string) $inscription->getIdInscription(),
                    $pack->getPrixFinal(),
                    sprintf('Inscription EcoAdventure - %s', $pack->getNom()),
                    $this->generateUrl('app_pack_inscription_payment_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    $user->getPrenom(),
                    $user->getNom(),
                    $user->getEmail(),
                ));
            } catch (PaymentGatewayConfigurationException $exception) {
                $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_FAILED);
                $entityManager->flush();

                $this->addFlash('danger', 'Le paiement en ligne n est pas encore configure. Ajoutez KONNECT_API_KEY et KONNECT_WALLET_ID dans votre environnement.');

                return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
            } catch (PaymentGatewayException $exception) {
                $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_FAILED);
                $entityManager->flush();

                $this->addFlash('danger', 'Le service de paiement est indisponible pour le moment. Reessayez dans quelques minutes.');

                return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
            }

            $inscription->setPaymentReference($paymentSession->getPaymentRef());
            $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PENDING);
            $entityManager->flush();

            $packFeedbackTracker->track($user, $pack, 'inscription_payment_started', [
                'inscription_id' => $inscription->getIdInscription(),
                'payment_gateway' => $inscription->getPaymentGateway(),
                'payment_reference' => $inscription->getPaymentReference(),
            ]);

            return $this->redirect($paymentSession->getPayUrl());
        }

        $latestInscription = $inscriptionRepository->findLatestForUserAndPack($user->getId_user(), $pack->getIdPack());

        return $this->render('front/hedisPackInscription/pack_inscription.html.twig', [
            'pack' => $pack,
            'form' => $form->createView(),
            'currentUser' => $user,
            'packInsight' => $currentInsight,
            'alternativePack' => $alternativePack,
            'packExplanation' => $packExplanation,
            'holidayContext' => $holidayContext,
            'latestInscription' => $latestInscription,
            'paymentGatewayConfigured' => $paymentGateway->isConfigured(),
            'paymentToken' => $paymentGateway->getToken(),
            'demoCardPaymentEnabled' => $this->isDemoCardPaymentEnabled(),
            'cardOcrApiUrl' => $this->getParameter('card_ocr_api_url'),
        ]);
    }

    #[Route('/packs/{id}/inscription/card-demo', name: 'app_pack_inscription_card_demo', methods: ['POST'])]
    public function demoCardPayment(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        PackFeedbackTracker $packFeedbackTracker,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof UserApp) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $pack = $entityManager->getRepository(Pack::class)->find($id);
        if (!$pack) {
            throw $this->createNotFoundException('Pack introuvable.');
        }

        if (!$this->isDemoCardPaymentEnabled()) {
            $this->addFlash('danger', 'Le paiement carte demo est desactive.');

            return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
        }

        if (!$this->isCsrfTokenValid('card_payment_' . $pack->getIdPack(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session de paiement invalide. Rechargez la page puis reessayez.');

            return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
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
            $this->addFlash('danger', 'Les donnees carte demo sont incompletes ou invalides. Utilisez par exemple 4242 4242 4242 4242, une expiration future et CVC 123.');

            return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
        }

        $displayName = trim(sprintf('%s %s', $user->getPrenom(), $user->getNom()));
        $paymentReference = sprintf('DEMO-%s-%s', strtoupper($cardLast4), strtoupper(bin2hex(random_bytes(4))));

        $inscription = new Inscription();
        $inscription->setPack($pack);
        $inscription->setUserApp($user);
        $inscription->setNomUser($displayName);
        $inscription->setNomPack($pack->getNom());
        $inscription->setMontantTotal((string) $pack->getPrixFinal());
        $inscription->setDateInscription(new \DateTime());
        $inscription->setStatutInscr(StatutInscription::CONFIRMEE);
        $inscription->setPaymentGateway(Inscription::PAYMENT_GATEWAY_CARD_DEMO);
        $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PAID);
        $inscription->setPaymentOrderId($this->buildPaymentOrderId($pack));
        $inscription->setPaymentReference($paymentReference);
        $inscription->setPaidAt(new \DateTimeImmutable());

        $entityManager->persist($inscription);
        $entityManager->flush();

        $packFeedbackTracker->track($user, $pack, 'inscription_card_demo_paid', [
            'inscription_id' => $inscription->getIdInscription(),
            'payment_gateway' => $inscription->getPaymentGateway(),
            'payment_reference' => $inscription->getPaymentReference(),
            'card_brand' => $cardBrand,
            'card_last4' => $cardLast4,
        ]);

        $this->addFlash('success', 'Paiement carte demo accepte. Votre inscription au pack est confirmee.');

        return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
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

            return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
        }

        return new Response(sprintf('Paiement %s.', $inscription->getPaymentStatus() ?? 'mis a jour'), Response::HTTP_OK);
    }

    #[Route('/inscriptions/{id}/receipt.pdf', name: 'app_pack_inscription_receipt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function receipt(
        int $id,
        InscriptionRepository $inscriptionRepository,
        PackInscriptionReceiptBuilder $receiptBuilder,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof UserApp) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

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

        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !$this->passesLuhn($cardNumber)) {
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

        $expiryDate = $expiryDate->modify('+1 month');
        $currentMonth = new \DateTimeImmutable('first day of this month');

        return $expiryDate > $currentMonth && strlen($cardCvc) >= 3 && strlen($cardCvc) <= 4;
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

    private function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $shouldDouble = false;

        for ($index = strlen($digits) - 1; $index >= 0; --$index) {
            $digit = (int) $digits[$index];

            if ($shouldDouble) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $shouldDouble = !$shouldDouble;
        }

        return $sum > 0 && $sum % 10 === 0;
    }
}
