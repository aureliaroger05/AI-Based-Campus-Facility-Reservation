<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/facilities.php';

function parseClassScheduleDate(string $value): ?string {
    $value = trim($value);
    foreach (['!Y-m-d' => 'Y-m-d', '!m/d/Y' => 'm/d/Y', '!n/j/Y' => 'n/j/Y', '!Y/m/d' => 'Y/m/d', '!d-m-Y' => 'd-m-Y'] as $format => $outputFormat) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date !== false &&
            ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) &&
            $date->format($outputFormat) === $value
        ) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false && $timestamp > 0) {
        return date('Y-m-d', $timestamp);
    }

    return null;
}

function parseClassScheduleTime(string $value): ?string {
    $value = trim($value);
    foreach (['!H:i', '!H:i:s', '!g:i A', '!h:i A', '!g:i a', '!h:i a', '!g:iA', '!h:iA', '!g:ia', '!h:ia'] as $format) {
        $time = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($time !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $time->format('H:i:s');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('H:i:s', $timestamp);
    }

    return null;
}

function resolveFacility(string $facilityName, array $facilityIds): ?int {
    $cleanInput = trim($facilityName);
    if ($cleanInput === '') {
        return null;
    }

    $normalizedInput = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cleanInput));
    if ($normalizedInput === '') {
        return null;
    }

    // 1. Direct exact normalized match (ignoring spaces, punctuation, casing, e.g. "SLAB1" <-> "SLAB 1")
    foreach ($facilityIds as $name => $id) {
        $normDbName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$name));
        if ($normDbName === $normalizedInput) {
            return (int)$id;
        }
    }

    // 2. Exact word boundary match (e.g. "201" matching "ROOM 201")
    foreach ($facilityIds as $name => $id) {
        if (preg_match('/\b' . preg_quote($cleanInput, '/') . '\b/i', (string)$name)) {
            return (int)$id;
        }
    }

    // 3. Substring match (e.g. "Gym" matching "Gymnasium")
    $matches = [];
    foreach ($facilityIds as $name => $id) {
        $normDbName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$name));
        if (str_contains($normDbName, $normalizedInput) || str_contains($normalizedInput, $normDbName)) {
            $matches[] = (int)$id;
        }
    }

    $uniqueMatches = array_unique($matches);
    if (count($uniqueMatches) === 1) {
        return (int)$uniqueMatches[0];
    }

    return null;
}

function resolveSubject(string $subjectCode, string $subjectName, string $section = '', string $instructor = ''): string {
    $parts = [];
    $code = trim($subjectCode);
    $name = trim($subjectName);
    $sec = trim($section);

    if ($code !== '' && $name !== '') {
        $parts[] = "$code - $name";
    } elseif ($name !== '') {
        $parts[] = $name;
    } elseif ($code !== '') {
        $parts[] = $code;
    }

    if ($sec !== '') {
        $parts[] = "($sec)";
    }

    $result = trim(implode(' ', $parts));
    return $result !== '' ? $result : 'Class Schedule';
}

function resolveDayToDates(string $day, int $weeks = 12): array {
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
    $targetDay = null;
    foreach ($dayMap as $name => $num) {
        if (stripos($normalized, $name) !== false) {
            $targetDay = $num;
            break;
        }
    }

    if ($targetDay === null) {
        return [];
    }

    $dates = [];
    $tz = new DateTimeZone('Asia/Manila');
    $cursor = new DateTime('now', $tz);
    $currentDayOfWeek = (int)$cursor->format('N');
    $diff = $targetDay - $currentDayOfWeek;
    $firstOccurrence = (clone $cursor)->modify("{$diff} days");

    // Include occurrences from today onward (skip past days this week)
    if ($firstOccurrence->format('Y-m-d') < $cursor->format('Y-m-d')) {
        $firstOccurrence->modify('+7 days');
    }

    for ($i = 0; $i < $weeks; $i++) {
        $dateObj = (clone $firstOccurrence)->modify("+{$i} weeks");
        $dates[] = $dateObj->format('Y-m-d');
    }

    return $dates;
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

        // Clean UTF-8 BOM and normalize header names (lowercase, underscores replaced by spaces)
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
        $headers = array_map(static fn($h) => strtolower(trim(str_replace('_', ' ', (string)$h))), $headers);
        $columnIndexes = array_flip($headers);

        // Fetch all facilities from DB
        $facilities = getDB()->query('SELECT id, name FROM facilities')->fetchAll();
        $facilityIds = [];
        foreach ($facilities as $facility) {
            $key = trim((string)$facility['name']);
            $facilityIds[$key] = (int)$facility['id'];
        }

        if (empty($facilityIds)) {
            return ['imported' => 0, 'errors' => ['No facilities exist in the database. Please create facilities before importing schedules.']];
        }

        // Detect columns present in CSV
        $hasSlab = isset($columnIndexes['slab']);
        $hasRoom = isset($columnIndexes['room']);
        $hasFacility = isset($columnIndexes['facility']);
        $hasDay = isset($columnIndexes['day']);
        $hasDate = isset($columnIndexes['date']);
        $hasStartTime = isset($columnIndexes['start time']);
        $hasEndTime = isset($columnIndexes['end time']);
        $hasSubject = isset($columnIndexes['subject']);
        $hasSubjectName = isset($columnIndexes['subject name']);
        $hasSubjectCode = isset($columnIndexes['subject code']);

        if (!$hasFacility && !$hasSlab && !$hasRoom) {
            return ['imported' => 0, 'errors' => ['The CSV must include a facility column (e.g. "facility", "slab", or "room").']];
        }
        if (!$hasDate && !$hasDay) {
            return ['imported' => 0, 'errors' => ['The CSV must include either a "date" (YYYY-MM-DD) or "day" (e.g. Monday) column.']];
        }
        if (!$hasStartTime || !$hasEndTime) {
            return ['imported' => 0, 'errors' => ['The CSV must include "start_time" and "end_time" columns.']];
        }

        $rows = [];
        $errors = [];
        $lineNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if ($values === [null] || (count($values) === 1 && trim((string)$values[0]) === '')) {
                continue;
            }

            $getValue = static function (string $col) use ($columnIndexes, $values): string {
                return isset($columnIndexes[$col]) ? trim((string)($values[$columnIndexes[$col]] ?? '')) : '';
            };

            // 1. Resolve facility
            $facilityId = null;
            $rawFacility = '';
            if ($hasSlab && $getValue('slab') !== '') {
                $rawFacility = $getValue('slab');
                $facilityId = resolveFacility($rawFacility, $facilityIds);
            }
            if ($facilityId === null && $hasFacility && $getValue('facility') !== '') {
                $rawFacility = $getValue('facility');
                $facilityId = resolveFacility($rawFacility, $facilityIds);
            }
            if ($facilityId === null && $hasRoom && $getValue('room') !== '') {
                $rawFacility = $getValue('room');
                $facilityId = resolveFacility($rawFacility, $facilityIds);
            }

            if ($facilityId === null) {
                $errors[] = "Row {$lineNumber}: Could not match facility '{$rawFacility}' to any existing facility in the system.";
                continue;
            }

            // 2. Resolve times
            $rawStart = $getValue('start time');
            $rawEnd = $getValue('end time');
            $startTime = parseClassScheduleTime($rawStart);
            $endTime = parseClassScheduleTime($rawEnd);

            if ($startTime === null) {
                $errors[] = "Row {$lineNumber}: Invalid start time '{$rawStart}'.";
                continue;
            }
            if ($endTime === null) {
                $errors[] = "Row {$lineNumber}: Invalid end time '{$rawEnd}'.";
                continue;
            }
            if ($startTime >= $endTime) {
                $errors[] = "Row {$lineNumber}: Start time ({$rawStart}) must be earlier than end time ({$rawEnd}).";
                continue;
            }

            // 3. Resolve subject
            if ($hasSubject && $getValue('subject') !== '') {
                $subject = $getValue('subject');
            } else {
                $subject = resolveSubject(
                    $getValue('subject code'),
                    $getValue('subject name'),
                    $getValue('section'),
                    $getValue('instructor')
                );
            }

            if (strlen($subject) > 255) {
                $subject = mb_substr($subject, 0, 255);
            }

            // 4. Resolve dates (specific date or recurring weekly days)
            $classDates = [];
            if ($hasDate && $getValue('date') !== '') {
                $parsedDate = parseClassScheduleDate($getValue('date'));
                if ($parsedDate === null) {
                    $errors[] = "Row {$lineNumber}: Invalid date '{$getValue('date')}'. Use YYYY-MM-DD or M/D/YYYY.";
                    continue;
                }
                $classDates[] = $parsedDate;
            } elseif ($hasDay && $getValue('day') !== '') {
                $classDates = resolveDayToDates($getValue('day'), 12);
                if (empty($classDates)) {
                    $errors[] = "Row {$lineNumber}: Could not resolve day '{$getValue('day')}'. Use Monday, Tuesday, etc.";
                    continue;
                }
            }

            foreach ($classDates as $classDate) {
                $rows[] = [
                    'facility_id' => $facilityId,
                    'subject'     => $subject,
                    'class_date'  => $classDate,
                    'start_time'  => $startTime,
                    'end_time'    => $endTime,
                    'line'        => $lineNumber,
                ];
            }
        }

        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        if (empty($rows)) {
            return ['imported' => 0, 'errors' => ['No schedule rows were found in the uploaded file.']];
        }

        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            $exact = $pdo->prepare(
                'SELECT id FROM class_schedules WHERE facility_id = ? AND class_date = ? AND start_time = ? AND end_time = ? LIMIT 1'
            );
            $reservationConflict = $pdo->prepare(
                "SELECT id FROM reservations WHERE facility_id = ? AND reserve_date = ? AND status != 'cancelled' AND (" .
                "((start_time <> '00:00:00' OR end_time <> '00:00:00') AND start_time < ? AND end_time > ?) " .
                "OR (start_time = '00:00:00' AND end_time = '00:00:00' AND reserve_time >= ? AND reserve_time < ?)) LIMIT 1"
            );
            $existingOverlap = $pdo->prepare(
                'SELECT id FROM class_schedules WHERE facility_id = ? AND class_date = ? AND start_time < ? AND end_time > ? LIMIT 1'
            );
            $insert = $pdo->prepare(
                'INSERT INTO class_schedules (facility_id, subject, class_date, start_time, end_time) VALUES (?, ?, ?, ?, ?)'
            );

            $imported = 0;

            foreach ($rows as $row) {
                // If exact block already exists, skip quietly
                $exact->execute([$row['facility_id'], $row['class_date'], $row['start_time'], $row['end_time']]);
                if ($exact->fetchColumn() !== false) {
                    continue;
                }

                // Check conflict with an existing reservation
                $reservationConflict->execute([
                    $row['facility_id'],
                    $row['class_date'],
                    $row['end_time'],
                    $row['start_time'],
                    $row['start_time'],
                    $row['end_time'],
                ]);
                if ($reservationConflict->fetch() !== false) {
                    $errors[] = "Row {$row['line']} on {$row['class_date']}: Class time ({$row['start_time']} - {$row['end_time']}) conflicts with an active reservation.";
                    continue;
                }

                // Check conflict with another overlapping class schedule
                $existingOverlap->execute([
                    $row['facility_id'],
                    $row['class_date'],
                    $row['end_time'],
                    $row['start_time'],
                ]);
                if ($existingOverlap->fetch() !== false) {
                    $errors[] = "Row {$row['line']} on {$row['class_date']}: Class time overlaps an already registered class for this facility.";
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
