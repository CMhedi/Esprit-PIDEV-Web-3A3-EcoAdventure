<?php

namespace App\Controller;

use App\Repository\PackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackAfficheController extends AbstractController
{
    #[Route('/packs', name: 'app_packs', methods: ['GET'])]
    public function index(Request $request, PackRepository $packRepository): Response
    {
        $search = $request->query->get('q', '');
        $sort = $request->query->get('sort', '');
        $type = $request->query->get('type', '');
        $statut = $request->query->get('statut', '');

        $packs = $packRepository->findForFront($search, $type, $statut, $sort);

        return $this->render('front/hedisPackInscription/packs.html.twig', [
            'packs' => $packs,
            'currentSearch' => $search,
            'currentSort' => $sort,
            'currentType' => $type,
            'currentStatut' => $statut,
            'typesDisponibles' => $packRepository->findDistinctTypes(),
            'statutsDisponibles' => $packRepository->findDistinctStatuts(),
        ]);
    }
}
