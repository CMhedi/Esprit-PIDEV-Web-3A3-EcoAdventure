<?php
namespace App\Tests\Service;

use App\Entity\ReservationSeance;
use App\Service\ReservationManager;
use App\Enum\StatutReservation;
use PHPUnit\Framework\TestCase;

class ReservationManagerTest extends TestCase
{
    public function testReservationValide(): void
    {
        $reservation = new ReservationSeance();

        $reservation->setStatut(StatutReservation::CONFIRMEE);
        $reservation->setDate_reservation(new \DateTime());

        $manager = new ReservationManager();

        $this->assertTrue($manager->validate($reservation, 5, 10));
    }

    public function testSeanceComplete(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $reservation = new ReservationSeance();
        $reservation->setStatut(StatutReservation::CONFIRMEE);
        $reservation->setDate_reservation(new \DateTime());

        (new ReservationManager())->validate($reservation, 10, 10);
    }

    public function testReservationSansDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $reservation = new ReservationSeance();
        $reservation->setStatut(StatutReservation::CONFIRMEE);

        (new ReservationManager())->validate($reservation, 5, 10);
    }
}