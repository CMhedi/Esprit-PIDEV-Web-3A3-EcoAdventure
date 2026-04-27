<?php

namespace App\Tests;

use App\Entity\Conversation;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use PHPUnit\Framework\TestCase;

final class ConversationEntityTest extends TestCase
{
    public function testConversationCreationWithDefaults(): void
    {
        $user = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($user)
            ->setTitre('My Conversation')
            ->setEst_groupe(false);

        self::assertSame($user, $conversation->getCreateur());
        self::assertSame('My Conversation', $conversation->getTitre());
        self::assertFalse($conversation->isEst_groupe());
        self::assertNotNull($conversation->getDate_creation());
        self::assertCount(0, $conversation->getParticipants());
        self::assertCount(0, $conversation->getMessages());
    }

    public function testConversationAddParticipant(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $participant1 = (new UserApp())
            ->setNom('Participant1')
            ->setPrenom('User')
            ->setEmail('participant1@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $participant2 = (new UserApp())
            ->setNom('Participant2')
            ->setPrenom('User')
            ->setEmail('participant2@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Group Chat')
            ->setEst_groupe(true);

        $conversation->addParticipant($participant1);
        $conversation->addParticipant($participant2);

        self::assertCount(2, $conversation->getParticipants());
        self::assertTrue($conversation->getParticipants()->contains($participant1));
        self::assertTrue($conversation->getParticipants()->contains($participant2));
    }

    public function testConversationDoesNotDuplicateParticipant(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $participant = (new UserApp())
            ->setNom('Participant')
            ->setPrenom('User')
            ->setEmail('participant@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('No Duplicate Chat')
            ->setEst_groupe(true);

        $conversation->addParticipant($participant);
        $conversation->addParticipant($participant);

        self::assertCount(1, $conversation->getParticipants());
    }

    public function testConversationRemoveParticipant(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $participant = (new UserApp())
            ->setNom('Participant')
            ->setPrenom('User')
            ->setEmail('participant@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Remove Test')
            ->setEst_groupe(true);

        $conversation->addParticipant($participant);
        self::assertCount(1, $conversation->getParticipants());

        $conversation->removeParticipant($participant);
        self::assertCount(0, $conversation->getParticipants());
    }

    public function testConversationPrivateBlockFeature(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Private Conversation')
            ->setEst_groupe(false);

        self::assertFalse($conversation->isPrivateBlocked());

        $conversation->blockPrivateConversation();

        self::assertTrue($conversation->isPrivateBlocked());
    }

    public function testConversationGroupCannotBeBlocked(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Group Chat')
            ->setEst_groupe(true);

        self::assertFalse($conversation->isPrivateBlocked());

        $conversation->blockPrivateConversation();

        // Group conversations should not be affected
        self::assertFalse($conversation->isPrivateBlocked());
    }

    public function testConversationBlockDoesNotAffectAlreadyBlockedConversation(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Already Blocked')
            ->setEst_groupe(false);

        $conversation->blockPrivateConversation();
        self::assertTrue($conversation->isPrivateBlocked());

        // Block again - should not duplicate prefix
        $conversation->blockPrivateConversation();
        self::assertTrue($conversation->isPrivateBlocked());
        self::assertSame('Already Blocked', $conversation->getTitre());
    }

    public function testConversationWithoutTitleAfterBlock(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setEst_groupe(false);
        // Intentionally not setting titre

        $conversation->blockPrivateConversation();

        self::assertTrue($conversation->isPrivateBlocked());
        self::assertNull($conversation->getTitre());
    }

    public function testConversationIsGroupChat(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $participant1 = (new UserApp())
            ->setNom('P1')
            ->setPrenom('User')
            ->setEmail('p1@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $participant2 = (new UserApp())
            ->setNom('P2')
            ->setPrenom('User')
            ->setEmail('p2@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $groupConversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Team Group')
            ->setEst_groupe(true);

        $groupConversation->addParticipant($participant1);
        $groupConversation->addParticipant($participant2);

        self::assertTrue($groupConversation->isEst_groupe());
        self::assertCount(2, $groupConversation->getParticipants());
    }

    public function testConversationPrivateChat(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $recipient = (new UserApp())
            ->setNom('Recipient')
            ->setPrenom('User')
            ->setEmail('recipient@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $privateConversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Private Chat')
            ->setEst_groupe(false);

        $privateConversation->addParticipant($recipient);

        self::assertFalse($privateConversation->isEst_groupe());
        self::assertCount(1, $privateConversation->getParticipants());
    }

    public function testConversationTitleValidation(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setEst_groupe(true);

        // Minimum length
        $conversation->setTitre('AB');
        self::assertSame('AB', $conversation->getTitre());

        // Maximum length (150 characters)
        $longTitle = str_repeat('a', 150);
        $conversation->setTitre($longTitle);
        self::assertSame($longTitle, $conversation->getTitre());
    }

    public function testConversationCreationDate(): void
    {
        $creator = (new UserApp())
            ->setNom('Creator')
            ->setPrenom('User')
            ->setEmail('creator@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $beforeCreation = new \DateTime();
        $conversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Time Test')
            ->setEst_groupe(false);
        $afterCreation = new \DateTime();

        $creationTime = $conversation->getDate_creation();
        self::assertGreaterThanOrEqual($beforeCreation, $creationTime);
        self::assertLessThanOrEqual($afterCreation, $creationTime);
    }

    public function testConversationWithMultipleParticipants(): void
    {
        $creator = (new UserApp())
            ->setNom('Admin')
            ->setPrenom('User')
            ->setEmail('admin@example.com')
            ->setRole(RoleUser::ADMIN)
            ->setMot_de_passe('secret');

        $participants = [];
        for ($i = 1; $i <= 5; ++$i) {
            $user = (new UserApp())
                ->setNom("User$i")
                ->setPrenom('Name')
                ->setEmail("user$i@example.com")
                ->setRole(RoleUser::USER_SIMPLE)
                ->setMot_de_passe('secret');
            $participants[] = $user;
        }

        $groupConversation = (new Conversation())
            ->setCreateur($creator)
            ->setTitre('Large Group')
            ->setEst_groupe(true);

        foreach ($participants as $participant) {
            $groupConversation->addParticipant($participant);
        }

        self::assertCount(5, $groupConversation->getParticipants());
        self::assertTrue($groupConversation->isEst_groupe());
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
