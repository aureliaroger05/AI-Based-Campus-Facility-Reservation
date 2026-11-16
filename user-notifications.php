<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $feed = getUserNotifications((int)($_SESSION['user_id'] ?? 0));
    echo json_encode($feed, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('User notifications feed failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Notifications are temporarily unavailable.'], JSON_UNESCAPED_UNICODE);
}
