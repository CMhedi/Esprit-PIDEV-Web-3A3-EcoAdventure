<?php

namespace App\Service\Risk;

use App\Dto\InscriptionRiskView;
use App\Dto\PackRiskView;
use App\Entity\Inscription;

final class InscriptionRiskEngine
{
    public function __construct(
        private readonly RiskLevelClassifier $levelClassifier,
        private readonly RiskInterpreter $riskInterpreter,
        private readonly RiskActionResolver $actionResolver,
    ) {
    }

    /**
     * @param Inscription[] $inscriptions
     * @param array<int, PackRiskView> $packRiskViews
     * @return array<int, InscriptionRiskView>
     */
    public function evaluate(array $inscriptions, array $packRiskViews): array
    {
        if ($inscriptions === []) {
            return [];
        }

        $maxAmount = 1.0;
        foreach ($inscriptions as $inscription) {
            $maxAmount = max($maxAmount, (float) ($inscription->getMontantTotal() ?? 0.0));
        }

        $views = [];
        foreach ($inscriptions as $inscription) {
            $amount = (float) ($inscription->getMontantTotal() ?? 0.0);
            $historyCount = $inscription->getUserApp()?->getInscriptions()->count() ?? 0;
            $userAverageAmount = $this->computeUserAverageAmount($inscription);
            $packRiskView = $this->resolvePackRiskView($inscription, $packRiskViews);
            $packRiskScore = $packRiskView?->getRiskScore() ?? 35.0;
            $packRiskLevel = $packRiskView?->getRiskLevel() ?? 'Medium Risk';

            $components = [
                'workflow' => $this->computeWorkflowRisk($inscription),
                'amount' => $this->clamp($amount / max($maxAmount, 1.0)),
                'pack_propagation' => $this->clamp($packRiskScore / 100),
                'urgency' => $this->computeUrgencyRisk($inscription),
                'history' => $this->computeHistoryRisk($historyCount),
                'anomaly' => $this->computeAmountAnomalyRisk($amount, $userAverageAmount, $historyCount),
                'strategic_value' => $this->computeStrategicValueRisk($amount, $maxAmount, $packRiskScore),
            ];

            $riskScore = round(100 * (
                (0.30 * $components['workflow']) +
                (0.18 * $components['amount']) +
                (0.22 * $components['pack_propagation']) +
                (0.10 * $components['urgency']) +
                (0.08 * $components['history']) +
                (0.07 * $components['anomaly']) +
                (0.05 * $components['strategic_value'])
            ), 1);

            $riskLevel = $this->levelClassifier->classify($riskScore);
            $drivers = $this->riskInterpreter->topDrivers($components, [
                'workflow' => 'un statut de dossier encore instable',
                'amount' => 'une exposition financiere elevee',
                'pack_propagation' => 'un pack lie deja classe a risque',
                'urgency' => 'une attente qui s allonge',
                'history' => 'un historique utilisateur limite',
                'anomaly' => 'une incoherence par rapport au comportement habituel',
                'strategic_value' => 'une valeur metier forte sur un contexte sensible',
            ]);
            $summary = $this->riskInterpreter->buildInscriptionSummary($riskLevel, $drivers, $packRiskScore);
            $actions = $this->actionResolver->resolveInscriptionActions($riskLevel, $components, $packRiskView);

            $views[$inscription->getIdInscription() ?? spl_object_id($inscription)] = new InscriptionRiskView(
                $inscription,
                $riskScore,
                $riskLevel,
                $summary,
                $drivers,
                $actions,
                $components,
                $packRiskScore,
                $packRiskLevel,
            );
        }

        uasort($views, static fn (InscriptionRiskView $left, InscriptionRiskView $right): int => $right->getRiskScore() <=> $left->getRiskScore());

        return $views;
    }

    /**
     * @param array<int, PackRiskView> $packRiskViews
     */
    private function resolvePackRiskView(Inscription $inscription, array $packRiskViews): ?PackRiskView
    {
        $packId = $inscription->getPack()?->getIdPack();

        if ($packId === null) {
            return null;
        }

        return $packRiskViews[$packId] ?? null;
    }

    private function computeWorkflowRisk(Inscription $inscription): float
    {
        return match ($inscription->getStatutInscr()?->value) {
            'EN_ATTENTE' => 1.0,
            'CONFIRMEE' => 0.42,
            'VALIDEE' => 0.18,
            'ANNULEE' => 0.55,
            default => 0.48,
        };
    }

    private function computeUrgencyRisk(Inscription $inscription): float
    {
        $date = $inscription->getDateInscription();
        if (!$date) {
            return 0.55;
        }

        $daysOld = (int) (new \DateTimeImmutable())->diff(\DateTimeImmutable::createFromInterface($date))->format('%a');

        if (($inscription->getStatutInscr()?->value ?? '') === 'EN_ATTENTE') {
            return $this->clamp($daysOld / 7);
        }

        return $this->clamp($daysOld / 30, 0.05, 0.35);
    }

    private function computeHistoryRisk(int $historyCount): float
    {
        return match (true) {
            $historyCount <= 1 => 0.72,
            $historyCount <= 3 => 0.4,
            default => 0.14,
        };
    }

    private function computeAmountAnomalyRisk(float $amount, float $userAverageAmount, int $historyCount): float
    {
        if ($historyCount <= 1 || $userAverageAmount <= 0.0) {
            return 0.42;
        }

        $distance = abs($amount - $userAverageAmount) / $userAverageAmount;

        return $this->clamp(max(0.1, min($distance, 1.0)));
    }

    private function computeStrategicValueRisk(float $amount, float $maxAmount, float $packRiskScore): float
    {
        $amountRatio = $this->clamp($amount / max($maxAmount, 1.0));

        return $this->clamp((0.55 * $amountRatio) + (0.45 * ($packRiskScore / 100)));
    }

    private function computeUserAverageAmount(Inscription $current): float
    {
        $inscriptions = $current->getUserApp()?->getInscriptions();
        if ($inscriptions === null || $inscriptions->count() <= 1) {
            return (float) ($current->getMontantTotal() ?? 0.0);
        }

        $total = 0.0;
        $count = 0;

        foreach ($inscriptions as $inscription) {
            if (!$inscription instanceof Inscription || $inscription === $current) {
                continue;
            }

            $total += (float) ($inscription->getMontantTotal() ?? 0.0);
            ++$count;
        }

        if ($count === 0) {
            return (float) ($current->getMontantTotal() ?? 0.0);
        }

        return $total / $count;
    }

    private function clamp(float $value, float $min = 0.0, float $max = 1.0): float
    {
        return max($min, min($max, $value));
    }
}
