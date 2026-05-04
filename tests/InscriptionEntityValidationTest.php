<?php

namespace App\Tests;

use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\StatutInscription;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InscriptionEntityValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidationRejectsMissingRequiredInscriptionFields(): void
    {
        $inscription = new Inscription();

        $violations = $this->validator->validate($inscription);
        $messages = array_map(static fn ($violation): string => $violation->getMessage(), iterator_to_array($violations));

        self::assertContains('Le statut est obligatoire.', $messages);
        self::assertContains('Le nom utilisateur est obligatoire.', $messages);
        self::assertContains('Le pack est obligatoire.', $messages);
        self::assertContains('L utilisateur est obligatoire.', $messages);
    }

    public function testValidationAcceptsCompleteInscriptionData(): void
    {
        $inscription = (new Inscription())
            ->setDateInscription(new \DateTimeImmutable())
            ->setStatutInscr(StatutInscription::EN_ATTENTE)
            ->setMontantTotal('120.00')
            ->setNomUser('Alice Ben Ali')
            ->setNomPack('Adventure Pack')
            ->setPack(new Pack())
            ->setUserApp(new UserApp());

        self::assertCount(0, $this->validator->validate($inscription));
    }
}
