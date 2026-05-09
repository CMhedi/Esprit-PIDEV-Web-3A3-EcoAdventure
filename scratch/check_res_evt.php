<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

$dbUrl = $_ENV['DATABASE_URL'] ?? '';
$parsedUrl = parse_url($dbUrl);

$host = $parsedUrl['host'];
$user = $parsedUrl['user'];
$pass = $parsedUrl['pass'] ?? '';
$db = ltrim($parsedUrl['path'], '/');

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("DESCRIBE reservation_evenement");
$columns = [];
while($row = $result->fetch_assoc()) {
    $columns[] = $row;
}
echo json_encode($columns, JSON_PRETTY_PRINT);

$conn->close();
