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
                $_SESSION['class_schedule_notification'] = [
                    'type' => $deleted ? 'success' : 'danger',
                    'message' => $deleted
                        ? 'Class schedule deleted successfully.'
                        : 'The class schedule could not be found.',
                ];
                header('Location: class_schedules.php');
                exit;
            } catch (PDOException $exception) {
                error_log('Failed to delete class schedule: ' . $exception->getMessage());
                $formErrors[] = 'The class schedule could not be deleted. Please try again.';
            }
        }
    } elseif (isset($_POST['clear_all_schedules'])) {
        try {
            getDB()->exec('DELETE FROM class_schedules');
            $_SESSION['class_schedule_notification'] = [
                'type' => 'success',
                'message' => 'All class schedules have been cleared successfully.',
            ];
            header('Location: class_schedules.php');
            exit;
        } catch (PDOException $exception) {
            error_log('Failed to clear schedules: ' . $exception->getMessage());
            $formErrors[] = 'Failed to clear schedules. Please try again.';
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
                $replaceExisting = !empty($_POST['replace_existing']);
                $result = importClassScheduleCsv((string)$upload['tmp_name'], $replaceExisting);
                $formErrors = $result['errors'];
                if (!$formErrors) {
                    $_SESSION['class_schedule_notification'] = [
                        'type' => 'success',
                        'message' => $result['imported'] > 0
                            ? $result['imported'] . ' class schedule row(s) imported successfully.'
                            : 'No new rows were added; all schedule blocks were already imported.',
                    ];
                    header('Location: class_schedules.php');
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

$scheduleNotification = $_SESSION['class_schedule_notification'] ?? null;
unset($_SESSION['class_schedule_notification']);

try {
    $scheduleStmt = getDB()->prepare(
        'SELECT cs.id, cs.day, cs.start_time, cs.end_time, cs.subject, COALESCE(f.name, "Facility " || cs.facility_id) AS facility_name '
 .
        'FROM class_schedules cs LEFT JOIN facilities f ON f.id = cs.facility_id ' .
        'ORDER BY cs.class_date DESC, cs.start_time ASC LIMIT 300'
    );
    $scheduleStmt->execute();
    $scheduleRows = $scheduleStmt->fetchAll();
} catch (PDOException $exception) {
    $scheduleRows = [];
    $formErrors[] = 'The class_schedules table does not exist yet. Run the database migration (migrations/20261112_create_class_schedules.sql) first.';
}

require '../header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <img src="../images/aclc logo.png" alt="ACLC Logo" class="navbar-aclc-logo"><a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-building me-2"></i>ACLC Reservation</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="../index.php"><i class="bi bi-house"></i> Home</a>
            <a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link" href="../logout.php" data-confirm="Are you sure you want to log out?">
                <i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
            </a>
        </div>
    </div>
</nav>

<main class="admin-schedules-main">
    <div class="container-fluid admin-schedules-content">
        <div class="admin-page-heading">
            <span class="admin-heading-icon"><i class="bi bi-calendar3"></i></span>
            <div>
                <h2 class="mb-1">Class schedules</h2>
                <p class="mb-0">Import and review facility class times blocked from reservations.</p>
            </div>
        </div>

        <?php if ($scheduleNotification): ?>
            <div class="alert alert-<?php echo htmlspecialchars($scheduleNotification['type'] ?? 'info', ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($scheduleNotification['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

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

       
                </form>
                <p class="small text-muted mt-3 mb-0">
                    The import is all-or-nothing. Invalid rows or overlapping classes prevent the entire file from being imported.
                    Excel workbooks must be saved as CSV first; recurring weekday schedules are not supported by this date-based importer.
                </p>
            </div>
        </section>

        <section class="card admin-view-card">
            <div class="card-header admin-section-heading d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-2"></i>Imported class schedules</span>
                <?php if ($scheduleRows): ?>
                    <span class="badge bg-primary rounded-pill"><?php echo count($scheduleRows); ?> block(s)</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$scheduleRows): ?>
                    <p class="text-muted mb-0">No upcoming classes have been imported.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0" data-pagination-page-size="10" data-pagination-label="Upcoming class schedules">
                            <thead>
                                <tr>
                                    <th>Day</th>
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
                                        <td><?php echo htmlspecialchars($row['day'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

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
    </div>
</main>

<?php require '../footer.php'; ?>
