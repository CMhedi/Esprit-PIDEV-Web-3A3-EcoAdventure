<?php

namespace App\Tests\Unit\Service;

use App\Entity\Evenement;
use App\Enum\CategorieEvenement;
use App\Repository\EvenementRepository;
use App\Service\AiEventOptimizerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AiEventOptimizerServiceTest extends TestCase
{
    private $httpClientMock;
    private $loggerMock;
    private $repositoryMock;
    private AiEventOptimizerService $service;

    protected function setUp(): void
    {
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->repositoryMock = $this->createMock(EvenementRepository::class);

        $this->service = new AiEventOptimizerService(
            $this->httpClientMock,
            $this->loggerMock,
            $this->repositoryMock
        );
    }

    public function testOptimizeWaitlistLimitFallbackNautique(): void
    {
        $evenement = new Evenement();
        $evenement->setCategorieEvt(CategorieEvenement::NAUTIQUE);
        $evenement->setNbPlaces(10);

        // Simulate API failure
        $this->httpClientMock->method('request')->willThrowException(new \Exception('Connection refused'));

        $limit = $this->service->optimizeWaitlistLimit($evenement);
        
        // Fallback for NAUTIQUE is 2
        $this->assertEquals(2, $limit);
    }

    public function testOptimizeWaitlistLimitFallbackOther(): void
    {
        $evenement = new Evenement();
        $evenement->setCategorieEvt(CategorieEvenement::NATURE);
        $evenement->setNbPlaces(10);

        // Simulate API failure
        $this->httpClientMock->method('request')->willThrowException(new \Exception('Connection refused'));

        $limit = $this->service->optimizeWaitlistLimit($evenement);
        
        // Fallback for other is 40% of 10 => 4
        $this->assertEquals(4, $limit);
    }

    public function testAnalyzeYieldManagementFallbackLowDemand(): void
    {
        $evenement = new Evenement();
        $evenement->setPrix(100.0);

        // Simulate API failure
        $this->httpClientMock->method('request')->willThrowException(new \Exception('Connection refused'));

        $result = $this->service->analyzeYieldManagement($evenement, 5.0); // 5 hours > 2
        
        $this->assertEquals(100.0, $result['suggested_price']);
        $this->assertEquals('Demande standard.', $result['admin_alert']);
    }

    public function testAnalyzeYieldManagementFallbackHighDemand(): void
    {
        $evenement = new Evenement();
        $evenement->setPrix(100.0);

        // Simulate API failure
        $this->httpClientMock->method('request')->willThrowException(new \Exception('Connection refused'));

        $result = $this->service->analyzeYieldManagement($evenement, 1.0); // 1 hour < 2
        
        $this->assertEquals(115.0, $result['suggested_price']);
        $this->assertStringContainsString('ALERTE DEMANDE FORTE', $result['admin_alert']);
    }

    public function testGetWeatherAiAlertFallbackBadWeather(): void
    {
        $evenement = new Evenement();
        $evenement->setTitre('Montagne Expedition');

        // Simulate API failure
        $this->httpClientMock->method('request')->willThrowException(new \Exception('Connection refused'));

        // Canicule > 35
        $result = $this->service->getWeatherAiAlert(['icon' => '01d', 'temp' => 40, 'description' => 'Ensoleillé'], $evenement);
        $this->assertEquals('danger', $result['type']);
        $this->assertStringContainsString('Alerte Canicule', $result['message']);

        // Glacial < 5
        $result = $this->service->getWeatherAiAlert(['icon' => '01d', 'temp' => -2, 'description' => 'Froid'], $evenement);
        $this->assertEquals('danger', $result['type']);
        $this->assertStringContainsString('Températures glaciales', $result['message']);
    }
}
