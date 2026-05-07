<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Psr\Log\LoggerInterface;

class NutritionApiService
{
    private string $apiKey;
    private const API_BASE_URL = 'https://api.spoonacular.com';

    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;

        // 🔥 API KEY
        $this->apiKey = '5cdb0a7de4b6405fb5a0e5450eaf6961';
    }

  /**
 * @return array{
 *     calories: float|int,
 *     protein: float|int,
 *     fat: float|int,
 *     carbs: float|int
 * }
 */
    public function getNutritionValues(string $ingredient): array
    {
        try {
            if (empty($ingredient)) {
                throw new \InvalidArgumentException("Ingrédient vide");
            }

            $ingredient = trim(strtolower($ingredient));

            // Ajouter "1" si pas présent
            if (!preg_match('/^\d/', $ingredient)) {
                $ingredient = '1 ' . $ingredient;
            }

            $this->logger->info("🌐 API CALL: $ingredient");

            // 🔥 EXACTEMENT comme Java
            $body = "ingredientList=$ingredient&servings=1&includeNutrition=true";

            $response = $this->httpClient->request(
                'POST',
                self::API_BASE_URL . '/recipes/parseIngredients?apiKey=' . $this->apiKey,
                [
                    'body' => $body,
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                ]
            );

            $content = $response->getContent();

            // 🔍 DEBUG IMPORTANT
            $this->logger->info("RAW RESPONSE: " . $content);

            $data = json_decode($content, true);

            if (empty($data)) {
                throw new \Exception("Réponse API vide");
            }

            return $this->parseNutritionResponse($data);

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur Nutrition: " . $e->getMessage());

            return [
                'calories' => 0,
                'protein' => 0,
                'fat' => 0,
                'carbs' => 0,
            ];
        }
    }

 /**
 * @param array<int, array<string, mixed>> $data
 * @return array{
 *     calories: float,
 *     protein: float,
 *     fat: float,
 *     carbs: float
 * }
 */
    private function parseNutritionResponse(array $data): array
    {
        if (!isset($data[0]['nutrition']['nutrients'])) {
            $this->logger->warning("⚠️ Pas de nutrition dans réponse");
            return $this->emptyNutrition();
        }

        $nutrients = $data[0]['nutrition']['nutrients'];

        $nutrition = [
            'calories' => 0,
            'protein' => 0,
            'fat' => 0,
            'carbs' => 0,
        ];

        foreach ($nutrients as $n) {
            $name = strtolower($n['name'] ?? '');
            $amount = (float)($n['amount'] ?? 0);

            if (str_contains($name, 'calorie')) {
                $nutrition['calories'] = $amount;
            } elseif (str_contains($name, 'protein')) {
                $nutrition['protein'] = $amount;
            } elseif (str_contains($name, 'fat')) {
                $nutrition['fat'] = $amount;
            } elseif (str_contains($name, 'carbohydrate') || str_contains($name, 'carb')) {
                $nutrition['carbs'] = $amount;
            }
        }

        return $nutrition;
    }
/**
 * @return array{
 *     calories: int,
 *     protein: int,
 *     fat: int,
 *     carbs: int
 * }
 */
    private function emptyNutrition(): array
    {
        return [
            'calories' => 0,
            'protein' => 0,
            'fat' => 0,
            'carbs' => 0,
        ];
    }

    /**
 * @return array<int, array<string, mixed>>
 */
    public function searchIngredients(string $query, int $number = 10): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                self::API_BASE_URL . '/food/ingredients/search',
                [
                    'query' => [
                        'query' => $query,
                        'number' => $number,
                        'apiKey' => $this->apiKey,
                    ],
                ]
            );

            $data = json_decode($response->getContent(), true);

            return $data['results'] ?? [];

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur recherche: " . $e->getMessage());
            return [];
        }
    }

/**
 * @return array{
 *     calories: mixed,
 *     carbs: mixed,
 *     fat: mixed,
 *     protein: mixed
 * }
 */
    public function getRecipeNutrition(int $recipeId): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                self::API_BASE_URL . "/recipes/$recipeId/nutritionWidget.json",
                [
                    'query' => [
                        'apiKey' => $this->apiKey,
                    ],
                ]
            );

            $data = json_decode($response->getContent(), true);

            return [
                'calories' => $data['calories'] ?? 0,
                'carbs' => $data['carbs'] ?? 0,
                'fat' => $data['fat'] ?? 0,
                'protein' => $data['protein'] ?? 0,
            ];

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur recette: " . $e->getMessage());

            return $this->emptyNutrition();
        }
    }

    /**
 * @return array{
 *     ingredient1: array{
 *         name: string,
 *         nutrition: array{
 *             calories: float|int,
 *             protein: float|int,
 *             fat: float|int,
 *             carbs: float|int
 *         }
 *     },
 *     ingredient2: array{
 *         name: string,
 *         nutrition: array{
 *             calories: float|int,
 *             protein: float|int,
 *             fat: float|int,
 *             carbs: float|int
 *         }
 *     },
 *     comparison: array{
 *         calories_diff: float|int,
 *         protein_diff: float|int,
 *         fat_diff: float|int,
 *         carbs_diff: float|int,
 *         healthier: string
 *     }
 * }
 */
    public function compareIngredients(string $ing1, string $ing2): array
{
    $nut1 = $this->getNutritionValues($ing1);
    $nut2 = $this->getNutritionValues($ing2);

    return [
        'ingredient1' => [
            'name' => $ing1,
            'nutrition' => $nut1
        ],
        'ingredient2' => [
            'name' => $ing2,
            'nutrition' => $nut2
        ],
        'comparison' => [
            'calories_diff' => $nut1['calories'] - $nut2['calories'],
            'protein_diff' => $nut1['protein'] - $nut2['protein'],
            'fat_diff' => $nut1['fat'] - $nut2['fat'],
            'carbs_diff' => $nut1['carbs'] - $nut2['carbs'],
            'healthier' => $nut1['calories'] < $nut2['calories'] ? 'ingredient1' : 'ingredient2'
        ]
    ];
}
/**
 * @return array{
 *     calories: int,
 *     protein: int,
 *     fat: int,
 *     carbs: int
 * }
 */
public function getDailyRecommendations(int $age, string $gender, float $activity): array
{
    return [
        'calories' => 2000,
        'protein' => 150,
        'fat' => 70,
        'carbs' => 250
    ];
}
}