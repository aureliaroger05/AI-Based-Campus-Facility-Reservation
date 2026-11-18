<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'auth.php';
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

        $classStmt = $pdo->prepare(
            "SELECT class_date, start_time, end_time FROM class_schedules " .
            "WHERE facility_id = ? AND class_date BETWEEN ? AND ? " .
            "ORDER BY class_date ASC, start_time ASC"
        );
        $classStmt->execute([$facility_id, $firstDate, $lastDate]);
        foreach ($classStmt->fetchAll() as $class) {
            $intervalsByDate[$class['class_date']][] = [
                max(0, timeToMinutesFromString($class['start_time'])),
                min(1440, timeToMinutesFromString($class['end_time'])),
            ];
        }

        $reservationIntervalsByDate = [];
        foreach ($reservations as $reservation) {
            $hasRange = isset($reservation['start_time'], $reservation['end_time'])
                && ($reservation['start_time'] !== '00:00:00' || $reservation['end_time'] !== '00:00:00');
            if (!$hasRange) {
                $startMinutes = timeToMinutesFromString($reservation['reserve_time'] ?? '00:00:00');
                $endMinutes = $startMinutes + 60;
            } else {
                $startMinutes = timeToMinutesFromString($reservation['start_time']);
                $endMinutes = timeToMinutesFromString($reservation['end_time']);
            }

            $intervalsByDate[$reservation['reserve_date']][] = [
                max(0, $startMinutes),
                min(1440, $endMinutes),
            ];
            $reservationIntervalsByDate[$reservation['reserve_date']][] = [
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
                'reservation_times' => $reservationIntervalsByDate[$date] ?? [],
            ];
        }

        return $statuses;
    }
}

$manilaTimezone = new DateTimeZone('Asia/Manila');
$manilaNow = new DateTimeImmutable('now', $manilaTimezone);
$requestedMonth = $_GET['month'] ?? $manilaNow->format('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedMonth)) {
    $requestedMonth = $manilaNow->format('Y-m');
}
$monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedMonth . '-01');
if (!$monthStart) {
    $monthStart = new DateTimeImmutable('first day of this month');
}
$monthEnd = $monthStart->modify('last day of this month');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$today = $manilaNow->format('Y-m-d');
$minimumReservationDate = $manilaNow->setTime(0, 0)->modify('+3 days')->format('Y-m-d');
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

    body.calendar-page {
        background:
            radial-gradient(ellipse at 92% 4%, rgba(23, 105, 170, 0.1), transparent 32rem),
            #f3f6fb;
    }

    body.calendar-page .reservation-calendar-card {
        border-color: #e0e9f2;
        border-radius: 1.1rem;
        background: #fff;
        box-shadow: 0 9px 26px rgba(23, 43, 77, 0.065);
    }

    body.calendar-page .reservation-calendar-card > .card-body {
        padding: clamp(0.65rem, 2vh, 1.25rem);
    }

    body.calendar-page .calendar-intro {
        position: relative;
        isolation: isolate;
        display: flex;
        min-height: 4.5rem;
        align-items: center;
        justify-content: space-between;
        overflow: hidden;
        gap: 0.5rem 1rem;
        margin-bottom: 0.9rem !important;
        padding: 0.85rem clamp(1rem, 3vw, 1.3rem);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 1rem;
        background: linear-gradient(115deg, #123b66 0%, #1769aa 58%, #438cc2 100%);
        box-shadow: 0 10px 24px rgba(18, 59, 102, 0.16);
        color: #fff !important;
        font-size: clamp(1rem, 2.4vh, 1.2rem);
        letter-spacing: -0.02em;
    }

    body.calendar-page .calendar-intro::after {
        position: absolute;
        z-index: -1;
        top: -4rem;
        right: 7%;
        width: 12rem;
        height: 12rem;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 50%;
        content: "";
        box-shadow: 0 0 0 1.5rem rgba(255, 255, 255, 0.035), 0 0 0 3rem rgba(255, 255, 255, 0.025);
        pointer-events: none;
    }

    body.calendar-page .calendar-intro > span {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
    }

    body.calendar-page .calendar-intro > span i {
        color: #fff;
    }

    body.calendar-page .calendar-intro small {
        position: relative;
        z-index: 1;
        color: rgba(255, 255, 255, 0.78);
        font-size: 0.72rem;
        font-weight: 500;
        letter-spacing: 0;
    }

    body.calendar-page .facility-calendar {
        border-color: #e0e9f2;
        border-radius: 1rem;
        box-shadow: 0 5px 18px rgba(30, 64, 110, 0.07);
    }

    body.calendar-page .calendar-heading {
        background: linear-gradient(110deg, #f7fafe 0%, #edf5fc 100%);
        border-bottom-color: #e8eef5;
    }

    body.calendar-page .calendar-heading h5,
    body.calendar-page .calendar-heading h5 > span:first-child {
        color: #27334a;
    }

    body.calendar-page .calendar-heading .calendar-year {
        background: #eaf4fc;
        color: #1769aa;
    }

    body.calendar-page .calendar-heading .btn {
        border-color: #e6e8f0;
        color: #68748a;
    }

    body.calendar-page .calendar-heading .btn:hover {
        border-color: #c9dced;
        background: #eaf4fc;
        color: #1769aa;
    }

    body.calendar-page .calendar-weekdays {
        background: #fafbfc;
    }

    body.calendar-page .calendar-weekdays span {
        color: #8690a1;
        font-weight: 700;
    }

    body.calendar-page .calendar-days {
        background: #f0f1f5;
    }

    body.calendar-page .calendar-day.available {
        background: #edf8f2;
        color: #26734f;
    }

    body.calendar-page .calendar-day.partial {
        background: linear-gradient(135deg, #f8e9c9 0%, #f8e9c9 48%, #fff 52%, #fff 100%);
        color: #465166;
    }

    body.calendar-page .calendar-day.reserved {
        background: linear-gradient(145deg, #e97778, #d9575c);
        color: #fff;
    }

    body.calendar-page .calendar-day.closed {
        background: repeating-linear-gradient(135deg, #f2f3f6, #f2f3f6 8px, #e9ebf0 8px, #e9ebf0 16px);
        color: #9299a7;
    }

    body.calendar-page .calendar-day.today .day-number {
        background: #1769aa;
    }

    body.calendar-page .calendar-day.selected {
        outline-color: #1769aa;
    }

    body.calendar-page .calendar-legend {
        color: #758096 !important;
    }

    @media (max-width: 575.98px) {
        body.calendar-page .calendar-intro {
            align-items: flex-start;
            flex-direction: column;
        }

        body.calendar-page .calendar-intro small {
            padding-left: 1.65rem;
        }
    }

    @media (max-height: 700px) {
        body.calendar-page {
            height: auto;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            overflow-y: auto;
        }

        body.calendar-page main#reservation-main {
            flex: 0 0 auto;
            min-height: calc(100vh - 4.5rem) !important;
            min-height: calc(100dvh - 4.5rem) !important;
            overflow: visible;
        }

        body.calendar-page main#reservation-main > .container {
            height: auto;
            min-height: 0;
        }

        body.calendar-page .reservation-calendar-card,
        body.calendar-page .facility-calendar {
            flex: 0 0 auto;
        }

        body.calendar-page .calendar-days {
            flex: 0 0 auto;
            min-height: 240px;
        }

        body.calendar-page .calendar-day {
            min-height: 40px;
        }
    }
</style>

<nav class="navbar navbar-expand-md navbar-dark bg-primary sticky-top">
    <div class="container">
        <img src="images/aclc logo.png" alt="ACLC Logo" class="navbar-aclc-logo"><a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-building me-2"></i>ACLC Reservation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <?php require __DIR__ . '/notifications_bar.php'; ?>
                <?php if (isLoggedIn()): ?>
                    <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Home</a>
                    <a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a>
                    <?php if (isAdmin()): ?>
                        <a class="nav-link" href="admin/"><i class="bi bi-gear"></i> Admin</a>
                    <?php endif; ?>
                    <a class="nav-link" href="logout.php" data-confirm="Are you sure you want to log out?"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a>
                <?php else: ?>
                    <a class="nav-link btn btn-outline-light" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main id="reservation-main" class="py-5">
    <div class="container">
        <?php if (isset($_GET['reserved']) && $_GET['reserved'] === '0'): ?>
                <div class="alert alert-danger" role="alert">
                    The reservation could not be completed. Submit before 5:00 PM, book at least 3 days ahead, and choose an available time longer than 30 minutes.
                </div>
        <?php endif; ?>
        <?php if (isset($_GET['reserved']) && $_GET['reserved'] === '1'): ?>
            <div class="modal fade action-result-modal" id="reservationSuccessModal" tabindex="-1" aria-labelledby="reservationSuccessTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="action-result-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></div>
                        <div class="modal-header border-0 justify-content-center pb-0">
                            <h2 class="modal-title fs-4" id="reservationSuccessTitle">Reservation submitted</h2>
                        </div>
                        <div class="modal-body text-center px-4">Your reservation was submitted successfully.</div>
                        <div class="modal-footer border-0 justify-content-center pt-0">
                            <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                function showReservationSuccessModal() {
                    const reservationModal = document.getElementById('reservationSuccessModal');
                    if (reservationModal && window.bootstrap) {
                        const modal = bootstrap.Modal.getOrCreateInstance(reservationModal);
                        reservationModal.addEventListener('hidden.bs.modal', function () {
                            const cleanUrl = new URL(window.location.href);
                            cleanUrl.searchParams.delete('reserved');
                            window.history.replaceState({}, '', cleanUrl);
                        }, { once: true });
                        modal.show();
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showReservationSuccessModal, { once: true });
                } else {
                    showReservationSuccessModal();
                }
            </script>
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
                    <h3 class="calendar-intro">
                        <span><i class="bi bi-calendar2-week" aria-hidden="true"></i>Choose a reservation date</span>
                        <small>Select an open day to explore facilities and available times.</small>
                    </h3>
                    <div class="facility-calendar mb-3">
    <div class="calendar-heading">
        <!-- Previous button (left side) -->
        <a class="btn btn-sm btn-outline-secondary" href="javascript:void(0)" data-action="prev" aria-label="Previous month">
            <i class="bi bi-chevron-left"></i>
        </a>
        
        <!-- Month name and year (center) -->
        <h5 class="mb-0 text-center">
            <span><?php echo htmlspecialchars($monthStart->format('F')); ?></span>
            <span class="calendar-year"><?php echo htmlspecialchars($monthStart->format('Y')); ?></span>
        </h5>
        
        <!-- Next button (right side) -->
        <a class="btn btn-sm btn-outline-secondary ms-auto" href="javascript:void(0)" data-action="next" aria-label="Next month">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>
                        <div class="calendar-weekdays" aria-hidden="true">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="calendar-days" id="reservation-calendar" style="grid-template-rows: repeat(<?php echo (int)ceil(($firstWeekday + $daysInMonth) / 7); ?>, minmax(0, 1fr));">
                            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                                $dateString = $monthStart->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                                $status = $allDateStatuses[$dateString];
                                $isPastDate = $dateString < $today;
                                $isTooSoon = $dateString < $minimumReservationDate;
                                $isSunday = (int)$monthStart->setDate(
                                    (int)$monthStart->format('Y'),
                                    (int)$monthStart->format('n'),
                                    $day
                                )->format('w') === 0;
                                $isDisabled = $isPastDate || $isTooSoon || $isSunday || !$status['available'];
                                $dateLabel = $isPastDate
                                    ? 'Past date'
                                    : ($isTooSoon
                                        ? 'Book at least 3 days ahead'
                                        : ($isSunday ? 'School closed' : $status['label']));
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
                        <span><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#edf8f2;"></span>Available</span>
                        <span><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:linear-gradient(135deg,#f8e9c9 50%,#fff 50%);"></span>Some facilities reserved</span>
                        <span><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#d9575c;"></span>No facilities available</span>
                        <span><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#e9ebf0;"></span>School closed</span>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="facility-modal" tabindex="-1" aria-labelledby="facility-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title h5" id="facility-modal-title">
                                Facility availability for <span id="selected-date-label"></span>
                            </h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info py-2" id="reservation-policy-note" role="note" aria-live="polite">
                                Requests must be submitted before 5 PM and reservations must be booked at least 3 days ahead.
                            </div>
                            <div id="no-facilities-message" class="alert alert-warning" hidden>
                                No facilities are available on this date.
                            </div>
                            <form method="POST" action="reservations.php" class="reservation-form" data-confirm="Are you sure you want to reserve this facility?">
                                <input type="hidden" name="reserve_date">
                                <div id="reservation-fields" hidden>
                                    <div class="mb-3">
                                        <label class="form-label" id="available-facilities-label">Choose a facility</label>
                                        <select id="facility-select" hidden aria-hidden="true" tabindex="-1">
                                            <option value="" selected disabled>Choose a facility</option>
                                <?php foreach ($facilities as $facility):
                                    $facilityId = (int)($facility['id'] ?? 0);
                                    ?>
                                            <option
                                                value="<?php echo $facilityId; ?>"
                                                data-booking-times="<?php echo htmlspecialchars(json_encode($facilityStatuses[$facilityId]), ENT_QUOTES, 'UTF-8'); ?>"
                                                disabled
                                                hidden
                                            ><?php echo htmlspecialchars($facility['name'] ?? ''); ?></option>
                                <?php endforeach; ?>
                                        </select>
                                        <div class="reservation-facility-grid" id="available-facility-rows" role="group" aria-labelledby="available-facilities-label">
                                            <?php foreach ($facilities as $facility):
                                                $facilityId = (int)($facility['id'] ?? 0);
                                                $facilityName = (string)($facility['name'] ?? '');
                                                ?>
                                            <article class="reservation-facility-card" data-facility-row="<?php echo $facilityId; ?>" hidden>
                                                <div class="reservation-facility-card-heading">
                                                    <h4><?php echo htmlspecialchars($facilityName); ?></h4>
                                                    <span class="badge text-bg-success" data-facility-availability>Available</span>
                                                </div>
                                                <div class="reservation-facility-busy">
                                                    <span class="reservation-facility-busy-label">Busy times</span>
                                                    <div class="reservation-facility-time-chips" data-facility-reserved-times></div>
                                                </div>
                                                <label class="reservation-facility-choice">
                                                    <input
                                                        class="form-check-input m-0"
                                                        type="radio"
                                                        name="facility_id"
                                                        value="<?php echo $facilityId; ?>"
                                                        aria-label="Select <?php echo htmlspecialchars($facilityName, ENT_QUOTES, 'UTF-8'); ?>"
                                                        required
                                                        disabled
                                                    >
                                                    <span>Select this facility</span>
                                                </label>
                                            </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div id="selected-facility-times" class="alert alert-light border mb-3" hidden aria-live="polite">
                                        <h4 class="h6 text-success">Available times</h4>
                                        <ul id="available-time-list" class="mb-0 small"></ul>
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-6 col-lg-3">
                                            <label class="form-label" for="reservation-start-time">Start Time</label>
                                            <input type="time" id="reservation-start-time" name="start_time" class="form-control" min="07:30" max="20:29" step="60" required>
                                        </div>
                                        <div class="col-6 col-lg-3">
                                            <label class="form-label" for="reservation-end-time">End Time</label>
                                            <input type="time" id="reservation-end-time" name="end_time" class="form-control" min="08:01" max="21:00" step="60" required>
                                        </div>
                                        <div class="col-lg">
                                            <label class="form-label" for="reservation-reason">Purpose of reservation</label>
                                            <input type="text" id="reservation-reason" name="reason" class="form-control" placeholder="e.g., Student organization meeting" required>
                                        </div>
                                        <div class="col-12 col-lg-auto">
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

    function renderFacilityReservedTimes(container, ranges) {
        container.replaceChildren();
        if (!ranges || ranges.length === 0) {
            const emptyMessage = document.createElement('span');
            emptyMessage.className = 'reservation-facility-no-busy-times';
            emptyMessage.textContent = 'No booked times';
            container.appendChild(emptyMessage);
            return;
        }

        ranges.forEach(function (range) {
            const timeChip = document.createElement('span');
            timeChip.className = 'reservation-facility-time-chip';
            timeChip.textContent = formatTimeRange(range);
            container.appendChild(timeChip);
        });
    }

    const facilitySelect = document.getElementById('facility-select');
    const availableFacilityRows = document.getElementById('available-facility-rows');
    const reservationForm = document.querySelector('.reservation-form');
    const startTimeInput = document.getElementById('reservation-start-time');
    const endTimeInput = document.getElementById('reservation-end-time');
    const reservationSubmitButton = reservationForm.querySelector('button[name="reserve_fac"]');
    const reservationPolicyNote = document.getElementById('reservation-policy-note');

    function minutesToTime(minutes) {
        return String(Math.floor(minutes / 60)).padStart(2, '0') + ':' +
            String(minutes % 60).padStart(2, '0');
    }

    function getMinimumBookingDate() {
        const parts = getManilaClock().date.split('-').map(Number);
        const minimumDate = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2] + 3));
        return minimumDate.toISOString().slice(0, 10);
    }

    function timeToMinutes(time) {
        return Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
    }

    function getAvailableReservationRanges(dateAvailability, selectedDate) {
        const availableTimes = dateAvailability.available_times || [[450, 1260]];
        const reservationTimes = dateAvailability.reservation_times || [];
        const earliestStart = getEarliestStartTime(selectedDate);
        const earliestMinutes = Number(earliestStart.slice(0, 2)) * 60 + Number(earliestStart.slice(3, 5));
        let ranges = availableTimes.map(function (range) {
            return [Math.max(range[0], earliestMinutes), range[1]];
        });

        reservationTimes.forEach(function (reservation) {
            ranges = ranges.map(function (range) {
                let start = range[0];
                let end = range[1];
                if (reservation[1] <= start) {
                    start = Math.max(start, reservation[1] + 60);
                }
                if (reservation[0] >= end) {
                    end = Math.min(end, reservation[0] - 60);
                }
                return [start, end];
            });
        });

        return ranges.filter(function (range) {
            return range[1] - range[0] > 30;
        });
    }

    function getSelectedFacilityAvailability(selectedDate) {
        const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            return {};
        }

        const availabilityByDate = JSON.parse(selectedOption.getAttribute('data-booking-times') || '{}');
        return availabilityByDate[selectedDate] || {};
    }

    function updateReservationSubmissionState() {
        const cutoffPassed = getManilaClock().time >= '17:00';
        reservationSubmitButton.disabled = cutoffPassed;
        reservationPolicyNote.textContent =
            'Requests must be submitted before 5 PM and reservations must be booked at least 3 days ahead.';
    }

    function updateReservationTimeOptions() {
        if (!reservationForm || !reservationForm.elements.reserve_date.value) {
            return;
        }

        const selectedDate = reservationForm.elements.reserve_date.value;
        const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
        const hasSelectedFacility = Boolean(selectedOption && selectedOption.value);
        const dateAvailability = hasSelectedFacility ? getSelectedFacilityAvailability(selectedDate) : {};
        const ranges = hasSelectedFacility ? getAvailableReservationRanges(dateAvailability, selectedDate) : [];
        const earliestStart = getEarliestStartTime(selectedDate);
        const previousStart = startTimeInput.value;
        const previousEnd = endTimeInput.value;
        startTimeInput.disabled = !hasSelectedFacility || ranges.length === 0;
        endTimeInput.disabled = !hasSelectedFacility || ranges.length === 0;
        startTimeInput.min = earliestStart;
        startTimeInput.max = '20:29';
        endTimeInput.max = '21:00';

        const startMinutes = previousStart ? timeToMinutes(previousStart) : NaN;
        const rangeForStart = ranges.find(function (range) {
            return startMinutes >= range[0] && startMinutes + 31 <= range[1];
        });
        if (!rangeForStart) {
            startTimeInput.value = '';
            endTimeInput.value = '';
        }

        if (startTimeInput.value) {
            const selectedStart = timeToMinutes(startTimeInput.value);
            const selectedRange = ranges.find(function (range) {
                return selectedStart >= range[0] && selectedStart + 31 <= range[1];
            });
            endTimeInput.min = minutesToTime(selectedStart + 31);
            const endMinutes = previousEnd ? timeToMinutes(previousEnd) : NaN;
            if (!selectedRange || endMinutes - selectedStart <= 30 || endMinutes > selectedRange[1]) {
                endTimeInput.value = '';
            }
        } else {
            endTimeInput.min = '08:01';
            endTimeInput.value = '';
        }

        renderTimeRanges(
            'available-time-list',
            ranges,
            'No available times longer than 30 minutes remain.'
        );
        updateReservationSubmissionState();
    }

    if (facilitySelect) {
        facilitySelect.addEventListener('change', function () {
            const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
            const selectedTimes = document.getElementById('selected-facility-times');
            if (!selectedOption || !selectedOption.value) {
                selectedTimes.hidden = true;
                startTimeInput.value = '';
                endTimeInput.value = '';
                updateReservationTimeOptions();
                return;
            }

            selectedTimes.hidden = false;
            updateReservationTimeOptions();
        });
    }

    if (availableFacilityRows && facilitySelect) {
        availableFacilityRows.addEventListener('change', function (event) {
            const radio = event.target.closest('input[name="facility_id"]');
            if (!radio) {
                return;
            }

            facilitySelect.value = radio.value;
            facilitySelect.dispatchEvent(new Event('change', { bubbles: true }));
            availableFacilityRows.querySelectorAll('[data-facility-row]').forEach(function (row) {
                row.classList.toggle('is-selected', row === radio.closest('[data-facility-row]'));
            });
        });

        availableFacilityRows.addEventListener('click', function (event) {
            const row = event.target.closest('[data-facility-row]');
            if (!row || event.target.closest('input')) {
                return;
            }

            const radio = row.querySelector('input[name="facility_id"]');
            if (!radio || radio.disabled) {
                return;
            }
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    document.querySelectorAll('.reservation-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.elements.reserve_date.value) {
                event.preventDefault();
                alert('Choose an available date from the calendar first.');
                return;
            }

            if (form.elements.reserve_date.value < getMinimumBookingDate()) {
                event.preventDefault();
                alert('Reservations must be made at least 3 days before the reservation date.');
                return;
            }

            if (getManilaClock().time >= '17:00') {
                event.preventDefault();
                updateReservationSubmissionState();
                alert('The daily reservation cutoff is 5:00 PM Manila time. Submit your request before the cutoff.');
                return;
            }

            const startTime = form.elements.start_time.value;
            const endTime = form.elements.end_time.value;
            if (!startTime || !endTime) {
                event.preventDefault();
                alert('Choose an available time longer than 30 minutes.');
                return;
            }

            const selectedOption = facilitySelect.options[facilitySelect.selectedIndex];
            const dateAvailability = getSelectedFacilityAvailability(form.elements.reserve_date.value);
            const startMinutes = timeToMinutes(startTime);
            const endMinutes = timeToMinutes(endTime);
            const hasAvailableRange = selectedOption && selectedOption.value &&
                endMinutes - startMinutes > 30 &&
                getAvailableReservationRanges(dateAvailability, form.elements.reserve_date.value).some(function (range) {
                    return startMinutes >= range[0] && endMinutes <= range[1];
            });

            if (!hasAvailableRange) {
                event.preventDefault();
                alert('That time is no longer available. Choose another available time longer than 30 minutes.');
                return;
            }
        });
    });

    if (startTimeInput && endTimeInput) {
        startTimeInput.addEventListener('change', updateReservationTimeOptions);
        endTimeInput.addEventListener('change', updateReservationTimeOptions);
        window.clearInterval(window.reservationTimeUpdateInterval);
        window.reservationTimeUpdateInterval = window.setInterval(function () {
            updateReservationTimeOptions();
            updateReservationSubmissionState();
        }, 10000);
        updateReservationSubmissionState();
    }

    function handleReservationCalendarClick(event) {
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
        const availableFacilityRows = document.getElementById('available-facility-rows');
        facilitySelect.value = '';
        availableFacilityRows.querySelectorAll('[data-facility-row]').forEach(function (row) {
            const radio = row.querySelector('input[name="facility_id"]');
            radio.checked = false;
            row.classList.remove('is-selected');
        });
        document.getElementById('selected-facility-times').hidden = true;
        document.getElementById('reservation-start-time').value = '';
        document.getElementById('reservation-end-time').value = '';
        updateReservationTimeOptions();
        let availableFacilityCount = 0;
        Array.from(facilitySelect.options).forEach(function (option) {
            if (!option.value) {
                return;
            }
            const availabilityByDate = JSON.parse(option.getAttribute('data-booking-times') || '{}');
            const dateAvailability = availabilityByDate[selectedDate] || {};
            const isAvailable = getAvailableReservationRanges(dateAvailability, selectedDate).length > 0;
            option.hidden = !isAvailable;
            option.disabled = !isAvailable;
            const row = availableFacilityRows.querySelector('[data-facility-row="' + option.value + '"]');
            if (row) {
                row.hidden = false;
                const radio = row.querySelector('input[name="facility_id"]');
                radio.disabled = !isAvailable;
                const isPartiallyBooked = (dateAvailability.reserved_times || []).length > 0;
                const availabilityLabel = row.querySelector('[data-facility-availability]');
                availabilityLabel.textContent = !isAvailable
                    ? 'No reservable times'
                    : (isPartiallyBooked ? 'Limited times' : 'Available');
                availabilityLabel.classList.toggle('text-bg-warning', isAvailable && isPartiallyBooked);
                availabilityLabel.classList.toggle('text-bg-success', !isPartiallyBooked && isAvailable);
                availabilityLabel.classList.toggle('text-bg-danger', !isAvailable);
                row.classList.toggle('is-unavailable', !isAvailable);
                renderFacilityReservedTimes(
                    row.querySelector('[data-facility-reserved-times]'),
                    dateAvailability.reserved_times || []
                );
            }
            if (isAvailable) {
                availableFacilityCount++;
            }
        });

        document.getElementById('no-facilities-message').hidden = availableFacilityCount > 0;
        document.getElementById('reservation-fields').hidden = availableFacilityCount === 0;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('facility-modal')).show();
    }

    if (window.reservationCalendarClickHandler) {
        document.removeEventListener('click', window.reservationCalendarClickHandler);
    }
    window.reservationCalendarClickHandler = handleReservationCalendarClick;
    document.addEventListener('click', window.reservationCalendarClickHandler);

    document.addEventListener('admin:view-unloading', function cleanupReservationCalendar() {
        document.removeEventListener('click', window.reservationCalendarClickHandler);
        window.clearInterval(window.reservationTimeUpdateInterval);
        window.reservationCalendarClickHandler = null;
        window.reservationTimeUpdateInterval = null;
    }, { once: true });
</script>
<!-- Calendar navigation fix - prevents page reload -->
<script>
document.body.addEventListener('click', function(e) {
    const link = e.target.closest('.calendar-heading a');
    if (!link || !link.href.includes('index.php?month=')) return;
    
    e.preventDefault();
    const href = link.href;
    const url = new URL(href, window.location.origin);
    const month = url.searchParams.get('month');
    
    if (!month) return;
    
    // Parse year and month from the URL
    const [year, monthNum] = month.split('-');
    const currentYear = year ? parseInt(year) : new Date().getFullYear();
    const currentMonth = monthNum ? parseInt(monthNum) : new Date().getMonth() + 1;
    
    let newMonth, newYear;
    if (link.classList.contains('next') || link.getAttribute('aria-label').includes('Next')) {
        newMonth = currentMonth + 1;
        newYear = newMonth > 12 ? currentYear + 1 : currentYear;
        newMonth = newMonth > 12 ? newMonth - 12 : newMonth;
    } else {
        newMonth = currentMonth - 1;
        newYear = newMonth < 1 ? currentYear - 1 : currentYear;
        newMonth = newMonth < 1 ? newMonth + 12 : newMonth;
    }
    
    const dateStr = newYear + '-' + ('0' + newMonth).slice(-2);
    
    // Load calendar via AJAX without page reload
    const container = document.getElementById('reservation-calendar');
    if (!container) return;
    
    fetch('index.php?month=' + dateStr)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newCalendar = doc.querySelector('#reservation-calendar');
            if (newCalendar) {
                container.innerHTML = newCalendar.innerHTML;
            }
        })
        .catch(error => {
            console.error('Error loading calendar:', error);
        });
});
</script>
<!-- Calendar navigation AJAX (fixes page reload, buttons side-by-side) -->
<script>
document.body.addEventListener('click', function(e) {
    const link = e.target.closest('.calendar-heading a[data-action]');
    if (!link) return;
    
    e.preventDefault();
    const action = link.dataset.action;
    const yearEl = document.querySelector('.calendar-year');
    const year = yearEl ? parseInt(yearEl.textContent) : new Date().getFullYear();
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    
    // Get current month from the displayed month name
    const monthEl = document.querySelector('.calendar-heading h5 span:first-child');
    const currentMonthText = monthEl ? monthEl.textContent : 'January';
    const currentMonth = monthNames.indexOf(currentMonthText) + 1;
    
    let newMonth, newYear;
    if (action === 'next') {
        newMonth = currentMonth + 1;
        newYear = newMonth > 12 ? year + 1 : year;
        newMonth = newMonth > 12 ? newMonth - 12 : newMonth;
    } else {
        newMonth = currentMonth - 1;
        newYear = newMonth < 1 ? year - 1 : year;
        newMonth = newMonth < 1 ? newMonth + 12 : newMonth;
    }
    
    const dateStr = newYear + '-' + ('0' + newMonth).slice(-2);
    
    fetch('index.php?month=' + dateStr)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update calendar days
            const newCalendar = doc.querySelector('#reservation-calendar');
            if (newCalendar) {
                document.getElementById('reservation-calendar').innerHTML = newCalendar.innerHTML;
            }
            
            // Update year
            const newYearEl = doc.querySelector('.calendar-year');
            if (newYearEl) {
                yearEl.textContent = newYearEl.textContent;
            }
            
            // Update month name - try to get from new HTML, fallback to calculation
            const newMonthNameEl = doc.querySelector('.calendar-heading h5 span:first-child');
            if (newMonthNameEl) {
                document.querySelector('.calendar-heading h5 span:first-child').textContent = newMonthNameEl.textContent;
            } else {
                document.querySelector('.calendar-heading h5 span:first-child').textContent = monthNames[newMonth - 1];
            }
        })
        .catch(error => {
            console.error('Error loading calendar:', error);
        });
});
</script>

<?php require 'footer.php'; ?>
