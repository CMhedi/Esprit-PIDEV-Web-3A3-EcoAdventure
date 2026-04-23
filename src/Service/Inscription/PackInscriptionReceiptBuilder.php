<?php

namespace App\Service\Inscription;

use App\Entity\Inscription;
use Nucleos\DompdfBundle\Factory\DompdfFactoryInterface;
use Twig\Environment;

final class PackInscriptionReceiptBuilder
{
    public function __construct(
        private readonly Environment $twig,
        private readonly PackInscriptionTicketFactory $ticketFactory,
        private readonly DompdfFactoryInterface $dompdfFactory,
    ) {
    }

    public function buildPdf(Inscription $inscription): string
    {
        $html = $this->twig->render('pdf/pack_inscription_receipt.html.twig', array_merge(
            $this->ticketFactory->buildViewData($inscription),
            ['generatedAt' => new \DateTimeImmutable()]
        ));

        $dompdf = $this->dompdfFactory->create([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => true,
            'chroot' => dirname(__DIR__, 3),
        ]);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
