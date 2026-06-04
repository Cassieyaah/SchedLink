<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Resolve core authorization role profiles
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$role = $user ? strtolower(trim($user['role'])) : '';
$redirect_page = "student_folder/studentdashboard.php";

if ($role === "faculty") {
    $redirect_page = "faculty_folder/facultydashboard.php";
} elseif ($role === "student") {
    $redirect_page = "student_folder/studentdashboard.php";
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

$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, $allowed_extensions, true)) {
    $_SESSION['upload_error'] = "Invalid file type. Please upload PNG, JPG, or WEBP.";
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

// ========================================================
// REVISED PYTHON EXECUTION PIPELINE
// ========================================================

// 1. Locate Python binaries environment and renamed script execution
$python_candidates = [
    realpath("../python/venv/Scripts/python.exe") ?: "",
    "C:\\Program Files\\Python314\\python.exe",
    "C:\\Users\\Danna\\AppData\\Local\\Programs\\Python\\Python314\\python.exe",
    "python"
];

$python = "python";
foreach ($python_candidates as $candidate) {
    if ($candidate === "") {
        continue;
    }

    if ($candidate === "python" || file_exists($candidate)) {
        $python = $candidate;
        break;
    }
}

$script = realpath("../python/ocr_service.py"); // Updated script name here
$uploaded_file = realpath($target);

// Fail early if paths are broken to avoid executing a dead command
if (!$script || !$uploaded_file) {
    $_SESSION['upload_error'] = "System path configuration error. Verify file paths in your directory structure.";
    header("Location: " . $redirect_page);
    exit();
}

// 2. Build the execution string redirecting hidden terminal errors (2>&1)
$command = "\"$python\" \"$script\" \"$uploaded_file\" 2>&1";
$output = shell_exec($command);

if (!$output) {
    $_SESSION['upload_error'] = "OCR processing failed. Terminal completely unresponsive.";
    header("Location: " . $redirect_page);
    exit();
}

// 3. Evaluate output stream data payload structure
$parsed_data = json_decode($output, true);

if ($parsed_data === null) {
    // If it's not valid JSON, capture the raw error text string directly onto the dashboard view
    $raw_error = trim(strip_tags($output));
    $raw_error = preg_replace('/\s+/', ' ', $raw_error);
    $_SESSION['upload_error'] = "OCR Service Engine Error: " . htmlspecialchars(substr($raw_error, 0, 240));
    header("Location: " . $redirect_page);
    exit();
}

if (isset($parsed_data['error'])) {
    $_SESSION['upload_error'] = "OCR processing failed: " . htmlspecialchars($parsed_data['error']);
    header("Location: " . $redirect_page);
    exit();
}

if (!is_array($parsed_data) || empty($parsed_data)) {
    $_SESSION['upload_error'] = "OCR could not detect schedule entries. Please upload a clearer screenshot.";
    header("Location: " . $redirect_page);
    exit();
}

// CACHE PARSED DATA IN SESSION INSTEAD OF BLIND SAVING TO THE DATABASE
$_SESSION['ocr_preview_data'] = $parsed_data;
$_SESSION['ocr_upload_original_name'] = $file['name'];
$_SESSION['ocr_upload_stored_path'] = str_replace("\\", "/", $target);
$_SESSION['upload_success'] = "OCR analysis complete! Please review and confirm your parsed schedule below.";

header("Location: " . $redirect_page);
exit();
