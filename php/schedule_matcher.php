<?php

function ensure_matched_schedule_schema(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS matched_schedules (
            matched_id INT(11) NOT NULL AUTO_INCREMENT,
            student_schedule_id INT(11) NOT NULL,
            professor_schedule_id INT(11) DEFAULT NULL,
            match_status ENUM('matched','no_match','pending','conflict') NOT NULL,
            matched_at DATETIME DEFAULT NULL,
            PRIMARY KEY (matched_id),
            KEY student_schedule_id (student_schedule_id, professor_schedule_id),
            KEY professor_schedule_id (professor_schedule_id),
            CONSTRAINT matched_schedules_ibfk_1 FOREIGN KEY (student_schedule_id) REFERENCES student_schedules (student_schedule_id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT matched_schedules_ibfk_2 FOREIGN KEY (professor_schedule_id) REFERENCES faculty_schedules (professor_schedule_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function normalize_match_day(string $day): array {
    $normalized = strtoupper(trim($day));
    if ($normalized === '') {
        return [];
    }

    $normalized = str_replace(['THURSDAY', 'THURS', 'THU'], 'TH', $normalized);
    $normalized = str_replace(['SUNDAY', 'SUN'], 'SU', $normalized);
    $normalized = str_replace(['MONDAY', 'MON'], 'M', $normalized);
    $normalized = str_replace(['TUESDAY', 'TUES', 'TUE'], 'T', $normalized);
    $normalized = str_replace(['WEDNESDAY', 'WED'], 'W', $normalized);
    $normalized = str_replace(['FRIDAY', 'FRI'], 'F', $normalized);
    $normalized = str_replace(['SATURDAY', 'SAT'], 'S', $normalized);

    preg_match_all('/TH|SU|M|T|W|F|S/', $normalized, $matches);
    return array_values(array_unique($matches[0] ?? []));
}

function days_overlap(string $student_day, string $faculty_day): bool {
    return count(array_intersect(normalize_match_day($student_day), normalize_match_day($faculty_day))) > 0;
}

function time_windows_overlap(string $student_start, string $student_end, string $faculty_start, string $faculty_end): bool {
    if ($student_start === '00:00:00' || $student_end === '00:00:00' || $faculty_start === '00:00:00' || $faculty_end === '00:00:00') {
        return false;
    }

    return strtotime($student_start) < strtotime($faculty_end) && strtotime($faculty_start) < strtotime($student_end);
}

function find_faculty_matches_for_student_schedule(mysqli $conn, array $student_schedule): array {
    $semester = $student_schedule['semester'] ?? '';
    $school_year = $student_schedule['school_year'] ?? '';
    $schedule_code = trim((string) ($student_schedule['schedule_code'] ?? ''));

    if ($schedule_code === '') {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT professor_schedule_id, schedule_code, day, time_start, time_end
        FROM faculty_schedules
        WHERE status = 'active'
          AND semester = ?
          AND school_year = ?
          AND schedule_code = ?
    ");
    $stmt->bind_param(
        "sss",
        $semester,
        $school_year,
        $schedule_code
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $matches = [];
    while ($faculty_schedule = $result->fetch_assoc()) {
        if (
            days_overlap($student_schedule['day'], $faculty_schedule['day'])
            && time_windows_overlap(
                $student_schedule['time_start'],
                $student_schedule['time_end'],
                $faculty_schedule['time_start'],
                $faculty_schedule['time_end']
            )
        ) {
            $matches[] = $faculty_schedule;
        }
    }

    $stmt->close();
    return $matches;
}

function upsert_student_schedule_match(mysqli $conn, array $student_schedule): void {
    ensure_matched_schedule_schema($conn);

    $student_schedule_id = (int) $student_schedule['student_schedule_id'];
    $delete_stmt = $conn->prepare("DELETE FROM matched_schedules WHERE student_schedule_id = ?");
    $delete_stmt->bind_param("i", $student_schedule_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    $matches = find_faculty_matches_for_student_schedule($conn, $student_schedule);

    if (count($matches) === 1) {
        $status = 'matched';
        $professor_schedule_id = (int) $matches[0]['professor_schedule_id'];
    } elseif (count($matches) > 1) {
        $status = 'conflict';
        $professor_schedule_id = (int) $matches[0]['professor_schedule_id'];
    } else {
        $status = 'no_match';
        $professor_schedule_id = null;
    }

    if ($professor_schedule_id === null) {
        $insert_stmt = $conn->prepare("
            INSERT INTO matched_schedules (student_schedule_id, professor_schedule_id, match_status, matched_at)
            VALUES (?, NULL, ?, NOW())
        ");
        $insert_stmt->bind_param("is", $student_schedule_id, $status);
    } else {
        $insert_stmt = $conn->prepare("
            INSERT INTO matched_schedules (student_schedule_id, professor_schedule_id, match_status, matched_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insert_stmt->bind_param("iis", $student_schedule_id, $professor_schedule_id, $status);
    }

    $insert_stmt->execute();
    $insert_stmt->close();
}

function match_student_upload_schedules(mysqli $conn, int $student_id, int $upload_id): void {
    $stmt = $conn->prepare("
        SELECT *
        FROM student_schedules
        WHERE student_id = ? AND upload_id = ? AND status = 'active'
    ");
    $stmt->bind_param("ii", $student_id, $upload_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($student_schedule = $result->fetch_assoc()) {
        upsert_student_schedule_match($conn, $student_schedule);
    }

    $stmt->close();
}

function refresh_matches_for_faculty_upload(mysqli $conn, int $professor_id, int $upload_id): void {
    ensure_matched_schedule_schema($conn);

    $faculty_stmt = $conn->prepare("
        SELECT DISTINCT semester, school_year
        FROM faculty_schedules
        WHERE professor_id = ? AND upload_id = ? AND status = 'active'
    ");
    $faculty_stmt->bind_param("ii", $professor_id, $upload_id);
    $faculty_stmt->execute();
    $faculty_result = $faculty_stmt->get_result();

    while ($term = $faculty_result->fetch_assoc()) {
        $student_stmt = $conn->prepare("
            SELECT DISTINCT ss.student_id, ss.upload_id
            FROM student_schedules ss
            WHERE ss.status = 'active'
              AND ss.semester = ?
              AND ss.school_year = ?
        ");
        $student_stmt->bind_param("ss", $term['semester'], $term['school_year']);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();

        while ($student_upload = $student_result->fetch_assoc()) {
            match_student_upload_schedules($conn, (int) $student_upload['student_id'], (int) $student_upload['upload_id']);
        }

        $student_stmt->close();
    }

    $faculty_stmt->close();
}

function refresh_matches_for_terms(mysqli $conn, array $terms): void {
    ensure_matched_schedule_schema($conn);

    foreach ($terms as $term) {
        $semester = $term['semester'] ?? '';
        $school_year = $term['school_year'] ?? '';

        if ($semester === '' || $school_year === '') {
            continue;
        }

        $student_stmt = $conn->prepare("
            SELECT DISTINCT student_id, upload_id
            FROM student_schedules
            WHERE status = 'active'
              AND semester = ?
              AND school_year = ?
        ");
        $student_stmt->bind_param("ss", $semester, $school_year);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();

        while ($student_upload = $student_result->fetch_assoc()) {
            match_student_upload_schedules($conn, (int) $student_upload['student_id'], (int) $student_upload['upload_id']);
        }

        $student_stmt->close();
    }
}

