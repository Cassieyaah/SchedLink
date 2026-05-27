upload_schedule.php
<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Kunin ang role ng user
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$role = $user ? strtolower(trim($user['role'])) : '';

// Ligtas na redirection papuntang mysched.php upang maiwasan ang loop bug
$redirect_page = "mysched.php";

if (!$user || !in_array($role, ["student", "faculty"], true)) {
    $_SESSION['upload_error'] = "Only student and faculty accounts can upload schedules here.";
    header("Location: " . $redirect_page);
    exit();
}

/* =================================================================
   AUTO-CREATE PROFILE ROW KUNG WALA PA SA DATABASE
   ================================================================= */
$profile_id = 0;
if ($role === 'student') {
    $stmt_profile = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $stmt_profile->bind_param("i", $user_id);
    $stmt_profile->execute();
    $res_profile = $stmt_profile->get_result()->fetch_assoc();
    
    if ($res_profile) {
        $profile_id = $res_profile['student_id'];
    } else {
        $stmt_insert_p = $conn->prepare("INSERT INTO students (user_id, student_number, program) VALUES (?, ?, 'not found in the uploaded image')");
        $temp_stud_num = rand(100000, 999999); 
        $stmt_insert_p->bind_param("ii", $user_id, $temp_stud_num);
        $stmt_insert_p->execute();
        $profile_id = $conn->insert_id;
    }
} elseif ($role === 'faculty') {
    $stmt_profile = $conn->prepare("SELECT professor_id FROM faculties WHERE user_id = ?");
    $stmt_profile->bind_param("i", $user_id);
    $stmt_profile->execute();
    $res_profile = $stmt_profile->get_result()->fetch_assoc();
    
    if ($res_profile) {
        $profile_id = $res_profile['professor_id'];
    } else {
        $stmt_insert_p = $conn->prepare("INSERT INTO faculties (user_id, department, fb_link) VALUES (?, 'not found in the uploaded image', '')");
        $stmt_insert_p->bind_param("i", $user_id);
        $stmt_insert_p->execute();
        $profile_id = $conn->insert_id;
    }
}

if ($profile_id === 0) {
    $_SESSION['upload_error'] = "Profile creation failed. Please check database structure.";
    header("Location: " . $redirect_page);
    exit();
}

// Siguraduhing may pinadalang file
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

$filename = $role . "schedule" . $user_id . "" . time() . "" . bin2hex(random_bytes(4)) . "." . $extension;
$target = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    $_SESSION['upload_error'] = "Could not save the uploaded file.";
    header("Location: " . $redirect_page);
    exit();
}

/* =================================================================
   EXECUTION NG INYONG PYTHON LOCAL TESSERACT OCR SYSTEM (UNTOUCHED)
   ================================================================= */
$absolute_image_path = realpath($target);
$python_script_path = realpath(_DIR_ . "/../python/ocr_service.py");

$command = "python " . escapeshellarg($python_script_path) . " " . escapeshellarg($absolute_image_path) . " 2>&1";
$output = shell_exec($command);

$parsed_subjects = json_decode(trim($output), true);

if (isset($parsed_subjects[0]['error'])) {
    $_SESSION['upload_error'] = "OCR Error: " . $parsed_subjects[0]['error'];
    header("Location: " . $redirect_page);
    exit();
}

if (empty($parsed_subjects) || !is_array($parsed_subjects)) {
    $_SESSION['upload_error'] = "OCR Parsing Failed. Mangyaring siguraduhing malinaw ang screenshot.";
    header("Location: " . $redirect_page);
    exit();
}

/* =================================================================
   DATABASE INSERTION + SMART DATA FILTERING AT SANITIZATION (PHP SIDE)
   ================================================================= */
$insert_query = "";

if ($role === 'student') {
    $archive_stmt = $conn->prepare("UPDATE student_schedules SET status = 'archived' WHERE student_id = ? AND status = 'active'");
    $archive_stmt->bind_param("i", $profile_id);
    $archive_stmt->execute();

    $insert_query = "INSERT INTO student_schedules 
        (student_id, schedule_code, course_code, course_description, time_start, time_end, day, room, semester, school_year, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
} elseif ($role === 'faculty') {
    $archive_stmt = $conn->prepare("UPDATE faculty_schedules SET status = 'archived' WHERE professor_id = ? AND status = 'active'");
    $archive_stmt->bind_param("i", $profile_id);
    $archive_stmt->execute();

    $insert_query = "INSERT INTO faculty_schedules 
        (professor_id, schedule_code, course_code, course_description, day, time_start, time_end, room, semester, school_year, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
}

if (!empty($insert_query)) {
    $insert_stmt = $conn->prepare($insert_query);
} else {
    $_SESSION['upload_error'] = "Invalid role configuration.";
    header("Location: " . $redirect_page);
    exit();
}

foreach ($parsed_subjects as $subject) {
    // 1. Sched Code Extraction
    $sched_code = $subject['schedule_code'] ?? $subject['sched_code'] ?? $subject['code'] ?? '';
    $sched_code = (!empty($sched_code)) ? (int)$sched_code : 0;
    
    // 2. Course Code
    $c_code = $subject['course_code'] ?? $subject['subject_code'] ?? '';
    $c_code = (trim($c_code) !== '') ? trim($c_code) : 'not found in the uploaded image';
    
    // Kunin ang hilaw na deskripsyon galing sa Python mo
    $raw_desc = $subject['course_description'] ?? $subject['description'] ?? '';
    $fallback_time = $subject['time'] ?? $subject['time_start'] ?? '';
    $fallback_day = $subject['day'] ?? '';
    
    // 3. SMART MULTI-ROW EXTRACTOR (Naghahanap ng Lahat ng Magkakahiwalay na Blocks)
    $all_times = [];
    $all_days = [];
    
    if (preg_match_all('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $raw_desc, $time_matches)) {
        $all_times = $time_matches[0];
    } elseif (preg_match_all('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $fallback_time, $time_matches)) {
        $all_times = $time_matches[0];
    }
    
    if (preg_match_all('/\b(M|T|W|TH|F|S|SU|Th)\b/i', $raw_desc, $day_matches)) {
        $all_days = array_map('strtoupper', $day_matches[0]);
    } elseif (preg_match_all('/\b(M|T|W|TH|F|S|SU|Th)\b/i', $fallback_day, $day_matches)) {
        $all_days = array_map('strtoupper', $day_matches[0]);
    }

    // 4. PAGLILINIS NG COURSE DESCRIPTION
    $c_desc = $raw_desc;
    foreach ($all_times as $tm) {
        $c_desc = str_replace($tm, '', $c_desc);
    }
    foreach ($all_days as $dy) {
        $c_desc = preg_replace('/\b' . preg_quote($dy, '/') . '\b/i', '', $c_desc);
    }
    
    $c_desc = preg_replace('/\b\d\.\d{2}\b/', '', $c_desc); 
    $c_desc = preg_replace('/\b(300|200)\b/', '', $c_desc); 
    $c_desc = str_replace([' - ', ' / ', '-', '/'], ' ', $c_desc);
    $c_desc = trim(preg_replace('/\s+/', ' ', $c_desc)); 
    
    if (empty($c_desc) || $c_desc === 'TBA') {
        $c_desc = 'not found in the uploaded image';
    }

    $room = (isset($subject['room']) && trim($subject['room']) !== '-' && trim($subject['room']) !== '') ? trim($subject['room']) : 'not found in the uploaded image';
    $sem  = 'not found in image';
    $sy   = 'not found in image';

    // 5. SPLIT INSERTION LOGIC (Dito masisigurong papasok lahat ng 8 entries)
    // Kung may magkaibang schedule blocks sa loob ng isang subject, ipapasok sila bilang magkahiwalay na row sa DB
    $total_blocks = max(count($all_times), count($all_days), 1);

    for ($i = 0; $i < $total_blocks; $i++) {
        $t_start = '00:00:00';
        $t_end = '00:00:00';
        $day = 'not found in the uploaded image';

        if (isset($all_times[$i])) {
            if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $all_times[$i], $single_time)) {
                $t_start = date("H:i:s", strtotime(trim($single_time[1])));
                $t_end = date("H:i:s", strtotime(trim($single_time[2])));
            }
        }
        
        if (isset($all_days[$i])) {
            $day = $all_days[$i];
        }

        if ($role === 'student') {
            $insert_stmt->bind_param(
                "iissssssss",
                $profile_id, $sched_code, $c_code, $c_desc, $t_start, $t_end, $day, $room, $sem, $sy
            );
        } else {
            $insert_stmt->bind_param(
                "iissssssss",
                $profile_id, $sched_code, $c_code, $c_desc, $day, $t_start, $t_end, $room, $sem, $sy
            );
        }
        $insert_stmt->execute();
    }
}

$_SESSION['upload_success'] = "Matagumpay na na-filter at na-import ang lahat ng iyong klase sa database!";
header("Location: " . $redirect_page);
exit();

// Helper para sa regex string replacement
function preg_re_replace($pattern, $replacement, $subject) {
    return preg_replace($pattern, $replacement, $subject);
}
?>