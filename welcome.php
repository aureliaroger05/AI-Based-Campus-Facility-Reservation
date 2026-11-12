<?php
$page_title = 'Welcome | Campus Facility Reservation';
$body_class = 'welcome-page';
require 'header.php';
?>

<main class="py-5">
    <div class="container">
        <section class="welcome-hero">
            <div class="welcome-hero-content">
                <span class="welcome-eyebrow"><i class="bi bi-stars me-2" aria-hidden="true"></i>Welcome to ACLC College Tacloban</span>
                <h1>Welcome to the Campus Facility Reservation System</h1>
                <p>Reserve campus facilities, check availability, and manage your bookings in one convenient place.</p>
                <a href="login.php" class="btn btn-light btn-lg welcome-sign-in">
                    Sign in to continue <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                </a>
                <div class="welcome-highlights" aria-label="System features">
                    <span><i class="bi bi-calendar-check" aria-hidden="true"></i> Easy reservations</span>
                    <span><i class="bi bi-qr-code" aria-hidden="true"></i> QR verification</span>
                    <span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Usage insights</span>
                </div>
            </div>
            <div class="welcome-hero-art" aria-hidden="true">
                <span class="welcome-art-orbit welcome-art-orbit-outer"></span>
                <span class="welcome-art-orbit welcome-art-orbit-inner"></span>
                <span class="welcome-art-icon"><i class="bi bi-building-check"></i></span>
                <span class="welcome-art-spark welcome-art-spark-one"><i class="bi bi-stars"></i></span>
                <span class="welcome-art-spark welcome-art-spark-two"><i class="bi bi-qr-code"></i></span>
            </div>
        </section>
    </div>
</main>

<?php require 'footer.php'; ?>
