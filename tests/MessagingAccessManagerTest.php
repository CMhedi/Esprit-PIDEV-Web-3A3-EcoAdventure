<?php

namespace App\Tests;

use App\Entity\BlockedMessagingUser;
use App\Entity\Conversation;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Repository\BlockedMessagingUserRepository;
use App\Service\MessagingAccessManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MessagingAccessManagerTest extends TestCase
{
    private MockObject|BlockedMessagingUserRepository $blockedUserRepositoryMock;
    private MessagingAccessManager $manager;

    protected function setUp(): void
    {
        $this->blockedUserRepositoryMock = $this->createMock(BlockedMessagingUserRepository::class);
        $this->manager = new MessagingAccessManager($this->blockedUserRepositoryMock);
    }

    public function testGetBlockedUserIdsReturnsEmptyArrayWhenNoneBlocked(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([]);

        $result = $this->manager->getBlockedUserIds();

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testGetBlockedUserIdsReturnsSortedArray(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([5, 2, 8, 1, 3]);

        $result = $this->manager->getBlockedUserIds();

        self::assertSame([1, 2, 3, 5, 8], $result);
    }

    public function testGetBlockedUserIdsCaches(): void
    {
        $this->blockedUserRepositoryMock
            ->expects($this->once())
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3]);

        $first = $this->manager->getBlockedUserIds();
        $second = $this->manager->getBlockedUserIds();

        self::assertSame($first, $second);
    }

    public function testIsUserCallBlockedReturnsFalseForNullUser(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([]);

        $result = $this->manager->isUserCallBlocked(null);

        self::assertFalse($result);
    }

    public function testIsUserCallBlockedReturnsFalseForUnblockedUser(): void
    {
        $user = (new UserApp())
            ->setNom('Normal')
            ->setPrenom('User')
            ->setEmail('normal@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($user, 'id_user', 10);

        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3]);

        $result = $this->manager->isUserCallBlocked($user);

        self::assertFalse($result);
    }

    public function testIsUserCallBlockedReturnsTrueForBlockedUser(): void
    {
        $user = (new UserApp())
            ->setNom('Blocked')
            ->setPrenom('User')
            ->setEmail('blocked@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($user, 'id_user', 5);

        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3, 5, 8]);

        $result = $this->manager->isUserCallBlocked($user);

        self::assertTrue($result);
    }

    public function testIsUserIdCallBlockedReturnsFalseForNullId(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3]);

        $result = $this->manager->isUserIdCallBlocked(null);

        self::assertFalse($result);
    }

    public function testIsUserIdCallBlockedReturnsFalseForUnblockedId(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3]);

        $result = $this->manager->isUserIdCallBlocked(10);

        self::assertFalse($result);
    }

    public function testIsUserIdCallBlockedReturnsTrueForBlockedId(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3, 5, 8]);

        $result = $this->manager->isUserIdCallBlocked(5);

        self::assertTrue($result);
    }

    public function testIsConversationCallBlockedReturnsFalseForNullConversation(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([]);

        $result = $this->manager->isConversationCallBlocked(null);

        self::assertFalse($result);
    }

    public function testIsConversationCallBlockedReturnsFalseWhenNoBlockedParticipants(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($creator, 'id_user', 1);

        $participant = (new UserApp())
            ->setNom('Participant')
            ->setPrenom('User')
            ->setEmail('participant@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($participant, 'id_user', 2);

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Normal Conversation')
            ->setEst_groupe(false);
        $conversation->addParticipant($participant);

        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([10, 11, 12]);

        $result = $this->manager->isConversationCallBlocked($conversation);

        self::assertFalse($result);
    }

    public function testIsConversationCallBlockedReturnsTrueWhenParticipantIsBlocked(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($creator, 'id_user', 1);

        $blockedParticipant = (new UserApp())
            ->setNom('Blocked')
            ->setPrenom('User')
            ->setEmail('blocked@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($blockedParticipant, 'id_user', 5);

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Conversation with Blocked')
            ->setEst_groupe(true);
        $conversation->addParticipant($blockedParticipant);

        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3, 5, 8]);

        $result = $this->manager->isConversationCallBlocked($conversation);

        self::assertTrue($result);
    }

    public function testIsConversationCallBlockedReturnsTrueWhenCreatorIsBlocked(): void
    {
        $blockedCreator = (new UserApp())
            ->setNom('Blocked')
            ->setPrenom('Creator')
            ->setEmail('blocked.creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($blockedCreator, 'id_user', 5);

        $participant = (new UserApp())
            ->setNom('Participant')
            ->setPrenom('User')
            ->setEmail('participant@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($participant, 'id_user', 2);

        $conversation = (new Conversation())
            ->setCreateur($blockedCreator)
            ->setTitre('Conversation with Blocked Creator')
            ->setEst_groupe(false);
        $conversation->addParticipant($participant);

        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3, 5, 8]);

        $result = $this->manager->isConversationCallBlocked($conversation);

        self::assertTrue($result);
    }

    public function testContainsCallBlockedUserReturnsFalseForEmptyList(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3]);

        $result = $this->manager->containsCallBlockedUser([]);

        self::assertFalse($result);
    }

    public function testContainsCallBlockedUserReturnsFalseWhenNoBlockedUsers(): void
    {
        $users = [
            (new UserApp())
                ->setNom('User1')
                ->setPrenom('Test')
                ->setEmail('user1@example.com')
                ->setRole(RoleUser::USER_SIMPLE)
                ->setMot_de_passe('secret'),
            (new UserApp())
                ->setNom('User2')
                ->setPrenom('Test')
                ->setEmail('user2@example.com')
                ->setRole(RoleUser::USER_SIMPLE)
                ->setMot_de_passe('secret'),
        ];
        $this->setEntityId($users[0], 'id_user', 10);
        $this->setEntityId($users[1], 'id_user', 11);

        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3]);

        $result = $this->manager->containsCallBlockedUser($users);

        self::assertFalse($result);
    }

    public function testContainsCallBlockedUserReturnsTrueWhenBlockedUserFound(): void
    {
        $normalUser = (new UserApp())
            ->setNom('Normal')
            ->setPrenom('User')
            ->setEmail('normal@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($normalUser, 'id_user', 10);

        $blockedUser = (new UserApp())
            ->setNom('Blocked')
            ->setPrenom('User')
            ->setEmail('blocked@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');
        $this->setEntityId($blockedUser, 'id_user', 5);

        $users = [$normalUser, $blockedUser];

        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3, 5, 8]);

        $result = $this->manager->containsCallBlockedUser($users);

        self::assertTrue($result);
    }

    public function testContainsCallBlockedUserReturnsFalseForNullValues(): void
    {
        $users = [null, null];

        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 2, 3]);

        $result = $this->manager->containsCallBlockedUser($users);

        self::assertFalse($result);
    }

    public function testGetBlockedUserIdsFiltersInvalidIds(): void
    {
        $this->blockedUserRepositoryMock
            ->method('findActiveBlockedUserIds')
            ->willReturn([0, -1, 5, 10, null]);

        $result = $this->manager->getBlockedUserIds();

        // Should filter out 0, -1, and null
        self::assertTrue(in_array(5, $result, true));
        self::assertTrue(in_array(10, $result, true));
        self::assertFalse(in_array(0, $result, true));
        self::assertFalse(in_array(-1, $result, true));
    }

    public function testMultipleCallsToGetBlockedUserIdsUseCache(): void
    {
        $this->blockedUserRepositoryMock
            ->expects(self::once())
            ->method('findActiveBlockedUserIds')
            ->willReturn([1, 5, 10]);

        // First call
        $first = $this->manager->getBlockedUserIds();

        // Second call should use cache
        $second = $this->manager->getBlockedUserIds();

        // Third call should still use cache
        $third = $this->manager->getBlockedUserIds();

        self::assertSame($first, $second);
        self::assertSame($second, $third);
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
