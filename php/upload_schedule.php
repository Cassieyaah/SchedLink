<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$role = $user ? strtolower(trim($user['role'])) : '';
$redirect_page = "studentdashboard.php";

if ($role === "faculty") {
    $redirect_page = "facultydashboard.php";
} elseif ($role === "student") {
    $redirect_page = "studentdashboard.php";
}

if (!$user || !in_array($role, ["student", "faculty"], true)) {
    $_SESSION['upload_error'] = "Only student and faculty accounts can upload schedules here.";
    header("Location: " . $redirect_page);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['schedule_file'])) {
    header("Location: " . $redirect_page);
    exit();
}

$file = $_FILES['schedule_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = "Upload failed. Please try again.";
    header("Location: " . $redirect_page);
    exit();
}

$max_size = 10 * 1024 * 1024;
if ($file['size'] > $max_size) {
    $_SESSION['upload_error'] = "File is too large. Maximum size is 10 MB.";
    header("Location: " . $redirect_page);
    exit();
}

$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, $allowed_extensions, true)) {
    $_SESSION['upload_error'] = "Invalid file type. Please upload PNG, JPG, WEBP, or PDF.";
    header("Location: " . $redirect_page);
    exit();
}

$upload_dir = "../uploads/schedules/" . $role . "/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename = $role . "_schedule_" . $user_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
$target = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    $_SESSION['upload_error'] = "Could not save the uploaded file.";
    header("Location: " . $redirect_page);
    exit();
}

$python = realpath("../python/venv/Scripts/python.exe");

$script = realpath("../python/test_ocr.py");

$uploaded_file = realpath($target);

$command = "\"$python\" \"$script\" \"$uploaded_file\"";

$output = shell_exec($command);

if (!$output) {

    $_SESSION['upload_error'] = "OCR processing failed.";

    header("Location: " . $redirect_page);

    exit();
}

$parsed_data = json_decode($output, true);

if ($parsed_data === null) {

    $_SESSION['upload_error'] = "Failed to parse OCR output.";

    header("Location: " . $redirect_page);

    exit();
}

$_SESSION['parsed_schedule'] = $parsed_data;
header("Location: " . $redirect_page);
exit();
