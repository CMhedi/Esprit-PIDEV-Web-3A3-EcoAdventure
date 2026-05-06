<?php

namespace App\Tests\Entity;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\UserApp;
use App\Enum\PrioriteMessage;
use App\Enum\RoleUser;
use App\Enum\StatutMessage;
use App\Enum\TypeMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MessageValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testNewMessageStartsWithExpectedDefaults(): void
    {
        $message = new Message();

        self::assertInstanceOf(\DateTimeInterface::class, $message->getDate_envoi());
        self::assertSame(PrioriteMessage::NORMAL, $message->getPrioriteMessage());
        self::assertSame([], $message->getAttachments());
        self::assertSame([], $message->getReactions());
    }

    public function testValidationRejectsBlankTextMessagesAndInvalidEditDate(): void
    {
        $message = new Message();
        $message->setType_message(TypeMessage::TEXTE);
        $message->setStatut_message(StatutMessage::ENVOYE);
        $message->setContenu('');
        $message->setConversation($this->createConversation());
        $message->setUserApp($this->createUser(14));

        $sentAt = new \DateTimeImmutable('2026-04-30 10:00:00');
        $message->setDate_envoi($sentAt);
        $message->setDate_modifier($sentAt->sub(new \DateInterval('PT10M')));

        $violations = $this->validator->validate($message, null, ['Default', 'text_message']);
        $messages = array_map(static fn ($violation): string => $violation->getMessage(), iterator_to_array($violations));

        self::assertContains('Le contenu du message ne peut pas être vide.', $messages);
        self::assertContains("La date de modification ne peut pas être antérieure à la date d'envoi.", $messages);
    }

    private function createConversation(): Conversation
    {
        $creator = $this->createUser(9);

        $conversation = new Conversation();
        $conversation->setCreateur($creator);
        $conversation->setTitre('Discussion test');
        $conversation->setEst_groupe(false);
        $conversation->addParticipant($creator);

        return $conversation;
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
