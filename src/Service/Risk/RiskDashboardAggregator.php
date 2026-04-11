<?php

namespace App\Service\Risk;

use App\Dto\InscriptionRiskView;
use App\Dto\PackRiskView;

final class RiskDashboardAggregator
{
    /**
     * @param array<int, PackRiskView> $packRiskViews
     * @param array<int, InscriptionRiskView> $inscriptionRiskViews
     * @return array<string, mixed>
     */
    public function build(array $packRiskViews, array $inscriptionRiskViews): array
    {
        $packList = array_values($packRiskViews);
        $inscriptionList = array_values($inscriptionRiskViews);

        $packAverage = $this->average(array_map(static fn (PackRiskView $view): float => $view->getRiskScore(), $packList));
        $inscriptionAverage = $this->average(array_map(static fn (InscriptionRiskView $view): float => $view->getRiskScore(), $inscriptionList));

        return [
            'pack_average' => round($packAverage, 1),
            'inscription_average' => round($inscriptionAverage, 1),
            'global_average' => round(($packAverage + $inscriptionAverage) / (($packList !== [] && $inscriptionList !== []) ? 2 : 1), 1),
            'critical_packs' => $this->countByLevel($packList, 'Critical Risk'),
            'high_packs' => $this->countAtLeast($packList, 50.0),
            'healthy_packs' => count(array_filter($packList, static fn (PackRiskView $view): bool => $view->getRiskScore() < 25.0)),
            'critical_inscriptions' => $this->countByLevel($inscriptionList, 'Critical Risk'),
            'priority_inscriptions' => $this->countAtLeast($inscriptionList, 50.0),
            'pack_distribution' => $this->distribution($packList),
            'inscription_distribution' => $this->distribution($inscriptionList),
            'top_risky_packs' => array_slice($packList, 0, 5),
            'top_risky_inscriptions' => array_slice($inscriptionList, 0, 5),
            'recommended_actions' => $this->collectActions($packList, $inscriptionList),
        ];
    }

    /**
     * @param array<int, object> $items
     */
    private function distribution(array $items): array
    {
        $distribution = [
            'Low Risk' => 0,
            'Medium Risk' => 0,
            'High Risk' => 0,
            'Critical Risk' => 0,
        ];

        foreach ($items as $item) {
            $level = $item->getRiskLevel();
            $distribution[$level] = ($distribution[$level] ?? 0) + 1;
        }

        return $distribution;
    }

    /**
     * @param array<int, PackRiskView|InscriptionRiskView> $items
     */
    private function countByLevel(array $items, string $level): int
    {
        return count(array_filter($items, static fn ($item): bool => $item->getRiskLevel() === $level));
    }

    /**
     * @param array<int, PackRiskView|InscriptionRiskView> $items
     */
    private function countAtLeast(array $items, float $threshold): int
    {
        return count(array_filter($items, static fn ($item): bool => $item->getRiskScore() >= $threshold));
    }

    /**
     * @param array<float> $values
     */
    private function average(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param PackRiskView[] $packList
     * @param InscriptionRiskView[] $inscriptionList
     * @return string[]
     */
    private function collectActions(array $packList, array $inscriptionList): array
    {
        $actions = [];

        foreach (array_slice($packList, 0, 3) as $riskView) {
            $actions = array_merge($actions, array_slice($riskView->getRecommendedActions(), 0, 2));
        }

        foreach (array_slice($inscriptionList, 0, 3) as $riskView) {
            $actions = array_merge($actions, array_slice($riskView->getRecommendedActions(), 0, 2));
        }

        return array_slice(array_values(array_unique($actions)), 0, 6);
    }
}
