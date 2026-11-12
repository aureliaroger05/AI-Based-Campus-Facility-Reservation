<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'auth.php';
if (!isLoggedIn()) {
    header('Location: welcome.php');
    exit;
}
$page_title = 'Facility Reservation Calendar';
$body_class = 'calendar-page';
require 'header.php';

if (!function_exists('timeToMinutesFromString')) {
    function timeToMinutesFromString($timeValue) {
        if (!$timeValue) {
            return 0;
        }

        $parts = explode(':', (string) $timeValue);
        $hours = (int)($parts[0] ?? 0);
        $minutes = (int)($parts[1] ?? 0);
        $seconds = (int)($parts[2] ?? 0);

        return ($hours * 60) + $minutes + ($seconds / 60);
    }
}

if (!function_exists('getFacilityMonthStatuses')) {
    function getFacilityMonthStatuses($facility_id, $firstDate, $lastDate) {
        $pdo = getDB();

        try {
            $stmt = $pdo->prepare(
                "SELECT reserve_date, start_time, end_time, reserve_time FROM reservations " .
                "WHERE facility_id = ? AND reserve_date BETWEEN ? AND ? AND status != 'cancelled' " .
                "ORDER BY reserve_date ASC, start_time ASC"
            );
            $stmt->execute([$facility_id, $firstDate, $lastDate]);
            $reservations = $stmt->fetchAll();
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S22' && (int)($e->errorInfo[1] ?? 0) !== 1054) {
                throw $e;
            }
            $stmt = $pdo->prepare(
                "SELECT reserve_date, reserve_time FROM reservations " .
                "WHERE facility_id = ? AND reserve_date BETWEEN ? AND ? AND status != 'cancelled' " .
                "ORDER BY reserve_date ASC, reserve_time ASC"
            );
            $stmt->execute([$facility_id, $firstDate, $lastDate]);
            $reservations = $stmt->fetchAll();
        }

        $intervalsByDate = [];

        foreach ($reservations as $reservation) {
            $hasRange = isset($reservation['start_time'], $reservation['end_time'])
                && ($reservation['start_time'] !== '00:00:00' || $reservation['end_time'] !== '00:00:00');
            if (!$hasRange) {
                $startMinutes = timeToMinutesFromString($reservation['reserve_time'] ?? '00:00:00');
                $endMinutes = $startMinutes + 1;
            } else {
                $startMinutes = timeToMinutesFromString($reservation['start_time']);
                $endMinutes = timeToMinutesFromString($reservation['end_time']);
            }

            $intervalsByDate[$reservation['reserve_date']][] = [
                max(0, $startMinutes),
                min(1440, $endMinutes),
            ];
        }

        $statuses = [];
        foreach ($intervalsByDate as $date => $intervals) {
            usort($intervals, function ($left, $right) {
                return $left[0] <=> $right[0];
            });

            $mergedIntervals = [];
            $rangeStart = null;
            $rangeEnd = null;

            foreach ($intervals as $interval) {
                if ($rangeStart === null) {
                    $rangeStart = $interval[0];
                    $rangeEnd = $interval[1];
                } elseif ($interval[0] <= $rangeEnd) {
                    $rangeEnd = max($rangeEnd, $interval[1]);
                } else {
                    $mergedIntervals[] = [$rangeStart, $rangeEnd];
                    $rangeStart = $interval[0];
                    $rangeEnd = $interval[1];
                }
            }

            if ($rangeStart !== null) {
                $mergedIntervals[] = [$rangeStart, $rangeEnd];
            }

            $schoolStart = 7 * 60 + 30;
            $schoolEnd = 21 * 60;
            $coveredSchoolMinutes = 0;
            $reservedTimes = [];
            $availableTimes = [];
            $cursor = $schoolStart;

            foreach ($mergedIntervals as $interval) {
                $start = max($schoolStart, $interval[0]);
                $end = min($schoolEnd, $interval[1]);
                if ($end <= $start) {
                    continue;
                }

                if ($start > $cursor) {
                    $availableTimes[] = [$cursor, $start];
                }

                $reservedTimes[] = [$start, $end];
                $coveredSchoolMinutes += max(0, $end - max($cursor, $start));
                $cursor = max($cursor, $end);
            }

            if ($cursor < $schoolEnd) {
                $availableTimes[] = [$cursor, $schoolEnd];
            }

            $isFullDay = $coveredSchoolMinutes >= ($schoolEnd - $schoolStart);
            $statuses[$date] = [
                'state' => $isFullDay ? 'reserved' : 'partial',
                'label' => $isFullDay ? 'Reserved' : 'Partially reserved',
                'reserved_times' => $reservedTimes,
                'available_times' => $availableTimes,
            ];
        }

        return $statuses;
    }
}

$requestedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedMonth)) {
    $requestedMonth = date('Y-m');
}
$monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedMonth . '-01');
if (!$monthStart) {
    $monthStart = new DateTimeImmutable('first day of this month');
}
$monthEnd = $monthStart->modify('last day of this month');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$today = date('Y-m-d');
?>

<style>
    body.calendar-page {
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    body.calendar-page {
        height: 100vh;
        height: 100dvh;
        display: flex;
        flex-direction: column;
        background: #f3f6fb;
    }

    body.calendar-page > .aclc-logo {
        pointer-events: none;
    }

    body.calendar-page > .navbar {
        flex: 0 0 auto;
    }

    body.calendar-page main#reservation-main {
        flex: 1 1 auto;
        min-height: 0 !important;
        padding-top: 0.65rem !important;
        padding-bottom: 0.65rem !important;
        overflow: hidden;
    }

    body.calendar-page main#reservation-main > .container {
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    body.calendar-page .reservation-calendar-card {
        flex: 1 1 auto;
        min-height: 0;
        margin-bottom: 0 !important;
        border: 1px solid #dce5f1;
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 12px 35px rgba(30, 64, 110, 0.12);
    }

    body.calendar-page .reservation-calendar-card > .card-body {
        min-height: 0;
        display: flex;
        flex-direction: column;
        padding: clamp(0.6rem, 2vh, 1.25rem);
    }

    body.calendar-page .calendar-intro {
        flex: 0 0 auto;
        margin-bottom: 0.6rem !important;
        color: #19375d;
        font-size: clamp(1rem, 2.4vh, 1.35rem);
        font-weight: 700;
    }

    body.calendar-page .facility-calendar {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        border: 1px solid #dce5f1;
        border-radius: 1rem;
        box-shadow: 0 5px 18px rgba(30, 64, 110, 0.07);
    }

    body.calendar-page .calendar-heading {
        flex: 0 0 auto;
        padding: 0.65rem 0.85rem;
        background: linear-gradient(110deg, #e5f0ff, #f4f8ff);
    }

    body.calendar-page .calendar-heading h5 {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 0.45rem;
        color: #17345a;
        font-size: clamp(1.15rem, 3.2vw, 1.8rem);
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: 0.015em;
    }

    body.calendar-page .calendar-heading h5 > span:first-child {
        color: #0b2f55;
    }

    body.calendar-page .calendar-heading .calendar-year {
        padding: 0.18em 0.48em;
        border-radius: 0.5rem;
        background: #d5e8ff;
        color: #135b98;
        font-size: 0.68em;
        font-weight: 750;
        letter-spacing: 0.04em;
    }

    body.calendar-page .calendar-heading .btn {
        width: 2.25rem;
        height: 2.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.7rem;
        background: #fff;
        border-color: #d8e2ef;
        color: #315779;
    }

    body.calendar-page .calendar-weekdays {
        flex: 0 0 auto;
        background: #fff;
    }

    body.calendar-page .calendar-weekdays span {
        padding: 0.35rem 0.2rem;
        color: #73839a;
        font-size: 0.72rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    body.calendar-page .calendar-days {
        flex: 1 1 auto;
        min-height: 0;
        grid-template-rows: repeat(6, minmax(0, 1fr));
        gap: 0;
        background: #fff;
    }

    body.calendar-page .calendar-day {
        min-height: 0;
        margin: 1px;
        padding: clamp(0.2rem, 0.75vw, 0.65rem);
        gap: clamp(0.15rem, 0.5vh, 0.45rem);
        border-radius: 0.45rem;
    }

    body.calendar-page .calendar-day {
        position: relative;
        align-items: flex-start;
        overflow: hidden;
        transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
    }

    body.calendar-page .calendar-day:not(:disabled):hover {
        z-index: 1;
        transform: scale(1.025);
        box-shadow: 0 5px 14px rgba(15, 45, 80, 0.18);
        filter: saturate(1.08);
    }

    body.calendar-page .calendar-day.available {
        background: linear-gradient(145deg, #f0fdf4, #dcfce7);
        color: #166534;
    }

    body.calendar-page .calendar-day.partial {
        background: linear-gradient(135deg, #ef5350 0%, #ef5350 48%, #fff 52%, #fff 100%);
        color: #26364a;
    }

    body.calendar-page .calendar-day.reserved {
        background: linear-gradient(145deg, #ef5350, #c62828);
        color: #fff;
    }

    body.calendar-page .calendar-day.closed {
        background: repeating-linear-gradient(135deg, #e9eef5, #e9eef5 8px, #e1e8f0 8px, #e1e8f0 16px);
        color: #68788d;
    }

    body.calendar-page .calendar-day .day-number {
        font-size: clamp(0.85rem, 1.8vh, 1.15rem);
    }

    body.calendar-page .calendar-day .day-state {
        max-width: 100%;
        font-size: clamp(0.5rem, 1.1vh, 0.68rem);
        line-height: 1.15;
    }

    body.calendar-page .calendar-day.today .day-number {
        display: inline-flex;
        min-width: 1.55rem;
        min-height: 1.55rem;
        margin: -0.25rem 0 0 -0.25rem;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #0d6efd;
        color: #fff;
    }

    body.calendar-page .calendar-day.selected {
        outline: 3px solid #0d6efd;
        outline-offset: -3px;
    }

    body.calendar-page .calendar-legend {
        flex: 0 0 auto;
        padding-top: 0.55rem;
        gap: 0.35rem 1rem !important;
        font-size: 0.75rem !important;
    }

    body.calendar-page > .mt-auto.pt-5.pb-5 {
        display: none !important;
    }

    @media (max-height: 650px) {
        body.calendar-page .calendar-intro {
            margin-bottom: 0.3rem !important;
        }

        body.calendar-page .calendar-weekdays span {
            padding: 0.2rem;
        }

        body.calendar-page .calendar-legend {
            padding-top: 0.3rem;
        }
    }

    .facility-calendar {
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        overflow: hidden;
        background: #fff;
    }

    .calendar-heading,
    .calendar-weekdays,
    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
    }

    .calendar-heading {
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb;
    }

    .calendar-weekdays {
        background: #f1f5f9;
    }

    .calendar-weekdays span {
        padding: 0.65rem 0.25rem;
        text-align: center;
        font-size: 0.78rem;
        font-weight: 700;
        color: #475569;
    }

    .calendar-days {
        gap: 1px;
        background: #e5e7eb;
    }

    .calendar-day {
        min-width: 0;
        min-height: 104px;
        border: 0;
        border-radius: 0;
        background: #fff;
        color: #1f2937;
        padding: 0.55rem;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.45rem;
        text-align: left;
    }

    .calendar-day:hover {
        filter: brightness(0.97);
    }

    .calendar-day.available {
        background: #f0fdf4;
    }

    .calendar-day.partial {
        background: linear-gradient(135deg, #dc3545 0%, #dc3545 50%, #fff 50%, #fff 100%);
    }

    .calendar-day.reserved {
        background: #dc3545;
        color: #fff;
    }

    .calendar-day.closed {
        background: #e2e8f0;
        color: #64748b;
    }

    .calendar-day:disabled {
        cursor: not-allowed;
        opacity: 0.7;
    }

    .calendar-day .day-number {
        font-weight: 700;
        font-size: 1rem;
        line-height: 1;
    }

    .calendar-day .day-state {
        font-size: 0.68rem;
        font-weight: 600;
    }

    .calendar-day.reserved .day-state {
        color: #fff;
    }

    .calendar-day.selected {
        outline: 3px solid #0d6efd;
        outline-offset: -3px;
    }

    .calendar-day.today .day-number {
        color: #0d6efd;
    }

    @media (max-width: 576px) {
        .calendar-day {
            min-height: 72px;
            padding: 0.35rem;
        }

        .calendar-day .day-state {
            font-size: 0.58rem;
        }
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-building me-2"></i>AI Reservation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (isLoggedIn()): ?>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a>
                    <?php if (isAdmin()): ?>
                        <a class="nav-link" href="admin/"><i class="bi bi-gear"></i> Admin</a>
                    <?php endif; ?>
                    <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a>
                </div>
            <?php else: ?>
                <a class="nav-link ms-auto btn btn-outline-light" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main id="reservation-main" class="py-5">
    <div class="container">
        <?php if (isset($_GET['reserved']) && $_GET['reserved'] === '0'): ?>
                <div class="alert alert-danger" role="alert">
                    The reservation could not be completed. The selected time may conflict with another reservation; please choose another time and try again.
                </div>
            <?php elseif (isset($_GET['reserved']) && $_GET['reserved'] === '1'): ?>
                <div class="alert alert-success" role="status">Reservation submitted successfully.</div>
        <?php endif; ?>
        <?php
            require 'facilities.php';
            $facilities = getFacilities();
            $facilityStatuses = [];
            $allDateStatuses = [];
            $firstWeekday = (int)$monthStart->format('w');
            $daysInMonth = (int)$monthStart->format('t');

            foreach ($facilities as $facility) {
                $facilityId = (int)($facility['id'] ?? 0);
                $facilityStatuses[$facilityId] = getFacilityMonthStatuses(
                    $facilityId,
                    $monthStart->format('Y-m-d'),
                    $monthEnd->format('Y-m-d')
                );
            }

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateString = $monthStart->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                $reservedFacilities = 0;
                $partiallyReservedFacilities = 0;

                foreach ($facilityStatuses as $statuses) {
                    $state = $statuses[$dateString]['state'] ?? 'available';
                    if ($state === 'reserved') {
                        $reservedFacilities++;
                    } elseif ($state === 'partial') {
                        $partiallyReservedFacilities++;
                    }
                }

                $allFacilitiesReserved = count($facilities) === 0 || $reservedFacilities === count($facilities);
                $dateIsPartial = $reservedFacilities > 0 || $partiallyReservedFacilities > 0;
                $allDateStatuses[$dateString] = [
                    'state' => $allFacilitiesReserved ? 'reserved' : ($dateIsPartial ? 'partial' : 'available'),
                    'label' => $allFacilitiesReserved
                        ? 'No facilities available'
                        : ($dateIsPartial ? 'Some facilities reserved' : 'Available'),
                    'available' => !$allFacilitiesReserved,
                ];
            }
            ?>

            <div class="card shadow-sm mb-4 reservation-calendar-card">
                <div class="card-body">
                    <h3 class="calendar-intro">Choose a reservation date</h3>
                    <div class="facility-calendar mb-3">
                        <div class="calendar-heading">
                            <a class="btn btn-sm btn-outline-secondary" href="index.php?month=<?php echo htmlspecialchars($previousMonth); ?>" aria-label="Previous month">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                            <h5 class="mb-0 text-center">
                                <span><?php echo htmlspecialchars($monthStart->format('F')); ?></span>
                                <span class="calendar-year"><?php echo htmlspecialchars($monthStart->format('Y')); ?></span>
                            </h5>
                            <a class="btn btn-sm btn-outline-secondary ms-auto" href="index.php?month=<?php echo htmlspecialchars($nextMonth); ?>" aria-label="Next month">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                        <div class="calendar-weekdays" aria-hidden="true">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="calendar-days" id="reservation-calendar">
                            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                                $dateString = $monthStart->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                                $status = $allDateStatuses[$dateString];
                                $isPastDate = $dateString < $today;
                                $isSunday = (int)$monthStart->setDate(
                                    (int)$monthStart->format('Y'),
                                    (int)$monthStart->format('n'),
                                    $day
                                )->format('w') === 0;
                                $isDisabled = $isPastDate || $isSunday || !$status['available'];
                                $dateLabel = $isPastDate ? 'Past date' : ($isSunday ? 'School closed' : $status['label']);
                                ?>
                                <button
                                    type="button"
                                    class="calendar-day <?php echo htmlspecialchars($isSunday ? 'closed' : $status['state']); ?><?php echo $dateString === $today ? ' today' : ''; ?>"
                                    <?php if ($day === 1): ?>style="grid-column-start: <?php echo $firstWeekday + 1; ?>"<?php endif; ?>
                                    data-date="<?php echo htmlspecialchars($dateString); ?>"
                                    title="<?php echo htmlspecialchars($dateLabel); ?>"
                                    aria-label="<?php echo htmlspecialchars($monthStart->format('F') . ' ' . $day . ', ' . $dateLabel); ?>"
                                    <?php echo $isDisabled ? 'disabled' : ''; ?>
                                >
                                    <span class="day-number"><?php echo $day; ?></span>
                                    <span class="day-state"><?php echo htmlspecialchars($dateLabel); ?></span>
                                </button>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="calendar-legend d-flex flex-wrap gap-3 small text-muted">
                        <span><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#f0fdf4;"></span>Available</span>
                        <span><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:linear-gradient(135deg,#dc3545 50%,#fff 50%);"></span>Some facilities reserved</span>
                        <span><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#dc3545;"></span>No facilities available</span>
                        <span><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#e2e8f0;"></span>School closed</span>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="facility-modal" tabindex="-1" aria-labelledby="facility-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title h5" id="facility-modal-title">
                                Available facilities for <span id="selected-date-label"></span>
                            </h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="no-facilities-message" class="alert alert-warning" hidden>
                                No facilities are available on this date.
                            </div>
                            <form method="POST" action="reservations.php" class="reservation-form">
                                <input type="hidden" name="reserve_date">
                                <div id="reservation-fields" hidden>
                                    <div class="mb-3">
                                        <label class="form-label" for="facility-select">Facility</label>
                                        <select class="form-select" id="facility-select" name="facility_id" required>
                                            <option value="" selected disabled>Choose a facility</option>
                                <?php foreach ($facilities as $facility):
                                    $facilityId = (int)($facility['id'] ?? 0);
                                    $reservedDates = [];
                                    foreach ($facilityStatuses[$facilityId] as $date => $status) {
                                        if ($status['state'] === 'reserved') {
                                            $reservedDates[] = $date;
                                        }
                                    }
                                    ?>
                                            <option
                                                value="<?php echo $facilityId; ?>"
                                                data-reserved-dates="<?php echo htmlspecialchars(json_encode($reservedDates), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-booking-times="<?php echo htmlspecialchars(json_encode($facilityStatuses[$facilityId]), ENT_QUOTES, 'UTF-8'); ?>"
                                                disabled
                                                hidden
                                            ><?php echo htmlspecialchars($facility['name'] ?? ''); ?></option>
                                <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div id="selected-facility-times" class="alert alert-light border mb-3" hidden aria-live="polite">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <h4 class="h6 text-danger">Reserved times</h4>
                                                <ul id="reserved-time-list" class="mb-0 small"></ul>
                                            </div>
                                            <div class="col-md-6">
                                                <h4 class="h6 text-success">Available times (7:30 AM–9:00 PM)</h4>
                                                <ul id="available-time-list" class="mb-0 small"></ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-sm-6 col-lg-3">
                                            <label class="form-label" for="reservation-start-time">Start Time</label>
                                            <input type="time" id="reservation-start-time" name="start_time" class="form-control" min="07:30" max="21:00" required>
                                        </div>
                                        <div class="col-sm-6 col-lg-3">
                                            <label class="form-label" for="reservation-end-time">End Time</label>
                                            <input type="time" id="reservation-end-time" name="end_time" class="form-control" min="07:30" max="21:00" required>
                                        </div>
                                        <div class="col-lg">
                                            <label class="form-label" for="reservation-reason">Reason</label>
                                            <input type="text" id="reservation-reason" name="reason" class="form-control" placeholder="e.g., Meeting / Practice" required>
                                        </div>
                                        <div class="col-lg-auto">
                                            <button type="submit" name="reserve_fac" class="btn btn-success w-100 px-3">
                                                <i class="bi bi-calendar-check me-1"></i> Reserve
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    </div>
</main>

<script>
const reservationServerTime = <?php echo (int)floor(microtime(true) * 1000); ?>;
const reservationClientTimeAtLoad = Date.now();

function getManilaClock() {
    const now = new Date(reservationServerTime + Date.now() - reservationClientTimeAtLoad);
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23'
    }).formatToParts(now).reduce(function (values, part) {
        values[part.type] = part.value;
        return values;
    }, {});

    return {
        date: parts.year + '-' + parts.month + '-' + parts.day,
        time: parts.hour + ':' + parts.minute,
        seconds: Number(parts.second)
    };
}

function getEarliestStartTime(selectedDate) {
    const openingTime = '07:30';
    const clock = getManilaClock();
    if (selectedDate !== clock.date) {
        return openingTime;
    }

    const minute = Number(clock.time.slice(3, 5)) + (clock.seconds > 0 ? 1 : 0);
    const hour = Number(clock.time.slice(0, 2)) + Math.floor(minute / 60);
    const roundedMinute = minute % 60;
    const currentMinute = hour * 60 + roundedMinute;
    const openingMinute = 7 * 60 + 30;
    if (currentMinute <= openingMinute) {
        return openingTime;
    }
    if (currentMinute > 21 * 60) {
        return '21:01';
    }

    return String(hour).padStart(2, '0') + ':' + String(roundedMinute).padStart(2, '0');
}

function formatTimeRange(range) {
        function formatTime(minutes) {
            const date = new Date(2000, 0, 1, Math.floor(minutes / 60), minutes % 60);
            return date.toLocaleTimeString(undefined, {
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        return formatTime(range[0]) + ' – ' + formatTime(range[1]);
    }

    function renderTimeRanges(listId, ranges, emptyMessage) {
        const list = document.getElementById(listId);
        list.replaceChildren();

        if (!ranges || ranges.length === 0) {
            const item = document.createElement('li');
            item.textContent = emptyMessage;
            list.appendChild(item);
            return;
        }

        ranges.forEach(function (range) {
            const item = document.createElement('li');
            item.textContent = formatTimeRange(range);
            list.appendChild(item);
        });
    }

    const facilitySelect = document.getElementById('facility-select');
    const reservationForm = document.querySelector('.reservation-form');
    const startTimeInput = document.getElementById('reservation-start-time');
    const endTimeInput = document.getElementById('reservation-end-time');

    function updateReservationTimeOptions() {
        if (!reservationForm || !reservationForm.elements.reserve_date.value) {
            return;
        }

        const selectedDate = reservationForm.elements.reserve_date.value;
        const earliestStart = getEarliestStartTime(selectedDate);
        startTimeInput.min = earliestStart;
        endTimeInput.min = startTimeInput.value || earliestStart;

        if (startTimeInput.value && startTimeInput.value < earliestStart) {
            startTimeInput.value = '';
            endTimeInput.value = '';
            endTimeInput.min = earliestStart;
        } else if (endTimeInput.value && startTimeInput.value && endTimeInput.value <= startTimeInput.value) {
            endTimeInput.value = '';
        }

        const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            return;
        }

        const availabilityByDate = JSON.parse(selectedOption.getAttribute('data-booking-times') || '{}');
        const dateAvailability = availabilityByDate[selectedDate] || {};
        let availableTimes = dateAvailability.available_times || [[450, 1260]];
        if (selectedDate === getManilaClock().date) {
            const earliestMinutes = Number(earliestStart.slice(0, 2)) * 60 + Number(earliestStart.slice(3, 5));
            availableTimes = availableTimes
                .map(function (range) {
                    return [Math.max(range[0], earliestMinutes), range[1]];
                })
                .filter(function (range) {
                    return range[0] < range[1];
                });
        }
        renderTimeRanges(
            'available-time-list',
            availableTimes,
            'No available times remain.'
        );
    }

    if (facilitySelect) {
        facilitySelect.addEventListener('change', function () {
            const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
            const selectedDate = document.querySelector('.reservation-form [name="reserve_date"]').value;
            const availabilityByDate = JSON.parse(selectedOption.getAttribute('data-booking-times') || '{}');
            const dateAvailability = availabilityByDate[selectedDate] || {};

            renderTimeRanges(
                'reserved-time-list',
                dateAvailability.reserved_times || [],
                'No bookings yet.'
            );
            renderTimeRanges(
                'available-time-list',
                dateAvailability.available_times || [[450, 1260]],
                'No available times remain.'
            );
            document.getElementById('selected-facility-times').hidden = false;
            updateReservationTimeOptions();
        });
    }

    document.querySelectorAll('.reservation-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.elements.reserve_date.value) {
                event.preventDefault();
                alert('Choose an available date from the calendar first.');
                return;
            }

            const startTime = form.elements.start_time.value;
            const endTime = form.elements.end_time.value;
            const earliestStart = getEarliestStartTime(form.elements.reserve_date.value);
            if (startTime < earliestStart) {
                event.preventDefault();
                alert('That start time has already passed. Choose a later available time.');
                updateReservationTimeOptions();
                return;
            }
            if (startTime < '07:30' || endTime > '21:00' || startTime >= endTime) {
                event.preventDefault();
                alert('Choose a valid time between 7:30 AM and 9:00 PM. End time must be after start time.');
                return;
            }

            const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
            const availabilityByDate = JSON.parse(selectedOption.getAttribute('data-booking-times') || '{}');
            const dateAvailability = availabilityByDate[form.elements.reserve_date.value] || {};
            const startMinutes = Number(startTime.slice(0, 2)) * 60 + Number(startTime.slice(3, 5));
            const endMinutes = Number(endTime.slice(0, 2)) * 60 + Number(endTime.slice(3, 5));
            const overlapsReservation = (dateAvailability.reserved_times || []).some(function (range) {
                return startMinutes < range[1] && endMinutes > range[0];
            });

            if (overlapsReservation) {
                event.preventDefault();
                alert('That time conflicts with an existing reservation. Choose a time from the available times list.');
                return;
            }

            if (!confirm('Are you sure you want to reserve this facility?')) {
                event.preventDefault();
            }
        });
    });

    if (startTimeInput && endTimeInput) {
        startTimeInput.addEventListener('change', updateReservationTimeOptions);
        endTimeInput.addEventListener('focus', updateReservationTimeOptions);
        window.setInterval(updateReservationTimeOptions, 10000);
    }

    document.addEventListener('click', function (event) {
        const dayButton = event.target.closest('.calendar-day:not(:disabled)');
        if (!dayButton) {
            return;
        }

        const selectedDate = dayButton.getAttribute('data-date');
        document.querySelectorAll('#reservation-calendar .calendar-day.selected').forEach(function (selectedDay) {
            selectedDay.classList.remove('selected');
        });
        dayButton.classList.add('selected');

        document.getElementById('selected-date-label').textContent =
            new Date(selectedDate + 'T00:00:00').toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

        const reservationForm = document.querySelector('.reservation-form');
        reservationForm.elements.reserve_date.value = selectedDate;
        const facilitySelect = document.getElementById('facility-select');
        facilitySelect.value = '';
        document.getElementById('selected-facility-times').hidden = true;
        document.getElementById('reservation-start-time').value = '';
        document.getElementById('reservation-end-time').value = '';
        updateReservationTimeOptions();
        let availableFacilityCount = 0;
        Array.from(facilitySelect.options).forEach(function (option) {
            if (!option.value) {
                return;
            }
            const reservedDates = JSON.parse(option.getAttribute('data-reserved-dates') || '[]');
            const isAvailable = !reservedDates.includes(selectedDate);
            option.hidden = !isAvailable;
            option.disabled = !isAvailable;
            if (isAvailable) {
                availableFacilityCount++;
            }
        });

        document.getElementById('no-facilities-message').hidden = availableFacilityCount > 0;
        document.getElementById('reservation-fields').hidden = availableFacilityCount === 0;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('facility-modal')).show();
    });
</script>

<?php require 'footer.php'; ?>
