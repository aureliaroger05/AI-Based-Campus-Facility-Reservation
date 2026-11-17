<?php
$pdo = new PDO("mysql:host=localhost;dbname=reservation_sys;charset=utf8mb4", "root", "");
$stmt = $pdo->query("SELECT id, name FROM facilities ORDER BY name");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['id'] . "|" . $r['name'] . PHP_EOL;
}
