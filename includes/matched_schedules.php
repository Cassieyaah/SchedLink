<?php
/**
 * SchedLink Core Match Engine (Production Version)
 * Populates, maintains, and updates the state of the matched_schedules tracking table.
 * ONLY filters and syncs schedules that are marked as 'active'.
 * * @param mysqli $conn The active database connection instance
 */
function synchronize_schedule_matches($conn): void {
    // 1. Insert tracking stubs ONLY for newly created ACTIVE student schedules missing from the ledger
    $conn->query("
        INSERT INTO matched_schedules (student_schedule_id, match_status, matched_at)
        SELECT ss.student_schedule_id, 'no_match', NOW()
        FROM student_schedules ss
        LEFT JOIN matched_schedules ms ON ss.student_schedule_id = ms.student_schedule_id
        WHERE ms.matched_id IS NULL AND ss.status = 'active'
    ");

    // 2. Clean up dead links OR links pointing to schedules that were removed or changed to 'archived'
    $conn->query("
        DELETE ms FROM matched_schedules ms
        LEFT JOIN student_schedules ss ON ms.student_schedule_id = ss.student_schedule_id
        WHERE ss.student_schedule_id IS NULL OR ss.status != 'active'
    ");

    // 3. Match records based on unique Schedule Codes, but ONLY if BOTH student and faculty rows are 'active'
    $conn->query("
        UPDATE matched_schedules ms
        JOIN student_schedules ss ON ms.student_schedule_id = ss.student_schedule_id
        JOIN faculty_schedules fs ON ss.schedule_code = fs.schedule_code
        SET 
            ms.professor_schedule_id = fs.professor_schedule_id,
            ms.match_status = 'matched',
            ms.matched_at = NOW()
        WHERE ss.status = 'active' AND fs.status = 'active'
    ");

    // 4. Reset records to 'no_match' if matching faculty schedules are missing, deleted, or flipped to 'archived'
    $conn->query("
        UPDATE matched_schedules ms
        LEFT JOIN student_schedules ss ON ms.student_schedule_id = ss.student_schedule_id
        LEFT JOIN faculty_schedules fs ON ss.schedule_code = fs.schedule_code AND fs.status = 'active'
        SET 
            ms.professor_schedule_id = NULL,
            ms.match_status = 'no_match',
            ms.matched_at = NOW()
        WHERE (fs.professor_schedule_id IS NULL OR ss.status != 'active') AND ms.match_status != 'no_match'
    ");
}