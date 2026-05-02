<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * 🚀 Service Churn - Simplifié et Direct
 */
class ChurnService
{
    private const API_TIMEOUT = 15;
    private const MAX_RETRIES = 3;
    
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $mlApiUrl = 'http://127.0.0.1:8001'
    ) {}
    
/**
 * @param array<string, float|int> $features
 * @return array{
 *     churn: int,
 *     probability: float
 * }
 */
public function predictUser(array $features): array
    {
        try {
            $this->logger->info("📊 Prédiction utilisateur");
            
            return $this->callApi('POST', '/predict', [
                'json' => $features
            ]);
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur prédiction: {$e->getMessage()}");
            throw $e;
        }
    }
    
 /**
 * @param array<int, array<string, float|int>> $usersData
 * @return array{
 *     count?: int,
 *     total?: int,
 *     predictions: array<int, array{
 *         churn: int,
 *         probability: float
 *     }>
 * }
 */
   public function predictBatch(array $usersData): array
{
    try {
        if (empty($usersData)) {
            throw new \InvalidArgumentException("Données vides");
        }

        $this->logger->info("🔄 Batch: " . count($usersData) . " utilisateurs");

        $response = $this->callApi('POST', '/predict/batch', [
            'json' => ['users' => $usersData]
        ]);

        // ✅ FIX ICI
        $count = $response['count'] ?? 0;

        $this->logger->info("✅ Batch complété: {$count} prédictions");

        return $response;

    } catch (\Exception $e) {
        $this->logger->error("❌ Erreur batch: {$e->getMessage()}");
        throw $e;
    }
}
    
    /**
     * 🔥 Vérifier si l'API ML est accessible
     * 
     * @return bool
     */
    public function isHealthy(): bool
    {
        try {
            $response = $this->callApi('GET', '/health');
            $isHealthy = ($response['status'] ?? null) === 'healthy'
    && ($response['model_loaded'] ?? false) === true;
            
            if ($isHealthy) {
                $this->logger->info("✅ API ML opérationnelle");
            } else {
                $this->logger->warning("⚠️ API ML non-healthy");
            }
            
            return $isHealthy;
        } catch (\Exception $e) {
            $this->logger->warning("⚠️ API inaccessible: {$e->getMessage()}");
            return false;
        }
    }
    
    // ==================== PRIVATE METHODS ====================
    
/**
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
    private function callApi(string $method, string $endpoint, array $options = []): array
    {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < self::MAX_RETRIES) {
            try {
                $options['timeout'] = self::API_TIMEOUT;
                
                $response = $this->httpClient->request(
                    $method,
                    $this->mlApiUrl . $endpoint,
                    $options
                );
                
                $data = $response->toArray();
                
                if ($attempt > 0) {
                    $this->logger->info("✅ Reconnecté après {$attempt} tentative(s)");
                }
                
                return $data;
                
            } catch (\Exception $e) {
                $lastError = $e;
                $attempt++;
                
                if ($attempt < self::MAX_RETRIES) {
                    $waitMs = pow(2, $attempt) * 500; // Backoff exponentiel: 1s, 2s, 4s
                    $this->logger->warning("⚠️ Tentative {$attempt} échouée, nouvelle tentative dans {$waitMs}ms");
                    usleep($waitMs * 1000);
                }
            }
        }
        
        throw new \RuntimeException("API échouée après {$attempt} tentatives: " . $lastError->getMessage());
    }
}