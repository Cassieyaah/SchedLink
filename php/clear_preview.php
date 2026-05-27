<?php
session_start();
unset($_SESSION['ocr_preview_data']);
$_SESSION['upload_error'] = "Upload discarded by user.";
header("Location: studentdashboard.php");
exit();