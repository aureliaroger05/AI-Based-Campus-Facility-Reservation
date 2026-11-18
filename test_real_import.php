<?php
// Real import test - uses actual form validation logic
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/class_schedules.php';

// Simulate what the form does when CSV is uploaded
// The form checks: !isset($_FILES['schedule_file']) || !is_array($_FILES['schedule_file'])
// So if we provide that, it should proceed to the import

// But since we can't easily simulate file upload via CLI,
// let me just check what the code would do with your CSV structure

echo "=== CSV Structure Validation ===\n\n";

// Your CSV headers (as they would appear)
$csv_headers = ['facility', 'day', 'subject', 'start_time', 'end_time'];

// Normalize like the code does
$normalized_headers = array_map(
    static fn($h) => strtolower(trim(str_replace('_', ' ', (string)$h))),
    $csv_headers
);

echo "CSV Headers: " . implode(', ', $csv_headers) . "\n";
echo "Normalized: " . implode(', ', $normalized_headers) . "\n\n";

// Check required columns
$checks = [
    'facility' => isset($normalized_headers['facility']),
    'start time' => isset($normalized_headers['start time']),
    'end time' => isset($normalized_headers['end time']),
    'day' => isset($normalized_headers['day']),
    'subject' => isset($normalized_headers['subject']),
];

echo "Column Checks:\n";
foreach ($checks as $col => $exists) {
    echo "  " . str_pad($col, 15) . ": " . ($exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
}

$all_ok = array_values($checks) === array_fill(0, count($checks), true);
echo "\n" . ($all_ok ? "✅ ALL COLUMNS PRESENT - Import should work!" : "❌ Missing columns") . "\n\n";

// Now let's test actual facility resolution
echo "=== Facility Resolution Test ===\n\n";
global $pdo;
$pdo = new PDO('mysql:host=localhost;dbname=reservation_sys', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$facilities = $pdo->query('SELECT id, name FROM facilities')->fetchAll();
echo "Facilities in DB:\n";
foreach ($facilities as $f) {
    echo "  ID:{$f['id']} Name:{$f['name']}\n";
}

// Test resolving SLAB1 like the code does
$cleanInput = 'SLAB1';
$normalizedInput = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cleanInput));
echo "\nCSV facility: SLAB1 → normalized: $normalizedInput\n";

foreach ($facilities as $name => $id) {
    $normDbName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    $match = ($normDbName === $normalizedInput) ? "✅ EXACT MATCH" : "";
    echo "  DB: {$name} → normalized: $normDbName $match\n";
}