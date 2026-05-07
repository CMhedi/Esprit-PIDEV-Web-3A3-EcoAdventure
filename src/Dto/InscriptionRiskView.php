<?php

namespace App\Dto;

use App\Entity\Inscription;

final class InscriptionRiskView
{
    /**
     * @param string[] $drivers
     * @param string[] $recommendedActions
     * @param array<string, float> $components
     */
    public function __construct(
        private readonly Inscription $inscription,
        private readonly float $riskScore,
        private readonly string $riskLevel,
        private readonly string $summary,
        private readonly array $drivers,
        private readonly array $recommendedActions,
        private readonly array $components,
        private readonly float $packRiskScore,
        private readonly string $packRiskLevel,
    ) {
    }

    public function getInscription(): Inscription
    {
        return $this->inscription;
    }

    public function getRiskScore(): float
    {
        return $this->riskScore;
    }

    public function getRiskLevel(): string
    {
        return $this->riskLevel;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return string[]
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * @return string[]
     */
    public function getRecommendedActions(): array
    {
        return $this->recommendedActions;
    }

    /**
     * @return array<string, float>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    public function getPackRiskScore(): float
    {
        return $this->packRiskScore;
    }

    public function getPackRiskLevel(): string
    {
        return $this->packRiskLevel;
    }

    public function getPrimaryAction(): ?string
    {
        return $this->recommendedActions[0] ?? null;
    }

    public function isCritical(): bool
    {
        return $this->riskLevel === 'Critical Risk';
    }

    public function requiresAttention(): bool
    {
        return $this->riskScore >= 50.0;
    }
}
