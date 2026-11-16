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

$facilityNames = [];
foreach (getFacilities() as $facility) {
    $facilityNames[(int)$facility['id']] = (string)$facility['name'];
}

$monthlyForecasts = [];
$reportRows = [];
foreach ($forecasts as $facilityId => $facilityForecast) {
    $facilityId = (int)$facilityId;
    $facilityName = $facilityNames[$facilityId] ?? 'Facility #' . $facilityId;

    foreach ($facilityForecast as $point) {
        $forecastDate = new DateTimeImmutable($point['date']);
        $monthKey = $forecastDate->format('Y-m');
        $predictedUsage = max(0, (int)$point['predicted_usage']);
        $dailyRow = [
            'month' => $forecastDate->format('F Y'),
            'facility' => $facilityName,
            'date' => $forecastDate->format('Y-m-d'),
            'predicted_usage' => $predictedUsage,
        ];

        if (!isset($monthlyForecasts[$monthKey])) {
            $monthlyForecasts[$monthKey] = [
                'label' => $forecastDate->format('F Y'),
                'total' => 0,
                'days' => [],
                'facilities' => [],
                'rows' => [],
            ];
        }

        if (!isset($monthlyForecasts[$monthKey]['facilities'][$facilityId])) {
            $monthlyForecasts[$monthKey]['facilities'][$facilityId] = [
                'name' => $facilityName,
                'total' => 0,
                'days' => 0,
                'peak_date' => $dailyRow['date'],
                'peak_usage' => -1,
            ];
        }

        $monthlyForecasts[$monthKey]['total'] += $predictedUsage;
        $monthlyForecasts[$monthKey]['days'][$dailyRow['date']] = true;
        $monthlyForecasts[$monthKey]['facilities'][$facilityId]['total'] += $predictedUsage;
        $monthlyForecasts[$monthKey]['facilities'][$facilityId]['days']++;
        if ($predictedUsage > $monthlyForecasts[$monthKey]['facilities'][$facilityId]['peak_usage']) {
            $monthlyForecasts[$monthKey]['facilities'][$facilityId]['peak_usage'] = $predictedUsage;
            $monthlyForecasts[$monthKey]['facilities'][$facilityId]['peak_date'] = $dailyRow['date'];
        }
        $monthlyForecasts[$monthKey]['rows'][] = $dailyRow;
        $reportRows[] = $dailyRow;
    }
}

ksort($monthlyForecasts);
foreach ($monthlyForecasts as &$monthForecast) {
    $monthForecast['days'] = count($monthForecast['days']);
    uasort($monthForecast['facilities'], function ($left, $right) {
        return strcasecmp($left['name'], $right['name']);
    });
    usort($monthForecast['rows'], function ($left, $right) {
        $dateOrder = strcmp($left['date'], $right['date']);
        return $dateOrder !== 0 ? $dateOrder : strcasecmp($left['facility'], $right['facility']);
    });
}
unset($monthForecast);

$reportJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
$reportRowsJson = json_encode($reportRows, $reportJsonFlags);
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
                <p>Plan ahead with a 90-day facility demand outlook grouped by calendar month.</p>
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

        <?php if (!empty($monthlyForecasts)): ?>
            <section class="forecast-monthly-section" aria-labelledby="forecast-monthly-title">
                <div class="forecast-monthly-heading">
                    <div>
                        <span class="forecast-section-kicker">Monthly outlook</span>
                        <h2 id="forecast-monthly-title">Demand clustered by month</h2>
                        <p>Monthly totals are broken down by facility and busiest forecast day.</p>
                    </div>
                    <button
                        type="button"
                        class="btn btn-primary"
                        id="generateForecastReport"
                        aria-expanded="false"
                        aria-controls="forecastDetailedReport"
                    >
                        <i class="bi bi-file-earmark-bar-graph me-2" aria-hidden="true"></i>Generate detailed report
                    </button>
                </div>

                <div class="forecast-month-grid">
                    <?php foreach ($monthlyForecasts as $monthForecast): ?>
                        <article class="forecast-month-card">
                            <div class="forecast-month-card-heading">
                                <div>
                                    <span class="forecast-card-kicker">Forecast month</span>
                                    <h3><?php echo htmlspecialchars($monthForecast['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                </div>
                                <span class="forecast-month-total"><?php echo (int)$monthForecast['total']; ?><small> projected</small></span>
                            </div>
                            <p class="forecast-month-meta"><?php echo (int)$monthForecast['days']; ?> forecast days · <?php echo count($monthForecast['facilities']); ?> facilities</p>
                            <ul class="forecast-month-facilities">
                                <?php foreach ($monthForecast['facilities'] as $facilityForecast): ?>
                                    <li>
                                        <span>
                                            <strong><?php echo htmlspecialchars($facilityForecast['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <small>Peak <?php echo htmlspecialchars(date('M j', strtotime($facilityForecast['peak_date'])), ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int)$facilityForecast['peak_usage']; ?> projected</small>
                                        </span>
                                        <b><?php echo (int)$facilityForecast['total']; ?></b>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>

                <section class="forecast-detailed-report" id="forecastDetailedReport" hidden aria-labelledby="forecast-report-title">
                    <div class="forecast-report-heading">
                        <div>
                            <span class="forecast-section-kicker">Generated report</span>
                            <h3 id="forecast-report-title">Detailed facility demand</h3>
                            <p>Daily projections are grouped into their forecast month and use up to 30 recent usage-log dates, averaged by weekday with a 5% adjustment.</p>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="downloadForecastReport">
                            <i class="bi bi-download me-2" aria-hidden="true"></i>Download CSV
                        </button>
                    </div>

                    <?php foreach ($monthlyForecasts as $monthForecast): ?>
                        <section class="forecast-report-month">
                            <div class="forecast-report-month-heading">
                                <h4><?php echo htmlspecialchars($monthForecast['label'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <span><?php echo (int)$monthForecast['total']; ?> projected · <?php echo count($monthForecast['rows']); ?> daily records</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped forecast-report-table mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Facility</th>
                                            <th scope="col">Forecast date</th>
                                            <th scope="col" class="text-end">Projected usage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthForecast['rows'] as $reportRow): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($reportRow['facility'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars(date('D, M j, Y', strtotime($reportRow['date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-end"><?php echo (int)$reportRow['predicted_usage']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </section>
            </section>
        <?php endif; ?>

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
                                    <h3 class="mb-0"><?php echo htmlspecialchars($facilityNames[(int)$fid] ?? 'Facility #' . (int)$fid, ENT_QUOTES, 'UTF-8'); ?></h3>
                                </div>
                            </div>
                            <span class="forecast-range-badge"><i class="bi bi-calendar3 me-1"></i><?php echo count($fcast); ?> day<?php echo count($fcast) === 1 ? '' : 's'; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="forecast-chart-label">
                                <span>Projected usage</span>
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
                        const isDarkTheme = document.documentElement.dataset.adminTheme === 'dark';
                        const chartTickColor = isDarkTheme ? '#c1ccda' : '#687b90';
                        const chartGridColor = isDarkTheme
                            ? 'rgba(170,184,204,0.2)'
                            : 'rgba(100,116,139,0.1)';
                        new Chart(el, {
                            type: 'line',
                            data: {
                                labels: <?php echo $forecastLabelsJson; ?>,
                                datasets: [{
                                    label: 'Forecasted usage',
                                    data: <?php echo $forecastValuesJson; ?>,
                                    borderColor: '#1769aa',
                                    backgroundColor: 'rgba(23,105,170,0.12)',
                                    pointBackgroundColor: '#1769aa',
                                    pointBorderColor: '#1769aa',
                                    pointBorderWidth: 3,
                                    pointRadius: 0,
                                    pointHoverRadius: 6,
                                    borderWidth: 2.5,
                                    fill: true,
                                    tension: 0.45,
                                    cubicInterpolationMode: 'monotone'
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
                                        grid: { color: chartGridColor },
                                        ticks: { precision: 0, padding: 10, color: chartTickColor }
                                    },
                                    x: {
                                        border: { display: false },
                                        grid: { display: false },
                                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 7, callback: function(value) { const label = this.getLabelForValue(value); const d = new Date(label + 'T00:00:00'); return isNaN(d) ? label : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }); }, color: chartTickColor }
                                    }
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: isDarkTheme ? '#25334a' : '#172b4d',
                                        titleColor: isDarkTheme ? '#f4f7fc' : '#fff',
                                        bodyColor: isDarkTheme ? '#e5eaf2' : '#fff',
                                        titleFont: { weight: '600' },
                                        padding: 12,
                                        displayColors: false,
                                        callbacks: {
                                            label: function(context) { return ' ' + context.parsed.y + ' projected usage'; }
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

        <script>
            (function () {
                const generateButton = document.getElementById('generateForecastReport');
                const report = document.getElementById('forecastDetailedReport');
                const downloadButton = document.getElementById('downloadForecastReport');
                if (!generateButton || !report || !downloadButton) {
                    return;
                }

                const reportRows = <?php echo $reportRowsJson; ?>;
                generateButton.addEventListener('click', function () {
                    const isExpanded = generateButton.getAttribute('aria-expanded') === 'true';
                    report.hidden = isExpanded;
                    generateButton.setAttribute('aria-expanded', String(!isExpanded));
                    generateButton.innerHTML = isExpanded
                        ? '<i class="bi bi-file-earmark-bar-graph me-2" aria-hidden="true"></i>Generate detailed report'
                        : '<i class="bi bi-chevron-up me-2" aria-hidden="true"></i>Hide detailed report';
                    if (!isExpanded) {
                        report.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });

                downloadButton.addEventListener('click', function () {
                    const columns = ['Month', 'Facility', 'Forecast date', 'Projected usage'];
                    const csvCell = function (value) {
                        let text = String(value === null || value === undefined ? '' : value);
                        if (/^[-=+@]/.test(text)) {
                            text = "'" + text;
                        }
                        return '"' + text.replace(/"/g, '""') + '"';
                    };
                    const csvLines = [columns].concat(reportRows.map(function (row) {
                        return [row.month, row.facility, row.date, row.predicted_usage];
                    })).map(function (row) {
                        return row.map(csvCell).join(',');
                    });
                    const blob = new Blob(['\uFEFF' + csvLines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'facility-usage-forecast.csv';
                    link.click();
                    window.setTimeout(function () {
                        URL.revokeObjectURL(url);
                    }, 1000);
                });
            })();
        </script>

    </div>
</main>

<?php require '../footer.php'; ?>
