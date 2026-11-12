<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'facilities.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!function_exists('makeReservation')) {
    function makeReservation($facility_id, $date, $start_time, $end_time, $reason) {
        $bookingDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$date);
        if (
            !$bookingDate ||
            $bookingDate->format('Y-m-d') !== $date ||
            $bookingDate->format('w') === '0'
        ) {
            return false;
        }

        $start_time = (string)$start_time;
        $end_time = (string)$end_time;
        if (
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $start_time) ||
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $end_time)
        ) {
            return false;
        }

        $start_time = substr($start_time, 0, 5);
        $end_time = substr($end_time, 0, 5);
        if ($start_time < '07:30' || $end_time > '21:00' || $start_time >= $end_time) {
            return false;
        }

        $bookingStart = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $bookingDate->format('Y-m-d') . ' ' . $start_time,
            new DateTimeZone('Asia/Manila')
        );
        if (!$bookingStart || $bookingStart <= new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'))) {
            return false;
        }

        if (!isLoggedIn() || !isAvailable($facility_id, $date, $start_time, $end_time)) {
            return false;
        }

        $pdo = getDB();

        $isDuplicateError = static function (PDOException $exception): bool {
            return (int)($exception->errorInfo[1] ?? 0) === 1062;
        };
        $isMissingColumnError = static function (PDOException $exception): bool {
            return $exception->getCode() === '42S22'
                || (int)($exception->errorInfo[1] ?? 0) === 1054;
        };
        $restoreCancelledReservation = static function (bool $withReason) use (
            $pdo,
            $facility_id,
            $date,
            $start_time,
            $end_time,
            $reason
        ) {
            $find = $pdo->prepare(
                "SELECT id FROM reservations " .
                "WHERE facility_id = ? AND reserve_date = ? AND status = 'cancelled' " .
                "AND ((start_time = ? AND end_time = ?) " .
                "OR (start_time = '00:00:00' AND end_time = '00:00:00' AND reserve_time = ?)) " .
                "ORDER BY CASE WHEN start_time = ? AND end_time = ? THEN 0 ELSE 1 END, id DESC LIMIT 1"
            );
            $find->execute([$facility_id, $date, $start_time, $end_time, $start_time, $start_time, $end_time]);
            $cancelledId = $find->fetchColumn();
            if ($cancelledId === false) {
                return false;
            }

            $sql = "UPDATE reservations SET user_id = ?, start_time = ?, end_time = ?, reserve_time = NULL, status = 'pending'";
            $params = [$_SESSION['user_id'], $start_time, $end_time];
            if ($withReason) {
                $sql .= ", reason = ?";
                $params[] = $reason;
            }
            $sql .= " WHERE id = ? AND status = 'cancelled'";
            $params[] = $cancelledId;

            $update = $pdo->prepare($sql);
            $update->execute($params);
            return $update->rowCount() > 0 ? (int)$cancelledId : false;
        };

        // Support both schemas: (start_time/end_time) or old (reserve_time).
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO reservations (user_id, facility_id, reserve_date, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ok = $stmt->execute([$_SESSION['user_id'], $facility_id, $date, $start_time, $end_time, $reason]);
        } catch (PDOException $e) {
            if ($isDuplicateError($e)) {
                $res_id = $restoreCancelledReservation(true);
                if ($res_id === false) {
                    error_log('Duplicate reservation attempt blocked for facility ' . (int)$facility_id . ' on ' . $date . ' at ' . $start_time . '.');
                    return false;
                }
                $ok = true;
            } elseif (!$isMissingColumnError($e)) {
                throw $e;
            }

            if (!isset($ok)) {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO reservations (user_id, facility_id, reserve_date, start_time, end_time) VALUES (?, ?, ?, ?, ?)"
                    );
                    $ok = $stmt->execute([$_SESSION['user_id'], $facility_id, $date, $start_time, $end_time]);
                } catch (PDOException $withoutReasonError) {
                    if ($isDuplicateError($withoutReasonError)) {
                        $res_id = $restoreCancelledReservation(false);
                        if ($res_id === false) {
                            error_log('Duplicate reservation attempt blocked for facility ' . (int)$facility_id . ' on ' . $date . ' at ' . $start_time . '.');
                            return false;
                        }
                        $ok = true;
                    } elseif (!$isMissingColumnError($withoutReasonError)) {
                        throw $withoutReasonError;
                    }
                }
            }

            if (!isset($ok)) {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO reservations (user_id, facility_id, reserve_date, reserve_time, reason) VALUES (?, ?, ?, ?, ?)"
                    );
                    $ok = $stmt->execute([$_SESSION['user_id'], $facility_id, $date, $start_time, $reason]);
                } catch (PDOException $legacyError) {
                    if ($isDuplicateError($legacyError)) {
                        error_log('Duplicate legacy reservation attempt blocked for facility ' . (int)$facility_id . ' on ' . $date . ' at ' . $start_time . '.');
                        return false;
                    }
                    if (!$isMissingColumnError($legacyError)) {
                        throw $legacyError;
                    }

                    $stmt = $pdo->prepare(
                        "INSERT INTO reservations (user_id, facility_id, reserve_date, reserve_time) VALUES (?, ?, ?, ?)"
                    );
                    $ok = $stmt->execute([$_SESSION['user_id'], $facility_id, $date, $start_time]);
                }
            }
        }

        if ($ok) {
            $res_id = $res_id ?? $pdo->lastInsertId();
            $checkinToken = bin2hex(random_bytes(32));
            $saveToken = $pdo->prepare('UPDATE reservations SET checkin_token = ? WHERE id = ?');
            $saveToken->execute([$checkinToken, $res_id]);

            $qr_path = 'uploads/qrcodes/res_' . $res_id . '.svg';
            $qr_file_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $qr_path);
            if (generateQR((int)$res_id, $qr_file_path, $checkinToken)) {
                $update = $pdo->prepare("UPDATE reservations SET qr_code_path = ? WHERE id = ?");
                $update->execute([$qr_path, $res_id]);
            } else {
                error_log('Reservation ' . (int)$res_id . ' was saved, but its QR code could not be generated.');
            }

            logUsage($facility_id, $date);
            return $res_id;
        }

        return false;
    }
}


// Handle reservation submission from index.php
if (isset($_POST['reserve_fac'])) {
    $facility_id = (int)($_POST['facility_id'] ?? 0);
    $date = $_POST['reserve_date'] ?? '';

    $start_time = $_POST['start_time'] ?? '';

    $end_time = $_POST['end_time'] ?? '';

    $reason = $_POST['reason'] ?? '';

    if ($facility_id > 0 && $date && $start_time && $end_time) {
        $res_id = makeReservation($facility_id, $date, $start_time, $end_time, $reason);

        if ($res_id) {
            header('Location: index.php?reserved=1');
            exit;
        }
        header('Location: index.php?reserved=0');
        exit;
    }

    header('Location: index.php?reserved=0');
    exit;

}






function getUserReservations($user_id = null) {
    $user_id = $user_id ?: $_SESSION['user_id'];
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT r.*, f.name as facility_name FROM reservations r JOIN facilities f ON r.facility_id = f.id WHERE r.user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function logUsage($facility_id, $date) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO usage_logs (facility_id, reserve_date) VALUES (?, ?) ON DUPLICATE KEY UPDATE usage_count = usage_count + 1");
    $stmt->execute([$facility_id, $date]);
}

function generateQR(int $res_id, string $path, string $checkinToken): bool {
    require_once __DIR__ . '/qrcode.php';

    return generateReservationQR($res_id, $path, $checkinToken);
}
?>
