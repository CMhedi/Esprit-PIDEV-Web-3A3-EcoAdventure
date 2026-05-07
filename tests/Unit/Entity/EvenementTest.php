<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Evenement;
use App\Entity\EventRating;
use App\Entity\ReservationEvenement;
use App\Enum\CategorieEvenement;
use App\Enum\StatutReservationEvenement;
use PHPUnit\Framework\TestCase;

class EvenementTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $evenement = new Evenement();
        
        $evenement->setTitre('Randonnée test');
        $this->assertEquals('Randonnée test', $evenement->getTitre());
        
        $evenement->setDescription('Une description');
        $this->assertEquals('Une description', $evenement->getDescription());
        
        $date = new \DateTime('+1 week');
        $evenement->setDateEvent($date);
        $this->assertEquals($date, $evenement->getDateEvent());
        
        $evenement->setCategorieEvt(CategorieEvenement::NATURE);
        $this->assertEquals(CategorieEvenement::NATURE, $evenement->getCategorieEvt());
        
        $evenement->setNbPlaces(50);
        $this->assertEquals(50, $evenement->getNbPlaces());
        
        $evenement->setPrix(25.5);
        $this->assertEquals(25.5, $evenement->getPrix());
        
        $evenement->setLieu('Forêt');
        $this->assertEquals('Forêt', $evenement->getLieu());
        
        $evenement->setLimiteAttente(10);
        $this->assertEquals(10, $evenement->getLimiteAttente());
    }

    public function testGetPlacesRestantesSansReservation(): void
    {
        $evenement = new Evenement();
        $evenement->setNbPlaces(100);
        
        $this->assertEquals(100, $evenement->getPlacesRestantes());
    }

    public function testGetPlacesRestantesAvecReservations(): void
    {
        $evenement = new Evenement();
        $evenement->setNbPlaces(100);

        // Réservation confirmée
        $res1 = new ReservationEvenement();
        $res1->setNb_billets(2);
        $res1->setStatut_res(StatutReservationEvenement::CONFIRMEE);
        $evenement->addReservationEvenement($res1);

        // Réservation en attente (elle prend des places dans la logique actuelle)
        $res2 = new ReservationEvenement();
        $res2->setNb_billets(3);
        $res2->setStatut_res(StatutReservationEvenement::EN_ATTENTE);
        $evenement->addReservationEvenement($res2);
        
        // Réservation annulée (elle ne prend pas de places)
        $res3 = new ReservationEvenement();
        $res3->setNb_billets(5);
        $res3->setStatut_res(StatutReservationEvenement::ANNULEE);
        $evenement->addReservationEvenement($res3);
        
        // Liste d'attente (ne prend pas de places)
        $res4 = new ReservationEvenement();
        $res4->setNb_billets(1);
        $res4->setStatut_res(StatutReservationEvenement::LISTE_ATTENTE);
        $evenement->addReservationEvenement($res4);

        // Places restantes = 100 - (2 + 3) = 95
        $this->assertEquals(95, $evenement->getPlacesRestantes());
    }

    public function testGetAverageRatingWithoutRatings(): void
    {
        $evenement = new Evenement();
        $this->assertEquals(0.0, $evenement->getAverageRating());
    }
}
