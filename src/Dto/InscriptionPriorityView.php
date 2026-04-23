<?php

namespace App\Dto;

use App\Entity\Inscription;

final class InscriptionPriorityView
{
    public function __construct(
        private readonly Inscription $inscription,
        private readonly float $priorityScore,
        private readonly string $lane,
        private readonly string $explanation,
        private readonly float $packScore,
        private readonly int $userHistoryCount,
    ) {
    }

    public function getInscription(): Inscription
    {
        return $this->inscription;
    }

    public function getPriorityScore(): float
    {
        return $this->priorityScore;
    }

    public function getLane(): string
    {
        return $this->lane;
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }

    public function getPackScore(): float
    {
        return $this->packScore;
    }

    public function getUserHistoryCount(): int
    {
        return $this->userHistoryCount;
    }

    public function isHighPriority(): bool
    {
        return $this->priorityScore >= 75.0;
    }
}
