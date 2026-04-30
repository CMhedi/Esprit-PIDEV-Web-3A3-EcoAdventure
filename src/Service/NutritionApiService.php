<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
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

    // =========================
    // GET NUTRITION VALUES (VERSION JAVA COMPATIBLE)
    // =========================
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

    // =========================
    // PARSE RESPONSE (ROBUSTE)
    // =========================
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

    private function emptyNutrition(): array
    {
        return [
            'calories' => 0,
            'protein' => 0,
            'fat' => 0,
            'carbs' => 0,
        ];
    }

    // =========================
    // SEARCH INGREDIENTS
    // =========================
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

    // =========================
    // GET RECIPE NUTRITION
    // =========================
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

    public function getDailyRecommendations(int $age, string $gender, float $activity): array
    {
        $weight = 70.0;
        $height = 175.0;
        $gender = strtoupper($gender) === 'F' ? 'F' : 'M';

        if ($gender === 'F') {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
        } else {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
        }

        $calories = (int) round($bmr * max(1.2, $activity));
        $protein = (int) round($weight * 1.8);
        $fat = (int) round(($calories * 0.25) / 9);
        $carbs = (int) round(($calories - ($protein * 4 + $fat * 9)) / 4);

        return [
            'calories' => $calories,
            'protein' => max(0, $protein),
            'fat' => max(0, $fat),
            'carbs' => max(0, $carbs),
        ];
    }
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
}
