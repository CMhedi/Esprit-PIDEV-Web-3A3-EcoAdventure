<?php

use App\Service\NutritionApiService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

require_once __DIR__ . '/../vendor/autoload.php';

$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

/** @var NutritionApiService $apiService */
$apiService = $container->get(NutritionApiService::class);

$result = $apiService->getNutritionValues('200g chicken');

echo "RESULT:\n";
print_r($result);
