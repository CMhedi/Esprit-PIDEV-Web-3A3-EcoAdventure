<?php

namespace App\Service\Pack;

use App\Dto\PackInsightView;
use App\Entity\Inscription;
use App\Entity\Pack;
use App\Repository\FeedbackEventRepository;

final class PackInsightAssembler
{
    private const ACTIVE_STATUSES = ['actif', 'active', 'disponible', 'dispo'];

    public function __construct(
        private readonly FeedbackEventRepository $feedbackEventRepository,
    ) {
    }

    /**
     * @param Pack[] $packs
     * @return array<int, PackInsightView>
     */
    public function buildInsights(array $packs): array
    {
        if ($packs === []) {
            return [];
        }

        $now = new \DateTimeImmutable();
        $monthAgo = $now->sub(new \DateInterval('P30D'));
        $packIds = array_values(array_filter(array_map(static fn (Pack $pack): ?int => $pack->getIdPack(), $packs)));
        $recentFeedback = $this->feedbackEventRepository->getPackActionCounts($packIds, $monthAgo);

        $rawMetrics = [];
        $maxFinalPrice = 0.0;
        $maxInscriptions = 0;
        $maxRecentInscriptions = 0;
        $maxViews = 0;
        $maxOpens = 0;

        foreach ($packs as $pack) {
            $metrics = $this->extractMetrics($pack, $recentFeedback[$pack->getIdPack() ?? 0] ?? [], $monthAgo);
            $rawMetrics[$pack->getIdPack() ?? spl_object_id($pack)] = $metrics;

            $maxFinalPrice = max($maxFinalPrice, (float) $metrics['final_price']);
            $maxInscriptions = max($maxInscriptions, (int) $metrics['inscriptions_total']);
            $maxRecentInscriptions = max($maxRecentInscriptions, (int) $metrics['inscriptions_30d']);
            $maxViews = max($maxViews, (int) $metrics['views_30d']);
            $maxOpens = max($maxOpens, (int) $metrics['opens_30d']);
        }

        $scored = [];
        foreach ($packs as $pack) {
            $packId = $pack->getIdPack() ?? spl_object_id($pack);
            $metrics = $rawMetrics[$packId];

            $score = $this->computeScore(
                $metrics,
                max($maxFinalPrice, 1.0),
                max($maxInscriptions, 1),
                max($maxRecentInscriptions, 1),
                max($maxViews, 1),
                max($maxOpens, 1),
            );

            $scored[$packId] = [
                'pack' => $pack,
                'score' => $score,
                'badges' => $this->resolveBadges($metrics, $score),
                'reasons' => $this->buildReasons($metrics, $score),
                'metrics' => $metrics,
            ];
        }

        uasort($scored, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        $rank = 1;
        $insights = [];
        foreach ($scored as $packId => $payload) {
            $insights[(int) $packId] = new PackInsightView(
                $payload['pack'],
                $payload['score'],
                $rank++,
                $payload['badges'],
                $payload['reasons'],
                $payload['metrics'],
            );
        }

        return $insights;
    }

    /**
     * @param array<string, int> $feedbackCounts
     * @return array<string, float|int|string>
     */
    private function extractMetrics(Pack $pack, array $feedbackCounts, \DateTimeImmutable $monthAgo): array
    {
        $basePrice = (float) ($pack->getPrixBase() ?? 0);
        $finalPrice = (float) $pack->getPrixFinal();
        $reduction = (float) ($pack->getReduction() ?? 0);
        $inscriptionsTotal = 0;
        $inscriptions30d = 0;
        $pendingCount = 0;

        foreach ($pack->getInscriptions() as $inscription) {
            if (!$inscription instanceof Inscription) {
                continue;
            }

            ++$inscriptionsTotal;

            if ($inscription->getDateInscription() && $inscription->getDateInscription() >= $monthAgo) {
                ++$inscriptions30d;
            }

            if ($inscription->getStatutInscr()->value === 'EN_ATTENTE') {
                ++$pendingCount;
            }
        }

        $linkedActivities = $pack->getActivites()->count();
        $capacity = max((int) ($pack->getNbActivitesMax() ?? 0), 1);
        $coverageRatio = min($linkedActivities / $capacity, 1);
        $reductionRatio = $basePrice > 0 ? min($reduction / $basePrice, 1) : 0.0;
        $statusValue = in_array(mb_strtolower((string) $pack->getStatutPack()), self::ACTIVE_STATUSES, true) ? 1.0 : 0.35;

        return [
            'base_price' => $basePrice,
            'final_price' => $finalPrice,
            'reduction' => $reduction,
            'reduction_ratio' => $reductionRatio,
            'capacity' => $capacity,
            'linked_activities' => $linkedActivities,
            'coverage_ratio' => $coverageRatio,
            'status_value' => $statusValue,
            'inscriptions_total' => $inscriptionsTotal,
            'inscriptions_30d' => $inscriptions30d,
            'pending_count' => $pendingCount,
            'views_30d' => (int) ($feedbackCounts['view_pack_detail'] ?? 0),
            'opens_30d' => (int) ($feedbackCounts['open_inscription'] ?? 0),
            'confirmed_30d' => (int) ($feedbackCounts['inscription_confirmed'] ?? 0),
            'type_label' => (string) ($pack->getTypePack() ?? 'Standard'),
        ];
    }

    /**
     * @param array<string, float|int|string> $metrics
     */
    private function computeScore(
        array $metrics,
        float $maxFinalPrice,
        int $maxInscriptions,
        int $maxRecentInscriptions,
        int $maxViews,
        int $maxOpens,
    ): float {
        $priceAccessibility = 1 - min(((float) $metrics['final_price']) / $maxFinalPrice, 1);
        $valueScore = (0.55 * (float) $metrics['reduction_ratio']) + (0.45 * $priceAccessibility);
        $popularityScore = (0.6 * ((int) $metrics['inscriptions_total'] / $maxInscriptions))
            + (0.4 * ((int) $metrics['inscriptions_30d'] / $maxRecentInscriptions));
        $momentumScore = (0.65 * ((int) $metrics['views_30d'] / $maxViews))
            + (0.35 * ((int) $metrics['opens_30d'] / $maxOpens));
        $conversionScore = (int) $metrics['opens_30d'] > 0
            ? min(((int) $metrics['confirmed_30d']) / max((int) $metrics['opens_30d'], 1), 1)
            : 0.0;

        $score = 100 * (
            (0.26 * $valueScore) +
            (0.24 * $popularityScore) +
            (0.16 * (float) $metrics['coverage_ratio']) +
            (0.14 * $momentumScore) +
            (0.12 * $conversionScore) +
            (0.08 * (float) $metrics['status_value'])
        );

        return round(max(0.0, min($score, 100.0)), 1);
    }

    /**
     * @param array<string, float|int|string> $metrics
     * @return string[]
     */
    private function resolveBadges(array $metrics, float $score): array
    {
        $badges = [];

        if ((float) $metrics['reduction_ratio'] >= 0.18 && (float) $metrics['final_price'] <= (float) $metrics['base_price'] * 0.85) {
            $badges[] = 'Bon plan';
        }

        if ((int) $metrics['inscriptions_30d'] >= 2 || (int) $metrics['views_30d'] >= 4) {
            $badges[] = 'Tendance';
        }

        if ((int) $metrics['inscriptions_total'] >= 3) {
            $badges[] = 'Très demandé';
        }

        if ((float) $metrics['final_price'] >= 180 || mb_strtolower((string) $metrics['type_label']) === 'premium') {
            $badges[] = 'Premium';
        }

        if ((float) $metrics['final_price'] <= 120 && (int) $metrics['capacity'] <= 4) {
            $badges[] = 'Découverte';
        }

        if ($score >= 78 && !in_array('Tendance', $badges, true)) {
            $badges[] = 'Sélection experte';
        }

        return array_slice(array_values(array_unique($badges)), 0, 3);
    }

    /**
     * @param array<string, float|int|string> $metrics
     * @return string[]
     */
    private function buildReasons(array $metrics, float $score): array
    {
        $reasons = [];

        if ((float) $metrics['reduction_ratio'] >= 0.15) {
            $reasons[] = 'une réduction réellement compétitive';
        }

        if ((float) $metrics['coverage_ratio'] >= 0.6) {
            $reasons[] = 'une couverture d’activités cohérente';
        }

        if ((int) $metrics['inscriptions_total'] > 0) {
            $reasons[] = 'un historique d’inscriptions rassurant';
        }

        if ((int) $metrics['views_30d'] >= 3) {
            $reasons[] = 'un intérêt utilisateur récent';
        }

        if ($score >= 70 && $reasons === []) {
            $reasons[] = 'un équilibre global entre valeur, demande et activation';
        }

        if ($reasons === []) {
            $reasons[] = 'un positionnement encore à consolider';
        }

        return array_slice($reasons, 0, 3);
    }
}
