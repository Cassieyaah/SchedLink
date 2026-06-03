<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php'; // Siguraduhin ang path ng vendor
include '../includes/db.php';
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

        // I-save sa session para basahin ng iyong existing save_verified_schedule.php
        $_SESSION['ocr_preview_data'] = $parsed_data; 
        $_SESSION['upload_success'] = "Excel processed successfully!";
    } catch (Exception $e) {
        $_SESSION['upload_error'] = "Error: " . $e->getMessage();
    }
    header("Location: facultydashboard.php");
    exit();
}
?>