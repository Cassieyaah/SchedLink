<?php
session_start();
unset($_SESSION['ocr_preview_data']);
unset($_SESSION['ocr_upload_original_name'], $_SESSION['ocr_upload_stored_path']);
$_SESSION['upload_error'] = "Upload discarded by user.";

$redirect_page = "student_folder/studentdashboard.php";
if (!empty($_SESSION['user_id'])) {
    include '../includes/db.php';
    $user_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && strtolower(trim($user['role'])) === 'faculty') {
        $redirect_page = "faculty_folder/facultydashboard.php";
    }
}

header("Location: " . $redirect_page);
exit();

