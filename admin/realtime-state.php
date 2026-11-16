<?php
require '../auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Administrator authentication is required.']);
    exit;
}

try {
    $pdo = getDB();
    $revision = hash_init('sha256');
    $queries = [
        'users' => 'SELECT id, username, role FROM users ORDER BY id',
        'facilities' => 'SELECT id, name, avail_slots, max_capacity FROM facilities ORDER BY id',
        'reservations' => 'SELECT id, user_id, facility_id, reserve_date, start_time, end_time, reserve_time, status FROM reservations ORDER BY id',
        'class_schedules' => 'SELECT id, facility_id, class_date, start_time, end_time FROM class_schedules ORDER BY id',
    ];

    foreach ($queries as $table => $query) {
        hash_update($revision, $table . "\n");
        $statement = $pdo->query($query);
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            hash_update(
                $revision,
                json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n"
            );
        }
    }

    $pendingReservations = (int)$pdo->query(
        "SELECT COUNT(*) FROM reservations WHERE status = 'pending'"
    )->fetchColumn();

    echo json_encode([
        'revision' => hash_final($revision),
        'pendingReservations' => $pendingReservations,
    ]);
} catch (PDOException $exception) {
    error_log('Failed to check admin live-update state: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Live update state is temporarily unavailable.']);
}
