<?php

namespace App\Controller;

use App\Repository\InscriptionRepository;
use App\Repository\PackRepository;
use App\Service\AI\AiAdminSynthesizer;
use App\Service\AI\AiRiskExplainer;
use App\Service\Context\HolidayContextProvider;
use App\Service\Inscription\InscriptionAnalyticsBuilder;
use App\Service\Pack\PackInsightAssembler;
use App\Service\Risk\InscriptionRiskEngine;
use App\Service\Risk\PackRiskEngine;
use App\Service\Risk\RiskDashboardAggregator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InscriptionAdminController extends AbstractController
{
    #[Route('/admin/inscriptions', name: 'app_admin_inscriptions', methods: ['GET'])]
    public function inscriptions(
        Request $request,
        InscriptionRepository $inscriptionRepository,
        PackRepository $packRepository,
        PackInsightAssembler $packInsightAssembler,
        InscriptionAnalyticsBuilder $inscriptionAnalyticsBuilder,
        AiAdminSynthesizer $aiAdminSynthesizer,
        HolidayContextProvider $holidayContextProvider,
        PackRiskEngine $packRiskEngine,
        InscriptionRiskEngine $inscriptionRiskEngine,
        RiskDashboardAggregator $riskDashboardAggregator,
        AiRiskExplainer $aiRiskExplainer
    ): Response
    {
        $search = $request->query->get('search');

        $inscriptions = $inscriptionRepository->findForAdmin($search);
        $totalInscriptions = $inscriptionRepository->countAllInscriptions();
        $packInsights = $packInsightAssembler->buildInsights($packRepository->findAll());
        $packRiskViews = $packRiskEngine->evaluate($packInsights);
        $analytics = $inscriptionAnalyticsBuilder->build($inscriptions, $packInsights);
        $inscriptionRiskViews = $inscriptionRiskEngine->evaluate($inscriptions, $packRiskViews);
        $riskOverview = $riskDashboardAggregator->build($packRiskViews, $inscriptionRiskViews);
        $priorityMap = [];
        $inscriptionRiskMap = [];

        foreach ($analytics['priority_views'] as $priorityView) {
            $priorityMap[$priorityView->getInscription()->getIdInscription()] = $priorityView;
        }

        foreach ($inscriptionRiskViews as $riskView) {
            $inscriptionRiskMap[$riskView->getInscription()->getIdInscription()] = $riskView;
        }

        return $this->render('admin/inscriptions/InscriptionPacks.html.twig', [
            'inscriptions' => $inscriptions,
            'priorityMap' => $priorityMap,
            'inscriptionRiskMap' => $inscriptionRiskMap,
            'analyticsSummary' => $analytics['summary'],
            'analyticsSegments' => $analytics['segments'],
            'summaryNarrative' => $aiAdminSynthesizer->summarizeInscriptions($analytics['summary']),
            'riskOverview' => $riskOverview,
            'riskSummaryNarrative' => $aiRiskExplainer->summarizeDashboard($riskOverview),
            'holidayContext' => $holidayContextProvider->getContext(),
            'search' => $search,
            'totalInscriptions' => $totalInscriptions
        ]);
    }

    #[Route('/admin/inscriptions/{id}/delete', name: 'app_admin_inscription_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteInscription(
        int $id,
        Request $request,
        InscriptionRepository $inscriptionRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $inscription = $inscriptionRepository->find($id);

        if (!$inscription) {
            $this->addFlash('danger', 'Inscription introuvable.');
            return $this->redirectToRoute('app_admin_inscriptions');
        }

        if (!$this->isCsrfTokenValid('delete_inscription_' . $inscription->getIdInscription(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_inscriptions');
        }

        $nomUser = $inscription->getUserApp()
            ? trim(sprintf('%s %s', $inscription->getUserApp()->getPrenom(), $inscription->getUserApp()->getNom()))
            : ($inscription->getNomUser() ?: 'Utilisateur');
        $nomPack = $inscription->getNomPack() ?: ($inscription->getPack() ? $inscription->getPack()->getNom() : 'Pack');

        $entityManager->remove($inscription);
        $entityManager->flush();

        $this->addFlash('success', 'L’inscription de "' . $nomUser . '" pour "' . $nomPack . '" a été supprimée avec succès.');

        return $this->redirectToRoute('app_admin_inscriptions');
    }
}
