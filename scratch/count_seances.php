<?php

require_once 'vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use App\Entity\Seance;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

echo "Counting seances...\n";
$count = $em->getRepository(Seance::class)->count([]);
echo "Total seances: $count\n";

if ($count > 0) {
    $first = $em->getRepository(Seance::class)->findOneBy([]);
    echo "First seance: " . $first->getNom() . " (Statut: " . ($first->getStatutSeance() ? $first->getStatutSeance()->value : 'NULL') . ")\n";
}

echo "Check complete.\n";
