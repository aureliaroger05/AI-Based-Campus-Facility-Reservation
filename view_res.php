<?php
// QR scan/view reservation status
require 'config.php';
require 'auth.php';
$id = (int)($_GET['id'] ?? 0);
$isQrScan = isset($_GET['scan']) && $_GET['scan'] === '1';
$providedQrToken = $_GET['token'] ?? '';
if (!isLoggedIn()) {
    if ($isQrScan && is_string($providedQrToken) && preg_match('/^[a-f0-9]{64}$/', $providedQrToken)) {
        $_SESSION['admin_scan_return'] = 'view_res.php?' . http_build_query([
            'id' => $id,
            'scan' => '1',
            'token' => $providedQrToken,
        ]);
    }
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT r.*, u.username, u.full_name, f.name FROM reservations r JOIN users u ON r.user_id = u.id JOIN facilities f ON r.facility_id = f.id WHERE r.id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch();
if (!$res) {
    die('Reservation not found.');
}

if (
    empty($res['checkin_token']) &&
    isLoggedIn() &&
    (isAdmin() || (int)$res['user_id'] === (int)$_SESSION['user_id'])
) {
    $res['checkin_token'] = bin2hex(random_bytes(32));
    $saveToken = $pdo->prepare('UPDATE reservations SET checkin_token = ? WHERE id = ? AND checkin_token IS NULL');
    $saveToken->execute([$res['checkin_token'], $id]);
    if ($saveToken->rowCount() === 0) {
        $readToken = $pdo->prepare('SELECT checkin_token FROM reservations WHERE id = ?');
        $readToken->execute([$id]);
        $res['checkin_token'] = (string)$readToken->fetchColumn();
    }
}

$validQrToken = $isQrScan
    && is_string($providedQrToken)
    && preg_match('/^[a-f0-9]{64}$/', $providedQrToken)
    && !empty($res['checkin_token'])
    && hash_equals($res['checkin_token'], $providedQrToken);

if ($validQrToken && !isAdmin()) {
    $_SESSION['admin_scan_return'] = 'view_res.php?' . http_build_query([
        'id' => $id,
        'scan' => '1',
        'token' => $providedQrToken,
    ]);
    header('Location: login.php?admin_scan=1');
    exit;
}

$validQrScan = $validQrToken && isAdmin();

if (
    !$validQrScan &&
    (!isLoggedIn() || (!isAdmin() && (int)$res['user_id'] !== (int)$_SESSION['user_id']))
) {
    http_response_code(403);
    exit('Open this reservation from your account or scan its current QR code.');
}

if (isLoggedIn() && empty($_SESSION['reservation_use_csrf'])) {
    $_SESSION['reservation_use_csrf'] = bin2hex(random_bytes(32));
}

$checkinResult = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_used'])) {
    $qrTokenCheckIn = $validQrScan
        && is_string($_POST['checkin_token'] ?? null)
        && hash_equals($providedQrToken, $_POST['checkin_token']);
    if (!isAdmin() || (int)($_POST['reservation_id'] ?? 0) !== $id) {
        http_response_code(403);
        exit('This reservation check-in is not authorized.');
    }

    if (!$qrTokenCheckIn) {
        $providedToken = $_POST['csrf_token'] ?? '';
        if (
            !is_string($providedToken) ||
            empty($_SESSION['reservation_use_csrf']) ||
            !hash_equals($_SESSION['reservation_use_csrf'], $providedToken)
        ) {
            http_response_code(403);
            exit('Your session expired. Refresh the page and try again.');
        }
    }

    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT status FROM reservations WHERE id = ? AND checkin_token = ? FOR UPDATE');
        $lock->execute([$id, $res['checkin_token']]);
        $lockedReservation = $lock->fetch();

        if ($lockedReservation && $lockedReservation['status'] === 'confirmed') {
            $markUsed = $pdo->prepare("UPDATE reservations SET status = 'used', usage_confirmed = 1 WHERE id = ? AND status = 'confirmed'");
            $markUsed->execute([$id]);
            $checkinResult = $markUsed->rowCount() === 1 ? 'used' : 'unavailable';
        } elseif ($lockedReservation && $lockedReservation['status'] === 'used') {
            $checkinResult = 'already-used';
        } else {
            $checkinResult = 'unavailable';
        }

        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Failed to record reservation check-in: ' . $exception->getMessage());
        http_response_code(500);
        exit('The reservation could not be marked as used. Please try again.');
    }

    $redirectUrl = 'view_res.php?id=' . $id . '&checkin=' . rawurlencode($checkinResult);
    if ($qrTokenCheckIn) {
        $redirectUrl .= '&scan=1&token=' . rawurlencode($providedQrToken);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

$checkinResult = $_GET['checkin'] ?? '';
$isAdminViewer = isAdmin();
if ($validQrScan) {
    $checkinResult = $res['status'] === 'used'
        ? (($_GET['checkin'] ?? '') === 'already-used' ? 'already-used' : 'used')
        : ($res['status'] === 'confirmed' ? '' : 'unavailable');
}
if (
    !empty($res['checkin_token']) &&
    !empty($res['qr_code_path']) &&
    $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    require_once __DIR__ . '/qrcode.php';
    $qrFilePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $res['qr_code_path']);
    if (generateReservationQR($id, $qrFilePath, $res['checkin_token'])) {
        $res['qr_code_path'] = str_replace('\\', '/', $res['qr_code_path']);
    }
}
$qrFilePath = !empty($res['qr_code_path'])
    ? __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $res['qr_code_path'])
    : '';
$qrAssetUrl = $res['qr_code_path'] ?? '';
if ($qrFilePath !== '' && is_file($qrFilePath)) {
    $qrAssetUrl .= '?v=' . (int)filemtime($qrFilePath);
}
$reservationStart = $res['start_time'] ?? '';
if ($reservationStart === '' || $reservationStart === '00:00:00') {
    $reservationStart = $res['reserve_time'] ?? '';
}
$reservationEnd = $res['end_time'] ?? '';
$reservationStartLabel = $reservationStart !== '' ? date('g:i A', strtotime($reservationStart)) : '—';
$reservationEndLabel = $reservationEnd !== '' && $reservationEnd !== '00:00:00'
    ? date('g:i A', strtotime($reservationEnd))
    : '—';
$reservationDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($res['reserve_date'] ?? ''));
$reservationDateLabel = $reservationDate ? $reservationDate->format('F j, Y') : (string)($res['reserve_date'] ?? '—');
$reserverName = trim((string)($res['full_name'] ?? ''));
if ($reserverName === '') {
    $reserverName = (string)$res['username'];
}
?>
<?php
$page_title = 'Reservation View';
$body_class = 'reservation-view-page';
?>
<?php require 'header.php'; ?>

<?php if ($validQrScan): ?>
    <main class="qr-checkin-result-main">
        <section class="qr-checkin-result" role="status" aria-live="polite">
            <?php if ($checkinResult === 'used'): ?>
                <span class="qr-checkin-check"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                <h1>Check-in complete!</h1>
                <p>Your reservation has been checked in.</p>
            <?php elseif ($checkinResult === 'already-used'): ?>
                <span class="qr-checkin-check qr-checkin-check-info"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                <h1>Already checked in</h1>
                <p>This reservation was already checked in.</p>
            <?php elseif ($res['status'] === 'confirmed' && $checkinResult === ''): ?>
                <span class="qr-checkin-spinner" aria-hidden="true"></span>
                <h1>Checking you in…</h1>
                <p>Please wait a moment.</p>
                <form id="reservation-checkin-form" method="post" hidden>
                    <input type="hidden" name="reservation_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="checkin_token" value="<?php echo htmlspecialchars($providedQrToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="mark_used" value="1">
                </form>
                <script>
                    document.getElementById('reservation-checkin-form').requestSubmit();
                </script>
            <?php else: ?>
                <span class="qr-checkin-check qr-checkin-check-warning"><i class="bi bi-exclamation-lg" aria-hidden="true"></i></span>
                <h1>Check-in unavailable</h1>
                <p>Only confirmed reservations can be checked in.</p>
            <?php endif; ?>
            <?php if (in_array($checkinResult, ['used', 'already-used'], true)): ?>
                <dl class="qr-checkin-details">
                    <div><dt>Reserved by</dt><dd><?php echo htmlspecialchars($reserverName, ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt>Facility</dt><dd><?php echo htmlspecialchars($res['name'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt>Date</dt><dd><?php echo htmlspecialchars($reservationDateLabel, ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div><dt>Time</dt><dd><?php echo htmlspecialchars($reservationStartLabel . ' - ' . $reservationEndLabel, ENT_QUOTES, 'UTF-8'); ?></dd></div>
                </dl>
            <?php endif; ?>
        </section>
    </main>
<?php else: ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">


    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-building me-2"></i>AI Reservation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <?php if ($isAdminViewer): ?>
                    <a class="nav-link" href="admin/reservations.php"><i class="bi bi-clipboard-check"></i> Reservations</a>
                    <a class="nav-link" href="admin/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <?php else: ?>
                    <a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php" data-confirm="Are you sure you want to log out?"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </div>
    </div>
</nav>

<main class="reservation-view-main">
    <div class="container reservation-view-content">
        <div class="admin-page-heading reservation-view-heading">
            <span class="admin-heading-icon"><i class="bi bi-qr-code"></i></span>
            <div>
                <h2 class="mb-1">Reservation QR Code</h2>
                <p class="mb-0">Confirmation details for reservation #<?php echo $id; ?>.</p>
            </div>
        </div>

        <section class="card reservation-view-card">
            <div class="card-header profile-panel-heading">
                <span><i class="bi bi-calendar2-check me-2"></i>Reservation details</span>
                <span class="badge text-bg-<?php echo in_array($res['status'], ['confirmed', 'used'], true) ? 'success' : ($res['status'] === 'cancelled' ? 'danger' : 'secondary'); ?>">
                    <?php echo htmlspecialchars(ucfirst($res['status'])); ?>
                </span>
            </div>
            <div class="card-body">
                <?php if ($checkinResult === 'used'): ?>
                    <div class="alert alert-success fw-bold text-center fs-4" role="status">Reservation Used</div>
                <?php elseif ($checkinResult === 'already-used'): ?>
                    <div class="alert alert-info" role="status">This reservation has already been marked as used.</div>
                <?php elseif ($checkinResult === 'unavailable'): ?>
                    <div class="alert alert-warning" role="status">Only confirmed reservations can be marked as used.</div>
                <?php endif; ?>
                <dl class="reservation-view-details">
                    <div><dt>Facility</dt><dd><?php echo htmlspecialchars($res['name']); ?></dd></div>
                    <div><dt>Date</dt><dd><?php echo htmlspecialchars($res['reserve_date'] ?? ''); ?></dd></div>
                    <div><dt>Time</dt><dd><?php echo htmlspecialchars($reservationStart); ?> - <?php echo htmlspecialchars($reservationEnd); ?></dd></div>
                    <div><dt>Reason</dt><dd><?php echo htmlspecialchars($res['reason'] ?? '—'); ?></dd></div>
                </dl>

                <div class="reservation-qr-panel">
                    <h3><i class="bi bi-qr-code me-2"></i>Reservation QR</h3>
                    <?php if (!empty($res['qr_code_path'])): ?>
                        <img class="reservation-qr-image" src="<?php echo htmlspecialchars($qrAssetUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Reservation QR code">
                        <p class="mb-0 text-muted">Show this code when checking in to the facility.</p>
                    <?php else: ?>
                        <p class="mb-0 text-muted">The QR code for this reservation is currently unavailable.</p>
                    <?php endif; ?>
                </div>

                <div class="reservation-view-actions">
                    <?php if ($isAdminViewer && $res['status'] === 'confirmed'): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="reservation_id" value="<?php echo $id; ?>">
                            <input type="hidden" name="checkin_token" value="<?php echo htmlspecialchars($providedQrToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['reservation_use_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="btn btn-success" type="submit" name="mark_used" value="1">
                                <i class="bi bi-check-circle me-2"></i>Mark as used
                            </button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-primary" href="<?php echo $isAdminViewer ? 'admin/reservations.php' : 'profile.php'; ?>">
                        <i class="bi bi-arrow-left me-2"></i>Back to <?php echo $isAdminViewer ? 'Reservations' : 'Profile'; ?>
                    </a>
                    <?php if (!empty($res['qr_code_path'])): ?>
                        <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($qrAssetUrl, ENT_QUOTES, 'UTF-8'); ?>" download>
                            <i class="bi bi-download me-2"></i>Download QR
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</main>
<?php endif; ?>
<?php require 'footer.php'; ?>
