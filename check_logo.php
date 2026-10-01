<?php
require __DIR__ . '/config/config.php';
$db = App\Core\Database::getInstance()->getConnection();
$stmt = $db->query('SELECT id, nom, logo FROM shops LIMIT 10');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . PHP_EOL;
}
