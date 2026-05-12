<?php

require_once 'vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$conn = $em->getConnection();

echo "Checking columns for table 'seance'...\n";

try {
    $columns = $conn->createSchemaManager()->listTableColumns('seance');
    foreach ($columns as $column) {
        echo "- " . $column->getName() . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Check complete.\n";
