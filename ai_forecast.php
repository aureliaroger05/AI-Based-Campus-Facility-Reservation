<?php
require_once 'config.php';
require_once 'facilities.php';

if (!function_exists('getForecast')) {
function getForecast($facility_id, $days_ahead = 7) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT reserve_date, COUNT(*) as count FROM usage_logs WHERE facility_id = ? GROUP BY reserve_date ORDER BY reserve_date DESC LIMIT 30");
    $stmt->execute([$facility_id]);
    $data = $stmt->fetchAll();
    
    if (empty($data)) return [];
    
    // Simple linear regression / avg prediction
    $total_count = array_sum(array_column($data, 'count'));
    $days_count = count($data);
    $avg_usage = $days_count > 0 ? $total_count / $days_count : 0;
    $trend_factor = 1.05;  // 5% growth
    $forecast = [];
    for ($i = 0; $i < $days_ahead; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $base_pred = $avg_usage * $trend_factor;
        $variance = rand(-20,20)/100;
        $pred = round($base_pred * (1 + $variance));
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

