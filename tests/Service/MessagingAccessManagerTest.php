<?php

namespace App\Tests\Service;

use App\Entity\Conversation;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Repository\BlockedMessagingUserRepository;
use App\Service\MessagingAccessManager;
use PHPUnit\Framework\TestCase;

class MessagingAccessManagerTest extends TestCase
{
    public function testGetBlockedUserIdsSanitizesSortsAndCachesRepositoryResult(): void
    {
        $repo = $this->createMock(BlockedMessagingUserRepository::class);
        $repo->expects(self::once())
            ->method('findActiveBlockedUserIds')
            ->willReturn(['7', -2, 'foo', 3, 0, 7]);

        $service = new MessagingAccessManager($repo);

        self::assertSame([3, 7, 7], $service->getBlockedUserIds());
        self::assertSame([3, 7, 7], $service->getBlockedUserIds());
    }

    public function testIsUserIdCallBlockedHandlesNullAndKnownIds(): void
    {
        $repo = $this->createMock(BlockedMessagingUserRepository::class);
        $repo->expects(self::once())
            ->method('findActiveBlockedUserIds')
            ->willReturn([5, 9]);

        $service = new MessagingAccessManager($repo);

        self::assertFalse($service->isUserIdCallBlocked(null));
        self::assertFalse($service->isUserIdCallBlocked(1));
        self::assertTrue($service->isUserIdCallBlocked(5));
    }

    public function testBlockedParticipantConversationIsCallBlocked(): void
    {
        $repo = $this->createMock(BlockedMessagingUserRepository::class);
        $repo->expects(self::once())
            ->method('findActiveBlockedUserIds')
            ->willReturn([3]);

        $service = new MessagingAccessManager($repo);

        $viewer = $this->createUser(1);
        $blockedUser = $this->createUser(3);
        $conversation = new Conversation();
        $conversation->setCreateur($viewer);
        $conversation->setTitre('Blocked chat');
        $conversation->setEst_groupe(false);
        $conversation->addParticipant($viewer);
        $conversation->addParticipant($blockedUser);

        self::assertTrue($service->isConversationCallBlocked($conversation));
        self::assertTrue($service->isUserCallBlocked($blockedUser));
    }

    public function testContainsCallBlockedUserDetectsBlockedTarget(): void
    {
        $repo = $this->createMock(BlockedMessagingUserRepository::class);
        $repo->expects(self::once())
            ->method('findActiveBlockedUserIds')
            ->willReturn([3]);

        $service = new MessagingAccessManager($repo);

        $allowedUser = $this->createUser(2);
        $blockedUser = $this->createUser(3);

        self::assertFalse($service->containsCallBlockedUser([$allowedUser]));
        self::assertTrue($service->containsCallBlockedUser([$allowedUser, $blockedUser]));
    }

    public function testConversationIsBlockedWhenCreatorIsBlocked(): void
    {
        $repo = $this->createMock(BlockedMessagingUserRepository::class);
        $repo->expects(self::once())
            ->method('findActiveBlockedUserIds')
            ->willReturn([10]);

        $service = new MessagingAccessManager($repo);

        $creator = $this->createUser(10);
        $participant = $this->createUser(2);
        $conversation = new Conversation();
        $conversation->setCreateur($creator);
        $conversation->setTitre('Direct chat');
        $conversation->setEst_groupe(false);
        $conversation->addParticipant($participant);

        self::assertTrue($service->isConversationCallBlocked($conversation));
    }

    private function createUser(int $id): UserApp
    {
        $user = new UserApp();
        $user->setNom('User');
        $user->setPrenom((string) $id);
        $user->setEmail(sprintf('user%d@example.com', $id));
        $user->setRole(RoleUser::USER_SIMPLE);
        $user->setMot_de_passe('hashed-password');

        $reflectionProperty = new \ReflectionProperty(UserApp::class, 'id_user');
        $reflectionProperty->setValue($user, $id);

        return $user;
    }
}
