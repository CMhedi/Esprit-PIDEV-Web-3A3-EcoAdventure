<?php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=ecoadventure;charset=utf8mb4';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO($dsn, $user, $pass);
    $stmt = $pdo->query('SHOW TABLES LIKE "conversation_user"');
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($tables) > 0) {
        echo 'conversation_user table exists' . PHP_EOL;
        $stmt2 = $pdo->query('DESCRIBE conversation_user');
        $columns = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Null'] . ' - ' . $col['Key'] . ' - ' . $col['Default'] . ' - ' . $col['Extra'] . PHP_EOL;
        }
    } else {
        echo 'conversation_user table does not exist' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>