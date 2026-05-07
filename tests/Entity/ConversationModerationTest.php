<?php

namespace App\Tests\Entity;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\StatutMessage;
use App\Enum\TypeMessage;
use PHPUnit\Framework\TestCase;

final class ConversationModerationTest extends TestCase
{
    public function testPrivateConversationCanBeBlockedWithoutChangingPublicTitle(): void
    {
        $conversation = new Conversation();
        $conversation->setTitre('Support client');
        $conversation->setEst_groupe(false);

        $conversation->blockPrivateConversation();
        $conversation->blockPrivateConversation();

        self::assertTrue($conversation->isPrivateBlocked());
        self::assertSame('Support client', $conversation->getTitre());
    }

    public function testGroupConversationIsNotMarkedAsPrivateBlocked(): void
    {
        $conversation = new Conversation();
        $conversation->setTitre('Groupe EcoAdventure');
        $conversation->setEst_groupe(true);

        $conversation->blockPrivateConversation();

        self::assertFalse($conversation->isPrivateBlocked());
        self::assertSame('Groupe EcoAdventure', $conversation->getTitre());
    }

    public function testAddingAndRemovingMessageKeepsOwningSideConsistent(): void
    {
        $conversation = new Conversation();
        $conversation->setTitre('Discussion');
        $conversation->setEst_groupe(false);
        $conversation->setCreateur($this->createUser(1));

        $message = new Message();
        $message->setContenu('Bonjour');
        $message->setType_message(TypeMessage::TEXTE);
        $message->setStatut_message(StatutMessage::ENVOYE);
        $message->setUserApp($this->createUser(2));

        $conversation->addMessage($message);
        self::assertTrue($conversation->getMessages()->contains($message));
        self::assertSame($conversation, $message->getConversation());

        $conversation->removeMessage($message);
        self::assertFalse($conversation->getMessages()->contains($message));
        self::assertNull($message->getConversation());
    }

    private function createUser(int $id): UserApp
    {
        $user = new UserApp();
        $user->setNom('User');
        $user->setPrenom((string) $id);
        $user->setEmail(sprintf('messagerie%d@example.com', $id));
        $user->setRole(RoleUser::USER_SIMPLE);
        $user->setMot_de_passe('hashed-password');

        $reflectionProperty = new \ReflectionProperty(UserApp::class, 'id_user');
        $reflectionProperty->setValue($user, $id);

        return $user;
    }
}
