<?php

namespace App\Service\Risk;

use App\Dto\PackInsightView;

final class RiskInterpreter
{
    /**
     * @param array<string, float> $components
     * @param array<string, string> $labels
     * @return string[]
     */
    public function topDrivers(array $components, array $labels, int $limit = 3): array
    {
        arsort($components);

        $drivers = [];
        foreach ($components as $key => $value) {
            if ($value < 0.32) {
                continue;
            }

            $drivers[] = $labels[$key] ?? $key;

            if (count($drivers) >= $limit) {
                break;
            }
        }

        if ($drivers === []) {
            return ['aucun signal critique majeur'];
        }

        return $drivers;
    }

    /**
     * @param string[] $drivers
     */
    public function buildPackSummary(string $riskLevel, string $profile, array $drivers, PackInsightView $insight): string
    {
        $prefix = match ($riskLevel) {
            'Critical Risk' => 'Pack critique',
            'High Risk' => 'Pack sous tension',
            'Medium Risk' => 'Pack a surveiller',
            default => 'Pack sain',
        };

        $driverText = implode(', ', array_slice($drivers, 0, 2));

        if ($riskLevel === 'Low Risk') {
            return sprintf(
                '%s avec une attractivite de %.1f/100 et un profil "%s".',
                $prefix,
                $insight->getScore(),
                $profile
            );
        }

        return sprintf(
            '%s. Profil "%s" explique principalement par %s.',
            $prefix,
            $profile,
            $driverText
        );
    }

    /**
     * @param string[] $drivers
     */
    public function buildInscriptionSummary(string $riskLevel, array $drivers, float $packRiskScore): string
    {
        $prefix = match ($riskLevel) {
            'Critical Risk' => 'Inscription critique',
            'High Risk' => 'Inscription prioritaire',
            'Medium Risk' => 'Inscription a surveiller',
            default => 'Inscription stable',
        };

        if ($riskLevel === 'Low Risk') {
            return sprintf(
                '%s. Le dossier reste maitrise et le pack lie est a %.1f/100 de risque.',
                $prefix,
                $packRiskScore
            );
        }

        return sprintf(
            '%s. Les principaux facteurs sont %s.',
            $prefix,
            implode(', ', array_slice($drivers, 0, 2))
        );
    }
}
