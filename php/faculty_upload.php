<?php
session_start();
include '../includes/db.php';
// TANDAAN: Tinanggal na natin ang require vendor/autoload dahil hindi na tayo gagamit ng external library (optional ito kung gusto mong i-simplify)

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

$professor_id = (int)$_SESSION['user_id']; // Siguraduhin na ito ang tamang ID reference
$redirect = "../php/facultydashboard.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['schedule_file'])) {
    $file = $_FILES['schedule_file'];
    
    // Simpleng file validation
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($extension), ['xlsx', 'xls'])) {
        $_SESSION['upload_error'] = "Invalid file! Please upload an Excel file.";
        header("Location: $redirect");
        exit();
    }

    // DITO KA MAGLALAGAY NG LOGIC SA PAG-READ NG EXCEL
    // Dahil ayaw mo na ng OCR, maaari mong gamitin ang simpleng fgetcsv 
    // kung i-save mo ang Excel as CSV, o panatilihin ang PhpSpreadsheet 
    // kung gusto mo ng automated na pag-basa ng XLSX.
    
    $_SESSION['upload_success'] = "Schedule uploaded successfully!";
    header("Location: $redirect");
    exit();
}
?>
