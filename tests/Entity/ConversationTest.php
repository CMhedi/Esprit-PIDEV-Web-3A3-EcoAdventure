<?php

namespace App\Tests\Entity;

use App\Entity\Conversation;
use PHPUnit\Framework\TestCase;

final class ConversationTest extends TestCase
{
    public function testBlockPrivateConversationKeepsReadableTitleForUsers(): void
    {
        $conversation = new Conversation();
        $conversation->setEst_groupe(false);
        $conversation->setTitre('Support client');

        $conversation->blockPrivateConversation();

        self::assertTrue($conversation->isPrivateBlocked());
        self::assertSame('Support client', $conversation->getTitre());
    }

    public function testGroupConversationCannotBeMarkedAsBlockedPrivateConversation(): void
    {
        $conversation = new Conversation();
        $conversation->setEst_groupe(true);
        $conversation->setTitre('Equipe sprint web');

        $conversation->blockPrivateConversation();

        self::assertFalse($conversation->isPrivateBlocked());
        self::assertSame('Equipe sprint web', $conversation->getTitre());
    }
}
