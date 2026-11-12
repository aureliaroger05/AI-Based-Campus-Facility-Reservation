<?php
$page_title = 'Edit User';
$body_class = 'admin-form-page';
require '../auth.php';
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();

$user = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $user = $stmt->fetch();
}

if (!$user) {
    echo '<div class="container mt-5"><div class="alert alert-danger">User not found.</div><a href="index.php" class="btn btn-secondary">Back</a></div>';
    require '../footer.php';
    exit;
}

$updateError = '';
if (isset($_POST['save'])) {
    $data = [
        'username' => $_POST['username'],
        'role' => $_POST['role'],
        'first_name' => $_POST['first_name'] ?? null,
        'middle_name' => $_POST['middle_name'] ?? null,
        'last_name' => $_POST['last_name'] ?? null,
        'suffix' => $_POST['suffix'] ?? null,
        'birthdate' => $_POST['birthdate'] ?? null,
        'gender' => $_POST['gender'] ?? null,
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
    ];


    // Optional password
    if (!empty($_POST['password'])) {
        $data['password'] = $_POST['password'];
    }

    if (updateUser((int)$_POST['id'], $data)) {
        $_SESSION['dashboard_notification'] = [
            'type' => 'success',
            'message' => 'User updated successfully.',
        ];
        header('Location: index.php');
        exit;
    }

    $updateError = 'Failed to update user. Check the submitted information and try again.';
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
            <span class="admin-heading-icon"><i class="bi bi-person-gear"></i></span>
            <div>
                <h2 class="mb-1">Edit User</h2>
                <p class="mb-0">Update account details, contact information, and access role.</p>
            </div>
        </div>
        <section class="card admin-form-card">
            <div class="card-header admin-section-heading">
                <span><i class="bi bi-person-vcard me-2"></i>User information</span>
            </div>
            <div class="card-body p-4">
                <?php if ($updateError !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($updateError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="POST" class="admin-user-form admin-edit-user-form">
                    <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">

                    <div class="mb-3">
                        <label class="form-label" for="edit-username">Username</label>
                        <input id="edit-username" type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit-password">New Password</label>
                        <input id="edit-password" type="password" class="form-control" name="password" minlength="8" pattern="(?=.*\d)(?=.*[A-Za-z]).{8,}" title="Password must be at least 8 characters and include at least one letter and one number" autocomplete="new-password" aria-describedby="edit-password-help">
                        <small id="edit-password-help" class="text-muted">Leave blank to keep the current password.</small>
                    </div>

                    <div class="mb-3 admin-edit-user-name">
                        <label class="form-label">Name</label>
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <input type="text" class="form-control form-control-sm" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" placeholder="First name" aria-label="First name" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="text" class="form-control form-control-sm" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>" placeholder="Middle name" aria-label="Middle name">
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="text" class="form-control form-control-sm" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" placeholder="Last name" aria-label="Last name" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="text" class="form-control form-control-sm" name="suffix" value="<?php echo htmlspecialchars($user['suffix'] ?? ''); ?>" placeholder="Suffix (Jr.)" aria-label="Suffix">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit-birthdate">Birthdate</label>
                        <input id="edit-birthdate" type="date" class="form-control" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit-gender">Gender</label>
                        <select id="edit-gender" class="form-select" name="gender" required>
                            <?php
                                $g = $user['gender'] ?? '';
                                $options = ['male'=>'Male','female'=>'Female'];
                            ?>
                            <option value="" <?php echo ($g === '' || $g === null) ? 'selected' : ''; ?> disabled>Choose gender</option>
                            <?php foreach ($options as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($g === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit-email">Email</label>
                        <input id="edit-email" type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit-phone">Phone (10 digits after +63)</label>
                        <input id="edit-phone" type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required pattern="^\d{10}$" inputmode="numeric" placeholder="e.g., 9123456789">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit-role">Role</label>
                        <select id="edit-role" class="form-select" name="role" required>
                            <option value="user" <?php echo ($user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
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
