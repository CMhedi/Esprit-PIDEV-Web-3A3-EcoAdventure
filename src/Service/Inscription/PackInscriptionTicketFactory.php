<?php

namespace App\Service\Inscription;

use App\Entity\Inscription;
use App\Repository\InscriptionRepository;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PackInscriptionTicketFactory
{
    public function __construct(
        private readonly InscriptionRepository $inscriptionRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Code39BarcodeGenerator $barcodeGenerator,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {
    }

    public function buildViewData(Inscription $inscription): array
    {
        $user = $inscription->getUserApp();
        $pack = $inscription->getPack();
        $confirmedCount = $pack && $pack->getIdPack()
            ? $this->inscriptionRepository->countConfirmedForPack($pack->getIdPack())
            : 0;
        $capacity = $pack?->getNbActivitesMax();
        $remainingCount = $capacity !== null ? max($capacity - $confirmedCount, 0) : null;
        $ticketUrl = $this->generatePublicTicketUrl($inscription, true);
        $barcodeValue = sprintf('PK-%06d', (int) $inscription->getIdInscription());

        return [
            'inscription' => $inscription,
            'holderFirstName' => $user?->getPrenom() ?: 'Participant',
            'holderLastName' => $user?->getNom() ?: ($inscription->getNomUser() ?? 'EcoAdventure'),
            'holderFullName' => trim(sprintf('%s %s', $user?->getPrenom() ?? '', $user?->getNom() ?? '')) ?: ($inscription->getNomUser() ?? 'Participant EcoAdventure'),
            'holderPhone' => $user?->getTelephone() ?: 'Non renseigne',
            'holderEmail' => $user?->getEmail() ?: 'Non renseigne',
            'packName' => $inscription->getNomPack() ?: ($pack?->getNom() ?? 'Pack EcoAdventure'),
            'confirmedCount' => $confirmedCount,
            'remainingCount' => $remainingCount,
            'capacity' => $capacity,
            'mobileTicketUrl' => $ticketUrl,
            'qrCode' => $this->createQrCodeBase64($ticketUrl),
            'barcodeValue' => $barcodeValue,
            'barcodeSvg' => base64_encode($this->barcodeGenerator->generateSvg($barcodeValue)),
        ];
    }

    public function generatePublicTicketUrl(Inscription $inscription, bool $absolute = false): string
    {
        return $this->urlGenerator->generate(
            'app_pack_inscription_ticket',
            [
                'id' => $inscription->getIdInscription(),
                'token' => $this->generatePublicToken($inscription),
            ],
            $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    public function isValidPublicToken(Inscription $inscription, string $token): bool
    {
        return hash_equals($this->generatePublicToken($inscription), $token);
    }

    private function generatePublicToken(Inscription $inscription): string
    {
        $payload = implode('|', [
            (string) $inscription->getIdInscription(),
            (string) $inscription->getPaymentReference(),
            (string) $inscription->getPaymentOrderId(),
            $inscription->getPaidAt()?->format(DATE_ATOM) ?? '',
        ]);

        return substr(hash_hmac('sha256', $payload, $this->appSecret), 0, 32);
    }

    private function createQrCodeBase64(string $url): string
    {
        $writer = new SvgWriter();
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 220,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(17, 24, 39),
            backgroundColor: new Color(255, 255, 255)
        );

        return base64_encode($writer->write($qrCode)->getString());
    }
}
