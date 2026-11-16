<?php $page_title = 'Manage Reservations - Admin';
$body_class = 'admin-reservations-page';
require '../auth.php';
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

require '../header.php';

$pdo = getDB();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-building me-2"></i>AI Reservation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php"><i class="bi bi-house"></i> Home</a>
                <a class="nav-link" href="../profile.php"><i class="bi bi-person"></i> Profile</a>
                <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link" href="ai-forecast.php"><i class="bi bi-graph-up"></i> Forecast</a>
                <a class="nav-link active" href="reservations.php"><i class="bi bi-clipboard-check"></i> Reservations</a>

                <a class="nav-link" href="../logout.php" data-confirm="Are you sure you want to log out?"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>

            </div>
        </div>
    </div>
</nav>

<?php
// Handle confirm/cancel actions
if (isset($_POST['confirm'])) {
    $stmt = $pdo->prepare("UPDATE reservations SET status = 'confirmed' WHERE id = ?");
    $stmt->execute([$_POST['res_id']]);
    echo '<div class="alert alert-success">Reservation confirmed.</div>';
}

if (isset($_POST['cancel'])) {
    $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$_POST['res_id']]);
    echo '<div class="alert alert-info">Reservation cancelled.</div>';
}

$stmt = $pdo->query(
    "SELECT 
        r.*, 
        u.username, 
        f.name as facility
     FROM reservations r 
     JOIN users u ON r.user_id = u.id 
     JOIN facilities f ON r.facility_id = f.id
     ORDER BY r.created_at DESC"
);
$reservations = $stmt->fetchAll();
$reservationHistory = [];
$currentReservations = [];
$today = date('Y-m-d');
foreach ($reservations as $reservation) {
    if (($reservation['reserve_date'] ?? '') < $today) {
        $reservationHistory[] = $reservation;
    } else {
        $currentReservations[] = $reservation;
    }
}
$reservations = $currentReservations;
$groupReservationsByMonth = function (array $items, $newestFirst = false) {
    $groups = [];
    foreach ($items as $item) {
        $reserveDate = (string)($item['reserve_date'] ?? '');
        $monthKey = preg_match('/^\d{4}-\d{2}/', $reserveDate) ? substr($reserveDate, 0, 7) : 'unknown';
        $groups[$monthKey][] = $item;
    }

    foreach ($groups as &$monthItems) {
        usort($monthItems, function ($left, $right) use ($newestFirst) {
            $dateOrder = strcmp((string)($left['reserve_date'] ?? ''), (string)($right['reserve_date'] ?? ''));
            if ($dateOrder === 0) {
                $leftTime = (string)($left['start_time'] ?? $left['reserve_time'] ?? '');
                $rightTime = (string)($right['start_time'] ?? $right['reserve_time'] ?? '');
                $dateOrder = strcmp($leftTime, $rightTime);
            }
            return $newestFirst ? -$dateOrder : $dateOrder;
        });
    }
    unset($monthItems);

    if ($newestFirst) {
        krsort($groups);
    } else {
        ksort($groups);
    }
    return $groups;
};
$reservationsByMonth = $groupReservationsByMonth($reservations);
$reservationHistoryByMonth = $groupReservationsByMonth($reservationHistory, true);
?>

<main class="admin-reservations-main">
<div class="container-fluid admin-reservations-content">
    <div class="admin-page-heading">
        <span class="admin-heading-icon"><i class="bi bi-calendar2-check"></i></span>
        <div>
            <h2 class="mb-1">Manage Reservations</h2>
            <p class="mb-0">Review requests, check booking details, and manage reservation status.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
            <span class="badge text-bg-primary"><?php echo count($reservations); ?> current</span>
            <span class="badge text-bg-secondary"><?php echo count($reservationHistory); ?> history</span>
        </div>
    </div>

    <?php if (!empty($_GET['reserved'])): ?>
        <div class="alert alert-success text-center">The reservation is successful.</div>
    <?php endif; ?>
    <section class="card reservations-table-card">
    <div class="card-header admin-section-heading">
        <span><i class="bi bi-list-check me-2"></i>Current and upcoming reservations</span>
    </div>
    <div class="card-body">
    <?php if (empty($reservationsByMonth)): ?>
        <p class="text-muted text-center py-3 mb-0">There are no current or upcoming reservations.</p>
    <?php else: ?>
        <?php foreach ($reservationsByMonth as $monthKey => $monthReservations): ?>
            <?php $monthLabel = $monthKey === 'unknown' ? 'Date not set' : (new DateTimeImmutable($monthKey . '-01'))->format('F Y'); ?>
            <section class="forecast-report-month reservation-month-group">
                <div class="forecast-report-month-heading">
                    <h4><?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?></h4>
                    <span><?php echo count($monthReservations); ?> reservation<?php echo count($monthReservations) === 1 ? '' : 's'; ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped admin-data-table reservations-data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Facility</th>
                                <th>Day</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>QR</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                <?php foreach ($monthReservations as $r): ?>
                <?php
                $reservationStart = $r['start_time'] ?? '';
                if ($reservationStart === '' || $reservationStart === '00:00:00') {
                    $reservationStart = $r['reserve_time'] ?? '';
                }
                $reservationEnd = $r['end_time'] ?? '';
                if ($reservationEnd === '00:00:00') {
                    $reservationEnd = '—';
                }
                ?>
                <tr>
                    <td><?php echo $r['id']; ?></td>
                    <td><?php echo htmlspecialchars($r['username']); ?></td>
                    <td><?php echo htmlspecialchars($r['facility']); ?></td>

                    <td><?php echo htmlspecialchars($r['reserve_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($reservationStart); ?></td>
                    <td><?php echo htmlspecialchars($reservationEnd); ?></td>
                    <td><?php echo htmlspecialchars($r['reason'] ?? ''); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $r['status'] === 'confirmed' ? 'success' : ($r['status'] === 'cancelled' ? 'danger' : ($r['status'] === 'completed' ? 'primary' : ($r['status'] === 'used' ? 'secondary' : 'warning'))); ?>">
                            <?php echo ucfirst($r['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        if (!empty($r['qr_code_path'])) {
                            echo '<a href="../view_res.php?id=' . (int)$r['id'] . '" target="_blank" rel="noopener">View QR</a>';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if (in_array($r['status'], ['pending', 'confirmed'], true)): ?>
                            <?php
                            // Auto-handle reservations created by the admin account (admin-as-user)
                            if (
                                ($_SERVER['HTTP_X_ADMIN_LIVE_REFRESH'] ?? '') !== 'true' &&
                                $r['status'] === 'pending' &&
                                isset($_SESSION['role']) && $_SESSION['role'] === 'admin' &&
                                isset($r['user_id']) && (int)$r['user_id'] === (int)$_SESSION['user_id']
                            ) {
                                $stmt = $pdo->prepare("UPDATE reservations SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
                                $stmt->execute([(int)$r['id']]);
                                $r['status'] = 'confirmed';
                                echo '<span class="badge bg-success">Confirmed</span>';
                                ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="res_id" value="<?php echo (int)$r['id']; ?>">
                                    <button type="submit" name="cancel" class="btn btn-warning btn-sm" data-confirm="Cancel this reservation?">Cancel</button>
                                </form>
                                <?php
                            } else {
                            ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="res_id" value="<?php echo $r['id']; ?>">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <button type="submit" name="confirm" class="btn btn-success btn-sm">Confirm</button>
                                    <?php endif; ?>
                                    <button type="submit" name="cancel" class="btn btn-warning btn-sm" data-confirm="Cancel this reservation?">Cancel</button>
                                </form>
                            <?php } ?>
                        <?php else: ?>
                            <span class="text-muted">Handled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
    </section>

    <section class="card reservations-table-card mt-4">
    <div class="card-header admin-section-heading">
        <span><i class="bi bi-clock-history me-2"></i>Reservation history (past days)</span>
    </div>
    <div class="card-body">
    <?php if (empty($reservationHistory)): ?>
        <p class="text-muted mb-0">There are no reservations from past days.</p>
    <?php else: ?>
            <?php foreach ($reservationHistoryByMonth as $monthKey => $monthReservations): ?>
                <?php $monthLabel = $monthKey === 'unknown' ? 'Date not set' : (new DateTimeImmutable($monthKey . '-01'))->format('F Y'); ?>
                <section class="forecast-report-month reservation-month-group">
                    <div class="forecast-report-month-heading">
                        <h4><?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?></h4>
                        <span><?php echo count($monthReservations); ?> reservation<?php echo count($monthReservations) === 1 ? '' : 's'; ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped admin-data-table reservations-data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Facility</th>
                                    <th>Day</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>QR</th>
                                </tr>
                            </thead>
                            <tbody>
                <?php foreach ($monthReservations as $r): ?>
                <?php
                $reservationStart = $r['start_time'] ?? '';
                if ($reservationStart === '' || $reservationStart === '00:00:00') {
                    $reservationStart = $r['reserve_time'] ?? '';
                }
                $reservationEnd = $r['end_time'] ?? '';
                if ($reservationEnd === '00:00:00') {
                    $reservationEnd = '—';
                }
                ?>
                <tr>
                    <td><?php echo (int)$r['id']; ?></td>
                    <td><?php echo htmlspecialchars($r['username']); ?></td>
                    <td><?php echo htmlspecialchars($r['facility']); ?></td>
                    <td><?php echo htmlspecialchars($r['reserve_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($reservationStart); ?></td>
                    <td><?php echo htmlspecialchars($reservationEnd); ?></td>
                    <td><?php echo htmlspecialchars($r['reason'] ?? ''); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $r['status'] === 'confirmed' ? 'success' : ($r['status'] === 'cancelled' ? 'danger' : ($r['status'] === 'completed' ? 'primary' : ($r['status'] === 'used' ? 'secondary' : 'warning'))); ?>">
                            <?php echo htmlspecialchars(ucfirst($r['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($r['qr_code_path'])): ?>
                            <a href="../view_res.php?id=<?php echo (int)$r['id']; ?>" target="_blank" rel="noopener">View QR</a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
    <?php endif; ?>
    </div>
    </section>
</div>

<div class="container-fluid admin-reservations-content mb-3 mt-3">
    <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
</div>
</main>

<div style="height: 1px;"></div>
<?php require '../footer.php'; ?>
