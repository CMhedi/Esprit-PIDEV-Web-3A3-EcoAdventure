<?php

namespace App\Tests;

use App\Controller\UserSeanceController;
use App\Entity\ReservationActivite;
use App\Entity\Seance;
use App\Enum\StatutReservationActivite;
use App\Repository\ReservationSeanceRepository;
use App\Repository\SeanceRepository;
use App\Repository\UserAppRepository;
use App\Service\GoogleCalendarService;
use App\Service\RecommendationService;
use App\Service\ReservationActiviteManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ActivityReservationTest extends TestCase
{
    // ===== RESERVATION ACTIVITE MANAGER TESTS =====

    public function testReservationActiviteValidateReturnsTrueForACompleteFutureReservation(): void
    {
        $reservation = new ReservationActivite();
        $reservation->setDateRes(new \DateTimeImmutable('+2 days'));
        $reservation->setStatutRes(StatutReservationActivite::CONFIRMEE);
        $reservation->setNbPersonnes(4);

        $manager = new ReservationActiviteManager();

        self::assertTrue($manager->validate($reservation));
    }

    public function testReservationActiviteValidateRejectsPastReservationDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de reservation doit etre aujourd hui ou dans le futur.');

        $reservation = new ReservationActivite();
        $reservation->setDateRes(new \DateTimeImmutable('-1 day'));
        $reservation->setStatutRes(StatutReservationActivite::EN_ATTENTE);
        $reservation->setNbPersonnes(2);

        $manager = new ReservationActiviteManager();
        $manager->validate($reservation);
    }

    public function testReservationActiviteValidateRejectsMissingStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut de reservation est obligatoire.');

        $reservation = new ReservationActivite();
        $reservation->setDateRes(new \DateTimeImmutable('+1 day'));
        $reservation->setStatutRes(null);
        $reservation->setNbPersonnes(2);

        $manager = new ReservationActiviteManager();
        $manager->validate($reservation);
    }

    public function testReservationActiviteValidateRejectsHeadcountOutsideAllowedRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de personnes doit etre entre 1 et 100.');

        $reservation = new ReservationActivite();
        $reservation->setDateRes(new \DateTimeImmutable('+1 day'));
        $reservation->setStatutRes(StatutReservationActivite::CONFIRMEE);
        $reservation->setNbPersonnes(101);

        $manager = new ReservationActiviteManager();
        $manager->validate($reservation);
    }

    // ===== USER SEANCE CONTROLLER AVAILABILITY TESTS =====

    public function testUserSeanceValidateCapacityReturnsTrueWhenSeatsRemain(): void
    {
        $seance = new Seance();
        $seance->setCapacite(5);

        $reservationRepo = $this->createMock(ReservationSeanceRepository::class);
        $reservationRepo->expects(self::once())
            ->method('countReservations')
            ->with(self::identicalTo($seance))
            ->willReturn(3);

        $controller = $this->createUserSeanceController($reservationRepo);

        self::assertTrue($this->invokePrivateMethod($controller, 'validateCapacity', [$seance]));
    }

    public function testUserSeanceValidateCapacityReturnsFalseWhenSeanceIsFull(): void
    {
        $seance = new Seance();
        $seance->setCapacite(5);

        $reservationRepo = $this->createMock(ReservationSeanceRepository::class);
        $reservationRepo->expects(self::once())
            ->method('countReservations')
            ->with(self::identicalTo($seance))
            ->willReturn(5);

        $controller = $this->createUserSeanceController($reservationRepo);

        self::assertFalse($this->invokePrivateMethod($controller, 'validateCapacity', [$seance]));
    }

    public function testUserSeanceValidateCapacityReturnsFalseWhenReservationsExceedCapacity(): void
    {
        $seance = new Seance();
        $seance->setCapacite(5);

        $reservationRepo = $this->createMock(ReservationSeanceRepository::class);
        $reservationRepo->expects(self::once())
            ->method('countReservations')
            ->with(self::identicalTo($seance))
            ->willReturn(7);

        $controller = $this->createUserSeanceController($reservationRepo);

        self::assertFalse($this->invokePrivateMethod($controller, 'validateCapacity', [$seance]));
    }

    // ===== HELPER METHODS =====

    private function createUserSeanceController(ReservationSeanceRepository $reservationRepo): UserSeanceController
    {
        return new UserSeanceController(
            $this->createStub(LoggerInterface::class),
            $reservationRepo,
            $this->createStub(SeanceRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(UserAppRepository::class),
            $this->createMock(RecommendationService::class),
            $this->createMock(GoogleCalendarService::class),
        );
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokePrivateMethod(object $instance, string $methodName, array $arguments): mixed
    {
        $method = new \ReflectionMethod($instance, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($instance, $arguments);
    }
}
