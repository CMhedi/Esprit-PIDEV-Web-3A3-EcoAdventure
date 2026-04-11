<?php

namespace App\Service\Inscription;

use App\Entity\Inscription;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Twig\Environment;

final class PackInscriptionReceiptBuilder
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function buildPdf(Inscription $inscription): string
    {
        $writer = new SvgWriter();
        $qrCode = new QrCode(
            data: sprintf(
                'PACK_INSCRIPTION:%d|PACK:%s|USER:%s|AMOUNT:%s',
                $inscription->getIdInscription(),
                $inscription->getNomPack() ?: ($inscription->getPack()?->getNom() ?? 'PACK'),
                $inscription->getNomUser() ?: ($inscription->getUserApp()?->getEmail() ?? 'USER'),
                $inscription->getMontantTotal() ?? '0'
            ),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 190,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $result = $writer->write($qrCode);
        $html = $this->twig->render('pdf/pack_inscription_receipt.html.twig', [
            'inscription' => $inscription,
            'qrCode' => base64_encode($result->getString()),
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
