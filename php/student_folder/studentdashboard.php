<?php
session_start();
include '../../includes/db.php';
require_once __DIR__ . '/../schedule_matcher.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        users.*,
        students.student_id,
        students.student_number,
        students.program
    FROM users
    LEFT JOIN students ON users.user_id = students.user_id
    WHERE users.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: ../logIn.php");
    exit();
}

$role     = strtolower(trim($user['role']));
$fullname = $user['fullname'];

if ($role !== "student") {
    if ($role === "faculty") {
        header("Location: ../faculty_folder/facultydashboard.php");
        exit();
    }

    if ($role === "admin") {
        header("Location: ../admin_folder/admindashboard.php");
        exit();
    }

    session_destroy();
    header("Location: ../logIn.php");
    exit();
}

/* =========================
   PROFILE IMAGE RESOLUTION
========================= */
$default_image   = "../../media/images.jpg";
$profile_picture = $default_image;

$stored_picture = trim($user['profile_picture'] ?? '');

if ($stored_picture !== '') {
    $uploaded_path = "../../uploads/" . $stored_picture;
    if (file_exists($uploaded_path)) {
        $profile_picture = $uploaded_path;
    }
    // If uploaded file no longer exists on disk, fall back to default
}

// Verify the default image itself exists; if not, use a generated avatar
if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($fullname) . "&size=200&background=4a90d9&color=fff";
}

function day_matches_today(string $stored_day, string $today_code): bool {
    $normalized = strtoupper(trim($stored_day));
    if ($normalized === '') {
        return false;
    }

    $normalized = str_replace(['THU', 'THURS', 'THURSDAY'], 'TH', $normalized);
    $normalized = str_replace(['MONDAY', 'MON'], 'M', $normalized);
    $normalized = str_replace(['TUESDAY', 'TUE', 'TUES'], 'T', $normalized);
    $normalized = str_replace(['WEDNESDAY', 'WED'], 'W', $normalized);
    $normalized = str_replace(['FRIDAY', 'FRI'], 'F', $normalized);
    $normalized = str_replace(['SATURDAY', 'SAT'], 'S', $normalized);
    $normalized = str_replace(['SUNDAY', 'SUN'], 'SU', $normalized);

    preg_match_all('/TH|SU|M|T|W|F|S/', $normalized, $matches);
    return in_array($today_code, $matches[0] ?? [], true);
}

$day_map = [
    1 => 'M',
    2 => 'T',
    3 => 'W',
    4 => 'TH',
    5 => 'F',
    6 => 'S',
    7 => 'SU',
];
$today_code = $day_map[(int) date('N')];
$today_label = date('l, F j, Y');
$latest_upload = null;
$latest_schedule_rows = [];
$today_schedule_rows = [];

ensure_matched_schedule_schema($conn);

/* =========================
   LATEST UPLOAD SCHEDULE QUERY
========================= */
$latest_stmt = $conn->prepare("
    SELECT upload_id, original_filename, uploaded_at
    FROM schedule_uploads
    WHERE user_id = ? AND role = 'student'
    ORDER BY uploaded_at DESC, upload_id DESC
    LIMIT 1
");
$latest_stmt->bind_param("i", $user_id);
$latest_stmt->execute();
$latest_upload = $latest_stmt->get_result()->fetch_assoc();
$latest_stmt->close();

if (!empty($user['student_id']) && $latest_upload) {
    $scheduleQuery = "
        SELECT
            ss.*,
            ms.match_status,
            faculty_users.fullname AS assigned_faculty_name
        FROM student_schedules ss
        LEFT JOIN matched_schedules ms ON ss.student_schedule_id = ms.student_schedule_id
        LEFT JOIN faculty_schedules fs ON ms.professor_schedule_id = fs.professor_schedule_id
        LEFT JOIN faculties faculty_profiles ON fs.faculty_id = faculty_profiles.faculty_id
        LEFT JOIN users faculty_users ON faculty_profiles.user_id = faculty_users.user_id
        WHERE ss.student_id = ? AND ss.upload_id = ?
        ORDER BY ss.time_start ASC, ss.course_code ASC
    ";
    $stmt2 = $conn->prepare($scheduleQuery);
    $upload_id = (int) $latest_upload['upload_id'];
    $stmt2->bind_param("ii", $user['student_id'], $upload_id);
    $stmt2->execute();
    $scheduleResult = $stmt2->get_result();

    while ($row = $scheduleResult->fetch_assoc()) {
        $latest_schedule_rows[] = $row;
        if (day_matches_today($row['day'] ?? '', $today_code)) {
            $today_schedule_rows[] = $row;
        }
    }

    $stmt2->close();
}

$total_latest_subjects = count(array_unique(array_map(function ($row) {
    return ($row['schedule_code'] ?? '') . '|' . ($row['course_code'] ?? '');
}, $latest_schedule_rows)));
$today_subject_count = count($today_schedule_rows);
$assigned_faculty_count = count(array_filter($latest_schedule_rows, function ($row) {
    return ($row['match_status'] ?? '') === 'matched' && !empty($row['assigned_faculty_name']);
}));

$upload_success = $_SESSION['upload_success'] ?? '';
$upload_error = $_SESSION['upload_error'] ?? '';
unset($_SESSION['upload_success'], $_SESSION['upload_error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../../css/studentDashBoard.css">
    <link rel="stylesheet" href="../../css/uploadSchedule.css">
    <link rel="stylesheet" href="../../fonts/css/all.min.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <div class="profile">

            <img src="<?php echo htmlspecialchars($profile_picture, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Picture">

            <?php if ($user): ?>

                <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>

                <p>Student Account</p>

            <?php else: ?>

                <h3>Guest User</h3>
                <p>No account found</p>
                <p>Please sign up</p>

            <?php endif; ?>

        </div>

        <div class="section-title">GENERAL</div>

        <div class="nav">

            <a class="active" href="studentdashboard.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>

            <a href="myschedule.php">
                <i class="fa-regular fa-calendar"></i> My Schedule
            </a>

            <a href="#" data-upload-open>
                <i class="fa-solid fa-upload"></i> Upload Schedule
            </a>

            <a href="profile.php">
                <i class="fa-solid fa-user"></i> Profile
            </a>

            <a href="../logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>

        </div>

        <div class="divider"></div>

    </div>

    <div class="sidebar-footer">
        <img src="../../media/cvsulogo.png" alt="CvSU Logo">
        <p>Cavite State University</p>
    </div>

</div>

<!-- HEADER -->
<div class="header">

    <h2>Student Dashboard</h2>

    <div class="user-box">
        Welcome, <?php echo htmlspecialchars($user['fullname']); ?>
    </div>

</div>

<!-- MAIN -->
<div class="main">

    <?php if ($upload_success): ?>
        <div class="dashboard-alert success-alert">
            <?php echo htmlspecialchars($upload_success); ?>
        </div>
    <?php endif; ?>

    <?php if ($upload_error): ?>
        <div class="dashboard-alert error-alert">
            <?php echo htmlspecialchars($upload_error); ?>
        </div>
    <?php endif; ?>

    <?php if ($role == "student" && (empty($user['student_number']) || empty($user['program']))): ?>
        <div style="
            background:#fff3cd;
            color:#856404;
            padding:15px;
            border-radius:10px;
            margin-bottom:20px;
            border:1px solid #ffeeba;
        ">
            Your profile is incomplete.
            <a href="profile.php" style="
                display:inline-block;
                margin-left:10px;
                color:#0b6b3a;
                font-weight:bold;
                text-decoration:none;
            ">
                Complete Profile
            </a>
        </div>
    <?php endif; ?>

    <div class="dashboard-container">

        <a href="#" class="upload-btn" data-upload-open>
            <i class="fa-solid fa-upload"></i>
            Upload Schedule
        </a>

        <div class="stats">

            <div class="stat-card">
                <h4>Latest Upload Subjects</h4>
                <p><?php echo $total_latest_subjects; ?></p>
            </div>

            <div class="stat-card">
                <h4>Today's Classes</h4>
                <p><?php echo $today_subject_count; ?></p>
            </div>

            <div class="stat-card">
                <h4>Assigned Faculty</h4>
                <p><?php echo $assigned_faculty_count; ?></p>
            </div>

            <div class="stat-card">
                <h4>Current Day</h4>
                <p><?php echo htmlspecialchars($today_code); ?></p>
            </div>

        </div>

        <div class="section-title-main">
            <h3>Today's Schedule</h3>
            <p class="schedule-context">
                <?php echo htmlspecialchars($today_label); ?>
                <?php if ($latest_upload): ?>
                    &middot; from latest upload on <?php echo date("F j, Y g:i A", strtotime($latest_upload['uploaded_at'])); ?>
                <?php endif; ?>
            </p>
            <div class="line"></div>
        </div>

        <div class="card-container">

            <?php if (!empty($today_schedule_rows)): ?>

                <?php foreach ($today_schedule_rows as $row): ?>

                    <div class="card">
                        <div class="card-info-main">
                            <h4>
                                <?php echo htmlspecialchars($row['course_code']); ?> —
                                <?php echo htmlspecialchars($row['course_description']); ?>
                            </h4>
                            <p><?php echo htmlspecialchars($row['room']); ?></p>
                        </div>
                        <div class="card-info-meta">
                            <p class="card-time">
                                <?php echo date("g:i A", strtotime($row['time_start'])); ?> –
                                <?php echo date("g:i A", strtotime($row['time_end'])); ?>
                            </p>
                            <p><?php echo htmlspecialchars($row['day']); ?></p>
                            <p>
                                Faculty:
                                <?php if (($row['match_status'] ?? '') === 'matched' && !empty($row['assigned_faculty_name'])): ?>
                                    <?php echo htmlspecialchars($row['assigned_faculty_name']); ?>
                                <?php elseif (($row['match_status'] ?? '') === 'conflict'): ?>
                                    Multiple possible matches
                                <?php else: ?>
                                    Not assigned yet
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <?php if ($latest_upload): ?>
                    <p class="empty-state">No classes scheduled for today in your latest upload.</p>
                <?php else: ?>
                    <p class="empty-state">No uploaded schedule found yet.</p>
                <?php endif; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- UPLOAD SCHEDULE MODAL -->
<div class="upload-modal" id="uploadScheduleModal" aria-hidden="true" hidden>
    <div class="upload-modal-backdrop" data-upload-close></div>

    <div class="upload-modal-content" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
        <button type="button" class="upload-modal-close" data-upload-close aria-label="Close upload schedule popup">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="upload-modal-header">
            <div class="upload-modal-icon">
                <i class="fa-solid fa-file-arrow-up"></i>
            </div>
            <div>
                <h3 id="uploadModalTitle">Upload Schedule</h3>
                <p>Upload a clear screenshot or file of your class schedule.</p>
            </div>
        </div>

        <form action="upload_schedule.php" method="POST" enctype="multipart/form-data" class="schedule-upload-form">
            <label class="schedule-dropzone" for="schedule_file">
                <i class="fa-regular fa-image"></i>
                <span class="dropzone-title">Choose schedule file</span>
                <span class="dropzone-help">PNG, JPG, or WEBP up to 10 MB</span>
                <span class="selected-file" id="selectedScheduleFile">No file selected</span>
            </label>

            <input
                type="file"
                id="schedule_file"
                name="schedule_file"
                accept="image/png,image/jpeg,image/webp"
                required
            >

            <div class="upload-modal-actions">
                <button type="button" class="secondary-upload-btn" data-upload-close>Cancel</button>
                <button type="submit" class="primary-upload-btn">
                    <i class="fa-solid fa-upload"></i>
                    Upload
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const links = document.querySelectorAll(".nav a");
const uploadModal = document.getElementById("uploadScheduleModal");
const uploadTriggers = document.querySelectorAll("[data-upload-open]");
const uploadClosers = document.querySelectorAll("[data-upload-close]");
const scheduleFileInput = document.getElementById("schedule_file");
const selectedScheduleFile = document.getElementById("selectedScheduleFile");

function openUploadModal() {
    uploadModal.removeAttribute("hidden");
    uploadModal.classList.add("show");
    uploadModal.setAttribute("aria-hidden", "false");
}

function closeUploadModal() {
    uploadModal.classList.remove("show");
    uploadModal.setAttribute("aria-hidden", "true");
    uploadModal.setAttribute("hidden", "");
}

uploadTriggers.forEach(trigger => {
    trigger.addEventListener("click", function (e) {
        e.preventDefault();
        openUploadModal();
    });
});

uploadClosers.forEach(closer => {
    closer.addEventListener("click", closeUploadModal);
});

scheduleFileInput.addEventListener("change", function () {
    selectedScheduleFile.textContent = this.files.length ? this.files[0].name : "No file selected";
});

window.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && uploadModal.classList.contains("show")) {
        closeUploadModal();
    }
});

if (window.location.hash === "#upload") {
    openUploadModal();
}

links.forEach(link => {
    link.addEventListener("click", function (e) {

        const target = this.getAttribute("href");
        if (this.hasAttribute("data-upload-open")) return;

        if (!target || target === "#") return;

        e.preventDefault();

        document.querySelector(".main").classList.add("page-transition");

        setTimeout(() => {
            window.location.href = target;
        }, 180);
    });
});
</script>

<?php if (!empty($_SESSION['ocr_preview_data'])): ?>
<div class="upload-modal" id="previewScheduleModal" style="display: flex; z-index: 9999;">
    <div class="upload-modal-backdrop"></div>
    <div class="upload-modal-content" style="max-width: 850px; width: 90%; max-height: 85vh; overflow-y: auto;">
        
        <div class="upload-modal-header">
            <div class="upload-modal-icon" style="background: #e6f4ea; color: #137333;">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div>
                <h3>Verify Extracted Schedule Data</h3>
                <p>Tesseract has completed extraction processing. Review entries and correct any alignment errors before saving to your profile.</p>
            </div>
        </div>

        <?php if ($upload_error): ?>
            <div class="dashboard-alert error-alert">
                <?php echo htmlspecialchars($upload_error); ?>
            </div>
        <?php endif; ?>

        <form action="save_verified_schedule.php" method="POST" class="schedule-upload-form">
            <div class="preview-cards-container" style="margin: 20px 0; display: flex; flex-direction: column; gap: 15px;">
                
                <?php 
                $course_index = 0;
                foreach ($_SESSION['ocr_preview_data'] as $course): 
                    $sched_code  = htmlspecialchars($course['schedule_code'] ?? '');
                    $course_code = htmlspecialchars($course['course_code'] ?? '');
                    $description = htmlspecialchars($course['description'] ?? '');
                    
                    if (!empty($course['meetings'])):
                        foreach ($course['meetings'] as $meeting_index => $meeting):
                            $day   = htmlspecialchars($meeting['day'] ?? '');
                            $room  = htmlspecialchars($meeting['room'] ?? '');
                            $time  = htmlspecialchars($meeting['time'] ?? '');
                ?>
                    <div class="preview-row-card" style="background: #f8f9fa; border: 1px solid #dadce0; padding: 15px; border-radius: 8px; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; align-items: center;">
                        
                        <div>
                            <label style="font-size: 11px; color: #5f6368; font-weight: bold; display: block; margin-bottom: 4px;">Sched Code</label>
                            <input type="text" name="courses[<?php echo $course_index; ?>][sched_code]" value="<?php echo $sched_code; ?>" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;" required>
                        </div>

                        <div>
                            <label style="font-size: 11px; color: #5f6368; font-weight: bold; display: block; margin-bottom: 4px;">Course Code</label>
                            <input type="text" name="courses[<?php echo $course_index; ?>][course_code]" value="<?php echo $course_code; ?>" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;" required>
                        </div>

                        <div>
                            <label style="font-size: 11px; color: #5f6368; font-weight: bold; display: block; margin-bottom: 4px;">Description</label>
                            <input type="text" name="courses[<?php echo $course_index; ?>][description]" value="<?php echo $description; ?>" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;" required>
                        </div>

                        <div>
                            <label style="font-size: 11px; color: #5f6368; font-weight: bold; display: block; margin-bottom: 4px;">Day</label>
                            <input type="text" name="courses[<?php echo $course_index; ?>][day]" value="<?php echo $day; ?>" placeholder="M/T/W/TH/F" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;" required>
                        </div>

                        <div>
                            <label style="font-size: 11px; color: #5f6368; font-weight: bold; display: block; margin-bottom: 4px;">Time Range</label>
                            <input type="text" name="courses[<?php echo $course_index; ?>][time_range]" value="<?php echo $time; ?>" placeholder="13:00-15:00" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;" required>
                        </div>

                        <div>
                            <label style="font-size: 11px; color: #5f6368; font-weight: bold; display: block; margin-bottom: 4px;">Room</label>
                            <input type="text" name="courses[<?php echo $course_index; ?>][room]" value="<?php echo $room; ?>" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                        </div>

                    </div>
                <?php 
                            $course_index++;
                        endforeach;
                    endif;
                endforeach; 
                ?>

            </div>

            <div class="upload-modal-actions">
                <a href="../clear_preview.php" class="secondary-upload-btn" style="text-decoration: none; text-align: center; line-height: 38px;">Discard Upload</a>
                    <button
                        type="submit"
                        id="confirmSaveBtn"
                        class="primary-upload-btn"
                        style="background: #137333;">
                        <i class="fa-solid fa-square-check"></i> Confirm & Save Schedule
                    </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

</body>
<script>
const confirmSaveBtn = document.getElementById("confirmSaveBtn");

if (confirmSaveBtn) {
    confirmSaveBtn.addEventListener("click", function (e) {

        const confirmed = confirm(
            "Important Notice\n\n" +
            "The extracted schedule data was generated automatically using OCR and may contain errors.\n\n" +
            "Please review the information carefully before saving.\n\n" +
            "You can still edit your schedule later in the 'My Schedule' page.\n\n" +
            "Do you want to continue saving?"
        );

        if (!confirmed) {
            e.preventDefault();
        }
    });
}
</script>
</html>

