<?php

namespace App\Service\Inscription;

use App\Entity\Inscription;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class PackInscriptionReceiptBuilder
{
    public function __construct(
        private readonly Environment $twig,
        private readonly PackInscriptionTicketFactory $ticketFactory,
    ) {
    }

    public function buildPdf(Inscription $inscription): string
    {
        $html = $this->twig->render('pdf/pack_inscription_receipt.html.twig', array_merge(
            $this->ticketFactory->buildViewData($inscription),
            ['generatedAt' => new \DateTimeImmutable()]
        ));

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', dirname(__DIR__, 3));

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
