<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location:../php/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: ../php/login.php");
    exit();
}

$role     = $user['role'];
$fullname = $user['fullname'];

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
    // If uploaded file no longer exists on disk, fall back to default
}

// Verify the default image itself exists; if not, use a generated avatar
if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($fullname) . "&size=200&background=4a90d9&color=fff";
}

/* =========================
   SCHEDULE QUERY
========================= */
if ($role == "student") {

    $scheduleQuery = "SELECT * FROM student_schedules WHERE student_id = ?";
    $stmt2 = $conn->prepare($scheduleQuery);
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $scheduleResult = $stmt2->get_result();

} elseif ($role == "professor") {

    $scheduleQuery = "SELECT * FROM professor_schedules WHERE professor_id = ?";
    $stmt2 = $conn->prepare($scheduleQuery);
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $scheduleResult = $stmt2->get_result();

} else {
    $scheduleResult = null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst(htmlspecialchars($role)); ?> Dashboard</title>
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../fonts/css/all.min.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <div class="profile">

            <img src="<?php echo htmlspecialchars($profile_picture, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Picture">

            <?php if ($user): ?>

                <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>

                <?php if ($role == "student"): ?>
                    <p>Student Account</p>
                <?php elseif ($role == "professor"): ?>
                    <p>Faculty Account</p>
                <?php endif; ?>

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

            <a href="#">
                <i class="fa-regular fa-calendar"></i> My Schedule
            </a>

            <a href="upload_schedule.php">
                <i class="fa-solid fa-upload"></i> Upload Schedule
            </a>

            <a href="profile.php">
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

    <h2><?php echo ucfirst(htmlspecialchars($role)); ?> Dashboard</h2>

    <div class="user-box">
        Welcome, <?php echo htmlspecialchars($user['fullname']); ?>
    </div>

</div>

<!-- MAIN -->
<div class="main">

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

        <a href="upload_schedule.php" class="upload-btn">
            <i class="fa-solid fa-upload"></i>
            Upload Schedule
        </a>

        <div class="stats">

            <div class="stat-card">
                <h4>Total Subjects</h4>
                <p>5</p>
            </div>

            <div class="stat-card">
                <h4>Upcoming Classes</h4>
                <p>3</p>
            </div>

            <div class="stat-card">
                <h4>Matched Schedules</h4>
                <p>2</p>
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

<script>
const links = document.querySelectorAll(".nav a");

links.forEach(link => {
    link.addEventListener("click", function (e) {

        const target = this.getAttribute("href");

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