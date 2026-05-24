<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location:../php/logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        users.*,
        faculties.professor_id,
        faculties.department
    FROM users
    LEFT JOIN faculties ON users.user_id = faculties.user_id
    WHERE users.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: ../php/logIn.php");
    exit();
}

$role     = strtolower(trim($user['role']));
$fullname = $user['fullname'];

if ($role !== "faculty") {
    if ($role === "student") {
        header("Location: studentdashboard.php");
        exit();
    }

    if ($role === "admin") {
        header("Location: admindashboard.php");
        exit();
    }

    session_destroy();
    header("Location: ../php/logIn.php");
    exit();
}

/* =========================
   PROFILE IMAGE RESOLUTION
========================= */
$default_image   = "../media/images.jpg";
$profile_picture = $default_image;

$stored_picture = trim($user['profile_picture'] ?? '');

if ($stored_picture !== '') {
    $uploaded_path = "../uploads/" . $stored_picture;
    if (file_exists($uploaded_path)) {
        $profile_picture = $uploaded_path;
    }
}

if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($fullname) . "&size=200&background=4a90d9&color=fff";
}

/* =========================
   SCHEDULE QUERY
========================= */
if (!empty($user['professor_id'])) {
    $scheduleQuery = "SELECT * FROM faculty_schedules WHERE professor_id = ?";
    $stmt2 = $conn->prepare($scheduleQuery);
    $stmt2->bind_param("i", $user['professor_id']);
    $stmt2->execute();
    $scheduleResult = $stmt2->get_result();
} else {
    $scheduleResult = null;
}

$upload_success = $_SESSION['upload_success'] ?? '';
$upload_error = $_SESSION['upload_error'] ?? '';
unset($_SESSION['upload_success'], $_SESSION['upload_error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../css/uploadSchedule.css">
    <link rel="stylesheet" href="../fonts/css/all.min.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <div class="profile">

            <img src="<?php echo htmlspecialchars($profile_picture, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Picture">

            <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
            <p>Faculty Account</p>

        </div>

        <div class="section-title">GENERAL</div>

        <div class="nav">

            <a class="active" href="facultydashboard.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>

            <a href="#">
                <i class="fa-regular fa-calendar"></i> My Schedule
            </a>

            <a href="#" data-upload-open>
                <i class="fa-solid fa-upload"></i> Upload Schedule
            </a>

            <a href="facultyprofile.php">
                <i class="fa-solid fa-user"></i> Profile
            </a>

            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>

        </div>

        <div class="divider"></div>

    </div>

    <div class="sidebar-footer">
        <img src="../media/cvsulogo.png" alt="CvSU Logo">
        <p>Cavite State University</p>
    </div>

</div>

<!-- HEADER -->
<div class="header">

    <h2>Faculty Dashboard</h2>

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

    <?php if (empty($user['professor_id']) || empty($user['department'])): ?>
        <div style="
            background:#fff3cd;
            color:#856404;
            padding:15px;
            border-radius:10px;
            margin-bottom:20px;
            border:1px solid #ffeeba;
        ">
            Your faculty profile is incomplete.
            <a href="facultyprofile.php" style="
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
                <h4>Total Subjects</h4>
                <p><?php echo $scheduleResult ? $scheduleResult->num_rows : 0; ?></p>
            </div>

            <div class="stat-card">
                <h4>Upcoming Classes</h4>
                <p>0</p>
            </div>

            <div class="stat-card">
                <h4>Matched Schedules</h4>
                <p>0</p>
            </div>

        </div>

        <div class="section-title-main">
            <h3>My Schedule</h3>
            <div class="line"></div>
        </div>

        <div class="card-container">

            <?php if ($scheduleResult && $scheduleResult->num_rows > 0): ?>

                <?php while ($row = $scheduleResult->fetch_assoc()): ?>

                    <div class="card">
                        <h4>
                            <?php echo htmlspecialchars($row['course_code']); ?> -
                            <?php echo htmlspecialchars($row['course_description']); ?>
                        </h4>
                        <p>
                            <?php echo htmlspecialchars($row['day']); ?> |
                            <?php echo date("g:i A", strtotime($row['time_start'])); ?>
                            -
                            <?php echo date("g:i A", strtotime($row['time_end'])); ?>
                        </p>
                        <p><?php echo htmlspecialchars($row['room']); ?></p>
                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <p class="empty-state">No schedules found.</p>

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
                <p>Upload a clear screenshot or file of your faculty schedule.</p>
            </div>
        </div>

        <form action="upload_schedule.php" method="POST" enctype="multipart/form-data" class="schedule-upload-form">
            <label class="schedule-dropzone" for="schedule_file">
                <i class="fa-regular fa-image"></i>
                <span class="dropzone-title">Choose schedule file</span>
                <span class="dropzone-help">PNG, JPG, WEBP, or PDF up to 10 MB</span>
                <span class="selected-file" id="selectedScheduleFile">No file selected</span>
            </label>

            <input
                type="file"
                id="schedule_file"
                name="schedule_file"
                accept="image/png,image/jpeg,image/webp,application/pdf"
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

</body>
</html>
