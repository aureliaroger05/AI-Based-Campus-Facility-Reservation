<?php
require_once 'config.php';
require_once 'auth.php';

if (!function_exists('getFacilities')) {
function getFacilities($admin = false) {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM facilities ORDER BY name");
    return $stmt->fetchAll();
}
}

if (!function_exists('getFacility')) {
function getFacility($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM facilities WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
}

// Admin add facility
if (!function_exists('addFacility')) {
function addFacility($data) {
    if (!isAdmin()) return false;
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO facilities (name) VALUES (?)");
    return $stmt->execute([$data['name']]);
}
}

// Admin update facility
if (!function_exists('updateFacility')) {
function updateFacility($id, $data) {
    if (!isAdmin()) return false;
    $pdo = getDB();

    $stmt = $pdo->prepare("UPDATE facilities SET name = ? WHERE id = ?");
    return $stmt->execute([$data['name'], $id]);
}
}

// Admin delete facility
if (!function_exists('deleteFacility')) {
function deleteFacility($id) {
    if (!isAdmin()) return false;
    $pdo = getDB();

    // Reservations may reference facilities; rely on FK constraints / schema behavior.
    $stmt = $pdo->prepare("DELETE FROM facilities WHERE id = ?");
    return $stmt->execute([$id]) && $stmt->rowCount() > 0;
}
}


// Check availability
if (!function_exists('isAvailable')) {
function isAvailable($facility_id, $date, $start_time, $end_time) {
    $pdo = getDB();

    if (!$facility_id || !$date || !$start_time || !$end_time) {
        return false;
    }

    $start_time = (string) $start_time;
    $end_time = (string) $end_time;

    if ($start_time >= $end_time) {
        return false;
    }

    $classSchedule = $pdo->prepare(
        "SELECT id FROM class_schedules " .
        "WHERE facility_id = ? AND class_date = ? AND start_time < ? AND end_time > ? LIMIT 1"
    );
    $classSchedule->execute([$facility_id, $date, $end_time, $start_time]);
    if ($classSchedule->fetch() !== false) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM reservations " .
            "WHERE facility_id = ? AND reserve_date = ? AND status != 'cancelled' " .
            "AND (" .
            "((start_time <> '00:00:00' OR end_time <> '00:00:00') AND start_time < ? AND end_time > ?) " .
            "OR (start_time = '00:00:00' AND end_time = '00:00:00' AND reserve_time >= ? AND reserve_time < ?)" .
            ") LIMIT 1"
        );
        $stmt->execute([$facility_id, $date, $end_time, $start_time, $start_time, $end_time]);
        return $stmt->fetch() === false;
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S22' && (int)($e->errorInfo[1] ?? 0) !== 1054) {
            throw $e;
        }

        // Fallback old schema uses reserve_time only
        $stmt = $pdo->prepare(
            "SELECT id FROM reservations " .
            "WHERE facility_id = ? AND reserve_date = ? AND reserve_time = ? AND status != 'cancelled' LIMIT 1"
        );
        $stmt->execute([$facility_id, $date, $start_time]);
        return $stmt->fetch() === false;
    }
}
}
?>
