<?php

namespace App\Service\Inscription;

use App\Dto\InscriptionPriorityView;
use App\Dto\PackInsightView;
use App\Entity\Inscription;

final class InscriptionAnalyticsBuilder
{
    public function __construct(
        private readonly InscriptionPriorityScorer $priorityScorer,
    ) {
    }

    /**
     * @param Inscription[] $inscriptions
     * @param array<int, PackInsightView> $packInsights
     * @return array{priority_views: InscriptionPriorityView[], summary: array<string, mixed>, segments: array<string, int>}
     */
    public function build(array $inscriptions, array $packInsights): array
    {
        $maxAmount = 1.0;
        foreach ($inscriptions as $inscription) {
            $maxAmount = max($maxAmount, (float) ($inscription->getMontantTotal() ?? 0));
        }

        $priorityViews = [];
        $statusBreakdown = [];
        $packCounts = [];
        $segments = [
            'Prioritaire' => 0,
            'À confirmer' => 0,
            'Stable' => 0,
            'Faible' => 0,
        ];
        $revenueTotal = 0.0;
        $pendingRevenue = 0.0;
        $recent7Days = 0;
        $previous7Days = 0;

        $now = new \DateTimeImmutable();
        $sevenDaysAgo = $now->sub(new \DateInterval('P7D'));
        $fourteenDaysAgo = $now->sub(new \DateInterval('P14D'));

        foreach ($inscriptions as $inscription) {
            $priorityView = $this->priorityScorer->score($inscription, $packInsights, $maxAmount);
            $priorityViews[] = $priorityView;
            $segments[$priorityView->getLane()]++;

            $status = $inscription->getStatutInscr()?->value ?? 'INCONNU';
            $statusBreakdown[$status] = ($statusBreakdown[$status] ?? 0) + 1;

            $packName = $inscription->getNomPack() ?: ($inscription->getPack()?->getNom() ?? 'Pack');
            $packCounts[$packName] = ($packCounts[$packName] ?? 0) + 1;

            $amount = (float) ($inscription->getMontantTotal() ?? 0);
            $revenueTotal += $amount;

            if ($status === 'EN_ATTENTE') {
                $pendingRevenue += $amount;
            }

            $date = $inscription->getDateInscription()
                ? \DateTimeImmutable::createFromInterface($inscription->getDateInscription())
                : null;

            if ($date && $date >= $sevenDaysAgo) {
                ++$recent7Days;
            } elseif ($date && $date >= $fourteenDaysAgo) {
                ++$previous7Days;
            }
        }

        usort($priorityViews, static fn (InscriptionPriorityView $left, InscriptionPriorityView $right): int => $right->getPriorityScore() <=> $left->getPriorityScore());
        arsort($packCounts);
        arsort($statusBreakdown);

        return [
            'priority_views' => $priorityViews,
            'summary' => [
                'total' => count($inscriptions),
                'pending_count' => $statusBreakdown['EN_ATTENTE'] ?? 0,
                'confirmed_count' => ($statusBreakdown['CONFIRMEE'] ?? 0) + ($statusBreakdown['VALIDEE'] ?? 0),
                'cancelled_count' => $statusBreakdown['ANNULEE'] ?? 0,
                'revenue_total' => round($revenueTotal, 2),
                'pending_revenue' => round($pendingRevenue, 2),
                'high_priority_count' => count(array_filter($priorityViews, static fn (InscriptionPriorityView $view): bool => $view->isHighPriority())),
                'top_pack' => array_key_first($packCounts) ?? 'Aucun',
                'trend_label' => $recent7Days >= $previous7Days ? 'Hausse récente' : 'Rythme à relancer',
                'status_breakdown' => $statusBreakdown,
            ],
            'segments' => $segments,
        ];
    }
}
