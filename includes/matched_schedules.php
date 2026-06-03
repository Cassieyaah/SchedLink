<?php
/**
 * SchedLink Matching Engine - Schema Management & Data Association
 */

/**
 * Automatically creates the matched_schedules table if it doesn't exist
 * and sets up constraints to prevent orphan data.
 */
function ensure_matched_schedule_schema($conn): void {
    // 1. Create the tracking table for schedule alignments
    $conn->query("
        CREATE TABLE IF NOT EXISTS matched_schedules (
            match_id INT(11) NOT NULL AUTO_INCREMENT,
            student_schedule_id INT(11) DEFAULT NULL,
            professor_schedule_id INT(11) DEFAULT NULL,
            match_status ENUM('matched', 'conflict', 'no_match') DEFAULT 'no_match',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (match_id),
            UNIQUE KEY unique_student_sched (student_schedule_id),
            KEY professor_schedule_id (professor_schedule_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");

    // 2. Dynamically add Foreign Key constraints if they don't exist yet
    $fkCheckStudent = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'matched_schedules' 
          AND COLUMN_NAME = 'student_schedule_id' 
          AND REFERENCED_TABLE_NAME = 'student_schedules'
    ");
    if ($fkCheckStudent && $fkCheckStudent->num_rows === 0) {
        $conn->query("
            ALTER TABLE matched_schedules 
            ADD CONSTRAINT fk_matched_student 
            FOREIGN KEY (student_schedule_id) 
            REFERENCES student_schedules (student_schedule_id) 
            ON DELETE CASCADE ON UPDATE CASCADE
        ");
    }

    $fkCheckFaculty = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'matched_schedules' 
          AND COLUMN_NAME = 'professor_schedule_id' 
          AND REFERENCED_TABLE_NAME = 'faculty_schedules'
    ");
    if ($fkCheckFaculty && $fkCheckFaculty->num_rows === 0) {
        $conn->query("
            ALTER TABLE matched_schedules 
            ADD CONSTRAINT fk_matched_faculty 
            FOREIGN KEY (professor_schedule_id) 
            REFERENCES faculty_schedules (professor_schedule_id) 
            ON DELETE CASCADE ON UPDATE CASCADE
        ");
    }
}

/**
 * Automatically evaluates and matches a specific student's uploaded schedule
 * against all currently active faculty schedules based on matching schedule codes.
 */
function match_student_upload_schedules($conn, $student_id, $upload_id) {
    $student_id = (int)$student_id;
    $upload_id = (int)$upload_id;

    // 1. Clear any old structural match rows for this specific upload first
    $conn->query("
        DELETE ms FROM matched_schedules ms
        INNER JOIN student_schedules ss ON ms.student_schedule_id = ss.student_schedule_id
        WHERE ss.student_id = $student_id AND ss.upload_id = $upload_id
    ");

    // 2. Find student rows and locate faculty entries with the exact same schedule_code
    // Filter by matching Semester and School Year to avoid cross-year mismatches
    $query = "
        SELECT 
            ss.student_schedule_id, 
            fs.professor_schedule_id
        FROM student_schedules ss
        INNER JOIN faculty_schedules fs ON ss.schedule_code = fs.schedule_code 
            AND ss.semester = fs.semester 
            AND ss.school_year = fs.school_year
        WHERE ss.student_id = ? AND ss.upload_id = ? AND ss.status = 'active' AND fs.status = 'active'
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $student_id, $upload_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. Populate matching pairs into the tracking table
    $insertStmt = $conn->prepare("
        INSERT INTO matched_schedules (student_schedule_id, professor_schedule_id, match_status) 
        VALUES (?, ?, 'matched')
        ON DUPLICATE KEY UPDATE professor_schedule_id = VALUES(professor_schedule_id), match_status = 'matched'
    ");

    while ($row = $result->fetch_assoc()) {
        $insertStmt->bind_param("ii", $row['student_schedule_id'], $row['professor_schedule_id']);
        $insertStmt->execute();
    }
    $insertStmt->close();
    $stmt->close();
}

/**
 * Refreshes matches from the Faculty perspective when an instructor updates their file rows
 */
function refresh_matches_for_faculty_upload($conn, $faculty_id, $upload_id) {
    $faculty_id = (int)$faculty_id;
    $upload_id = (int)$upload_id;

    // Clear old links pointing to this faculty's update sequence
    $conn->query("DELETE FROM matched_schedules WHERE professor_schedule_id IN (
        SELECT professor_schedule_id FROM faculty_schedules WHERE faculty_id = $faculty_id AND upload_id = $upload_id
    )");

    // Re-link matching students who share these schedule codes
    $query = "
        INSERT INTO matched_schedules (student_schedule_id, professor_schedule_id, match_status)
        SELECT ss.student_schedule_id, fs.professor_schedule_id, 'matched'
        FROM faculty_schedules fs
        INNER JOIN student_schedules ss ON fs.schedule_code = ss.schedule_code 
            AND fs.semester = ss.semester 
            AND fs.school_year = ss.school_year
        WHERE fs.faculty_id = ? AND fs.upload_id = ? AND fs.status = 'active' AND ss.status = 'active'
        ON DUPLICATE KEY UPDATE professor_schedule_id = VALUES(professor_schedule_id), match_status = 'matched'
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $faculty_id, $upload_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Global synchronization algorithm to sweep and heal broken associations
 */
function synchronize_schedule_matches($conn) {
    // 1. Clean up orphan rows where schedules no longer exist or were deleted
    $conn->query("
        DELETE ms FROM matched_schedules ms
        LEFT JOIN student_schedules ss ON ms.student_schedule_id = ss.student_schedule_id
        LEFT JOIN faculty_schedules fs ON ms.professor_schedule_id = fs.professor_schedule_id
        WHERE ss.student_schedule_id IS NULL OR fs.professor_schedule_id IS NULL
    ");

    // 2. Perform a blanket check to catch any loose active pairs that haven't been logged yet
    $conn->query("
        INSERT INTO matched_schedules (student_schedule_id, professor_schedule_id, match_status)
        SELECT ss.student_schedule_id, fs.professor_schedule_id, 'matched'
        FROM student_schedules ss
        INNER JOIN faculty_schedules fs ON ss.schedule_code = fs.schedule_code 
            AND ss.semester = fs.semester 
            AND ss.school_year = fs.school_year
        WHERE ss.status = 'active' AND fs.status = 'active'
        ON DUPLICATE KEY UPDATE match_status = 'matched'
    ");
}