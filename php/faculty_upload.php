<?php
session_start();

include '../includes/db.php';
include_once '../includes/matched_schedules.php'; // Include Option B Matching Ledger Engine
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = (int) $_SESSION['user_id'];
$redirect = "../php/facultydashboard.php";

/* ========================================================
   GET SITE SETTINGS & TRANSLATE ENUM VALUE FOR FACULTY
   ======================================================== */
$settings_query = $conn->query("SELECT semester, school_year FROM site_settings WHERE id = 1")->fetch_assoc();
$admin_semester     = $settings_query['semester'] ?? '1st';
$active_school_year = $settings_query['school_year'] ?? '2025-2026';

// Translation Map: Converts '1st'/'2nd'/'summer' from admin settings into what faculty_schedules ENUM expects
$semester_map = [
    '1st'    => '1st Semester',
    '2nd'    => '2nd Semester',
    'summer' => 'Summer'
];

// Fallback to '1st Semester' if the mapped value doesn't exist
$active_semester = $semester_map[$admin_semester] ?? '1st Semester';

/* =========================================
   GET FACULTY / PROFESSOR ID
========================================= */
$stmt = $conn->prepare("
    SELECT faculty_id 
    FROM faculties 
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$faculty = $result->fetch_assoc();

$stmt->close();

/* AUTO CREATE FACULTY IF MISSING */
if (!$faculty) {
    $create = $conn->prepare("
        INSERT INTO faculties (user_id)
        VALUES (?)
    ");

    $create->bind_param("i", $user_id);
    $create->execute();

    $faculty = [
        'faculty_id' => $create->insert_id
    ];

    $create->close();
}

$faculty_id = (int)$faculty['faculty_id'];

/* =========================================
   HANDLE EXCEL UPLOAD
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['schedule_file'])) {

    $file = $_FILES['schedule_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_error'] = "File upload error.";
        header("Location: $redirect");
        exit();
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
        $_SESSION['upload_error'] = "Invalid file! Excel or CSV only.";
        header("Location: $redirect");
        exit();
    }

    try {
        /* LOAD EXCEL */
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        /* CREATE UPLOAD LOG WITH TRANSLATED SEMESTER */
        $stmt = $conn->prepare("
            INSERT INTO schedule_uploads
            (user_id, role, original_filename, semester, school_year)
            VALUES (?, 'faculty', ?, ?, ?)
        ");

        $stmt->bind_param("isss", $user_id, $file['name'], $active_semester, $active_school_year);
        $stmt->execute();

        $upload_id = $stmt->insert_id;
        $stmt->close();

        /* INSERT SCHEDULES */
        $insert = $conn->prepare("
            INSERT INTO faculty_schedules
            (
                faculty_id,
                upload_id,
                schedule_code,
                course_code,
                course_year,
                room,
                semester,
                school_year,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $status = "active";

        foreach ($rows as $i => $row) {
            if ($i === 1) continue; // Skip header row

            $schedule_code = trim($row['A'] ?? ''); 
            $course_code   = trim($row['B'] ?? ''); 
            $course_year   = trim($row['C'] ?? ''); 
            $room          = trim($row['D'] ?? ''); 

            if (
                $schedule_code === '' &&
                $course_code === '' &&
                $course_year === '' &&
                $room === ''
            ) {
                continue;
            }

            $insert->bind_param(
                "iisssssss",
                $faculty_id,
                $upload_id,
                $schedule_code,
                $course_code,
                $course_year,
                $room,
                $active_semester,
                $active_school_year,
                $status
            );

            $insert->execute();
        }

        $insert->close();

        // RUN ENGINE: Check matching states across all students now that new faculty codes are indexed!
        synchronize_schedule_matches($conn);

        $_SESSION['upload_success'] = "Schedule uploaded and matches processed successfully!";
        header("Location: faculty_schedule.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['upload_error'] = "Upload failed: " . $e->getMessage();
        header("Location: $redirect");
        exit();
    }
}
?>