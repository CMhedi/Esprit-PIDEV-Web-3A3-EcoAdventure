<?php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Enum\CategorieEvenement;
use App\Service\EventManager;
use PHPUnit\Framework\TestCase;

class EventManagerTest extends TestCase
{
    private EventManager $eventManager;

    protected function setUp(): void
    {
        $this->eventManager = new EventManager();
    }

    public function testValidateEventRulesSuccess(): void
    {
        $evenement = new Evenement();
        $evenement->setNbPlaces(100);
        $evenement->setLimiteAttente(20); // 20 <= 100, OK
        $evenement->setCategorieEvt(CategorieEvenement::NATURE); 
        $evenement->setPrix(15.0); // Le prix doit désormais être > 0

        // Aucune exception ne doit être levée
        $this->eventManager->validateEventRules($evenement);
        $this->assertTrue(true);
    }

    public function testFailsWhenWaitlistExceedsCapacity(): void
    {
        $evenement = new Evenement();
        $evenement->setNbPlaces(50);
        $evenement->setLimiteAttente(60); // 60 > 50, ERREUR

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("La limite de la liste d'attente ne peut pas dépasser le nombre total de places.");

        $this->eventManager->validateEventRules($evenement);
    }

    public function testFailsWhenEventIsFree(): void
    {
        $evenement = new Evenement();
        $evenement->setNbPlaces(100);
        $evenement->setLimiteAttente(10);
        $evenement->setCategorieEvt(CategorieEvenement::NATURE);
        $evenement->setPrix(0); // Gratuit, ERREUR

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Un événement ne peut pas être gratuit (le prix doit être supérieur à 0).");

        $this->eventManager->validateEventRules($evenement);
    }
}
