<?php
// Direct import test - bypasses form
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class_schedules.php';

echo "Testing importClassScheduleCsv function...\n\n";

// Create a minimal test CSV in temp location
$test_csv = tempnam(sys_get_temp_dir(), 'schedule_test');
file_put_contents($test_csv, "facility,day,subject,start_time,end_time\n");
file_put_contents($test_csv, "SLAB1,Monday,Test Subject,7:30 AM,10:30 AM\n");

try {
    $result = importClassScheduleCsv($test_csv, false);
    echo "Import Result:\n";
    print_r($result);
    
    if ($result['imported'] > 0) {
        echo "\n✅ SUCCESS: Import worked!\n";
    } else {
        echo "\n❌ FAILED: " . implode(', ', $result['errors']) . "\n";
    }
} catch (PDOException $e) {
    echo "❌ PDOException: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
} catch (Throwable $e) {
    echo "❌ Throwable: " . $e->getMessage() . "\n";
}

// Cleanup
unlink($test_csv);
echo "\nTest complete.\n";