<?php
$page_title = 'Admin Dashboard';
$body_class = 'admin-dashboard-page';
require '../auth.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

require_once '../facilities.php';

if (isset($_POST['delete_facility'])) {
    try {
        $deleted = deleteFacility((int)($_POST['facility_id'] ?? 0));
        $_SESSION['dashboard_notification'] = [
            'type' => $deleted ? 'success' : 'danger',
            'message' => $deleted ? 'Facility deleted successfully.' : 'Unable to delete facility.',
        ];
    } catch (PDOException $exception) {
        error_log('Failed to delete facility from admin dashboard: ' . $exception->getMessage());
        $_SESSION['dashboard_notification'] = [
            'type' => 'danger',
            'message' => 'Unable to delete facility. It may still be linked to reservations.',
        ];
    }
    header('Location: index.php');
    exit;
}

if (isset($_POST['delete_user'])) {
    try {
        $deleted = deleteUser((int)($_POST['user_id'] ?? 0));
        $_SESSION['dashboard_notification'] = [
            'type' => $deleted ? 'success' : 'danger',
            'message' => $deleted ? 'User deleted successfully.' : 'Unable to delete user.',
        ];
    } catch (PDOException $exception) {
        error_log('Failed to delete user from admin dashboard: ' . $exception->getMessage());
        $_SESSION['dashboard_notification'] = [
            'type' => 'danger',
            'message' => 'Unable to delete user. The account may still be linked to reservations.',
        ];
    }
    header('Location: index.php');
    exit;
}

$dashboardNotification = $_SESSION['dashboard_notification'] ?? null;
unset($_SESSION['dashboard_notification']);

require '../header.php';

$pdo = getDB();
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$facilities = $pdo->query("SELECT * FROM facilities ORDER BY id DESC")->fetchAll();
$reservations = $pdo->query("SELECT COUNT(*) as total FROM reservations")->fetch();
$pendingReservations = $pdo->query("SELECT COUNT(*) AS total FROM reservations WHERE status = 'pending'")->fetch();

$chartMonthStart = new DateTimeImmutable('first day of this month');
$chartStart = $chartMonthStart->modify('-5 months');
$chartEnd = $chartMonthStart->modify('+1 month');
$chartStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(reserve_date, '%Y-%m') AS reservation_month, COUNT(*) AS total " .
    "FROM reservations WHERE reserve_date >= ? AND reserve_date < ? AND status <> 'cancelled' " .
    "GROUP BY reservation_month ORDER BY reservation_month"
);
$chartStmt->execute([$chartStart->format('Y-m-d'), $chartEnd->format('Y-m-d')]);
$reservationTotalsByMonth = [];
foreach ($chartStmt->fetchAll() as $monthTotal) {
    $reservationTotalsByMonth[$monthTotal['reservation_month']] = (int)$monthTotal['total'];
}
$reservationChartLabels = [];
$reservationChartData = [];
for ($month = $chartStart; $month < $chartEnd; $month = $month->modify('+1 month')) {
    $monthKey = $month->format('Y-m');
    $reservationChartLabels[] = $month->format('M');
    $reservationChartData[] = $reservationTotalsByMonth[$monthKey] ?? 0;
}

$recentReservations = $pdo->query(
    "SELECT r.id, r.reserve_date, r.status, u.username, f.name AS facility " .
    "FROM reservations r " .
    "LEFT JOIN users u ON r.user_id = u.id " .
    "LEFT JOIN facilities f ON r.facility_id = f.id " .
    "ORDER BY r.created_at DESC LIMIT 4"
)->fetchAll();
?>

<div class="admin-dashboard-shell">
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">
        <a class="admin-sidebar-brand" href="index.php">
            <img src="../images/aclc logo.png" alt="">
            <span><strong>ACLC Reservation</strong><small>Campus workspace</small></span>
        </a>

        <nav class="admin-sidebar-nav">
            <span class="admin-nav-caption">Workspace</span>
            <a class="admin-sidebar-link is-active" href="index.php" data-spa-view="dashboard" aria-current="page"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
            <a class="admin-sidebar-link" href="reservations.php" data-spa-view="reservations"><i class="bi bi-calendar2-check"></i><span>Reservations</span></a>
            <a class="admin-sidebar-link" href="ai-forecast.php" data-spa-view="forecast"><i class="bi bi-graph-up-arrow"></i><span>Usage forecast</span></a>

            <span class="admin-nav-caption">Management</span>
            <a class="admin-sidebar-link" href="#users" data-spa-view="dashboard" data-spa-section="users"><i class="bi bi-people"></i><span>Users</span></a>
            <a class="admin-sidebar-link" href="#facilities" data-spa-view="dashboard" data-spa-section="facilities"><i class="bi bi-buildings"></i><span>Facilities</span></a>
            <a class="admin-sidebar-link" href="class_schedules.php" data-spa-view="schedules"><i class="bi bi-calendar3"></i><span>Class schedules</span></a>

            <span class="admin-nav-caption">Account</span>
            <a class="admin-sidebar-link" href="../index.php" data-spa-view="calendar"><i class="bi bi-house-door"></i><span>Reservation calendar</span></a>
            <a class="admin-sidebar-link" href="../profile.php" data-spa-view="profile"><i class="bi bi-person"></i><span>My profile</span></a>
        </nav>

        <div class="admin-sidebar-footer">
            <span class="admin-user-avatar"><?php echo htmlspecialchars(strtoupper(substr((string)($_SESSION['username'] ?? 'A'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="admin-sidebar-user"><strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></strong><small>Administrator</small></span>
            <a class="admin-logout-link" href="../logout.php" data-confirm="Are you sure you want to log out?" aria-label="Log out"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </aside>

    <button class="admin-sidebar-backdrop" type="button" aria-label="Close navigation" tabindex="-1"></button>

    <div class="admin-dashboard-workspace">
        <header class="admin-topbar">
            <button class="admin-sidebar-toggle" type="button" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <div class="admin-topbar-breadcrumb"><span>Workspace</span><i class="bi bi-chevron-right"></i><strong>Dashboard</strong></div>
            <div class="admin-live-status" data-admin-live-status data-state="connecting" role="status" aria-live="polite">
                <span class="admin-live-status-dot" aria-hidden="true"></span>
                <span>Connecting</span>
            </div>
            <label class="admin-dashboard-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span class="visually-hidden">Filter recent reservations</span>
                <input type="search" id="adminDashboardSearch" placeholder="Filter recent reservations..." autocomplete="off">
                <kbd>Ctrl K</kbd>
            </label>
            <div class="admin-topbar-tools">
                <button class="admin-topbar-icon-button" type="button" data-admin-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
                    <i class="bi bi-moon-stars" aria-hidden="true"></i>
                </button>
                <div class="admin-notifications">
                    <button class="admin-topbar-icon-button admin-notification-toggle" type="button" data-admin-notifications-toggle data-pending-count="<?php echo (int)$pendingReservations['total']; ?>" aria-label="Notifications" aria-expanded="false" aria-controls="adminNotificationPanel">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                        <span class="admin-notification-badge" data-admin-notification-badge<?php echo (int)$pendingReservations['total'] > 0 ? '' : ' hidden'; ?>><?php echo (int)$pendingReservations['total'] > 99 ? '99+' : (int)$pendingReservations['total']; ?></span>
                    </button>
                    <section class="admin-notification-panel" id="adminNotificationPanel" aria-label="Notifications" hidden>
                        <div class="admin-notification-panel-heading">
                            <strong>Notifications</strong>
                            <span>Live updates</span>
                        </div>
                        <div class="admin-notification-item" aria-live="polite">
                            <span class="admin-notification-item-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                            <div>
                                <strong>Reservation requests</strong>
                                <p data-admin-notification-message><?php echo (int)$pendingReservations['total'] > 0 ? (int)$pendingReservations['total'] . ' reservation' . ((int)$pendingReservations['total'] === 1 ? '' : 's') . ' waiting for review.' : 'You are all caught up.'; ?></p>
                            </div>
                        </div>
                        <a href="reservations.php" data-admin-shell-view="reservations">Review reservations<i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </section>
                </div>
            </div>
            <div class="admin-topbar-user">
                <span class="admin-user-avatar"><?php echo htmlspecialchars(strtoupper(substr((string)($_SESSION['username'] ?? 'A'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                <span><strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></strong><small>Administrator</small></span>
            </div>
        </header>

<main class="admin-dashboard-main">
    <div class="container-fluid admin-dashboard-content">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div class="admin-dashboard-heading admin-shell-view-heading">
                <span class="admin-heading-icon"><i class="bi bi-speedometer2"></i></span>
                <div>
                    <h2 class="mb-1">Good day, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="mb-0">Here is your campus reservation overview.</p>
                </div>
            </div>
        </div>

    <div class="row g-3 admin-stat-row">
        <div class="col-6 col-xl-3">
            <div class="card admin-stat-card admin-stat-users">
                <div class="card-body">
                    <span class="admin-stat-icon"><i class="bi bi-people-fill"></i></span>
                    <div><p class="admin-stat-label">Total Users</p>
                    <h2><?php echo count($users); ?></h2></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card admin-stat-card admin-stat-facilities">
                <div class="card-body">
                    <span class="admin-stat-icon"><i class="bi bi-building"></i></span>
                    <div><p class="admin-stat-label">Facilities</p>
                    <h2><?php echo count($facilities); ?></h2></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card admin-stat-card admin-stat-reservations">
                <div class="card-body">
                    <span class="admin-stat-icon"><i class="bi bi-calendar2-check"></i></span>
                    <div><p class="admin-stat-label">Reservations</p>
                    <h2><?php echo $reservations['total']; ?></h2></div>
                    <a href="reservations.php" class="btn btn-sm btn-outline-primary ms-auto">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card admin-stat-card admin-stat-pending">
                <div class="card-body">
                    <span class="admin-stat-icon"><i class="bi bi-hourglass-split"></i></span>
                    <div><p class="admin-stat-label">Pending requests</p>
                    <h2><?php echo (int)$pendingReservations['total']; ?></h2></div>
                    <a href="reservations.php" class="btn btn-sm btn-outline-primary ms-auto">Review</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1 admin-insights-row">
        <div class="col-xl-8">
            <section class="card admin-insights-card h-100">
                <div class="card-header admin-insights-heading">
                    <div>
                        <h3 class="h6 mb-1">Reservation activity</h3>
                        <p class="small mb-0">Non-cancelled reservations over the last six months</p>
                    </div>
                    <span class="admin-insights-icon"><i class="bi bi-calendar3"></i></span>
                </div>
                <div class="card-body">
                    <div class="admin-reservation-chart-wrap">
                        <canvas id="adminReservationChart" aria-label="Reservations by month" role="img"></canvas>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="card admin-insights-card h-100">
                <div class="card-header admin-insights-heading">
                    <div>
                        <h3 class="h6 mb-1">Recent reservations</h3>
                        <p class="small mb-0">Latest booking activity</p>
                    </div>
                    <a class="admin-insights-link" href="reservations.php">View all <i class="bi bi-arrow-up-right"></i></a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recentReservations): ?>
                        <ul class="admin-recent-list">
                            <?php foreach ($recentReservations as $recentReservation): ?>
                                <?php
                                $statusClass = [
                                    'pending' => 'pending',
                                    'confirmed' => 'confirmed',
                                    'completed' => 'completed',
                                    'cancelled' => 'cancelled',
                                ][$recentReservation['status']] ?? 'pending';
                                ?>
                                <li class="admin-recent-item" data-admin-search-item>
                                    <span class="admin-recent-icon"><i class="bi bi-calendar2-check"></i></span>
                                    <div class="admin-recent-details">
                                        <strong><?php echo htmlspecialchars($recentReservation['facility'] ?? 'Facility unavailable', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo htmlspecialchars($recentReservation['username'] ?? 'User unavailable', ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(date('M j', strtotime($recentReservation['reserve_date'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <span class="admin-status-pill admin-status-<?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($recentReservation['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="admin-recent-empty">
                            <i class="bi bi-calendar2-x"></i>
                            <span>No reservations yet</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent border-top-0 d-flex flex-wrap gap-2 py-3">
                    <a href="class_schedules.php" class="btn btn-outline-primary"><i class="bi bi-upload me-2"></i>Import schedule</a>
                    <a href="add_facility.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Add facility</a>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mt-1 admin-management-row">
        <div class="col-xl-6">
            <section class="card admin-management-card" id="users">
            <div class="card-header admin-section-heading">
                <div><i class="bi bi-people me-2"></i>Users</div>
                <span class="badge text-bg-light"><?php echo count($users); ?></span>
            </div>
            <div class="card-body">
            <div class="table-responsive">
            <table class="table table-striped align-middle admin-data-table" data-admin-search-table data-pagination-page-size="10" data-pagination-label="Users">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo (int)$u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($u['role'])); ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="edit_user.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <form method="POST" data-confirm="Delete this user?">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <a href="add_user.php" class="btn btn-primary">Add User</a>
            </div>
            </section>
        </div>

        <div class="col-xl-6">
            <section class="card admin-management-card" id="facilities">
            <div class="card-header admin-section-heading">
                <div><i class="bi bi-building me-2"></i>Facilities</div>
                <span class="badge text-bg-light"><?php echo count($facilities); ?></span>
            </div>
            <div class="card-body">
            <div class="table-responsive">
            <table class="table table-striped align-middle admin-data-table" data-admin-search-table data-pagination-page-size="10" data-pagination-label="Facilities">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($facilities as $f): ?>
                    <tr>
                        <td><?php echo (int)$f['id']; ?></td>
                        <td><?php echo htmlspecialchars($f['name']); ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="edit_facility.php?id=<?php echo (int)$f['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <form method="POST" data-confirm="Delete this facility?">
                                    <input type="hidden" name="facility_id" value="<?php echo (int)$f['id']; ?>">
                                    <button type="submit" name="delete_facility" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <a href="add_facility.php" class="btn btn-success mt-2">Add Facility</a>
            <a href="class_schedules.php" class="btn btn-outline-primary mt-2 ms-1">Import Class Schedule</a>
            </div>
            </section>
        </div>
    </div>
    <p class="admin-search-empty" id="adminSearchEmpty" hidden>No matching users, facilities, or recent reservations.</p>
    </div>

<?php if (is_array($dashboardNotification) && isset($dashboardNotification['message'])): ?>
    <?php $notificationIsSuccess = ($dashboardNotification['type'] ?? '') === 'success'; ?>
    <div class="modal fade action-result-modal<?php echo $notificationIsSuccess ? '' : ' action-result-modal-error'; ?>" id="dashboardResultModal" tabindex="-1" aria-labelledby="dashboardResultTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="action-result-icon" aria-hidden="true">
                    <i class="bi <?php echo $notificationIsSuccess ? 'bi-check-lg' : 'bi-exclamation-lg'; ?>"></i>
                </div>
                <div class="modal-header border-0 justify-content-center pb-0">
                    <h2 class="modal-title fs-4" id="dashboardResultTitle"><?php echo $notificationIsSuccess ? 'Success' : 'Action not completed'; ?></h2>
                </div>
                <div class="modal-body text-center px-4"><?php echo htmlspecialchars((string)$dashboardNotification['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="modal-footer border-0 justify-content-center pt-0">
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        function showDashboardResultModal() {
            const resultModal = document.getElementById('dashboardResultModal');
            if (resultModal && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(resultModal).show();
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showDashboardResultModal, { once: true });
        } else {
            showDashboardResultModal();
        }
    </script>
<?php endif; ?>

</main>

<script data-admin-dashboard-chart>
    document.addEventListener('DOMContentLoaded', function () {
        window.initializeAdminReservationChart = function () {
            const chartCanvas = document.getElementById('adminReservationChart');
            if (!chartCanvas || !window.Chart) return;
            const existingChart = Chart.getChart(chartCanvas);
            if (existingChart) existingChart.destroy();
            const isDarkTheme = document.documentElement.dataset.adminTheme === 'dark';
            const chartTickColor = isDarkTheme ? '#c1ccda' : '#788397';
            const chartGridColor = isDarkTheme
                ? 'rgba(170, 184, 204, 0.2)'
                : 'rgba(120, 131, 151, 0.14)';

            new Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($reservationChartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                datasets: [{
                    label: 'Reservations',
                    data: <?php echo json_encode($reservationChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                    borderColor: '#6865e8',
                    backgroundColor: 'rgba(104, 101, 232, 0.12)',
                    borderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.38
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { intersect: false, mode: 'index' }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: chartTickColor }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: chartTickColor },
                        grid: { color: chartGridColor, borderDash: [4, 4] }
                    }
                }
            }
            });
        };
        window.initializeAdminReservationChart();
    });

    document.addEventListener('admin:view-loaded', function (event) {
        if (event.detail && event.detail.view === 'dashboard' && window.initializeAdminReservationChart) {
            window.initializeAdminReservationChart();
        }
    });
</script>

<script data-admin-shell-script>
    document.addEventListener('DOMContentLoaded', function () {
        const shell = document.querySelector('.admin-dashboard-shell');
        const sidebarToggle = document.querySelector('.admin-sidebar-toggle');
        const sidebarBackdrop = document.querySelector('.admin-sidebar-backdrop');
        const sidebarLinks = document.querySelectorAll('.admin-sidebar-link');
        const searchInput = document.getElementById('adminDashboardSearch');
        const searchEmpty = document.getElementById('adminSearchEmpty');

        function setSidebarOpen(isOpen) {
            if (!shell || !sidebarToggle) return;
            shell.classList.toggle('is-sidebar-open', isOpen);
            sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            sidebarToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                setSidebarOpen(!shell.classList.contains('is-sidebar-open'));
            });
        }
        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', function () {
                setSidebarOpen(false);
            });
        }
        sidebarLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                setSidebarOpen(false);
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const query = searchInput.value.trim().toLocaleLowerCase();
                let visibleMatches = 0;

                document.querySelectorAll('[data-admin-search-table] tbody tr').forEach(function (row) {
                    const hiddenManagementRow = row.closest('.admin-management-row');
                    const matches = !query || row.textContent.toLocaleLowerCase().includes(query);
                    row.dataset.tableSearchMatch = matches ? 'true' : 'false';
                    row.hidden = !matches;
                    if (hiddenManagementRow && getComputedStyle(hiddenManagementRow).display === 'none') return;
                    if (matches && query) visibleMatches++;
                });

                document.querySelectorAll('[data-admin-search-item]').forEach(function (item) {
                    const matches = !query || item.textContent.toLocaleLowerCase().includes(query);
                    item.hidden = !matches;
                    if (matches && query) visibleMatches++;
                });

                if (searchEmpty) {
                    searchEmpty.hidden = !query || visibleMatches > 0;
                }
                if (document.querySelector('[data-admin-search-table]')) {
                    document.dispatchEvent(new CustomEvent('table-pagination:refresh'));
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && searchInput) {
                event.preventDefault();
                searchInput.focus();
            }
        });
    });
</script>

<script src="../assets/js/admin-navigation.js?v=<?php echo (int)filemtime(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'admin-navigation.js'); ?>"></script>

</div>
</div>

<?php require '../footer.php'; ?>
