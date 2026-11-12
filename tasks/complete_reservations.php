<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';

$pdo = getDB();
$localNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$statement = $pdo->prepare(
    "UPDATE reservations
     SET status = 'completed'
     WHERE status = 'confirmed'
       AND end_time > '00:00:00'
       AND TIMESTAMP(reserve_date, end_time) <= ?"
);
$statement->execute([$localNow->format('Y-m-d H:i:s')]);

if ($statement->rowCount() > 0) {
    error_log('Reservation auto-completion updated ' . $statement->rowCount() . ' reservation(s).');
}
