<?php
session_start();

// Ensure the database connection layout configuration handles requests cleanly
include '../includes/db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// FORCE IDENTIFICATION VIA DATABASE LOOKUP (Bypasses missing session strings)
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$role = $user ? strtolower(trim($user['role'])) : '';
$redirect_page = ($role === "faculty") ? "facultydashboard.php" : "studentdashboard.php";

// Catch empty or broken submissions early
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['courses'])) {
    $_SESSION['upload_error'] = "No schedule data received for confirmation.";
    header("Location: " . $redirect_page);
    exit();
}

$conn->begin_transaction();

try {
    // Fetch active semester settings configuration constraints
    $settings_query = $conn->query("SELECT current_semester, current_school_year FROM system_settings WHERE id = 1");
    if (!$settings_query) {
        throw new Exception("System settings table configuration missing. Please verify database schema.");
    }
    
    $system_config  = $settings_query->fetch_assoc();
    $current_semester    = $system_config['current_semester'] ?? '1st Semester';
    $current_school_year = $system_config['current_school_year'] ?? '2025-2026';

    if ($role === "student") {
        $profile_stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $profile_res = $profile_stmt->get_result()->fetch_assoc();
        $profile_stmt->close();

        if (!$profile_res) {
            throw new Exception("Student profile matching identity missing. Please complete your profile records details first.");
        }
        $student_id = $profile_res['student_id'];

        // Drop current term tracking boundaries safely to prepare clean write
        $delete_stmt = $conn->prepare("DELETE FROM student_schedules WHERE student_id = ? AND semester = ? AND school_year = ?");
        $delete_stmt->bind_param("iss", $student_id, $current_semester, $current_school_year);
        $delete_stmt->execute();
        $delete_stmt->close();

        $insert_stmt = $conn->prepare("INSERT INTO student_schedules (student_id, schedule_code, course_code, course_description, time_start, time_end, day, room, semester, school_year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");

    } else if ($role === "faculty") {
        $profile_stmt = $conn->prepare("SELECT professor_id FROM faculties WHERE user_id = ?");
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $profile_res = $profile_stmt->get_result()->fetch_assoc();
        $profile_stmt->close();

        if (!$profile_res) {
            throw new Exception("Faculty profile matching identity missing. Please complete your profile records details first.");
        }
        $professor_id = $profile_res['professor_id'];

        $delete_stmt = $conn->prepare("DELETE FROM faculty_schedules WHERE professor_id = ? AND semester = ? AND school_year = ?");
        $delete_stmt->bind_param("iss", $professor_id, $current_semester, $current_school_year);
        $delete_stmt->execute();
        $delete_stmt->close();

        $insert_stmt = $conn->prepare("INSERT INTO faculty_schedules (professor_id, schedule_code, course_code, course_description, day, time_start, time_end, room, semester, school_year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    } else {
        throw new Exception("Unauthorized role status action matching exception structural scope: Current user detected as role: '" . htmlspecialchars($role) . "'");
    }

    if (!$insert_stmt) {
        throw new Exception("Database preparation statement failure: " . $conn->error);
    }

    // Loop through the verified form fields posted from our modal layout view
    foreach ($_POST['courses'] as $index => $course) {
        $sched_code  = $course['sched_code'] ?? 'UNKNOWN';
        $course_code = $course['course_code'] ?? 'UNKNOWN';
        $description = $course['description'] ?? 'GENERAL ACADEMIC COURSE';
        $day         = $course['day'] ?? 'M';
        $room        = !empty($course['room']) ? $course['room'] : 'TBA';
        $time_range  = $course['time_range'] ?? '';
        
        $time_start = "00:00:00";
        $time_end   = "00:00:00";

        // Parse time ranges safely into standardized structures
        if (!empty($time_range) && strpos($time_range, '-') !== false) {
            list($start, $end) = explode('-', $time_range);
            $time_start = date("H:i:s", strtotime(trim($start)));
            $time_end   = date("H:i:s", strtotime(trim($end)));
        }

        if ($role === "student") {
            $insert_stmt->bind_param(
                "isssssssss", 
                $student_id, $sched_code, $course_code, $description, 
                $time_start, $time_end, $day, $room, 
                $current_semester, $current_school_year
            );
        } else {
            $insert_stmt->bind_param(
                "isssssssss", 
                $professor_id, $sched_code, $course_code, $description, 
                $day, $time_start, $time_end, $room, 
                $current_semester, $current_school_year
            );
        }
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed executing record save at row index ($index): " . $insert_stmt->error);
        }
    }

    $insert_stmt->close();
    $conn->commit();

    // SUCCESS! Clear the preview cache so the modal closes automatically
    unset($_SESSION['ocr_preview_data']);
    $_SESSION['upload_success'] = "Verified class schedule entries saved successfully for $current_semester!";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['upload_error'] = "Database Processing Exception: " . $e->getMessage();
}

header("Location: " . $redirect_page);
exit();