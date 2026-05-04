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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PackInscriptionTicketFactory
{
    public function __construct(
        private readonly InscriptionRepository $inscriptionRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Code39BarcodeGenerator $barcodeGenerator,
        private readonly RequestStack $requestStack,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
        #[Autowire('%public_app_url%')]
        private readonly string $publicAppUrl,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
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
        $path = $this->urlGenerator->generate(
            'app_pack_inscription_ticket',
            [
                'id' => $inscription->getIdInscription(),
                'token' => $this->generatePublicToken($inscription),
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH
        );

        if (!$absolute) {
            return $path;
        }

        $baseUrl = $this->resolvePublicBaseUrl();
        if ($baseUrl !== null) {
            return rtrim($baseUrl, '/') . $path;
        }

        return $this->urlGenerator->generate(
            'app_pack_inscription_ticket',
            [
                'id' => $inscription->getIdInscription(),
                'token' => $this->generatePublicToken($inscription),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
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

    private function resolvePublicBaseUrl(): ?string
    {
        $configuredBaseUrl = trim($this->publicAppUrl);
        if ($configuredBaseUrl !== '') {
            return $configuredBaseUrl;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        $host = strtolower($request->getHost());
        if (!$this->isLoopbackHost($host)) {
            return $request->getSchemeAndHttpHost();
        }

        $lanIp = $this->detectLanIp();
        if ($lanIp === null) {
            return $request->getSchemeAndHttpHost();
        }

        $port = $request->getPort();
        $portSuffix = $this->isDefaultPort($request->getScheme(), (int)$port) ? '' : ':' . $port;

        return sprintf('%s://%s%s', $request->getScheme(), $lanIp, $portSuffix);
    }

    private function detectLanIp(): ?string
    {
        $hostname = gethostname();
        if ($hostname === false) {
            return null;
        }
        $hostnames = gethostbynamel($hostname);
        $hostIps = is_array($hostnames) ? $hostnames : [];
        $privateIps = [];

        foreach ($hostIps as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $this->isPrivateIpv4($ip)) {
                $privateIps[] = $ip;
            }
        }

        foreach ($privateIps as $ip) {
            if (!preg_match('/\.(0|1|255)$/', $ip)) {
                return $ip;
            }
        }

        return $privateIps !== [] ? end($privateIps) ?: null : null;
    }

    private function isPrivateIpv4(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.')) {
            return true;
        }

        if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip) === 1) {
            return true;
        }

        return false;
    }

    private function isLoopbackHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }
}
