<?php

namespace App\Tests;

use App\Entity\Evenement;
use App\Entity\ReservationEvenement;
use App\Entity\EventRating;
use App\Entity\UserApp;
use App\Enum\CategorieEvenement;
use App\Enum\StatutReservationEvenement;
use App\Service\EventManager;
use App\Service\AiEventOptimizerService;
use App\Repository\EvenementRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventModuleTest extends TestCase
{
    // =========================================================================
    // 1. TESTS ENTITÉ EVENEMENT (4 TESTS)
    // =========================================================================

    public function testEvenementBasics(): void
    {
        $evenement = new Evenement();
        $evenement->setTitre('Randonnée Nature');
        $evenement->setNbPlaces(100);
        $this->assertEquals('Randonnée Nature', $evenement->getTitre());
        $this->assertEquals(100, $evenement->getNbPlaces());
    }

    public function testEvenementDateValidation(): void
    {
        $evenement = new Evenement();
        $date = new \DateTime('+1 month');
        $evenement->setDateEvent($date);
        $this->assertEquals($date, $evenement->getDateEvent());
    }

    public function testGetPlacesRestantesSansReservation(): void
    {
        $evenement = new Evenement();
        $evenement->setNbPlaces(50);
        $this->assertEquals(50, $evenement->getPlacesRestantes());
    }

    public function testGetPlacesRestantesAvecReservations(): void
    {
        $evenement = new Evenement();
        $evenement->setNbPlaces(100);

        $res = new ReservationEvenement();
        $res->setNb_billets(15);
        $res->setStatutReservationEvenement(StatutReservationEvenement::CONFIRMEE);
        $evenement->addReservationEvenement($res);

        $this->assertEquals(85, $evenement->getPlacesRestantes());
    }

    // =========================================================================
    // 2. TESTS ENTITÉ RESERVATION EVENEMENT (3 TESTS)
    // =========================================================================

    public function testReservationEvenementBasics(): void
    {
        $res = new ReservationEvenement();
        $res->setNb_billets(5);
        $this->assertEquals(5, $res->getNb_billets());
    }

    public function testReservationEvenementStatus(): void
    {
        $res = new ReservationEvenement();
        $res->setStatutReservationEvenement(StatutReservationEvenement::CONFIRMEE);
        $this->assertEquals(StatutReservationEvenement::CONFIRMEE, $res->getStatutReservationEvenement());
    }

    public function testReservationEvenementNbBillets(): void
    {
        $res = new ReservationEvenement();
        $res->setNb_billets(10);
        $this->assertGreaterThan(0, $res->getNb_billets());
    }

    // =========================================================================
    // 3. TESTS ENTITÉ EVENT RATING (2 TESTS)
    // =========================================================================

    public function testEventRatingBasics(): void
    {
        $rating = new EventRating();
        $rating->setNote(5);
        $rating->setCommentaire('Excellent');
        $this->assertEquals(5, $rating->getNote());
        $this->assertEquals('Excellent', $rating->getCommentaire());
    }

    public function testGetAverageRating(): void
    {
        $evenement = new Evenement();
        $this->assertEquals(0.0, $evenement->getAverageRating());
    }

    // =========================================================================
    // 4. TESTS SERVICE EVENT MANAGER (3 TESTS)
    // =========================================================================

    public function testValidateEventRulesSuccess(): void
    {
        $manager = new EventManager();
        $evenement = new Evenement();
        $evenement->setNbPlaces(50);
        $evenement->setLimiteAttente(10);
        $evenement->setPrix(20.0);
        $evenement->setCategorieEvt(CategorieEvenement::NATURE);

        $manager->validateEventRules($evenement);
        $this->assertTrue(true);
    }

    public function testFailsWhenWaitlistExceedsCapacity(): void
    {
        $manager = new EventManager();
        $evenement = new Evenement();
        $evenement->setNbPlaces(50);
        $evenement->setLimiteAttente(60);

        $this->expectException(\LogicException::class);
        $manager->validateEventRules($evenement);
    }

    public function testFailsWhenEventIsFree(): void
    {
        $manager = new EventManager();
        $evenement = new Evenement();
        $evenement->setPrix(0);

        $this->expectException(\LogicException::class);
        $manager->validateEventRules($evenement);
    }

    // =========================================================================
    // 5. TESTS SERVICE AI OPTIMIZER (4 TESTS)
    // =========================================================================

    public function testAiWaitlistNautique(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $repo = $this->createMock(EvenementRepository::class);
        $service = new AiEventOptimizerService($httpClient, $logger, $repo);
        
        $evenement = new Evenement();
        $evenement->setCategorieEvt(CategorieEvenement::NAUTIQUE);
        $evenement->setNbPlaces(10);
        $httpClient->method('request')->willThrowException(new \Exception());

        $this->assertEquals(2, $service->optimizeWaitlistLimit($evenement));
    }

    public function testAiWaitlistStandard(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $repo = $this->createMock(EvenementRepository::class);
        $service = new AiEventOptimizerService($httpClient, $logger, $repo);
        
        $evenement = new Evenement();
        $evenement->setCategorieEvt(CategorieEvenement::NATURE);
        $evenement->setNbPlaces(10);
        $httpClient->method('request')->willThrowException(new \Exception());

        $this->assertEquals(4, $service->optimizeWaitlistLimit($evenement));
    }

    public function testAiYieldLowDemand(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $repo = $this->createMock(EvenementRepository::class);
        $service = new AiEventOptimizerService($httpClient, $logger, $repo);
        
        $evenement = new Evenement();
        $evenement->setPrix(100.0);
        $httpClient->method('request')->willThrowException(new \Exception());

        $result = $service->analyzeYieldManagement($evenement, 5.0);
        $this->assertEquals(100.0, $result['suggested_price']);
    }

    public function testAiYieldHighDemand(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $repo = $this->createMock(EvenementRepository::class);
        $service = new AiEventOptimizerService($httpClient, $logger, $repo);
        
        $evenement = new Evenement();
        $evenement->setPrix(100.0);
        $httpClient->method('request')->willThrowException(new \Exception());

        $result = $service->analyzeYieldManagement($evenement, 1.0);
        $this->assertEquals(115.0, $result['suggested_price']);
    }
}
