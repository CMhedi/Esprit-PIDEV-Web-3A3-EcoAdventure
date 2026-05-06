<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\ReservationEvenement;
use App\Entity\UserApp;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Twig\Environment;

class EventDocumentService
{
    private Environment $twig;
    private ReservationPricingService $pricingService;

    public function __construct(Environment $twig, ReservationPricingService $pricingService)
    {
        $this->twig = $twig;
        $this->pricingService = $pricingService;
    }

    /**
     * Génère un QR Code en base64 pour une référence donnée
     */
    public function generateQrCode(string $content): string
    {
        $writer = new SvgWriter();
        $qrCode = new QrCode(
            data: $content,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 200,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $result = $writer->write($qrCode);
        return base64_encode($result->getString());
    }

    /**
     * Génère le PDF d'un ticket
     */
    public function generateTicketPdf(Evenement $evenement, UserApp $user, int $totalBillets): string
    {
        $qrCodeBase64 = $this->generateQrCode(sprintf('EVENT:%d|USER:%d|TICKETS:%d', $evenement->getId_evenement(), $user->getId_user(), $totalBillets));
        $pricing = $this->pricingService->calculatePricing($evenement, $totalBillets);

        $html = $this->twig->render('front/event/ticket_pdf.html.twig', [
            'evenement' => $evenement,
            'user' => $user,
            'totalBillets' => $totalBillets,
            'qrCode' => $qrCodeBase64,
            'reference' => 'EVT-' . $evenement->getId_evenement() . '-' . $user->getId_user(),
            'pricing' => $pricing
        ]);

        return $this->renderPdf($html);
    }

    /**
     * Génère le PDF d'une facture
     */
    public function generateInvoicePdf(Evenement $evenement, UserApp $user, int $totalBillets): string
    {
        $pricing = $this->pricingService->calculatePricing($evenement, $totalBillets);

        $html = $this->twig->render('front/event/invoice_pdf.html.twig', [
            'evenement' => $evenement,
            'user' => $user,
            'totalBillets' => $totalBillets,
            'pricing' => $pricing,
            'reference' => 'FAC-' . strtoupper(substr($evenement->getTitre(), 0, 3)) . '-' . $evenement->getId_evenement() . '-' . $user->getId_user()
        ]);

        return $this->renderPdf($html);
    }

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
