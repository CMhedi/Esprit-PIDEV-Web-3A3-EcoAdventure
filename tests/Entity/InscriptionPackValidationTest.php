<?php

namespace App\Tests\Entity;

use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\StatutInscription;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InscriptionPackValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidationRejectsIncompletePackInscription(): void
    {
        $inscription = new Inscription();
        $inscription->setNomUser('');

        $violations = $this->validator->validate($inscription);
        $paths = array_map(
            static fn ($violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations)
        );

        self::assertContains('statut_inscr', $paths);
        self::assertContains('nom_user', $paths);
        self::assertContains('pack', $paths);
        self::assertContains('userApp', $paths);
    }

    public function testPaidStatusDrivesPackInscriptionPaymentState(): void
    {
        $inscription = $this->createCompleteInscription();

        $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PENDING);
        self::assertFalse($inscription->isPaid());

        $inscription->setPaymentStatus(Inscription::PAYMENT_STATUS_PAID);
        $inscription->setStatutInscr(StatutInscription::CONFIRMEE);
        $inscription->setPaidAt(new \DateTimeImmutable('2026-05-07 10:00:00'));

        self::assertTrue($inscription->isPaid());
        self::assertSame(StatutInscription::CONFIRMEE, $inscription->getStatutInscr());
        self::assertInstanceOf(\DateTimeImmutable::class, $inscription->getPaidAt());
    }

    public function testPaymentIdentifiersAreTrimmed(): void
    {
        $inscription = $this->createCompleteInscription();

        $inscription->setPaymentGateway(' stripe ');
        $inscription->setPaymentReference(' ref-123 ');
        $inscription->setPaymentOrderId(' order-123 ');
        $inscription->setPaymentStatus(' pending ');

        self::assertSame('stripe', $inscription->getPaymentGateway());
        self::assertSame('ref-123', $inscription->getPaymentReference());
        self::assertSame('order-123', $inscription->getPaymentOrderId());
        self::assertSame('pending', $inscription->getPaymentStatus());
    }

    private function createCompleteInscription(): Inscription
    {
        $inscription = new Inscription();
        $inscription->setDateInscription(new \DateTimeImmutable('2026-05-07 09:00:00'));
        $inscription->setStatutInscr(StatutInscription::EN_ATTENTE);
        $inscription->setMontantTotal('120.00');
        $inscription->setNomUser('Jane Doe');
        $inscription->setNomPack('Pack aventure');
        $inscription->setPack(new Pack());
        $inscription->setUserApp($this->createUser());

        return $inscription;
    }

    private function createUser(): UserApp
    {
        $user = new UserApp();
        $user->setNom('Doe');
        $user->setPrenom('Jane');
        $user->setEmail('jane.pack@example.com');
        $user->setRole(RoleUser::USER_SIMPLE);
        $user->setMot_de_passe('hashed-password');

        return $user;
    }
}
