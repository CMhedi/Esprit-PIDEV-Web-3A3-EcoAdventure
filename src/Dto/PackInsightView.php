<?php

namespace App\Dto;

use App\Entity\Pack;

final class PackInsightView
{
    /**
     * @param string[] $badges
     * @param string[] $reasons
     * @param array<string, float|int|string> $metrics
     */
    public function __construct(
        private readonly Pack $pack,
        private readonly float $score,
        private readonly int $rank,
        private readonly array $badges,
        private readonly array $reasons,
        private readonly array $metrics,
        private readonly float $recommendationScore = 0.0,
    ) {
    }

    public function getPack(): Pack
    {
        return $this->pack;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    /**
     * @return string[]
     */
    public function getBadges(): array
    {
        return $this->badges;
    }

    public function getPrimaryBadge(): ?string
    {
        return $this->badges[0] ?? null;
    }

    /**
     * @return string[]
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    /**
     * @return array<string, float|int|string>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getRecommendationScore(): float
    {
        return $this->recommendationScore;
    }

    public function withRecommendationScore(float $recommendationScore): self
    {
        return new self(
            $this->pack,
            $this->score,
            $this->rank,
            $this->badges,
            $this->reasons,
            $this->metrics,
            $recommendationScore,
        );
    }
}
