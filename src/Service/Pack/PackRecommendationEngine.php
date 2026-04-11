<?php

namespace App\Service\Pack;

use App\Dto\PackInsightView;
use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;

final class PackRecommendationEngine
{
    /**
     * @param Pack[] $packs
     * @param array<int, PackInsightView> $insights
     * @return PackInsightView[]
     */
    public function recommendForUser(array $packs, array $insights, ?UserApp $user, int $limit = 3): array
    {
        if ($packs === []) {
            return [];
        }

        if (!$user instanceof UserApp || $user->getInscriptions()->count() === 0) {
            $generic = array_values($insights);
            usort($generic, static fn (PackInsightView $left, PackInsightView $right): int => $right->getScore() <=> $left->getScore());

            return array_slice($generic, 0, $limit);
        }

        $profile = $this->buildUserProfile($user);
        $recommended = [];

        foreach ($packs as $pack) {
            $packId = $pack->getIdPack();
            if (!$packId || !isset($insights[$packId])) {
                continue;
            }

            $insight = $insights[$packId];
            $baseScore = $insight->getScore();
            $budgetAffinity = $this->computeBudgetAffinity((float) $pack->getPrixFinal(), $profile['avg_amount']);
            $typeAffinity = $this->computeTypeAffinity((string) $pack->getTypePack(), $profile['type_preferences']);
            $capacityAffinity = $this->computeCapacityAffinity((int) $pack->getNbActivitesMax(), $profile['avg_capacity']);
            $noveltyBoost = in_array($packId, $profile['known_pack_ids'], true) ? 0.15 : 1.0;

            $recommendationScore = round(
                (0.6 * $baseScore) +
                (40 * (
                    (0.4 * $budgetAffinity) +
                    (0.35 * $typeAffinity) +
                    (0.25 * $capacityAffinity)
                ) * $noveltyBoost),
                1
            );

            $recommended[] = $insight->withRecommendationScore($recommendationScore);
        }

        usort($recommended, static fn (PackInsightView $left, PackInsightView $right): int => $right->getRecommendationScore() <=> $left->getRecommendationScore());

        return array_slice($recommended, 0, $limit);
    }

    /**
     * @param Pack[] $packs
     * @param array<int, PackInsightView> $insights
     */
    public function recommendAlternative(
        Pack $currentPack,
        array $packs,
        array $insights,
        ?UserApp $user,
    ): ?PackInsightView {
        $recommendations = $this->recommendForUser($packs, $insights, $user, 5);

        foreach ($recommendations as $recommendation) {
            if ($recommendation->getPack()->getIdPack() !== $currentPack->getIdPack()) {
                return $recommendation;
            }
        }

        return null;
    }

    /**
     * @return array{avg_amount: float, avg_capacity: float, type_preferences: array<string, int>, known_pack_ids: int[]}
     */
    private function buildUserProfile(UserApp $user): array
    {
        $amounts = [];
        $capacities = [];
        $typePreferences = [];
        $knownPackIds = [];

        foreach ($user->getInscriptions() as $inscription) {
            if (!$inscription instanceof Inscription) {
                continue;
            }

            $amounts[] = (float) ($inscription->getMontantTotal() ?? 0);

            if ($inscription->getPack()) {
                $pack = $inscription->getPack();
                $knownPackIds[] = (int) $pack->getIdPack();
                $capacities[] = (int) ($pack->getNbActivitesMax() ?? 0);
                $typeKey = mb_strtolower((string) $pack->getTypePack());
                $typePreferences[$typeKey] = ($typePreferences[$typeKey] ?? 0) + 1;
            }
        }

        return [
            'avg_amount' => $amounts === [] ? 0.0 : array_sum($amounts) / count($amounts),
            'avg_capacity' => $capacities === [] ? 0.0 : array_sum($capacities) / count($capacities),
            'type_preferences' => $typePreferences,
            'known_pack_ids' => array_values(array_unique($knownPackIds)),
        ];
    }

    /**
     * @param array<string, int> $typePreferences
     */
    private function computeTypeAffinity(string $packType, array $typePreferences): float
    {
        if ($typePreferences === []) {
            return 0.55;
        }

        $packType = mb_strtolower($packType);
        $max = max($typePreferences);

        return ($typePreferences[$packType] ?? 0) / max($max, 1);
    }

    private function computeBudgetAffinity(float $packAmount, float $averageAmount): float
    {
        if ($averageAmount <= 0) {
            return 0.6;
        }

        $deltaRatio = abs($packAmount - $averageAmount) / max($averageAmount, $packAmount, 1.0);

        return max(0.0, 1 - $deltaRatio);
    }

    private function computeCapacityAffinity(int $capacity, float $averageCapacity): float
    {
        if ($averageCapacity <= 0) {
            return 0.55;
        }

        $deltaRatio = abs($capacity - $averageCapacity) / max($averageCapacity, $capacity, 1.0);

        return max(0.0, 1 - $deltaRatio);
    }
}
