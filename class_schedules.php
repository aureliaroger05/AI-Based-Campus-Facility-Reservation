<?php
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

function importClassScheduleCsv(string $filePath): array {
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The uploaded schedule file could not be opened.');
    }

    try {
        $headers = fgetcsv($handle);
        if ($headers === false) {
            return ['imported' => 0, 'errors' => ['The CSV file is empty.']];
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
        $headers = array_map(static fn($header) => strtolower(trim((string)$header)), $headers);
        $requiredHeaders = ['facility', 'subject', 'date', 'start_time', 'end_time'];
        if (count($headers) !== count(array_unique($headers)) || array_diff($requiredHeaders, $headers)) {
            return [
                'imported' => 0,
                'errors' => ['The CSV must include each of these headers once: facility, subject, date, start_time, end_time.'],
            ];
        }

        $columnIndexes = array_flip($headers);
        $facilities = getDB()->query('SELECT id, name FROM facilities')->fetchAll();
        $facilityIds = [];
        foreach ($facilities as $facility) {
            $key = strtolower(trim($facility['name']));
            if (isset($facilityIds[$key])) {
                return ['imported' => 0, 'errors' => ['Facility names must be unique to import schedule rows by facility name.']];
            }
            $facilityIds[$key] = (int)$facility['id'];
        }

        $rows = [];
        $errors = [];
        $lineNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if ($values === [null] || (count($values) === 1 && trim((string)$values[0]) === '')) {
                continue;
            }

            $getValue = static function (string $column) use ($columnIndexes, $values): string {
                return trim((string)($values[$columnIndexes[$column]] ?? ''));
            };

            $facilityName = $getValue('facility');
            $facilityKey = strtolower($facilityName);
            $subject = $getValue('subject');
            $date = parseClassScheduleDate($getValue('date'));
            $startTime = parseClassScheduleTime($getValue('start_time'));
            $endTime = parseClassScheduleTime($getValue('end_time'));

            if (!isset($facilityIds[$facilityKey])) {
                $errors[] = "Row {$lineNumber}: facility '{$facilityName}' does not match an existing facility name.";
                continue;
            }
            if ($subject === '') {
                $errors[] = "Row {$lineNumber}: subject is required.";
                continue;
            }
            $subjectLength = preg_match_all('/./us', $subject, $subjectCharacters);
            if ($subjectLength === false) {
                $errors[] = "Row {$lineNumber}: subject must contain valid UTF-8 text.";
                continue;
            }
            if ($subjectLength > 255) {
                $errors[] = "Row {$lineNumber}: subject must be 255 characters or fewer.";
                continue;
            }
            if ($date === null) {
                $errors[] = "Row {$lineNumber}: date must be YYYY-MM-DD or M/D/YYYY.";
                continue;
            }
            if ($startTime === null || $endTime === null || $startTime >= $endTime) {
                $errors[] = "Row {$lineNumber}: use valid start/end times with the start earlier than the end.";
                continue;
            }

            $rows[] = [
                'facility_id' => $facilityIds[$facilityKey],
                'facility' => $facilityName,
                'subject' => $subject,
                'class_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'line' => $lineNumber,
            ];
        }

        if (!$rows && !$errors) {
            $errors[] = 'The CSV contains no schedule rows.';
        }
        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        usort($rows, static function ($left, $right) {
            return [$left['facility_id'], $left['class_date'], $left['start_time']]
                <=> [$right['facility_id'], $right['class_date'], $right['start_time']];
        });

        for ($index = 1, $count = count($rows); $index < $count; $index++) {
            $previous = $rows[$index - 1];
            $current = $rows[$index];
            if (
                $previous['facility_id'] === $current['facility_id'] &&
                $previous['class_date'] === $current['class_date'] &&
                $current['start_time'] < $previous['end_time']
            ) {
                $errors[] = "Rows {$previous['line']} and {$current['line']}: class times overlap for the same facility and date.";
            }
        }
        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare(
                'SELECT id, start_time, end_time FROM class_schedules ' .
                'WHERE facility_id = ? AND class_date = ? AND start_time < ? AND end_time > ?'
            );
            $exact = $pdo->prepare(
                'SELECT id FROM class_schedules WHERE facility_id = ? AND class_date = ? AND start_time = ? AND end_time = ?'
            );
            $reservationConflict = $pdo->prepare(
                "SELECT id FROM reservations WHERE facility_id = ? AND reserve_date = ? AND status != 'cancelled' AND (" .
                "((start_time <> '00:00:00' OR end_time <> '00:00:00') AND start_time < ? AND end_time > ?) " .
                "OR (start_time = '00:00:00' AND end_time = '00:00:00' AND reserve_time >= ? AND reserve_time < ?)" .
                ') LIMIT 1'
            );
            $insert = $pdo->prepare(
                'INSERT INTO class_schedules (facility_id, subject, class_date, start_time, end_time) VALUES (?, ?, ?, ?, ?)'
            );
            $imported = 0;

            foreach ($rows as $row) {
                $exact->execute([$row['facility_id'], $row['class_date'], $row['start_time'], $row['end_time']]);
                if ($exact->fetchColumn() !== false) {
                    continue;
                }

                $reservationConflict->execute([
                    $row['facility_id'],
                    $row['class_date'],
                    $row['end_time'],
                    $row['start_time'],
                    $row['start_time'],
                    $row['end_time'],
                ]);
                if ($reservationConflict->fetch() !== false) {
                    $errors[] = "Row {$row['line']}: class time overlaps an existing reservation for this facility and date.";
                    continue;
                }

                $existing->execute([$row['facility_id'], $row['class_date'], $row['end_time'], $row['start_time']]);
                if ($existing->fetch() !== false) {
                    $errors[] = "Row {$row['line']}: class time overlaps an already imported class for this facility and date.";
                    continue;
                }

                $insert->execute([
                    $row['facility_id'],
                    $row['subject'],
                    $row['class_date'],
                    $row['start_time'],
                    $row['end_time'],
                ]);
                $imported++;
            }

            if ($errors) {
                $pdo->rollBack();
                return ['imported' => 0, 'errors' => $errors];
            }

            $pdo->commit();
            return ['imported' => $imported, 'errors' => []];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    } finally {
        fclose($handle);
    }
}
