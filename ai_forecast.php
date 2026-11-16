<?php
require_once 'config.php';
require_once 'facilities.php';

if (!function_exists('getForecast')) {
function getForecast($facility_id, $days_ahead = 90) {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT reserve_date, SUM(usage_count) AS count FROM usage_logs " .
        "WHERE facility_id = ? AND reserve_date <= CURDATE() " .
        "GROUP BY reserve_date ORDER BY reserve_date DESC LIMIT 30"
    );
    $stmt->execute([$facility_id]);
    $data = $stmt->fetchAll();
    
    if (empty($data)) return [];
    
    $total_count = array_sum(array_column($data, 'count'));
    $days_count = count($data);
    $avg_usage = $days_count > 0 ? $total_count / $days_count : 0;
    $weekdayUsage = [];
    foreach ($data as $usageDay) {
        $weekday = (int)date('w', strtotime($usageDay['reserve_date']));
        if (!isset($weekdayUsage[$weekday])) {
            $weekdayUsage[$weekday] = ['total' => 0, 'days' => 0];
        }
        $weekdayUsage[$weekday]['total'] += (int)$usageDay['count'];
        $weekdayUsage[$weekday]['days']++;
    }

    $weekdayAverages = [];
    foreach ($weekdayUsage as $weekday => $usage) {
        $weekdayAverages[$weekday] = $usage['total'] / $usage['days'];
    }

    $trend_factor = 1.05;
    $forecast = [];
    for ($i = 0; $i < $days_ahead; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $weekday = (int)date('w', strtotime($date));
        $baseline = $weekdayAverages[$weekday] ?? $avg_usage;
        $pred = round($baseline * $trend_factor);
        $forecast[] = ['date' => $date, 'predicted_usage' => max(0, $pred)];
    }
    return $forecast;
}
}

if (!function_exists('getAllForecasts')) {
function getAllForecasts() {
    $facilities = getFacilities();
    $forecasts = [];
    foreach ($facilities as $f) {
        $forecasts[$f['id']] = getForecast($f['id']);
    }
    return $forecasts;
}
}

?>

