<?php
if (!defined('HEADER_INCLUDED')) {
    define('HEADER_INCLUDED', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'AI Campus Facility Reservation'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../assets/css/style.css' : 'assets/css/style.css'; ?>?v=<?php echo (int)filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css'); ?>" rel="stylesheet">
</head>
<body<?php echo !empty($body_class) ? ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
<img src="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../images/aclc logo.png' : 'images/aclc logo.png'; ?>" alt="ACLC Logo" class="aclc-logo">
<?php } ?>
