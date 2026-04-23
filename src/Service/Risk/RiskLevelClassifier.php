<?php

namespace App\Service\Risk;

final class RiskLevelClassifier
{
    public function classify(float $score): string
    {
        return match (true) {
            $score >= 75.0 => 'Critical Risk',
            $score >= 50.0 => 'High Risk',
            $score >= 25.0 => 'Medium Risk',
            default => 'Low Risk',
        };
    }

    public function tone(string $level): string
    {
        return match ($level) {
            'Critical Risk' => 'danger',
            'High Risk' => 'warning',
            'Medium Risk' => 'info',
            default => 'success',
        };
    }
}
