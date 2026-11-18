x<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/facilities.php';

function parseClassScheduleDate(string $value): ?string {
    foreach (['!Y-m-d' => 'Y-m-d', '!m/d/Y' => 'm/d/Y', '!n/j/Y' => 'n/j/Y'] as $format => $outputFormat) {
        $date = DateTimeImmutable::createFromFormat($format, trim($value));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date !== false &&
            ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) &&
            $date->format($outputFormat) === trim($value)
        ) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

function parseClassScheduleTime(string $value): ?string {
    $value = trim($value);
    foreach (['!H:i', '!H:i:s', '!g:i A', '!h:i A'] as $format) {
        $time = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($time !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $time->format('H:i:s');
        }
    }

    return null;
}

function resolveFacility(string $facilityName, array $facilityIds): ?int {
    $key = strtolower(trim($facilityName));
    if (isset($facilityIds[$key])) {
        return (int)$facilityIds[$key];
    }

    $matches = [];
    foreach ($facilityIds as $name => $id) {
        if (stripos($name, $key) !== false || stripos($key, $name) !== false) {
            $matches[] = $id;
        }
    }

    if (count($matches) === 1) {
        return (int)array_unique($matches)[0];
    }

    return null;
}

function resolveSubject(string $subjectCode, string $subjectName): string {
    return trim($subjectName ?: $subjectCode ?: 'Unknown');
}

function resolveDayToDate(string $day, string $startHour, string $startMinute): ?string {
    $dayMap = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
    ];

    $normalized = strtolower(trim($day));
    $matched = null;
    foreach ($dayMap as $name => $num) {
        if (stripos($normalized, $name) !== false) {
            $matched = $num;
            break;
        }
    }

    if ($matched === null) {
        return null;
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $dayOfWeek = (int)$now->format('N');
    $target = $matched;

    $diff = $target - $dayOfWeek;
    if ($diff <= 0) {
        $diff += 7;
    }

    $importDate = (clone $now)->modify("+{$diff} days");
    $startTime = sprintf('%02d:%02d:00', (int)$startHour, (int)$startMinute);

    $candidate = $importDate->format('Y-m-d') . " $startTime";
    $test = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $candidate, new DateTimeZone('Asia/Manila'));
    if ($test === false) {
        return null;
    }

    $errors = DateTimeImmutable::getLastErrors();
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }

    return $test->format('Y-m-d');
}
