<?php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Service\ReservationManager;
use PHPUnit\Framework\TestCase;

class ReservationManagerTest extends TestCase
{
    private ReservationManager $reservationManager;

    protected function setUp(): void
    {
        $this->reservationManager = new ReservationManager();
    }

    public function testValidateReservationDemandeSuccess(): void
    {
        $evenement = new Evenement();
        // Événement dans le futur
        $evenement->setDateEvent(new \DateTime('+1 month'));
        $evenement->setNbPlaces(50);
        
        // La validation ne doit lever aucune exception
        $this->reservationManager->validateReservationDemande($evenement, 2);
        $this->assertTrue(true);
    }

    public function testValidateReservationDemandeFailsForPastEvent(): void
    {
        $evenement = new Evenement();
        // Événement dans le passé
        $evenement->setDateEvent(new \DateTime('-1 day'));
        $evenement->setNbPlaces(50);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Impossible de réserver pour un événement déjà passé.");

        $this->reservationManager->validateReservationDemande($evenement, 2);
    }

    public function testValidateReservationDemandeFailsForInsufficientPlaces(): void
    {
        $evenement = new Evenement();
        $evenement->setDateEvent(new \DateTime('+1 month'));
        $evenement->setNbPlaces(5); // Seulement 5 places disponibles

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Il ne reste que 5 place(s).");

        // On demande 6 places, cela doit échouer
        $this->reservationManager->validateReservationDemande($evenement, 6);
    }
}
