<?php
// Actual error capture - goes through real import flow
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class_schedules.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['schedule_file'])) {
    echo "=== Form Submitted ===\n\n";
    
    // Copy uploaded file to temp location for testing
    $upload = $_FILES['schedule_file'];
    if ($upload['error'] === UPLOAD_ERR_OK) {
        $temp_file = sys_get_temp_dir() . '/schedule_test_' . uniqid() . '.csv';
        if (move_uploaded_file($upload['tmp_name'], $temp_file)) {
            try {
                // Test with replace_existing = false
                $result = importClassScheduleCsv($temp_file, false);
                echo "Import Result:\n";
                echo "  Imported: " . $result['imported'] . "\n";
                if ($result['errors']) {
                    echo "  Errors: " . implode(', ', $result['errors']) . "\n";
                }
            } catch (PDOException $e) {
                echo "❌ PDOException CAUGHT!\n";
                echo "  Message: " . $e->getMessage() . "\n";
                echo "  File: " . $e->getFile() . "\n";
                echo "  Line: " . $e->getLine() . "\n";
            } catch (RuntimeException $e) {
                echo "❌ RuntimeException CAUGHT!\n";
                echo "  Message: " . $e->getMessage() . "\n";
            }
            unlink($temp_file);
        }
    }
} else {
    echo "No form submission detected.\n";
    echo "Please go to: admin/class_schedules.php and upload your CSV.\n";
}

echo "\n=== End ===\n";