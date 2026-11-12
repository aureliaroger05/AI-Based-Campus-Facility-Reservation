<?php
$page_title = 'Login';
$body_class = 'login-page';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
if (isset($_POST['login'])) {
    require 'auth.php';

    if (login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        if (isAdmin() && !empty($_SESSION['admin_scan_return'])) {
            $scanReturn = $_SESSION['admin_scan_return'];
            unset($_SESSION['admin_scan_return']);
            header('Location: ' . $scanReturn);
            exit;
        }
        if (!isAdmin() && !empty($_SESSION['admin_scan_return'])) {
            $message = 'Only an administrator account can scan a reservation QR code.';
        } else {
            header('Location: index.php');
            exit;
        }
    } else {
        $message = 'Invalid credentials';
    }
}

require 'header.php';
?>

<main class="py-5">
    <div class="container login-container">
        <div class="login-layout">
            <section class="login-welcome-panel">
                <span class="login-welcome-eyebrow"><i class="bi bi-stars me-2" aria-hidden="true"></i>Your campus, connected</span>
                <h1>Good to have you here.</h1>
                <p>Sign in to reserve facilities, keep track of your bookings, and make your next campus event happen.</p>
                <div class="login-feature-list">
                    <span><i class="bi bi-calendar-check" aria-hidden="true"></i> Simple facility booking</span>
                    <span><i class="bi bi-qr-code" aria-hidden="true"></i> Quick QR verification</span>
                    <span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Helpful usage forecasts</span>
                </div>
                <div class="login-welcome-art" aria-hidden="true">
                    <span class="login-art-ring login-art-ring-outer"></span>
                    <span class="login-art-ring login-art-ring-inner"></span>
                    <span class="login-art-building"><i class="bi bi-building-check"></i></span>
                    <span class="login-art-spark"><i class="bi bi-stars"></i></span>
                </div>
            </section>

            <section class="card login-card">
                <div class="card-header login-card-heading">
                    <span class="login-card-icon"><i class="bi bi-person-lock" aria-hidden="true"></i></span>
                    <div>
                        <span class="login-card-kicker">Welcome back</span>
                        <h2 class="mb-0">Sign in</h2>
                    </div>
                </div>
                <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-danger mb-3" role="alert"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>

                        <p class="login-card-description"><?php echo !empty($_SESSION['admin_scan_return']) ? 'Sign in with an administrator account to complete this QR check-in.' : 'Use your account to continue to campus reservations.'; ?></p>
                        <form method="POST" novalidate>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="loginUsername">Username</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="loginUsername"
                                    name="username"
                                    autocomplete="username"
                                    required
                                    placeholder="Enter your username"
                                >
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-semibold" for="loginPasswordField">Password</label>
                                <input
                                    type="password"
                                    class="form-control"
                                    name="password"
                                    id="loginPasswordField"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Enter your password"
                                >

                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="showLoginPassword" />
                                    <label class="form-check-label" for="showLoginPassword">Show password</label>
                                </div>
                            </div>

                            <script>
                                (function () {
                                    const cb = document.getElementById('showLoginPassword');
                                    const pw = document.getElementById('loginPasswordField');
                                    if (!cb || !pw) return;
                                    cb.addEventListener('change', function () {
                                        pw.type = cb.checked ? 'text' : 'password';
                                    });
                                })();
                            </script>

                            <button type="submit" name="login" class="btn btn-primary w-100 mt-3 py-2 fw-bold login-submit">
                                Sign in <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                            </button>

                            <div class="login-security-note">
                                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                <small>Sign in with the credentials provided for your account.</small>
                            </div>
                        </form>
                </div>
            </section>
        </div>
    </div>
</main>

<?php require 'footer.php'; ?>
