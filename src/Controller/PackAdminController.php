<?php

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Pack;
use App\Form\PackType;
use App\Repository\PackRepository;
use App\Service\Pack\PackInsightAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class PackAdminController extends AbstractController
{
    #[Route('/admin/packs', name: 'app_admin_packs', methods: ['GET'])]
    public function packs(
        Request $request,
        PackRepository $packRepository,
        SessionInterface $session,
        PackInsightAssembler $packInsightAssembler
    ): Response {
        $search = $request->query->get('search');
        $sort = $request->query->get('sort');

        $packs = $packRepository->findForAdmin($search, $sort);
        $totalPacks = $packRepository->countAllPacks();
        $packInsights = $packInsightAssembler->buildInsights($packs);
        $topPromising = array_slice(array_values($packInsights), 0, 3);
        $averagePackScore = $packInsights === []
            ? 0.0
            : array_sum(array_map(static fn ($insight): float => $insight->getScore(), array_values($packInsights))) / count($packInsights);

        $deleteCaptcha = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $deleteAllCaptcha = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $session->set('delete_pack_captcha', $deleteCaptcha);
        $session->set('delete_all_packs_captcha', $deleteAllCaptcha);

        return $this->render('admin/packs/index.html.twig', [
            'packs' => $packs,
            'packInsights' => $packInsights,
            'topPromising' => $topPromising,
            'averagePackScore' => round($averagePackScore, 1),
            'search' => $search,
            'sort' => $sort,
            'totalPacks' => $totalPacks,
            'deleteCaptcha' => $deleteCaptcha,
            'deleteAllCaptcha' => $deleteAllCaptcha
        ]);
    }

    #[Route('/admin/packs/new', name: 'app_admin_pack_new')]
    public function newPack(Request $request, EntityManagerInterface $entityManager): Response
    {
        $pack = new Pack();
        $form = $this->createForm(PackType::class, $pack);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ((float) $pack->getReduction() > (float) $pack->getPrixBase()) {
                $this->addFlash('danger', 'La reduction ne peut pas etre superieure au prix de base.');

                return $this->render('admin/packs/AjoutPack_Admin.html.twig', [
                    'form' => $form->createView()
                ]);
            }

            $entityManager->persist($pack);
            $entityManager->flush();

            $this->addFlash('success', 'Le pack a été ajouté avec succès.');

            return $this->redirectToRoute('app_admin_packs');
        }

        return $this->render('admin/packs/AjoutPack_Admin.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/admin/packs/{id}', name: 'app_admin_pack_show', requirements: ['id' => '\d+'])]
    public function showPack(int $id): Response
    {
        return new Response('Détails du pack ID : ' . $id);
    }

   #[Route('/admin/packs/{id}/edit', name: 'app_admin_pack_edit', requirements: ['id' => '\d+'])]
public function editPack(
    int $id,
    Request $request,
    PackRepository $packRepository,
    EntityManagerInterface $entityManager
): Response {
    $pack = $packRepository->find($id);

    if (!$pack) {
        $this->addFlash('danger', 'Pack introuvable.');
        return $this->redirectToRoute('app_admin_packs');
    }

    $ancienNom = $pack->getNom();
    $ancienType = $pack->getTypePack();
    $ancienPrix = $pack->getPrixBase();
    $ancienneReduction = $pack->getReduction();
    $ancienNbActivites = $pack->getNbActivitesMax();
    $ancienStatut = $pack->getStatutPack();

    $form = $this->createForm(PackType::class, $pack);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $aucunChangement =
            $ancienNom === $pack->getNom() &&
            $ancienType === $pack->getTypePack() &&
            $ancienPrix === $pack->getPrixBase() &&
            $ancienneReduction === $pack->getReduction() &&
            $ancienNbActivites === $pack->getNbActivitesMax() &&
            $ancienStatut === $pack->getStatutPack();

        if ($aucunChangement) {
            $this->addFlash('danger', 'Aucune modification détectée sur ce pack.');
            return $this->redirectToRoute('app_admin_pack_edit', ['id' => $pack->getIdPack()]);
        }

        if ((float) $pack->getReduction() > (float) $pack->getPrixBase()) {
            $this->addFlash('danger', 'La réduction ne peut pas être supérieure au prix de base.');
            return $this->redirectToRoute('app_admin_pack_edit', ['id' => $pack->getIdPack()]);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Le pack a été modifié avec succès.');

        return $this->redirectToRoute('app_admin_packs');
    }

    return $this->render('admin/packs/ModifierPack_Admin.html.twig', [
        'form' => $form->createView(),
        'pack' => $pack
    ]);
}

    #[Route('/admin/packs/{id}/delete', name: 'app_admin_pack_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePack(
        int $id,
        Request $request,
        PackRepository $packRepository,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        $pack = $packRepository->find($id);

        if (!$pack) {
            $this->addFlash('danger', 'Pack introuvable.');
            return $this->redirectToRoute('app_admin_packs');
        }

        if (!$this->isCsrfTokenValid('delete_pack_' . $pack->getIdPack(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_packs');
        }

        $captchaSaisi = strtoupper(trim((string) $request->request->get('delete_captcha')));
        $captchaSession = strtoupper((string) $session->get('delete_pack_captcha'));

        if (empty($captchaSaisi) || $captchaSaisi !== $captchaSession) {
            $this->addFlash('danger', 'Captcha de suppression incorrect.');
            return $this->redirectToRoute('app_admin_packs');
        }

        $this->detachPackFromInscriptions($pack, $entityManager);
        $nomPack = $pack->getNom();

        $entityManager->remove($pack);
        $entityManager->flush();

        $session->remove('delete_pack_captcha');

        $this->addFlash('success', 'Le pack "' . $nomPack . '" a été supprimé avec succès.');

        return $this->redirectToRoute('app_admin_packs');
    }

    #[Route('/admin/packs/delete-all', name: 'app_admin_pack_delete_all', methods: ['POST'])]
    public function deleteAllPacks(
        Request $request,
        PackRepository $packRepository,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        if (!$this->isCsrfTokenValid('delete_all_packs', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_packs');
        }

        $captchaSaisi = strtoupper(trim((string) $request->request->get('delete_all_captcha')));
        $captchaSession = strtoupper((string) $session->get('delete_all_packs_captcha'));

        if (empty($captchaSaisi) || $captchaSaisi !== $captchaSession) {
            $this->addFlash('danger', 'Captcha de suppression globale incorrect.');
            return $this->redirectToRoute('app_admin_packs');
        }

        $packs = $packRepository->findAll();

        foreach ($packs as $pack) {
            $this->detachPackFromInscriptions($pack, $entityManager);
            $entityManager->remove($pack);
        }

        $entityManager->flush();

        $session->remove('delete_all_packs_captcha');

        $this->addFlash('success', 'Tous les packs ont été supprimés avec succès.');

        return $this->redirectToRoute('app_admin_packs');
    }

    #[Route('/admin/packs/export/pdf', name: 'app_admin_packs_export_pdf', methods: ['GET'])]
    public function exportPdf(PackRepository $packRepository, PackInsightAssembler $packInsightAssembler): Response
    {
        $packs = $packRepository->findAllForPdf();
        $packInsights = $packInsightAssembler->buildInsights($packs);

        // Statistiques avancées
        $stats = [
            'total' => count($packs),
            'actifs' => 0,
            'inactifs' => 0,
            'totalPrixBase' => 0,
            'totalReductions' => 0,
            'moyennePrixFinal' => 0,
            'topPack' => null,
            'packTypes' => [],
            'prixMoyenParType' => []
        ];

        foreach ($packs as $pack) {
            $prixBase = (float) $pack->getPrixBase();
            $reduction = (float) $pack->getReduction();
            $prixFinal = $prixBase - $reduction;

            $stats['totalPrixBase'] += $prixBase;
            $stats['totalReductions'] += $reduction;

            $type = $pack->getTypePack();
            if (!isset($stats['packTypes'][$type])) {
                $stats['packTypes'][$type] = 0;
                $stats['prixMoyenParType'][$type] = ['total' => 0, 'count' => 0];
            }
            $stats['packTypes'][$type]++;
            $stats['prixMoyenParType'][$type]['total'] += $prixFinal;
            $stats['prixMoyenParType'][$type]['count']++;

            if (strtolower($pack->getStatutPack()) === 'actif' ||
                strtolower($pack->getStatutPack()) === 'active' ||
                strtolower($pack->getStatutPack()) === 'disponible') {
                $stats['actifs']++;
            } else {
                $stats['inactifs']++;
            }

            // Trouver le pack avec le meilleur score
            $insight = $packInsights[$pack->getIdPack()] ?? null;
            if ($insight && (!$stats['topPack'] || $insight->getScore() > ($packInsights[$stats['topPack']->getIdPack()] ?? null)?->getScore())) {
                $stats['topPack'] = $pack;
            }
        }

        $stats['moyennePrixFinal'] = $stats['total'] > 0 ? ($stats['totalPrixBase'] - $stats['totalReductions']) / $stats['total'] : 0;

        // Calculer les moyennes par type
        foreach ($stats['prixMoyenParType'] as $type => &$data) {
            $data['moyenne'] = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;
        }

        $html = $this->renderView('admin/packs/pdf.html.twig', [
            'packs' => $packs,
            'packInsights' => $packInsights,
            'dateExport' => new \DateTime(),
            'stats' => $stats
        ]);

        // Configuration Dompdf améliorée pour une qualité exceptionnelle
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('dpi', 150);
        $options->set('defaultPaperSize', 'a4');
        $options->set('defaultPaperOrientation', 'portrait');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('debugPng', false);
        $options->set('debugKeepTemp', false);
        $options->set('debugCss', false);
        $options->set('debugLayout', false);
        $options->set('debugLayoutLines', false);
        $options->set('debugLayoutBlocks', false);
        $options->set('debugLayoutInline', false);
        $options->set('debugLayoutPaddingBox', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');

        // Métadonnées PDF
        $dompdf->addInfo('Title', 'Rapport des Packs EcoAdventure');
        $dompdf->addInfo('Subject', 'Rapport professionnel des packs disponibles');
        $dompdf->addInfo('Author', 'EcoAdventure Platform');
        $dompdf->addInfo('Creator', 'Système d\'administration EcoAdventure');
        $dompdf->addInfo('Producer', 'Dompdf ' . \Dompdf\Version::VERSION);
        $dompdf->addInfo('CreationDate', date('Y-m-d\TH:i:sP'));
        $dompdf->addInfo('ModDate', date('Y-m-d\TH:i:sP'));

        $dompdf->render();

        $response = new Response($dompdf->output());
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'rapport-packs-ecoadventure-' . date('Y-m-d-H-i-s') . '.pdf'
        );

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'public');

        return $response;
    }

    private function detachPackFromInscriptions(Pack $pack, EntityManagerInterface $entityManager): void
    {
        $inscriptions = $entityManager->getRepository(Inscription::class)->findBy(['pack' => $pack]);

        foreach ($inscriptions as $inscription) {
            if (!$inscription->getNomPack()) {
                $inscription->setNomPack($pack->getNom());
            }

            $inscription->setPack(null);
        }
    }
}
