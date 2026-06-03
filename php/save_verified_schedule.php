<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php'; 
include '../includes/db.php';
include_once '../includes/matched_schedules.php'; // Include Option B Matching Ledger Engine

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['schedule_file'])) {
    try {
        $spreadsheet = IOFactory::load($_FILES['schedule_file']['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        $parsed_data = [];
        foreach ($data as $index => $row) {
            if ($index == 0) continue; // Skip header
            $parsed_data[] = [
                'schedule_code' => $row[0] ?? '',
                'course_description' => $row[1] ?? '',
                'course_code' => $row[2] ?? '',
                'room' => $row[3] ?? ''
            ];
        }

        // Save to session to be processed by verified block below
        $_SESSION['ocr_preview_data'] = $parsed_data; 
        $_SESSION['upload_success'] = "Excel processed successfully!";
    } catch (Exception $e) {
        $_SESSION['upload_error'] = "Error: " . $e->getMessage();
    }
    header("Location: facultydashboard.php");
    exit();
}
?>
<?php
// Ensure the database connection layout configuration handles requests cleanly

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

function ensure_schedule_upload_schema(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS schedule_uploads (
            upload_id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            role ENUM('student','faculty') NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            stored_file_path VARCHAR(255) DEFAULT NULL,
            semester ENUM('1st Semester','2nd Semester') NOT NULL DEFAULT '1st Semester',
            school_year VARCHAR(9) NOT NULL DEFAULT '2025-2026',
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (upload_id),
            KEY user_id (user_id),
            CONSTRAINT schedule_uploads_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $studentColumn = $conn->query("SHOW COLUMNS FROM student_schedules LIKE 'upload_id'");
    if ($studentColumn && $studentColumn->num_rows === 0) {
        $conn->query("ALTER TABLE student_schedules ADD upload_id INT(11) DEFAULT NULL AFTER student_id");
        $conn->query("ALTER TABLE student_schedules ADD KEY upload_id (upload_id)");
        $conn->query("ALTER TABLE student_schedules ADD CONSTRAINT student_schedules_upload_fk FOREIGN KEY (upload_id) REFERENCES schedule_uploads (upload_id) ON DELETE CASCADE ON UPDATE CASCADE");
    }

    $facultyColumn = $conn->query("SHOW COLUMNS FROM faculty_schedules LIKE 'upload_id'");
    if ($facultyColumn && $facultyColumn->num_rows === 0) {
        $conn->query("ALTER TABLE faculty_schedules ADD upload_id INT(11) DEFAULT NULL AFTER faculty_id");
        $conn->query("ALTER TABLE faculty_schedules ADD KEY upload_id (upload_id)");
        $conn->query("ALTER TABLE faculty_schedules ADD CONSTRAINT faculty_schedules_upload_fk FOREIGN KEY (upload_id) REFERENCES schedule_uploads (upload_id) ON DELETE CASCADE ON UPDATE CASCADE");
    }
}

function get_or_create_student_id(mysqli $conn, int $user_id): int {

    $profile_stmt = $conn->prepare("
        SELECT student_id 
        FROM students 
        WHERE user_id = ?
    ");

    if (!$profile_stmt) {
        throw new Exception("Failed preparing student lookup: " . $conn->error);
    }

    $profile_stmt->bind_param("i", $user_id);
    $profile_stmt->execute();

    $profile_res = $profile_stmt->get_result()->fetch_assoc();

    $profile_stmt->close();

    // Existing student profile found
    if ($profile_res) {
        return (int) $profile_res['student_id'];
    }

    // No student profile found
    throw new Exception(
        "Student profile not found. Please complete your profile first before uploading schedules."
    );
}

function get_or_create_faculty_id(mysqli $conn, int $user_id): int {
    $profile_stmt = $conn->prepare("SELECT faculty_id FROM faculties WHERE user_id = ?");
    $profile_stmt->bind_param("i", $user_id);
    $profile_stmt->execute();
    $profile_res = $profile_stmt->get_result()->fetch_assoc();
    $profile_stmt->close();

    if ($profile_res) {
        return (int) $profile_res['faculty_id'];
    }

    $department = 'not found in the uploaded image';
    $fb_link = '';
    $insert_stmt = $conn->prepare("INSERT INTO faculties (user_id, department, fb_link) VALUES (?, ?, ?)");
    if (!$insert_stmt) {
        throw new Exception("Could not prepare faculty profile creation: " . $conn->error);
    }

    $insert_stmt->bind_param("iss", $user_id, $department, $fb_link);
    if (!$insert_stmt->execute()) {
        throw new Exception("Could not create faculty profile for schedule upload: " . $insert_stmt->error);
    }

    $faculty_id = (int) $conn->insert_id;
    $insert_stmt->close();
    return $faculty_id;
}

// Catch empty or broken submissions early
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['courses'])) {
    $_SESSION['upload_error'] = "No schedule data received for confirmation.";
    header("Location: " . $redirect_page);
    exit();
}

ensure_schedule_upload_schema($conn);

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

    $original_filename = $_SESSION['ocr_upload_original_name'] ?? 'Uploaded schedule';
    $stored_file_path  = $_SESSION['ocr_upload_stored_path'] ?? null;
    $upload_stmt = $conn->prepare("INSERT INTO schedule_uploads (user_id, role, original_filename, stored_file_path, semester, school_year) VALUES (?, ?, ?, ?, ?, ?)");
    $upload_stmt->bind_param("isssss", $user_id, $role, $original_filename, $stored_file_path, $current_semester, $current_school_year);
    if (!$upload_stmt->execute()) {
        throw new Exception("Failed creating upload record: " . $upload_stmt->error);
    }
    $upload_id = $conn->insert_id;
    $upload_stmt->close();

    if ($role === "student") {
        $student_id = get_or_create_student_id($conn, $user_id);
        $insert_stmt = $conn->prepare("INSERT INTO student_schedules (student_id, upload_id, schedule_code, course_code, course_description, time_start, time_end, day, room, semester, school_year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");

    } else if ($role === "faculty") {
        $faculty_id = get_or_create_faculty_id($conn, $user_id);
        $insert_stmt = $conn->prepare("INSERT INTO faculty_schedules (faculty_id, upload_id, schedule_code, course_code, course_description, day, time_start, time_end, room, semester, school_year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
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
                "iisssssssss", 
                $student_id, $upload_id, $sched_code, $course_code, $description, 
                $time_start, $time_end, $day, $room, 
                $current_semester, $current_school_year
            );
        } else {
            $insert_stmt->bind_param(
                "iisssssssss", 
                $faculty_id, $upload_id, $sched_code, $course_code, $description, 
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

    // RUN THE MATCH ENGINE HERE: Real-time calculation right after database write commits
    synchronize_schedule_matches($conn);

    // SUCCESS! Clear the preview cache so the modal closes automatically
    unset($_SESSION['ocr_preview_data'], $_SESSION['ocr_upload_original_name'], $_SESSION['ocr_upload_stored_path']);
    $_SESSION['upload_success'] = "Verified class schedule entries saved and matched successfully!";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['upload_error'] = "Database Processing Exception: " . $e->getMessage();
}

header("Location: " . $redirect_page);
exit();