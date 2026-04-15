<?php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=ecoadventure;charset=utf8mb4';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO($dsn, $user, $pass);

    // Get all conversations
    $stmt = $pdo->query('SELECT id_conversation, titre, id_createur FROM conversation WHERE est_groupe = 1');
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conversations as $conv) {
        $convId = $conv['id_conversation'];
        $titre = $conv['titre'];
        $createurId = $conv['id_createur'];

        // Add creator as participant
        $pdo->prepare('INSERT IGNORE INTO conversation_user (conversation_id_conversation, user_app_id_user) VALUES (?, ?)')->execute([$convId, $createurId]);

        // Parse participants from title
        if (strpos($titre, 'Ahmed MonNom & Ben Salem Sami') !== false) {
            // Add Sami (id 2)
            $pdo->prepare('INSERT IGNORE INTO conversation_user (conversation_id_conversation, user_app_id_user) VALUES (?, ?)')->execute([$convId, 2]);
        }
        if (strpos($titre, 'Ahmed MonNom & Gharbi Yassine') !== false) {
            // Add Yassine (id 4)
            $pdo->prepare('INSERT IGNORE INTO conversation_user (conversation_id_conversation, user_app_id_user) VALUES (?, ?)')->execute([$convId, 4]);
        }
    }

    echo 'Conversation participants populated successfully' . PHP_EOL;

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>