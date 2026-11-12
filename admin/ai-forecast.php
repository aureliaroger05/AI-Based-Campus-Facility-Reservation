<?php
$page_title = 'AI Usage Forecast - Admin';
$body_class = 'admin-forecast-page';
require '../auth.php';
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

require '../header.php';

$pdo = getDB();
require '../ai_forecast.php';
$forecasts = getAllForecasts();
$forecastFacilityCount = 0;
$forecastPointCount = 0;
$forecastWindow = 0;

foreach ($forecasts as $fcast) {
    if (empty($fcast)) {
        continue;
    }

    $forecastFacilityCount++;
    $forecastPointCount += count($fcast);
    $forecastWindow = max($forecastWindow, count($fcast));
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
                <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link active" href="ai-forecast.php"><i class="bi bi-graph-up"></i> Forecast</a>
                <a class="nav-link" href="reservations.php"><i class="bi bi-clipboard-check"></i> Reservations</a>
                <a class="nav-link" href="../logout.php" data-confirm="Are you sure you want to log out?"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </div>
    </div>
</nav>

<main class="py-4 admin-forecast-main">
    <div class="container admin-forecast-content">
        <section class="forecast-hero">
            <div class="forecast-hero-copy">
                <span class="forecast-eyebrow"><i class="bi bi-stars me-2"></i>Operations intelligence</span>
                <h1>Usage forecast</h1>
                <p>Plan ahead with AI-powered facility demand projections based on recent reservation logs.</p>
            </div>
            <div class="forecast-hero-mark" aria-hidden="true">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
        </section>

        <section class="forecast-summary" aria-label="Forecast summary">
            <article class="forecast-summary-card">
                <span class="forecast-summary-icon forecast-summary-blue"><i class="bi bi-buildings"></i></span>
                <div>
                    <span class="forecast-summary-label">Facilities tracked</span>
                    <strong><?php echo $forecastFacilityCount; ?></strong>
                </div>
            </article>
            <article class="forecast-summary-card">
                <span class="forecast-summary-icon forecast-summary-violet"><i class="bi bi-graph-up"></i></span>
                <div>
                    <span class="forecast-summary-label">Forecast points</span>
                    <strong><?php echo $forecastPointCount; ?></strong>
                </div>
            </article>
            <article class="forecast-summary-card">
                <span class="forecast-summary-icon forecast-summary-green"><i class="bi bi-calendar3"></i></span>
                <div>
                    <span class="forecast-summary-label">Longest outlook</span>
                    <strong><?php echo $forecastWindow > 0 ? $forecastWindow . ' days' : '—'; ?></strong>
                </div>
            </article>
        </section>

        <div class="forecast-section-heading">
            <div>
                <span class="forecast-section-kicker">Facility insights</span>
                <h2>Upcoming demand</h2>
            </div>
            <?php if ($forecastFacilityCount > 0): ?>
                <span class="forecast-live-indicator"><span></span>Forecast ready</span>
            <?php endif; ?>
        </div>

        <?php if ($forecastFacilityCount === 0): ?>
            <section class="forecast-empty-state">
                <span class="forecast-empty-icon"><i class="bi bi-bar-chart-line"></i></span>
                <h3>No forecasts to show yet</h3>
                <p>Once reservation activity is available, facility projections will appear here.</p>
                <a class="btn btn-primary" href="reservations.php"><i class="bi bi-calendar2-check me-2"></i>View reservations</a>
            </section>
        <?php else: ?>
        <div class="row g-4 forecast-grid">
            <?php foreach ($forecasts as $fid => $fcast): if (!empty($fcast)): ?>
                <?php
                $forecastLabels = array_column($fcast, 'date');
                $forecastValues = array_column($fcast, 'predicted_usage');
                $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
                $forecastLabelsJson = json_encode($forecastLabels, $jsonFlags);
                $forecastValuesJson = json_encode($forecastValues, $jsonFlags);
                ?>
                <div class="col-12 col-lg-6">
                    <section class="card forecast-card">
                        <div class="card-header forecast-card-heading">
                            <div>
                                <span class="forecast-facility-icon"><i class="bi bi-building"></i></span>
                                <div>
                                    <span class="forecast-card-kicker">Facility</span>
                                    <h3 class="mb-0">#<?php echo (int)$fid; ?></h3>
                                </div>
                            </div>
                            <span class="forecast-range-badge"><i class="bi bi-calendar3 me-1"></i><?php echo count($fcast); ?> day<?php echo count($fcast) === 1 ? '' : 's'; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="forecast-chart-label">
                                <span>Projected reservations</span>
                                <span><i class="bi bi-arrow-up-right"></i> Daily trend</span>
                            </div>
                            <div class="forecast-chart-wrap">
                                <canvas id="adminForecast<?php echo (int)$fid; ?>"></canvas>
                            </div>
                        </div>
                    </section>
                </div>

                <script>
                    (function(){
                        const el = document.getElementById('adminForecast<?php echo (int)$fid; ?>');
                        if(!el) return;
                        new Chart(el, {
                            type: 'line',
                            data: {
                                labels: <?php echo $forecastLabelsJson; ?>,
                                datasets: [{
                                    label: 'Forecasted Usage',
                                    data: <?php echo $forecastValuesJson; ?>,
                                    borderColor: '#1769aa',
                                    backgroundColor: 'rgba(23,105,170,0.12)',
                                    pointBackgroundColor: '#1769aa',
                                    pointBorderColor: '#1769aa',
                                    pointBorderWidth: 3,
                                    pointRadius: 3,
                                    pointHoverRadius: 6,
                                    fill: true,
                                    tension: 0.35
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        border: { display: false },
                                        grid: { color: 'rgba(100,116,139,0.1)' },
                                        ticks: { precision: 0, padding: 10 }
                                    },
                                    x: {
                                        border: { display: false },
                                        grid: { display: false },
                                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 7 }
                                    }
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: '#172b4d',
                                        titleFont: { weight: '600' },
                                        padding: 12,
                                        displayColors: false,
                                        callbacks: {
                                            label: function(context) { return ' ' + context.parsed.y + ' projected reservations'; }
                                        }
                                    }
                                }
                            }
                        });
                    })();
                </script>
            <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<?php require '../footer.php'; ?>
