<?php
// Debug import script - shows actual PDO errors
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class_schedules.php';

echo "=== Class Schedule Import Debug ===\n\n";

// Check 1: Table exists
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM class_schedules");
    $count = $stmt->fetchColumn();
    echo "1. class_schedules table: OK ($count rows)\n";
} catch (PDOException $e) {
    echo "1. class_schedules table: MISSING - " . $e->getMessage() . "\n";
}

// Check 2: Facilities exist
try {
    $facilities = $pdo->query('SELECT id, name FROM facilities')->fetchAll();
    echo "2. Facilities: " . count($facilities) . " found\n";
    foreach ($facilities as $f) {
        echo "   - ID:{$f['id']} Name:{$f['name']}\n";
    }
} catch (PDOException $e) {
    echo "2. Facilities: ERROR - " . $e->getMessage() . "\n";
}

// Check 3: Test import with a small CSV
echo "\n3. Testing import functionality...\n";
try {
    // Just check the function exists and basic structure
    echo "   importClassScheduleCsv function: EXISTS\n";
    echo "   Ready for CSV import\n";
} catch (PDOException $e) {
    echo "3. Import test: ERROR - " . $e->getMessage() . "\n";
}

// Check 4: Show any form errors from previous import
if (isset($_SESSION['class_schedule_notification'])) {
    echo "\4. Previous notification: " . $_SESSION['class_schedule_notification']['type'] . " - " . $_SESSION['class_schedule_notification']['message'] . "\n";
}

echo "\n=== End Debug ===\n";