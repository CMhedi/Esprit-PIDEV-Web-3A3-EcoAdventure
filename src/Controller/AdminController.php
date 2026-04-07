<?php

namespace App\Controller;

use App\Entity\Pack;
use App\Repository\PackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        $stats = [
            'users' => 576,
            'events' => 48,
            'packs' => 16,
            'inscriptions' => 1235
        ];

        $recentEvents = [
            [
                'title' => 'Randonnée en Montagne',
                'location' => 'Ain Draham',
                'date' => '12/04/2026',
                'participants' => 75,
                'status' => 'Active'
            ],
            [
                'title' => 'Kayak Adventure',
                'location' => 'Bizerte',
                'date' => '20/04/2026',
                'participants' => 53,
                'status' => 'À venir'
            ],
            [
                'title' => 'Escalade en Pleine Nature',
                'location' => 'Zaghouan',
                'date' => '28/04/2026',
                'participants' => 34,
                'status' => 'Clôturée'
            ]
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recentEvents' => $recentEvents
        ]);
    }

    #[Route('/admin/packs', name: 'app_admin_packs', methods: ['GET'])]
    public function packs(Request $request, PackRepository $packRepository): Response
    {
        $search = $request->query->get('search');
        $sort = $request->query->get('sort');

        $packs = $packRepository->findForAdmin($search, $sort);
        $totalPacks = $packRepository->countAllPacks();

        return $this->render('admin/packs/index.html.twig', [
            'packs' => $packs,
            'search' => $search,
            'sort' => $sort,
            'totalPacks' => $totalPacks
        ]);
    }

    #[Route('/admin/packs/new', name: 'app_admin_pack_new')]
    public function newPack(): Response
    {
        return new Response('Page Ajouter Pack');
    }

    #[Route('/admin/packs/{id}', name: 'app_admin_pack_show', requirements: ['id' => '\d+'])]
    public function showPack(int $id): Response
    {
        return new Response('Détails du pack ID : ' . $id);
    }

    #[Route('/admin/packs/{id}/edit', name: 'app_admin_pack_edit', requirements: ['id' => '\d+'])]
    public function editPack(int $id): Response
    {
        return new Response('Modifier le pack ID : ' . $id);
    }

    #[Route('/admin/packs/{id}/delete', name: 'app_admin_pack_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePack(
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

        if (!$this->isCsrfTokenValid('delete_pack_' . $pack->getIdPack(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_packs');
        }

        $nomPack = $pack->getNom();

        $entityManager->remove($pack);
        $entityManager->flush();

        $this->addFlash('success', 'Le pack "' . $nomPack . '" a été supprimé avec succès.');

        return $this->redirectToRoute('app_admin_packs');
    }

    #[Route('/admin/packs/delete-all', name: 'app_admin_pack_delete_all', methods: ['POST'])]
    public function deleteAllPacks(
        Request $request,
        PackRepository $packRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('delete_all_packs', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_packs');
        }

        $packs = $packRepository->findAll();

        foreach ($packs as $pack) {
            $entityManager->remove($pack);
        }

        $entityManager->flush();

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