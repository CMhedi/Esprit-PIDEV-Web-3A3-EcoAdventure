<?php

namespace App\Tests;

use App\Entity\BlockedMessagingUser;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use PHPUnit\Framework\TestCase;

final class BlockedMessagingUserEntityTest extends TestCase
{
    public function testBlockedMessagingUserCreation(): void
    {
        $user = (new UserApp())
            ->setNom('Blocked')
            ->setPrenom('User')
            ->setEmail('blocked@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user)
            ->setActive(true)
            ->setReason('Spam behavior');

        self::assertSame($user, $blockedUser->getUserApp());
        self::assertTrue($blockedUser->isActive());
        self::assertSame('Spam behavior', $blockedUser->getReason());
        self::assertNotNull($blockedUser->getCreatedAt());
    }

    public function testBlockedMessagingUserDefaultActive(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user);

        self::assertTrue($blockedUser->isActive());
    }

    public function testBlockedMessagingUserCanBeInactivated(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user)
            ->setActive(true);

        self::assertTrue($blockedUser->isActive());

        $blockedUser->setActive(false);

        self::assertFalse($blockedUser->isActive());
    }

    public function testBlockedMessagingUserWithReason(): void
    {
        $user = (new UserApp())
            ->setNom('Spammer')
            ->setPrenom('User')
            ->setEmail('spammer@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $reasons = [
            'Spam behavior',
            'Harassment',
            'Inappropriate content',
            'Abuse',
        ];

        foreach ($reasons as $reason) {
            $blockedUser = (new BlockedMessagingUser())
                ->setUserApp($user)
                ->setReason($reason);

            self::assertSame($reason, $blockedUser->getReason());
        }
    }

    public function testBlockedMessagingUserWithoutReason(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user);

        self::assertNull($blockedUser->getReason());
    }

    public function testBlockedMessagingUserCreatedAtTimestamp(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $beforeCreation = new \DateTimeImmutable();
        $blockedUser = new BlockedMessagingUser();
        $blockedUser->setUserApp($user);
        $afterCreation = new \DateTimeImmutable();

        $createdAt = $blockedUser->getCreatedAt();
        self::assertNotNull($createdAt);
        self::assertGreaterThanOrEqual($beforeCreation, $createdAt);
        self::assertLessThanOrEqual($afterCreation, $createdAt);
    }

    public function testBlockedMessagingUserCanUpdateReason(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user)
            ->setReason('Initial reason');

        self::assertSame('Initial reason', $blockedUser->getReason());

        $blockedUser->setReason('Updated reason');

        self::assertSame('Updated reason', $blockedUser->getReason());
    }

    public function testBlockedMessagingUserCanClearReason(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user)
            ->setReason('Some reason')
            ->setReason(null);

        self::assertNull($blockedUser->getReason());
    }

    public function testBlockedMessagingUserMultipleUsers(): void
    {
        $users = [];
        for ($i = 1; $i <= 3; ++$i) {
            $user = (new UserApp())
                ->setNom("BlockedUser$i")
                ->setPrenom('Test')
                ->setEmail("blocked$i@example.com")
                ->setRole(RoleUser::USER_SIMPLE)
                ->setMot_de_passe('secret');
            $users[] = $user;
        }

        $blockedUsers = [];
        foreach ($users as $i => $user) {
            $blockedUser = (new BlockedMessagingUser())
                ->setUserApp($user)
                ->setReason("Reason $i")
                ->setActive(true);
            $blockedUsers[] = $blockedUser;
        }

        self::assertCount(3, $blockedUsers);
        foreach ($blockedUsers as $blockedUser) {
            self::assertTrue($blockedUser->isActive());
        }
    }

    public function testBlockedMessagingUserUnblocking(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user)
            ->setActive(true)
            ->setReason('Blocked for spam');

        self::assertTrue($blockedUser->isActive());

        // Simulate unblocking
        $blockedUser->setActive(false);

        self::assertFalse($blockedUser->isActive());
        self::assertSame('Blocked for spam', $blockedUser->getReason());
    }

    public function testBlockedMessagingUserCustomCreatedAt(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $customDate = new \DateTimeImmutable('2025-01-01 10:00:00');
        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user)
            ->setCreatedAt($customDate);

        self::assertSame($customDate, $blockedUser->getCreatedAt());
    }

    public function testBlockedMessagingUserLongReason(): void
    {
        $user = (new UserApp())
            ->setNom('User')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $longReason = str_repeat('a', 255);
        $blockedUser = (new BlockedMessagingUser())
            ->setUserApp($user)
            ->setReason($longReason);

        self::assertSame($longReason, $blockedUser->getReason());
    }
}
