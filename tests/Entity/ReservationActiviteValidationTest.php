<?php

namespace App\Tests\Entity;

use App\Entity\ReservationActivite;
use App\Enum\StatutReservationActivite;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ReservationActiviteValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidationRejectsPastDateMissingStatusAndOutOfRangeHeadcount(): void
    {
        $reservation = new ReservationActivite();
        $reservation->setDateRes(new \DateTimeImmutable('-1 day'));
        $reservation->setStatutRes(null);
        $reservation->setNbPersonnes(101);

        $violations = $this->validator->validate($reservation);
        $messages = array_map(static fn ($violation): string => $violation->getMessage(), iterator_to_array($violations));

        self::assertContains('La date de reservation doit etre aujourd hui ou dans le futur.', $messages);
        self::assertContains('Le statut de reservation est obligatoire.', $messages);
        self::assertContains('Le nombre de personnes doit etre entre 1 et 100.', $messages);
    }

    public function testValidationAcceptsACompleteFutureReservation(): void
    {
        $reservation = new ReservationActivite();
        $reservation->setDateRes(new \DateTimeImmutable('+2 days'));
        $reservation->setStatutRes(StatutReservationActivite::CONFIRMEE);
        $reservation->setNbPersonnes(4);

        self::assertCount(0, $this->validator->validate($reservation));
    }
}
