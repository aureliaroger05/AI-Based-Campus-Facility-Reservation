<?php
require_once __DIR__ . '/config.php';

const NOTIFICATION_RECENT_DAYS = 14;
const NOTIFICATION_PANEL_LIMIT = 8;
const NOTIFICATION_STATUS_RANK = [
    'pending' => 0,
    'confirmed' => 1,
    'completed' => 2,
    'cancelled' => 3,
];

/**
 * Human readable date label for a reservation notification.
 */
function notificationDateLabel(string $reserveDate, DateTimeImmutable $today): string
{
    if ($reserveDate === $today->format('Y-m-d')) {
        return 'Today';
    }
    if ($reserveDate === $today->modify('+1 day')->format('Y-m-d')) {
        return 'Tomorrow';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $reserveDate);
    if (!$date) {
        return $reserveDate;
    }

    return $date->format('M j, Y');
}

/**
 * Formats the reserved time window, tolerating the legacy reserve_time column.
 */
function notificationTimeLabel(array $row): string
{
    $start = substr((string)($row['start_time'] ?? ''), 0, 5);
    $end = substr((string)($row['end_time'] ?? ''), 0, 5);
    if ($start !== '' && $end !== '' && ($start !== '00:00' || $end !== '00:00')) {
        return $start . ' - ' . $end;
    }

    $legacy = substr((string)($row['reserve_time'] ?? ''), 0, 5);
    return $legacy !== '' ? $legacy : 'Time not set';
}

/**
 * Turns a reservation row into a notification item, or null when it is not worth surfacing.
 */
function buildReservationNotification(array $row, DateTimeImmutable $today): ?array
{
    $status = (string)($row['status'] ?? 'pending');
    $reserveDate = substr((string)($row['reserve_date'] ?? ''), 0, 10);
    if ($reserveDate === '') {
        return null;
    }

    $recentThreshold = $today->modify('-' . NOTIFICATION_RECENT_DAYS . ' days')->format('Y-m-d');
    $titles = [
        'pending' => 'Waiting for approval',
        'confirmed' => 'Reservation confirmed',
        'completed' => 'Reservation completed',
        'cancelled' => 'Reservation cancelled',
    ];

    if ($status === 'pending') {
        $message = 'Your request is queued for admin review.';
    } elseif ($status === 'confirmed') {
        if ($reserveDate < $today->format('Y-m-d')) {
            return null;
        }
        $message = 'Your slot is booked. Show the QR code on arrival.';
    } elseif ($status === 'completed' || $status === 'cancelled') {
        if ($reserveDate < $recentThreshold) {
            return null;
        }
        $message = $status === 'completed'
            ? 'This booking has been marked as used.'
            : 'This booking was cancelled.';
    } else {
        return null;
    }

    $facility = trim((string)($row['facility_name'] ?? '')) ?: 'Facility';

    return [
        'id' => (int)($row['id'] ?? 0),
        'status' => $status,
        'title' => $titles[$status] ?? ucfirst($status),
        'message' => $message,
        'meta' => $facility . ' · ' . notificationDateLabel($reserveDate, $today) . ' · ' . notificationTimeLabel($row),
        'href' => 'view_res.php?id=' . (int)($row['id'] ?? 0),
        'date' => $reserveDate,
        'rank' => NOTIFICATION_STATUS_RANK[$status] ?? 9,
    ];
}
/**
 * Builds the ordered notification feed for one user.
 *
 * Upcoming work (pending, confirmed) comes first in date order, then the
 * recent history (completed, cancelled) newest first.
 */
function getUserNotifications(int $userId): array
{
    if ($userId <= 0) {
        return ['count' => 0, 'items' => [], 'generatedAt' => gmdate('c')];
    }

    $pdo = getDB();
    $today = new DateTimeImmutable('today');
    $recentThreshold = $today->modify('-' . NOTIFICATION_RECENT_DAYS . ' days')->format('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT r.id, r.reserve_date, r.status, r.start_time, r.end_time, f.name AS facility_name " .
        "FROM reservations r LEFT JOIN facilities f ON f.id = r.facility_id " .
        "WHERE r.user_id = ? AND (r.status IN ('pending', 'confirmed') OR r.reserve_date >= ?) " .
        "ORDER BY r.reserve_date DESC, r.id DESC LIMIT 60"
    );
    $stmt->execute([$userId, $recentThreshold]);

    $upcoming = [];
    $history = [];
    $count = 0;

    foreach ($stmt->fetchAll() as $row) {
        $item = buildReservationNotification($row, $today);
        if ($item === null) {
            continue;
        }

        if ($item['rank'] <= 1) {
            $upcoming[] = $item;
            $count++;
        } else {
            $history[] = $item;
        }
    }

    usort($upcoming, static function (array $left, array $right): int {
        return [$left['date'], $left['id']] <=> [$right['date'], $right['id']];
    });
    usort($history, static function (array $left, array $right): int {
        return [$right['date'], $right['id']] <=> [$left['date'], $left['id']];
    });

    $items = array_slice(array_merge($upcoming, $history), 0, NOTIFICATION_PANEL_LIMIT);

    return [
        'count' => $count,
        'items' => $items,
        'upcomingCount' => count($upcoming),
        'generatedAt' => gmdate('c'),
    ];
}
