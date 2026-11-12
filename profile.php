<?php
    $page_title = 'Profile';
    $body_class = 'profile-page';
    require 'auth.php';
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    require 'header.php';
?>
<?php
    $user = getCurrentUser();
    $reservationActionMessage = null;
    $profileMessage = null;
    $showProfilePhotoForm = false;
    if (empty($_SESSION['profile_image_csrf'])) {
        $_SESSION['profile_image_csrf'] = bin2hex(random_bytes(32));
    }

    if (isset($_POST['upload_profile_image'])) {
        $showProfilePhotoForm = true;
        $providedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($providedToken) || !hash_equals($_SESSION['profile_image_csrf'], $providedToken)) {
            $profileMessage = ['danger', 'Your session expired. Refresh the page and try again.'];
        } elseif (!isset($_FILES['profile_image']) || !is_array($_FILES['profile_image'])) {
            $profileMessage = ['danger', 'Choose a photo to upload.'];
        } else {
            $upload = $_FILES['profile_image'];
            if ($upload['error'] !== UPLOAD_ERR_OK) {
                if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
                    $profileMessage = ['warning', 'Choose a photo to upload.'];
                } elseif ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $profileMessage = ['warning', 'The photo exceeds the server upload size limit.'];
                } else {
                    $profileMessage = ['danger', 'The photo could not be uploaded. Please try again.'];
                }
            } elseif ($upload['size'] > 5 * 1024 * 1024) {
                $profileMessage = ['warning', 'Choose an image smaller than 5 MB.'];
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($upload['tmp_name']);
                $imageInfo = getimagesize($upload['tmp_name']);
                $imageTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];

                if (!isset($imageTypes[$mimeType]) || $imageInfo === false || $imageInfo['mime'] !== $mimeType) {
                    $profileMessage = ['warning', 'Upload a valid JPG, PNG, GIF, or WebP image.'];
                } else {
                    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile_pictures';
                    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                        $profileMessage = ['danger', 'The photo upload folder could not be created.'];
                    } else {
                        $filename = bin2hex(random_bytes(16)) . '.' . $imageTypes[$mimeType];
                        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                            $profileMessage = ['danger', 'The photo could not be saved. Please try again.'];
                        } else {
                            $previousImage = $user['profile_image'] ?? '';
                            $imagePath = 'uploads/profile_pictures/' . $filename;
                            try {
                                $stmt = getDB()->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
                                $stmt->execute([$imagePath, $user['id']]);
                                $user = getCurrentUser();
                                $profileMessage = ['success', 'Profile photo updated.'];
                            } catch (PDOException $exception) {
                                if (!unlink($destination)) {
                                    error_log('Unable to remove profile photo after database update failure: ' . $destination);
                                }
                                error_log('Profile photo database update failed: ' . $exception->getMessage());
                                $profileMessage = ['danger', 'The photo could not be saved to your profile. Please try again.'];
                            }

                            if ($profileMessage[0] === 'success'
                                && preg_match('~^uploads/profile_pictures/[a-f0-9]{32}\.(jpg|png|gif|webp)$~D', $previousImage)) {
                                $previousPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $previousImage);
                                if (is_file($previousPath) && !unlink($previousPath)) {
                                    error_log('Unable to remove replaced profile photo: ' . $previousPath);
                                }
                            }
                            if ($profileMessage[0] === 'success') {
                                $showProfilePhotoForm = false;
                            }
                        }
                    }
                }
            }
        }
    }

    if (isset($_POST['cancel_reservation'])) {
        $reservationId = filter_input(INPUT_POST, 'res_id', FILTER_VALIDATE_INT);
        if ($reservationId) {
            $pdo = getDB();
            $stmt = $pdo->prepare(
                "UPDATE reservations SET status = 'cancelled' " .
                "WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed')"
            );
            $stmt->execute([$reservationId, $user['id']]);
            $reservationActionMessage = $stmt->rowCount() > 0
                ? ['success', 'Reservation cancelled.']
                : ['warning', 'This reservation could not be cancelled.'];
        } else {
            $reservationActionMessage = ['warning', 'This reservation could not be cancelled.'];
        }
    }

    if (isset($_POST['update'])) {
        if (!isValidGender($_POST['gender'] ?? null)) {
            $profileMessage = ['warning', 'Choose a valid gender before updating your profile.'];
        } else {
            $pdo = getDB();

            $first = trim((string)($_POST['first_name'] ?? ''));
            $middle = trim((string)($_POST['middle_name'] ?? ''));
            $last = trim((string)($_POST['last_name'] ?? ''));
            $suffix = trim((string)($_POST['suffix'] ?? ''));

            $fullName = trim(implode(' ', array_filter([$first, $middle, $last], fn($v) => $v !== '')));
            if ($suffix !== '') {
                $fullName = trim($fullName . ', ' . $suffix);
            }

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, birthdate = ?, gender = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([
                $fullName,
                $first ?: null,
                $middle ?: null,
                $last ?: null,
                $suffix ?: null,
                !empty($_POST['birthdate']) ? $_POST['birthdate'] : null,
                $_POST['gender'],
                $_POST['email'],
                $_POST['phone'],
                $user['id']
            ]);

            $user = getCurrentUser();
            $profileMessage = ['success', 'Profile updated.'];
        }
    }

    require 'reservations.php';
    $res = getUserReservations();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-building me-2"></i>AI Reservation</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Home</a>
                    <a class="nav-link" href="profile.php"><i class="bi bi-person"></i> Profile</a>
                    

                    <?php if (isAdmin()): ?><a class="nav-link" href="admin/"><i class="bi bi-gear"></i> Admin</a><?php endif; ?>
                    <a class="nav-link" href="logout.php" data-confirm="Are you sure you want to log out?"><i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>

                </div>
            </div>
        </div>
    </nav>
    <main class="py-5 profile-main">
    <div class="container mt-4">
        <div class="profile-page-heading">
            <span class="profile-heading-icon"><i class="bi bi-person-badge"></i></span>
            <div>
                <h2 class="mb-1">My Profile</h2>
                <p class="mb-0">Manage your personal information and view your reservations.</p>
            </div>
        </div>
        <?php if ($reservationActionMessage !== null): ?>
            <div class="alert alert-<?php echo htmlspecialchars($reservationActionMessage[0]); ?>" role="status">
                <?php echo htmlspecialchars($reservationActionMessage[1]); ?>
            </div>
        <?php endif; ?>
        <?php if ($profileMessage !== null): ?>
            <div class="alert alert-<?php echo htmlspecialchars($profileMessage[0]); ?>" role="status">
                <?php echo htmlspecialchars($profileMessage[1]); ?>
            </div>
        <?php endif; ?>
        <section class="card profile-panel mb-4">
            <div class="card-header profile-panel-heading">
                <i class="bi bi-person-lines-fill me-2"></i>Personal information
            </div>
            <div class="card-body p-4">
        <div class="profile-photo-section">
            <?php if (!empty($user['profile_image'])): ?>
                <img class="profile-photo" src="<?php echo htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Your profile photo">
            <?php else: ?>
                <span class="profile-photo profile-photo-placeholder" aria-label="No profile photo">
                    <i class="bi bi-person-fill" aria-hidden="true"></i>
                </span>
            <?php endif; ?>
            <div class="profile-photo-controls">
                <button type="button" class="btn btn-outline-primary" id="profilePhotoToggle"
                    aria-expanded="<?php echo $showProfilePhotoForm ? 'true' : 'false'; ?>" aria-controls="profilePhotoFields">
                    <i class="bi bi-camera me-2" aria-hidden="true"></i><?php echo $showProfilePhotoForm ? 'Cancel' : (!empty($user['profile_image']) ? 'Change photo' : 'Add photo'); ?>
                </button>
                <form method="POST" enctype="multipart/form-data" class="profile-photo-form">
                    <div id="profilePhotoFields"<?php echo $showProfilePhotoForm ? '' : ' hidden'; ?>>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_image_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <label class="form-label" for="profilePhotoUpload">Choose a photo</label>
                        <input class="form-control" type="file" id="profilePhotoUpload" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" <?php echo $showProfilePhotoForm ? 'required' : 'disabled'; ?>>
                        <div class="form-text">JPG, PNG, GIF, or WebP. Maximum file size: 5 MB (subject to server limits).</div>
                        <button type="submit" name="upload_profile_image" class="btn btn-primary mt-3">
                            <i class="bi bi-upload me-2" aria-hidden="true"></i>Upload photo
                        </button>
                    </div>
                </form>
            </div>
            <script>
                (function () {
                    const toggle = document.getElementById('profilePhotoToggle');
                    const fields = document.getElementById('profilePhotoFields');
                    const fileInput = document.getElementById('profilePhotoUpload');
                    if (!toggle || !fields || !fileInput) return;

                    toggle.addEventListener('click', function () {
                        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                        fields.hidden = isExpanded;
                        fileInput.disabled = isExpanded;
                        fileInput.required = !isExpanded;
                        toggle.setAttribute('aria-expanded', String(!isExpanded));
                        toggle.innerHTML = isExpanded
                            ? '<i class="bi bi-camera me-2" aria-hidden="true"></i><?php echo !empty($user['profile_image']) ? 'Change photo' : 'Add photo'; ?>'
                            : '<i class="bi bi-x-lg me-2" aria-hidden="true"></i>Cancel';
                    });
                })();
            </script>
        </div>
        <form method="POST" class="profile-edit-form">
            <div class="mb-3 profile-form-field profile-form-username">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled aria-label="Username">
            </div>
            <div class="mb-3 profile-form-field profile-form-name">
                <label class="form-label">Name</label>
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <input type="text" class="form-control form-control-sm" name="first_name" placeholder="First" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="text" class="form-control form-control-sm" name="middle_name" placeholder="Middle" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="text" class="form-control form-control-sm" name="last_name" placeholder="Last" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="text" class="form-control form-control-sm" name="suffix" placeholder="Suffix (Jr.)" value="<?php echo htmlspecialchars($user['suffix'] ?? ''); ?>">
                    </div>
                </div>
            </div>


            <div class="mb-3 profile-form-field">
                <label class="form-label">Birthdate</label>
                <input type="date" class="form-control" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
            </div>

            <div class="mb-3 profile-form-field">
                <label class="form-label">Gender</label>
                <select class="form-select" name="gender" required>
                    <?php $g = $user['gender'] ?? ''; ?>
                    <option value="" <?php echo ($g === '' || $g === null) ? 'selected' : ''; ?> disabled>Choose gender</option>
                    <option value="male" <?php echo ($g === 'male') ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($g === 'female') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>

            <div class="mb-3 profile-form-field">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
            </div>
            <div class="mb-3 profile-form-field">
                <label class="form-label">Phone</label>
                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            <div class="profile-form-actions">
                <button type="submit" name="update" class="btn btn-primary"><i class="bi bi-check2-circle me-2"></i>Update Profile</button>
            </div>

        </form>
            </div>
        </section>

        <section class="card profile-panel">
            <div class="card-header profile-panel-heading d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span><i class="bi bi-calendar2-check me-2"></i>My Reservations</span>
                <span class="badge text-bg-primary"><?php echo count($res ?? []); ?> total</span>
            </div>
            <div class="card-body p-4">
        <?php if (empty($res)): ?>
            <div class="profile-empty-state">
                <i class="bi bi-calendar-x"></i>
                <p class="mb-0">You don't have any reservations yet.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table profile-reservations-table">
            <thead><tr><th>Facility</th><th>Day</th><th>Start Time</th><th>End Time</th><th>Status</th><th>QR</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($res as $r): ?>
                <?php
                $reservationStart = $r['start_time'] ?? '';
                if ($reservationStart === '' || $reservationStart === '00:00:00') {
                    $reservationStart = $r['reserve_time'] ?? '';
                }
                $reservationEnd = $r['end_time'] ?? '';
                if ($reservationEnd === '00:00:00') {
                    $reservationEnd = '—';
                }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['facility_name']); ?></td>
<td><?php echo htmlspecialchars($r['reserve_date'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($reservationStart); ?></td>
                    <td><?php echo htmlspecialchars($reservationEnd); ?></td>
                    <td><span class="badge bg-<?php echo $r['status'] === 'confirmed' ? 'success' : ($r['status'] === 'completed' ? 'primary' : 'secondary'); ?>"><?php echo ucfirst($r['status']); ?></span></td>
<td><?php if ($r['status'] == 'confirmed' && $r['qr_code_path']): ?><a href="view_res.php?id=<?php echo (int)$r['id']; ?>" target="_blank">View QR</a><?php else: ?>QR on approval<?php endif; ?></td>
                    <td>
                        <?php if (in_array($r['status'], ['pending', 'confirmed'], true)): ?>
                            <form method="POST" class="d-inline" data-confirm="Cancel this reservation?">
                                <input type="hidden" name="res_id" value="<?php echo (int)$r['id']; ?>">
                                <button type="submit" name="cancel_reservation" class="btn btn-outline-danger btn-sm">Cancel</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
            </div>
        </section>
</div>
</main>
<?php require 'footer.php'; ?>
