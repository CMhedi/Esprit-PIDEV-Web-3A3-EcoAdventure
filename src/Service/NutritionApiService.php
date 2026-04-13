<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Psr\Log\LoggerInterface;

class NutritionApiService
{
    private const API_KEY = '5cdb0a7de4b6405fb5a0e5450eaf6961';
    private const API_BASE_URL = 'https://api.spoonacular.com';
    private const CACHE_EXPIRATION = 86400; // 24 heures

    private HttpClientInterface $httpClient;
    private FilesystemAdapter $cache;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->cache = new FilesystemAdapter('nutrition_api', 86400);
    }

    // ===== GET NUTRITION VALUES =====
    /**
     * Récupère les valeurs nutritionnelles d'un ingrédient
     * 
     * @param string $ingredient Nom de l'ingrédient (ex: "banana", "chicken breast")
     * @return array Tableau [calories, protein, fat, carbs]
     * @throws \Exception
     */
    public function getNutritionValues(string $ingredient): array
    {
        try {
            // ===== VALIDATION =====
            if (empty($ingredient)) {
                throw new \InvalidArgumentException('L\'ingrédient ne peut pas être vide');
            }

            $ingredient = trim($ingredient);

            // ===== VÉRIFIER LE CACHE =====
            $cacheKey = 'nutrition_' . md5(strtolower($ingredient));
            $cachedItem = $this->cache->getItem($cacheKey);

            if ($cachedItem->isHit()) {
                $this->logger->info("Nutrition trouvée en cache: $ingredient");
                return $cachedItem->get();
            }

            // ===== APPEL API =====
            $this->logger->info("Appel API pour: $ingredient");

            $response = $this->httpClient->request('POST', 
                self::API_BASE_URL . '/recipes/parseIngredients',
                [
                    'query' => [
                        'apiKey' => self::API_KEY,
                    ],
                    'body' => http_build_query([
                        'ingredientList' => $ingredient,
                        'servings' => 1,
                        'includeNutrition' => 'true'
                    ]),
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'timeout' => 10,
                ]
            );

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new \Exception("Erreur API: HTTP $statusCode");
            }

            $responseData = json_decode($response->getContent(), true);

            // ===== PARSER LA RÉPONSE =====
            $nutrition = $this->parseNutritionResponse($responseData, $ingredient);

            // ===== CACHE LE RÉSULTAT =====
            $cachedItem->set($nutrition);
            $cachedItem->expiresAfter(self::CACHE_EXPIRATION);
            $this->cache->save($cachedItem);

            $this->logger->info("Nutrition pour $ingredient: " . json_encode($nutrition));

            return $nutrition;

        } catch (\Exception $e) {
            $this->logger->error("Erreur nutrition: " . $e->getMessage());
            throw $e;
        }
    }

    // ===== PARSER LA RÉPONSE API =====
    /**
     * Parse la réponse JSON de l'API Spoonacular
     */
    private function parseNutritionResponse(array $responseData, string $ingredient): array
    {
        if (empty($responseData)) {
            throw new \Exception("Aucun résultat trouvé pour: $ingredient");
        }

        $firstResult = $responseData[0];

        if (!isset($firstResult['nutrition']['nutrients'])) {
            throw new \Exception("Structure de réponse invalide");
        }

        $nutrients = $firstResult['nutrition']['nutrients'];

        $nutrition = [
            'calories' => 0,
            'protein' => 0,
            'fat' => 0,
            'carbs' => 0,
            'fiber' => 0,
            'sugar' => 0
        ];

        foreach ($nutrients as $nutrient) {
            $name = $nutrient['name'] ?? '';
            $amount = (float)($nutrient['amount'] ?? 0);

            switch ($name) {
                case 'Calories':
                    $nutrition['calories'] = round($amount, 2);
                    break;
                case 'Protein':
                    $nutrition['protein'] = round($amount, 2);
                    break;
                case 'Fat':
                    $nutrition['fat'] = round($amount, 2);
                    break;
                case 'Carbohydrates':
                    $nutrition['carbs'] = round($amount, 2);
                    break;
                case 'Fiber':
                    $nutrition['fiber'] = round($amount, 2);
                    break;
                case 'Sugar':
                    $nutrition['sugar'] = round($amount, 2);
                    break;
            }
        }

        return $nutrition;
    }

    // ===== SEARCH INGREDIENTS =====
    /**
     * Cherche des ingrédients
     */
    public function searchIngredients(string $query, int $number = 10): array
    {
        try {
            if (empty($query)) {
                throw new \InvalidArgumentException('La requête ne peut pas être vide');
            }

            $cacheKey = 'search_' . md5(strtolower($query));
            $cachedItem = $this->cache->getItem($cacheKey);

            if ($cachedItem->isHit()) {
                return $cachedItem->get();
            }

            $response = $this->httpClient->request('GET',
                self::API_BASE_URL . '/food/ingredients/search',
                [
                    'query' => [
                        'query' => $query,
                        'number' => $number,
                        'apiKey' => self::API_KEY,
                    ],
                ]
            );

            $data = json_decode($response->getContent(), true);
            $results = $data['results'] ?? [];

            $cachedItem->set($results);
            $cachedItem->expiresAfter(self::CACHE_EXPIRATION);
            $this->cache->save($cachedItem);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error("Erreur recherche: " . $e->getMessage());
            return [];
        }
    }

    // ===== GET RECIPE NUTRITION =====
    /**
     * Récupère les infos nutritionnelles d'une recette
     */
    public function getRecipeNutrition(int $recipeId): array
    {
        try {
            $cacheKey = 'recipe_' . $recipeId;
            $cachedItem = $this->cache->getItem($cacheKey);

            if ($cachedItem->isHit()) {
                return $cachedItem->get();
            }

            $response = $this->httpClient->request('GET',
                self::API_BASE_URL . '/recipes/' . $recipeId . '/nutritionWidget.json',
                [
                    'query' => [
                        'apiKey' => self::API_KEY,
                    ],
                ]
            );

            $data = json_decode($response->getContent(), true);

            $nutrition = [
                'calories' => $data['calories'] ?? 0,
                'carbs' => $data['carbs'] ?? '0g',
                'fat' => $data['fat'] ?? '0g',
                'protein' => $data['protein'] ?? '0g',
            ];

            $cachedItem->set($nutrition);
            $cachedItem->expiresAfter(self::CACHE_EXPIRATION);
            $this->cache->save($cachedItem);

            return $nutrition;

        } catch (\Exception $e) {
            $this->logger->error("Erreur recette: " . $e->getMessage());
            return [];
        }
    }

    // ===== GET NUTRIENTS INFO =====
    /**
     * Obtient des infos détaillées sur un nutriment
     */
    public function getNutrientInfo(string $nutrientName): array
    {
        $nutrientsDatabase = [
            'Calories' => [
                'description' => 'Unité d\'énergie fournie par la nourriture',
                'dailyValue' => 2000,
                'unit' => 'kcal',
                'importance' => 'critique'
            ],
            'Protein' => [
                'description' => 'Acides aminés essentiels',
                'dailyValue' => 50,
                'unit' => 'g',
                'importance' => 'haute'
            ],
            'Fat' => [
                'description' => 'Lipides et acides gras',
                'dailyValue' => 78,
                'unit' => 'g',
                'importance' => 'haute'
            ],
            'Carbohydrates' => [
                'description' => 'Glucides (sucres et amidon)',
                'dailyValue' => 275,
                'unit' => 'g',
                'importance' => 'haute'
            ],
            'Fiber' => [
                'description' => 'Fibres alimentaires',
                'dailyValue' => 28,
                'unit' => 'g',
                'importance' => 'moyenne'
            ],
            'Sugar' => [
                'description' => 'Sucres simples',
                'dailyValue' => 50,
                'unit' => 'g',
                'importance' => 'moyenne'
            ],
        ];

        return $nutrientsDatabase[$nutrientName] ?? [
            'description' => 'Nutriment inconnu',
            'dailyValue' => 0,
            'unit' => 'g',
            'importance' => 'inconnue'
        ];
    }

    // ===== COMPARE INGREDIENTS =====
    /**
     * Compare deux ingrédients
     */
    public function compareIngredients(string $ingredient1, string $ingredient2): array
    {
        try {
            $nutrition1 = $this->getNutritionValues($ingredient1);
            $nutrition2 = $this->getNutritionValues($ingredient2);

            return [
                'ingredient1' => [
                    'name' => $ingredient1,
                    'nutrition' => $nutrition1
                ],
                'ingredient2' => [
                    'name' => $ingredient2,
                    'nutrition' => $nutrition2
                ],
                'comparison' => [
                    'calories_diff' => $nutrition1['calories'] - $nutrition2['calories'],
                    'protein_diff' => $nutrition1['protein'] - $nutrition2['protein'],
                    'fat_diff' => $nutrition1['fat'] - $nutrition2['fat'],
                    'carbs_diff' => $nutrition1['carbs'] - $nutrition2['carbs'],
                    'healthier' => $this->determineHealthier($nutrition1, $nutrition2)
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error("Erreur comparaison: " . $e->getMessage());
            return [];
        }
    }

    // ===== DETERMINE HEALTHIER =====
    /**
     * Détermine quel ingrédient est plus sain
     */
    private function determineHealthier(array $nutrition1, array $nutrition2): string
    {
        $score1 = ($nutrition1['protein'] * 4) - ($nutrition1['fat'] * 0.5) - ($nutrition1['sugar'] * 2);
        $score2 = ($nutrition2['protein'] * 4) - ($nutrition2['fat'] * 0.5) - ($nutrition2['sugar'] * 2);

        return $score1 > $score2 ? 'ingredient1' : 'ingredient2';
    }

    // ===== CLEAR CACHE =====
    /**
     * Vide le cache
     */
    public function clearCache(): void
    {
        $this->cache->clear();
        $this->logger->info("Cache nutrition vidé");
    }

    // ===== VALIDATE INGREDIENT =====
    /**
     * Valide si un ingrédient existe
     */
    public function validateIngredient(string $ingredient): bool
    {
        try {
            $results = $this->searchIngredients($ingredient, 1);
            return !empty($results);
        } catch (\Exception $e) {
            $this->logger->error("Erreur validation: " . $e->getMessage());
            return false;
        }
    }

    // ===== GET DAILY RECOMMENDATIONS =====
    /**
     * Obtient les recommandations journalières
     */
    public function getDailyRecommendations(int $age, string $gender, float $activity = 1.5): array
    {
        $isMale = strtoupper($gender) === 'M';

        // Besoins énergétiques de base
        $baseCalories = $isMale ? 2500 : 2000;
        $calories = round($baseCalories * $activity);

        // Répartition macros (40/30/30)
        $carbsCalories = $calories * 0.40;
        $proteinCalories = $calories * 0.30;
        $fatCalories = $calories * 0.30;

        return [
            'calories' => $calories,
            'protein' => round($proteinCalories / 4), // 1g protein = 4 cal
            'carbs' => round($carbsCalories / 4), // 1g carbs = 4 cal
            'fat' => round($fatCalories / 9), // 1g fat = 9 cal
            'fiber' => $age > 50 ? 21 : 25,
            'sugar_max' => 50,
        ];
    }
}