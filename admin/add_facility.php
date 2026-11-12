<?php
$page_title = 'Add Facility';
$body_class = 'admin-form-page';
require '../auth.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

require '../header.php';
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
                <a class="nav-link" href="reservations.php"><i class="bi bi-clipboard-check"></i> Reservations</a>

                <a class="nav-link" href="../logout.php" data-confirm="Are you sure you want to log out?"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </div>
    </div>
</nav>


<?php
    $formError = '';
    $formSuccess = false;

    if (isset($_POST['add'])) {
        $name = trim((string)($_POST['name'] ?? ''));

        if ($name === '') {
            $formError = 'Facility name is required.';
        } else {
            require '../facilities.php';

            try {
                $formSuccess = addFacility(['name' => $name]);
                if (!$formSuccess) {
                    $formError = 'The facility could not be added.';
                }
            } catch (PDOException $e) {
                error_log('Failed to add facility: ' . $e->getMessage());
                $formError = 'The facility could not be saved. Please check the database and try again.';
            }
        }
    }
?>
<main class="py-4 admin-form-main">
    <div class="container admin-form-container">
        <div class="admin-page-heading">
            <span class="admin-heading-icon"><i class="bi bi-building-add"></i></span>
            <div>
                <h2 class="mb-1">Add Facility</h2>
                <p class="mb-0">Add a campus space for users to reserve.</p>
            </div>
        </div>

        <section class="card admin-form-card">
            <div class="card-header admin-section-heading">
                <span><i class="bi bi-building me-2"></i>Facility information</span>
            </div>
            <div class="card-body">
                <?php if ($formSuccess): ?>
                    <div class="alert alert-success" role="status">Facility added.</div>
                <?php elseif ($formError !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($formError); ?></div>
                <?php endif; ?>

                <form method="POST" class="admin-facility-form" data-confirm="Are you sure you want to add this facility?">
                    <div class="mb-3">
                        <label class="form-label" for="facility-name">Facility name</label>
                        <input id="facility-name" type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" maxlength="100" required>
                    </div>
                    <div class="admin-edit-user-actions">
                        <button type="submit" name="add" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Facility</button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<?php require '../footer.php'; ?>
