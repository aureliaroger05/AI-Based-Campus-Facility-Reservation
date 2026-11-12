<?php
date_default_timezone_set('Asia/Manila');

/**
 * config.php - Secure PDO Database Connection
 * For AI-Based Campus Facility Reservation System
 * XAMPP MySQL: root/empty pass
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'reservation_sys');
define('DB_USER', 'root');
define('DB_PASS', '');  // Change if password set

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("DB Connection failed: " . $e->getMessage());
    die("Database connection failed. Ensure XAMPP MySQL running and db_init.sql imported.");
}

// Global $pdo for easy use
function getDB() {
    global $pdo;
    return $pdo;
}
?>
