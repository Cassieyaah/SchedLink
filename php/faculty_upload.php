<?php
session_start();

include '../includes/db.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = (int) $_SESSION['user_id'];
$redirect = "../php/facultydashboard.php";

/* =========================================
   GET FACULTY / PROFESSOR ID
========================================= */
$stmt = $conn->prepare("
    SELECT professor_id 
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
        'professor_id' => $create->insert_id
    ];

    $create->close();
}

$professor_id = (int)$faculty['professor_id'];

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

    if (!in_array($extension, ['xlsx', 'xls'])) {
        $_SESSION['upload_error'] = "Invalid file! Excel only.";
        header("Location: $redirect");
        exit();
    }

    try {

        /* LOAD EXCEL */
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        /* CREATE UPLOAD LOG (NO created_at column) */
        $stmt = $conn->prepare("
            INSERT INTO schedule_uploads
            (user_id, role, original_filename)
            VALUES (?, 'faculty', ?)
        ");

        $stmt->bind_param("is", $user_id, $file['name']);
        $stmt->execute();

        $upload_id = $stmt->insert_id;
        $stmt->close();

        /* INSERT SCHEDULES */
        $insert = $conn->prepare("
            INSERT INTO faculty_schedules
            (
                professor_id,
                upload_id,
                schedule_code,
                course_code,
                course_description,
                room,
                semester,
                school_year,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $semester = "1st Semester";
        $school_year = "2025-2026";
        $status = "active";

        foreach ($rows as $i => $row) {

            if ($i === 1) continue;

            $schedule_code = trim($row['A'] ?? '');
            $course_description = trim($row['B'] ?? '');
            $course_code = trim($row['C'] ?? '');
            $room = trim($row['D'] ?? '');

            if (
                $schedule_code === '' &&
                $course_description === '' &&
                $course_code === '' &&
                $room === ''
            ) {
                continue;
            }

            $insert->bind_param(
                "iisssssss",
                $professor_id,
                $upload_id,
                $schedule_code,
                $course_code,
                $course_description,
                $room,
                $semester,
                $school_year,
                $status
            );

            $insert->execute();
        }

        $insert->close();

        $_SESSION['upload_success'] = "Schedule uploaded successfully!";
        header("Location: $redirect");
        exit();

    } catch (Exception $e) {

        $_SESSION['upload_error'] = "Upload failed: " . $e->getMessage();
        header("Location: $redirect");
        exit();
    }
}
?>
