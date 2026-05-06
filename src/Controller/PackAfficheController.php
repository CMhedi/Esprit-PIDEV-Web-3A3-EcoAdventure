<?php

namespace App\Controller;

use App\Entity\UserApp;
use App\Repository\PackRepository;
use App\Service\Pack\PackInsightAssembler;
use App\Service\Pack\PackRecommendationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackAfficheController extends AbstractController
{
    #[Route('/packs', name: 'app_packs', methods: ['GET'])]
    public function index(
        Request $request,
        PackRepository $packRepository,
        PackInsightAssembler $packInsightAssembler,
        PackRecommendationEngine $packRecommendationEngine,
    ): Response {
        $search = $request->query->get('q', '');
        $sort = $request->query->get('sort', '');
        $type = $request->query->get('type', '');
        $statut = $request->query->get('statut', '');

        $packs = $packRepository->findForFront($search, $type, $statut, $sort);
        $packInsights = $packInsightAssembler->buildInsights($packs);
        $currentUser = $this->getUser() instanceof UserApp ? $this->getUser() : null;
        $topRecommendations = $packRecommendationEngine->recommendForUser($packs, $packInsights, $currentUser, 3);

        if ($sort === 'smart_desc') {
            usort($packs, static function ($left, $right) use ($packInsights): int {
                $leftScore = $packInsights[$left->getIdPack()]->getScore();
                $rightScore = $packInsights[$right->getIdPack()]->getScore();

                return $rightScore <=> $leftScore;
            });
        }

        return $this->render('front/hedisPackInscription/packs.html.twig', [
            'packs' => $packs,
            'packInsights' => $packInsights,
            'topRecommendations' => $topRecommendations,
            'currentSearch' => $search,
            'currentSort' => $sort,
            'currentType' => $type,
            'currentStatut' => $statut,
            'typesDisponibles' => $packRepository->findDistinctTypes(),
            'statutsDisponibles' => $packRepository->findDistinctStatuts(),
        ]);
    }

    #[Route('/portfolio-packs', name: 'app_portfolio_packs', methods: ['GET'])]
    public function portfolio(): Response
    {
        return $this->render('front/hedisPackInscription/portfolio.html.twig');
    }
}
