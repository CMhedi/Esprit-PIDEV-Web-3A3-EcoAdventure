<?php

namespace App\Model;

class UserProfile
{
    public function __construct(
        private int $userId,
        private ?int $favoriteCoach = null,
        private ?PreferredTime $preferredTime = null,
        private ?string $preferredDay = null,
        private string $activityLevel = 'new'
    ) {}

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getFavoriteCoach(): ?int
    {
        return $this->favoriteCoach;
    }

    public function getPreferredTime(): ?PreferredTime
    {
        return $this->preferredTime;
    }

    public function getPreferredDay(): ?string
    {
        return $this->preferredDay;
    }

    public function getActivityLevel(): string
    {
        return $this->activityLevel;
    }

    public function isNewUser(): bool
    {
        return $this->activityLevel === 'new';
    }

    public function isActive(): bool
    {
        return in_array($this->activityLevel, ['very_active', 'active']);
    }
}