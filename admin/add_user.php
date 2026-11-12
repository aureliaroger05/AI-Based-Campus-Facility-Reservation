<?php
$page_title = 'Add User';
$body_class = 'admin-form-page';
require '../auth.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$notification = null;
if (isset($_POST['add'])) {
    if (addUser($_POST)) {
        $notification = ['success', 'User added.'];
    } else {
        $notification = ['danger', 'Error adding user.'];
    }
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

<main class="py-4 admin-form-main">
    <div class="container admin-form-container">
        <div class="admin-page-heading">
            <span class="admin-heading-icon"><i class="bi bi-person-plus"></i></span>
            <div>
                <h2 class="mb-1">Add New User</h2>
                <p class="mb-0">Create a campus account and assign its role.</p>
            </div>
        </div>
        <section class="card admin-form-card">
            <div class="card-header admin-section-heading">
                <span><i class="bi bi-person-vcard me-2"></i>User information</span>
            </div>
            <div class="card-body p-4">
        <form method="POST" class="admin-user-form" data-confirm="Are you sure you want to add this user?">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" name="username" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required minlength="8" pattern="(?=.*\d)(?=.*[A-Za-z]).{8,}" title="Password must be at least 8 characters and include at least one letter and one number" id="passwordField">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="showPassword" />
                    <label class="form-check-label" for="showPassword">Show password</label>
                </div>
            </div>

            <script>
                (function(){
                    const cb = document.getElementById('showPassword');
                    const pw = document.getElementById('passwordField');
                    if(!cb || !pw) return;
                    cb.addEventListener('change', function(){
                        pw.type = cb.checked ? 'text' : 'password';
                    });
                })();
            </script>

            <div class="mb-3">
                <label class="form-label">Name</label>
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <input type="text" class="form-control form-control-sm" name="first_name" placeholder="First" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="text" class="form-control form-control-sm" name="middle_name" placeholder="Middle">
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="text" class="form-control form-control-sm" name="last_name" placeholder="Last" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="text" class="form-control form-control-sm" name="suffix" placeholder="Suffix (Jr.)">
                    </div>
                </div>
            </div>


            <div class="mb-3">
                <label class="form-label">Birthdate</label>
                <input type="date" class="form-control" name="birthdate">
            </div>

            <div class="mb-3">
                <label class="form-label">Gender</label>
                <select class="form-control" name="gender">
                    <option value="">Prefer not to say / N/A</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                    <option value="prefer_not_to_say">Prefer not to say</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone (10 digits after +63)</label>
                <input type="tel" class="form-control" name="phone" required pattern="^\d{10}$" inputmode="numeric" placeholder="e.g., 9123456789" title="Phone must be exactly 10 digits (without +63)">
                <small class="text-muted">Prefix is automatically assumed as +63</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <select class="form-control" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="d-flex flex-wrap gap-2 pt-2">
            <button type="submit" name="add" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Add User</button>

            <a href="index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
        </form>
        </section>
    </div>
</main>

<?php if ($notification !== null): ?>
    <div class="modal fade" id="userNotificationModal" tabindex="-1" aria-labelledby="userNotificationTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="userNotificationTitle">
                        <?php echo $notification[0] === 'success' ? 'Success' : 'Unable to add user'; ?>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"><?php echo htmlspecialchars($notification[1], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const notificationModal = document.getElementById('userNotificationModal');
            if (notificationModal && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(notificationModal).show();
            }
        });
    </script>
<?php endif; ?>

<?php require '../footer.php'; ?>
