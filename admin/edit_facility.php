<?php $page_title = 'Edit Facility'; $body_class = 'admin-form-page'; require '../header.php'; ?>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../auth.php';
require '../facilities.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$facilityId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$facility = $facilityId > 0 ? getFacility($facilityId) : null;

if (!$facility) {
    echo '<main class="py-4 admin-form-main"><div class="container admin-form-container"><div class="alert alert-danger" role="alert">Facility not found.</div><a href="index.php" class="btn btn-outline-secondary">Back to Dashboard</a></div></main>';
    require '../footer.php';
    exit;
}

$formError = '';
$formSuccess = false;
$facilityName = (string)$facility['name'];

if (isset($_POST['save'])) {
    $facilityName = trim((string)($_POST['name'] ?? ''));

    if ($facilityName === '') {
        $formError = 'Facility name is required.';
    } else {
        try {
            $formSuccess = updateFacility($facilityId, ['name' => $facilityName]);
            if (!$formSuccess) {
                $formError = 'The facility could not be updated.';
            }
        } catch (PDOException $exception) {
            error_log('Failed to update facility ' . $facilityId . ': ' . $exception->getMessage());
            $formError = 'The facility could not be saved. Please check the database and try again.';
        }
    }
}
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
                <a class="nav-link" href="index.php"><i class="bi bi-gear"></i> Admin</a>
                <a class="nav-link" href="ai-forecast.php"><i class="bi bi-graph-up"></i> Forecast</a>
                <a class="nav-link" href="reservations.php"><i class="bi bi-clipboard-check"></i> Reservations</a>
                <a class="nav-link" href="../logout.php" data-confirm="Are you sure you want to log out?"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </div>
    </div>
</nav>

<main class="py-4 admin-form-main">
    <div class="container admin-form-container">
        <div class="admin-page-heading">
            <span class="admin-heading-icon"><i class="bi bi-building-gear"></i></span>
            <div>
                <h2 class="mb-1">Edit Facility</h2>
                <p class="mb-0">Update the facility name shown during reservation.</p>
            </div>
        </div>

        <section class="card admin-form-card">
            <div class="card-header admin-section-heading">
                <span><i class="bi bi-building me-2"></i>Facility information</span>
            </div>
            <div class="card-body p-4">
                <?php if ($formSuccess): ?>
                    <div class="alert alert-success" role="alert">Facility updated.</div>
                <?php elseif ($formError !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($formError); ?></div>
                <?php endif; ?>

                <form method="POST" class="admin-facility-form">
                    <input type="hidden" name="id" value="<?php echo $facilityId; ?>">
                    <div class="mb-3">
                        <label class="form-label" for="facility-name">Facility name</label>
                        <input id="facility-name" type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($facilityName); ?>" maxlength="100" required autofocus>
                    </div>
                    <div class="admin-edit-user-actions">
                        <button type="submit" name="save" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Save Changes</button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<?php require '../footer.php'; ?>
