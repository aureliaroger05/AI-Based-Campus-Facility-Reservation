<?php
$page_title = 'Admin Dashboard';
$body_class = 'admin-dashboard-page';
require '../header.php';

session_start();
require '../auth.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}


$pdo = getDB();
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$facilities = $pdo->query("SELECT * FROM facilities ORDER BY id DESC")->fetchAll();
$reservations = $pdo->query("SELECT COUNT(*) as total FROM reservations")->fetch();
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
                <a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link" href="ai-forecast.php"><i class="bi bi-graph-up"></i> Forecast</a>
                <a class="nav-link" href="reservations.php"><i class="bi bi-clipboard-check"></i> Reservations</a>

                <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>

            </div>
        </div>
    </div>
</nav>

<main class="py-4 admin-dashboard-main">
    <div class="container-fluid admin-dashboard-content">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div class="admin-dashboard-heading">
                <span class="admin-heading-icon"><i class="bi bi-speedometer2"></i></span>
                <div>
                    <h2 class="mb-1">Admin Dashboard</h2>
                    <p class="mb-0">Overview and management for campus reservations.</p>
                </div>
            </div>
        </div>

<?php
require_once '../facilities.php';
require_once '../auth.php';


// Handle delete actions from dashboard
if (isset($_POST['delete_facility'])) {
    $ok = deleteFacility((int)$_POST['facility_id']);
    echo $ok ? '<div class="alert alert-success">Facility deleted.</div>' : '<div class="alert alert-danger">Unable to delete facility.</div>';
}

if (isset($_POST['delete_user'])) {
    $ok = deleteUser((int)$_POST['user_id']);
    echo $ok ? '<div class="alert alert-success">User deleted.</div>' : '<div class="alert alert-danger">Unable to delete user.</div>';
}

// Refresh lists after any delete action
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$facilities = $pdo->query("SELECT * FROM facilities ORDER BY id DESC")->fetchAll();
?>



    <div class="row g-3 admin-stat-row">
        <div class="col-sm-6 col-xl-4">
            <div class="card admin-stat-card admin-stat-users">
                <div class="card-body">
                    <span class="admin-stat-icon"><i class="bi bi-people-fill"></i></span>
                    <div><p class="admin-stat-label">Total Users</p>
                    <h2><?php echo count($users); ?></h2></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card admin-stat-card admin-stat-facilities">
                <div class="card-body">
                    <span class="admin-stat-icon"><i class="bi bi-building"></i></span>
                    <div><p class="admin-stat-label">Facilities</p>
                    <h2><?php echo count($facilities); ?></h2></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card admin-stat-card admin-stat-reservations">
                <div class="card-body">
                    <span class="admin-stat-icon"><i class="bi bi-calendar2-check"></i></span>
                    <div><p class="admin-stat-label">Reservations</p>
                    <h2><?php echo $reservations['total']; ?></h2></div>
                    <a href="reservations.php" class="btn btn-sm btn-outline-primary ms-auto">Manage</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1 admin-management-row">
        <div class="col-xl-6">
            <section class="card admin-management-card">
            <div class="card-header admin-section-heading">
                <div><i class="bi bi-people me-2"></i>Users</div>
                <span class="badge text-bg-light"><?php echo count($users); ?></span>
            </div>
            <div class="card-body">
            <div class="table-responsive">
            <table class="table table-striped align-middle admin-data-table">
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
                                <form method="POST" onsubmit="return confirm('Delete this user?');">
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
            <section class="card admin-management-card">
            <div class="card-header admin-section-heading">
                <div><i class="bi bi-building me-2"></i>Facilities</div>
                <span class="badge text-bg-light"><?php echo count($facilities); ?></span>
            </div>
            <div class="card-body">
            <div class="table-responsive">
            <table class="table table-striped align-middle admin-data-table">
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
                                <form method="POST" onsubmit="return confirm('Delete this facility?');">
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
            </div>
            </section>
        </div>
    </div>
    </div>





<?php require '../footer.php'; ?>
