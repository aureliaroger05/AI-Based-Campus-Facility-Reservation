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
        'monday'    => 1,
        'tuesday'   => 2,
        'wednesday' => 3,
        'thursday'  => 4,
        'friday'    => 5,
        'saturday'  => 6,
        'sunday'    => 7,
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

        $isOurFormat = ($headers[0] ?? '') === 'slab';

        if ($isOurFormat) {
            $requiredHeaders = ['slab', 'day', 'start time', 'end time', 'room', 'section', 'subject code', 'subject name', 'instructor', 'notes'];
        } else {
            $requiredHeaders = ['facility', 'subject', 'date', 'start_time', 'end_time'];
        }

        if (count($headers) !== count(array_unique($headers)) || array_diff($requiredHeaders, $headers)) {
            return [
                'imported' => 0,
                'errors'   => ['The CSV must include each of these headers once: ' . implode(', ', $requiredHeaders) . '.'],
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

            if ($isOurFormat) {
                $facilityName = $getValue('room');
                $subjectCode  = $getValue('subject code');
                $subjectName  = $getValue('subject name');
                $day          = $getValue('day');
                $startTime    = $getValue('start time');
                $endTime      = $getValue('end time');

                $facilityId = resolveFacility($facilityName, $facilityIds);
                if ($facilityId === null) {
                    $errors[] = "Row {$lineNumber}: Unknown facility/room '{$facilityName}'.";
                    continue;
                }

                $subject    = resolveSubject($subjectCode, $subjectName);
                $parsedDate = resolveDayToDate($day, $startTime, $startTime);
                if ($parsedDate === null) {
                    $errors[] = "Row {$lineNumber}: Could not resolve date from Day '{$day}'.";
                    continue;
                }

                $parsedStartTime = parseClassScheduleTime($startTime);
                $parsedEndTime   = parseClassScheduleTime($endTime);

                if ($parsedStartTime === null) {
                    $errors[] = "Row {$lineNumber}: Invalid start time '{$startTime}'.";
                    continue;
                }
                if ($parsedEndTime === null) {
                    $errors[] = "Row {$lineNumber}: Invalid end time '{$endTime}'.";
                    continue;
                }

                $rows[] = [
                    'facility_id'   => $facilityId,
                    'facility_name' => $facilityName,
                    'subject'       => $subject,
                    'class_date'    => $parsedDate,
                    'start_time'    => $parsedStartTime,
                    'end_time'      => $parsedEndTime,
                    'slab'          => $getValue('slab'),
                    'section'       => $getValue('section'),
                    'instructor'    => $getValue('instructor'),
                    'notes'         => $getValue('notes'),
                    'line'          => $lineNumber,
                ];
            } else {
                $facilityName = $getValue('facility');
                $facilityKey  = strtolower($facilityName);
                if (!isset($facilityIds[$facilityKey])) {
                    $errors[] = "Row {$lineNumber}: facility '{$facilityName}' does not match an existing facility name.";
                    continue;
                }
                $subject = $getValue('subject');
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
                $date = parseClassScheduleDate($getValue('date'));
                if ($date === null) {
                    $errors[] = "Row {$lineNumber}: date must be YYYY-MM-DD or M/D/YYYY.";
                    continue;
                }
                $startTime = parseClassScheduleTime($getValue('start_time'));
                $endTime   = parseClassScheduleTime($getValue('end_time'));
                if ($startTime === null || $endTime === null || $startTime >= $endTime) {
                    $errors[] = "Row {$lineNumber}: use valid start/end times with the start earlier than the end.";
                    continue;
                }

                $rows[] = [
                    'facility_id' => $facilityIds[$facilityKey],
                    'facility'    => $facilityName,
                    'subject'     => $subject,
                    'class_date'  => $date,
                    'start_time'  => $startTime,
                    'end_time'    => $endTime,
                    'line'        => $lineNumber,
                ];
            }
        }

        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        $originalRows = $rows;
        $rows = [];
        foreach ($originalRows as $row) {
            if ($row['start_time'] < $row['end_time']) {
                $rows[] = $row;
            } else {
                $errors[] = "Row {$row['line']}: start_time must be before end_time.";
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
                "OR (start_time = '00:00:00' AND end_time = '00:00:00' AND reserve_time >= ? AND reserve_time < ?)) LIMIT 1"
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
