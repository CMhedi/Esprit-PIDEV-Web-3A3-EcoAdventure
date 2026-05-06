<?php

namespace App\Service\Risk;

use App\Dto\PackInsightView;
use App\Dto\PackRiskView;

final class PackRiskEngine
{
    public function __construct(
        private readonly RiskLevelClassifier $levelClassifier,
        private readonly RiskInterpreter $riskInterpreter,
        private readonly RiskActionResolver $actionResolver,
    ) {
    }

    /**
     * @param array<int, PackInsightView> $packInsights
     * @return array<int, PackRiskView>
     */
    public function evaluate(array $packInsights): array
    {
        if ($packInsights === []) {
            return [];
        }

        $benchmarks = $this->buildBenchmarks($packInsights);
        $riskViews = [];

        foreach ($packInsights as $packId => $insight) {
            $metrics = $insight->getMetrics();
            $typeKey = $this->normalizeType((string) ($metrics['type_label'] ?? 'standard'));
            $typeBenchmark = $benchmarks[$typeKey] ?? $benchmarks['__global__'];

            $components = [
                'status' => $this->computeStatusRisk((float) ($metrics['status_value'] ?? 0.35)),
                'demand' => $this->computeDemandRisk((float) ($metrics['inscriptions_total'] ?? 0), (float) ($typeBenchmark['median_inscriptions'] ?? 0.0)),
                'conversion' => $this->computeConversionRisk((int) ($metrics['opens_30d'] ?? 0), (int) ($metrics['confirmed_30d'] ?? 0), (int) ($metrics['views_30d'] ?? 0)),
                'price_value' => $this->computePriceValueRisk(
                    (float) ($metrics['final_price'] ?? 0.0),
                    (int) ($metrics['linked_activities'] ?? 0),
                    (float) ($typeBenchmark['median_price_per_activity'] ?? 0.0)
                ),
                'coverage' => $this->clamp(1.0 - (float) ($metrics['coverage_ratio'] ?? 0.0)),
                'discount' => $this->computeDiscountRisk((float) ($metrics['reduction_ratio'] ?? 0.0)),
                'trend' => $this->computeTrendRisk(
                    (float) (($metrics['inscriptions_30d'] ?? 0) + ($metrics['views_30d'] ?? 0) + ($metrics['opens_30d'] ?? 0) + ($metrics['confirmed_30d'] ?? 0)),
                    (float) ($typeBenchmark['median_recent_signal'] ?? 0.0)
                ),
                'pending' => $this->computePendingRisk((int) ($metrics['pending_count'] ?? 0), (int) ($metrics['inscriptions_total'] ?? 0)),
                'attractiveness' => $this->clamp(1.0 - ($insight->getScore() / 100)),
            ];

            $riskScore = round(100 * (
                (0.12 * $components['status']) +
                (0.18 * $components['demand']) +
                (0.14 * $components['conversion']) +
                (0.13 * $components['price_value']) +
                (0.10 * $components['coverage']) +
                (0.07 * $components['discount']) +
                (0.08 * $components['trend']) +
                (0.08 * $components['pending']) +
                (0.10 * $components['attractiveness'])
            ), 1);

            $riskLevel = $this->levelClassifier->classify($riskScore);
            $riskProfile = $this->resolveProfile($components, $insight->getScore(), $riskScore);
            $drivers = $this->riskInterpreter->topDrivers($components, [
                'status' => 'un statut peu fiable',
                'demand' => 'une demande en retrait face aux packs comparables',
                'conversion' => 'une conversion faible entre ouvertures et confirmations',
                'price_value' => 'un positionnement prix / valeur desequilibre',
                'coverage' => 'une couverture d activites insuffisante',
                'discount' => 'une reduction mal calibree',
                'trend' => 'une dynamique recente trop faible',
                'pending' => 'un stock d inscriptions en attente trop eleve',
                'attractiveness' => 'une attractivite globale en retrait',
            ]);
            $summary = $this->riskInterpreter->buildPackSummary($riskLevel, $riskProfile, $drivers, $insight);
            $actions = $this->actionResolver->resolvePackActions($riskLevel, $riskProfile, $components, $metrics);

            $riskViews[(int) $packId] = new PackRiskView(
                $insight->getPack(),
                $riskScore,
                $riskLevel,
                $riskProfile,
                $summary,
                $drivers,
                $actions,
                $components,
                $insight->getScore(),
            );
        }

        uasort($riskViews, static fn (PackRiskView $left, PackRiskView $right): int => $right->getRiskScore() <=> $left->getRiskScore());

        return $riskViews;
    }

    /**
     * @param array<int, PackInsightView> $packInsights
     * @return array<string, array<string, float>>
     */
    private function buildBenchmarks(array $packInsights): array
    {
        $series = [];

        foreach ($packInsights as $insight) {
            $metrics = $insight->getMetrics();
            $typeKey = $this->normalizeType((string) ($metrics['type_label'] ?? 'standard'));
            $linkedActivities = max((int) ($metrics['linked_activities'] ?? 0), 1);
            $pricePerActivity = (float) ($metrics['final_price'] ?? 0.0) / $linkedActivities;
            $recentSignal = (float) (($metrics['inscriptions_30d'] ?? 0) + ($metrics['views_30d'] ?? 0) + ($metrics['opens_30d'] ?? 0) + ($metrics['confirmed_30d'] ?? 0));

            foreach ([$typeKey, '__global__'] as $bucket) {
                $series[$bucket]['inscriptions'][] = (float) ($metrics['inscriptions_total'] ?? 0.0);
                $series[$bucket]['price_per_activity'][] = $pricePerActivity;
                $series[$bucket]['recent_signal'][] = $recentSignal;
            }
        }

        $benchmarks = [];
        foreach ($series as $bucket => $values) {
            $benchmarks[$bucket] = [
                'median_inscriptions' => $this->median($values['inscriptions']),
                'median_price_per_activity' => $this->median($values['price_per_activity']),
                'median_recent_signal' => $this->median($values['recent_signal']),
            ];
        }

        return $benchmarks;
    }

    private function computeStatusRisk(float $statusValue): float
    {
        if ($statusValue >= 0.95) {
            return 0.08;
        }

        if ($statusValue >= 0.55) {
            return 0.45;
        }

        return 0.9;
    }

    private function computeDemandRisk(float $inscriptionsTotal, float $medianInscriptions): float
    {
        if ($medianInscriptions <= 0.0) {
            return $inscriptionsTotal > 0 ? 0.25 : 0.55;
        }

        if ($inscriptionsTotal <= 0.0) {
            return 0.9;
        }

        $ratio = min($inscriptionsTotal / $medianInscriptions, 1.0);

        return $this->clamp(max(0.12, 1.0 - $ratio));
    }

    private function computeConversionRisk(int $opens, int $confirmed, int $views): float
    {
        if ($opens > 0) {
            return $this->clamp(1.0 - min($confirmed / max($opens, 1), 1.0));
        }

        if ($views > 0) {
            return 0.62;
        }

        return 0.45;
    }

    private function computePriceValueRisk(float $finalPrice, int $linkedActivities, float $medianPricePerActivity): float
    {
        if ($linkedActivities <= 0) {
            return 0.92;
        }

        if ($medianPricePerActivity <= 0.0) {
            return 0.45;
        }

        $pricePerActivity = $finalPrice / max($linkedActivities, 1);
        $distance = abs($pricePerActivity - $medianPricePerActivity) / $medianPricePerActivity;

        return $this->clamp(max(0.1, min($distance * 1.15, 1.0)));
    }

    private function computeDiscountRisk(float $reductionRatio): float
    {
        return match (true) {
            $reductionRatio >= 0.08 && $reductionRatio <= 0.25 => 0.12,
            $reductionRatio >= 0.04 && $reductionRatio <= 0.35 => 0.4,
            default => 0.72,
        };
    }

    private function computeTrendRisk(float $recentSignal, float $medianRecentSignal): float
    {
        if ($medianRecentSignal <= 0.0) {
            return $recentSignal > 0.0 ? 0.25 : 0.55;
        }

        if ($recentSignal <= 0.0) {
            return 0.88;
        }

        $ratio = min($recentSignal / $medianRecentSignal, 1.0);

        return $this->clamp(max(0.12, 1.0 - $ratio));
    }

    private function computePendingRisk(int $pendingCount, int $inscriptionsTotal): float
    {
        if ($inscriptionsTotal <= 0) {
            return 0.25;
        }

        $ratio = $pendingCount / max($inscriptionsTotal, 1);

        return match (true) {
            $ratio <= 0.2 => 0.12,
            $ratio <= 0.45 => 0.42,
            default => 0.78,
        };
    }

    /**
     * @param array<string, float> $components
     */
    private function resolveProfile(array $components, float $attractivenessScore, float $riskScore): string
    {
        if ($riskScore <= 24.0 && $attractivenessScore >= 70.0) {
            return 'Sain et performant';
        }

        if (($components['price_value'] ?? 0.0) >= 0.65 || ($components['discount'] ?? 0.0) >= 0.68) {
            return 'A repositionner';
        }

        if (($components['demand'] ?? 0.0) >= 0.65 && ($components['conversion'] ?? 0.0) >= 0.5) {
            return 'Sous-exploite';
        }

        if (($components['status'] ?? 0.0) >= 0.7 || ($components['coverage'] ?? 0.0) >= 0.68) {
            return 'Fragile';
        }

        return 'A surveiller';
    }

    private function normalizeType(string $type): string
    {
        $normalized = trim(mb_strtolower($type));

        return $normalized !== '' ? $normalized : 'standard';
    }

    /**
     * @param float[] $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    private function clamp(float $value, float $min = 0.0, float $max = 1.0): float
    {
        return max($min, min($max, $value));
    }
}
