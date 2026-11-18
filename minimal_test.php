<?php
// Minimal import test - one row only
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class_schedules.php';

// Create a MINIMAL CSV with just one row
$test_csv = tempnam(sys_get_temp_dir(), 'schedule_minimal');
file_put_contents($test_csv, "facility,day,subject,start_time,end_time\n");
file_put_contents($test_csv, "SLAB1,Monday,Test Subject,7:30 AM,10:30 AM\n");

echo "Testing MINIMAL import:\n";
echo "CSV file: $test_csv\n\n";

try {
    $result = importClassScheduleCsv($test_csv, false);
    echo "Result:\n";
    print_r($result);
    echo "\n";
    
    if ($result['imported'] > 0) {
        echo "✅ SUCCESS!\n";
    } else {
        echo "❌ FAILED:\n";
        foreach ($result['errors'] as $err) {
            echo "  - $err\n";
        }
    }
} catch (PDOException $e) {
    echo "❌ PDOException!\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  File: " . substr($e->getFile(), strrpos($e->getFile(), '\\') + 1) . "\n";
    echo "  Line: " . $e->getLine() . "\n";
} catch (RuntimeException $e) {
    echo "❌ RuntimeException!\n";
    echo "  Message: " . $e->getMessage() . "\n";
}

// Cleanup
unlink($test_csv);
echo "\n=== Done ===\n";