<?php
// One-time migration: convert legacy 'used' status rows to 'completed'.
require_once dirname(__DIR__) . '/config.php';

$pdo = getDB();
$stmt = $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE status = 'used'");
$stmt->execute();
echo 'Migrated ' . $stmt->rowCount() . " reservation(s) from 'used' to 'completed'." . PHP_EOL;
