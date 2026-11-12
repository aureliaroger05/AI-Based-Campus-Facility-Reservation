<?php
$page_title = 'Import Class Schedule';
$body_class = 'admin-form-page';
require '../auth.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

require_once '../class_schedules.php';

if (!isset($_SESSION['class_schedule_csrf'])) {
    $_SESSION['class_schedule_csrf'] = bin2hex(random_bytes(32));
}

$formErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token'], $_SESSION['class_schedule_csrf']) ||
        !hash_equals($_SESSION['class_schedule_csrf'], (string)$_POST['csrf_token'])
    ) {
        $formErrors[] = 'Your form session expired. Reload this page and try again.';
    } elseif (isset($_POST['delete_schedule'])) {
        $scheduleId = filter_var($_POST['schedule_id'] ?? null, FILTER_VALIDATE_INT);
        if ($scheduleId === false || $scheduleId < 1) {
            $formErrors[] = 'Select a valid class schedule to delete.';
        } else {
            try {
                $delete = getDB()->prepare('DELETE FROM class_schedules WHERE id = ?');
                $delete->execute([$scheduleId]);
                $deleted = $delete->rowCount() > 0;
                $_SESSION['dashboard_notification'] = [
                    'type' => $deleted ? 'success' : 'danger',
                    'message' => $deleted
                        ? 'Class schedule deleted successfully.'
                        : 'The class schedule could not be found.',
                ];
                header('Location: index.php');
                exit;
            } catch (PDOException $exception) {
                error_log('Failed to delete class schedule: ' . $exception->getMessage());
                $formErrors[] = 'The class schedule could not be deleted. Please try again.';
            }
        }
    } elseif (!isset($_FILES['schedule_file']) || !is_array($_FILES['schedule_file'])) {
        $formErrors[] = 'Choose a CSV schedule file to upload.';
    } else {
        $upload = $_FILES['schedule_file'];
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $formErrors[] = match ((int)$upload['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The CSV file exceeds the upload size limit.',
                UPLOAD_ERR_NO_FILE => 'Choose a CSV schedule file to upload.',
                default => 'The CSV file could not be uploaded. Please try again.',
            };
        } elseif (
            strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION)) !== 'csv' ||
            !is_uploaded_file((string)($upload['tmp_name'] ?? ''))
        ) {
            $formErrors[] = 'Upload a valid .csv file.';
        } elseif ((int)($upload['size'] ?? 0) > 5 * 1024 * 1024) {
            $formErrors[] = 'The CSV file must be 5 MB or smaller.';
        } else {
            try {
                $result = importClassScheduleCsv((string)$upload['tmp_name']);
                $formErrors = $result['errors'];
                if (!$formErrors) {
                    $_SESSION['dashboard_notification'] = [
                        'type' => 'success',
                        'message' => $result['imported'] > 0
                            ? $result['imported'] . ' class schedule row(s) imported successfully.'
                            : 'No new rows were added; all schedule blocks were already imported.',
                    ];
                    header('Location: index.php');
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('Failed to import class schedule: ' . $exception->getMessage());
                $formErrors[] = 'The schedule could not be imported. Confirm the class schedule database migration has been applied, then try again.';
            } catch (RuntimeException $exception) {
                $formErrors[] = $exception->getMessage();
            }
        }
    }
}

$scheduleStmt = getDB()->prepare(
    'SELECT cs.id, cs.class_date, cs.start_time, cs.end_time, cs.subject, f.name AS facility_name ' .
    'FROM class_schedules cs JOIN facilities f ON f.id = cs.facility_id ' .
    'WHERE cs.class_date >= ? ' .
    'ORDER BY cs.class_date ASC, cs.start_time ASC LIMIT 200'
);
$scheduleStmt->execute([date('Y-m-d')]);
$scheduleRows = $scheduleStmt->fetchAll();

require '../header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-building me-2"></i>AI Reservation</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="../index.php"><i class="bi bi-house"></i> Home</a>
            <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link" href="../logout.php" data-confirm="Are you sure you want to log out?">
                <i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
            </a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="mb-4">
        <h1 class="h2">Import Class Schedule</h1>
        <p class="text-muted mb-0">Class times will be blocked from reservations for the matching facility and date.</p>
    </div>

    <?php if ($formErrors): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Schedule not imported:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($formErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Upload a CSV file</h2>
            <p>
                Use one row per class, with the facility name exactly as it appears in the system.
                Required columns: <code>facility, subject, date, start_time, end_time</code>.
                Dates: <code>YYYY-MM-DD</code> or <code>M/D/YYYY</code>. Times: <code>HH:MM</code>,
                <code>HH:MM:SS</code>, or a 12-hour time with AM/PM.
            </p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-outline-primary" href="../assets/templates/class_schedule_template.csv" download>
                    <i class="bi bi-download me-1"></i>Download CSV Template
                </a>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['class_schedule_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                <div class="mb-3">
                    <label class="form-label" for="schedule-file">Schedule CSV (maximum 5 MB)</label>
                    <input class="form-control" id="schedule-file" type="file" name="schedule_file" accept=".csv,text/csv" required>
                </div>
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-upload me-1"></i>Validate and Import
                </button>
            </form>
            <p class="small text-muted mt-3 mb-0">
                The import is all-or-nothing. Invalid rows or overlapping classes prevent the entire file from being imported.
                Excel workbooks must be saved as CSV first; recurring weekday schedules are not supported by this date-based importer.
            </p>
        </div>
    </section>

    <section class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5">Upcoming imported classes</h2>
            <?php if (!$scheduleRows): ?>
                <p class="text-muted mb-0">No upcoming classes have been imported.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Facility</th>
                                <th>Subject</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduleRows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['class_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(date('g:i A', strtotime($row['start_time'])), ENT_QUOTES, 'UTF-8'); ?>
                                        –
                                        <?php echo htmlspecialchars(date('g:i A', strtotime($row['end_time'])), ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['facility_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <form method="POST" data-confirm="Delete this class schedule block?">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['class_schedule_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="schedule_id" value="<?php echo (int)$row['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" name="delete_schedule">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">Showing up to 200 upcoming schedule rows.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require '../footer.php'; ?>
