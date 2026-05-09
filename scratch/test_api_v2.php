<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Kernel;
use App\Service\NutritionApiService;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
// Si le service est privé, on utilise le container de test ou on le récupère via le type-hinting si possible
// Mais en dev, on peut souvent y accéder via le container spécial
$apiService = $container->get(NutritionApiService::class);

$ingredient = "200g chicken";
echo "Testing API with: $ingredient\n";

try {
    $result = $apiService->getNutritionValues($ingredient);
    echo "RESULT:\n";
    print_r($result);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
