<?php
if (!defined('HEADER_INCLUDED')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    define('HEADER_INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars((isset($page_title) ? $page_title : 'Home') . ' | ACLC Facility Reservation System', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/png" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../images/aclc%20logo.png' : 'images/aclc%20logo.png'; ?>?v=<?php echo (int)filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'aclc logo.png'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../assets/css/style.css' : 'assets/css/style.css'; ?>?v=<?php echo (int)filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css'); ?>" rel="stylesheet">
    <?php if (strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false): ?>
    <script>document.documentElement.dataset.adminTheme = window.localStorage.getItem('admin-dashboard-theme') === 'dark' ? 'dark' : 'light';</script>
    <?php endif; ?>
    <?php if (!in_array($body_class ?? '', ['login-page', 'welcome-page'], true)): ?>
    <script>
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
    <?php endif; ?>
</head>
<body<?php echo !empty($body_class) ? ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
<?php if (!in_array($body_class ?? '', ['login-page', 'welcome-page'], true)): ?>
<img src="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../images/aclc logo.png' : 'images/aclc logo.png'; ?>" alt="ACLC Logo" class="aclc-logo">
<?php endif; ?>
<?php } ?>
