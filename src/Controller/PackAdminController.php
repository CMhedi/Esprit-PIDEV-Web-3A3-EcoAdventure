<?php

namespace App\Controller;

use App\Entity\Pack;
use App\Form\PackType;
use App\Repository\PackRepository;
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
        SessionInterface $session
    ): Response {
        $search = $request->query->get('search');
        $sort = $request->query->get('sort');

        $packs = $packRepository->findForAdmin($search, $sort);
        $totalPacks = $packRepository->countAllPacks();

        $deleteCaptcha = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $deleteAllCaptcha = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $session->set('delete_pack_captcha', $deleteCaptcha);
        $session->set('delete_all_packs_captcha', $deleteAllCaptcha);

        return $this->render('admin/packs/index.html.twig', [
            'packs' => $packs,
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
            $entityManager->remove($pack);
        }

        $entityManager->flush();

        $session->remove('delete_all_packs_captcha');

        $this->addFlash('success', 'Tous les packs ont été supprimés avec succès.');

        return $this->redirectToRoute('app_admin_packs');
    }

    #[Route('/admin/packs/export/pdf', name: 'app_admin_packs_export_pdf', methods: ['GET'])]
    public function exportPdf(PackRepository $packRepository): Response
    {
        $packs = $packRepository->findAllForPdf();

        $html = $this->renderView('admin/packs/pdf.html.twig', [
            'packs' => $packs,
            'dateExport' => new \DateTime()
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $response = new Response($dompdf->output());
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'packs_ecoadventure.pdf'
        );

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}