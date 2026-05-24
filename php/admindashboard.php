<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin || strtolower(trim($admin['role'])) !== 'admin') {
    header("Location: ../php/logIn.php");
    exit();
}

function countRows(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['total'] ?? 0);
}

$total_users = countRows($conn, "SELECT COUNT(*) AS total FROM users");
$total_students = countRows($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
$total_faculty = countRows($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'faculty'");
$total_admins = countRows($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
$student_schedules = countRows($conn, "SELECT COUNT(*) AS total FROM student_schedules");
$faculty_schedules = countRows($conn, "SELECT COUNT(*) AS total FROM faculty_schedules");
$pending_matches = countRows($conn, "SELECT COUNT(*) AS total FROM matched_schedules WHERE match_status = 'pending'");
$conflicts = countRows($conn, "SELECT COUNT(*) AS total FROM matched_schedules WHERE match_status = 'conflict'");

$recent_users = $conn->query("
    SELECT fullname, email, role
    FROM users
    ORDER BY user_id DESC
    LIMIT 5
");

$default_image = "../media/images.jpg";
$profile_picture = $default_image;
$stored_picture = trim($admin['profile_picture'] ?? '');

if ($stored_picture !== '') {
    $uploaded_path = "../uploads/" . $stored_picture;
    if (file_exists($uploaded_path)) {
        $profile_picture = $uploaded_path;
    }
}

if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($admin['fullname']) . "&size=200&background=4a90d9&color=fff";
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../css/adminDashboard.css">
    <link rel="stylesheet" href="../fonts/css/all.min.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <div class="profile">
            <img src="<?php echo e($profile_picture); ?>" alt="Profile Picture">
            <h3><?php echo e($admin['fullname']); ?></h3>
            <p>Administrator</p>
        </div>

        <div class="section-title">GENERAL</div>

        <div class="nav">

            <a class="active" href="admindashboard.php">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>

            <a href="adminprofile.php">
                <i class="fa-solid fa-user"></i> Profile
            </a>

            <a href="create_account.php">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </a>

            <a href="user_list.php">
                <i class="fa-solid fa-users"></i> User List
            </a>

            <a href="schedule_conflicts.php">
                <i class="fa-solid fa-triangle-exclamation"></i> Schedule Conflict Management
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

    <h2>Admin Dashboard</h2>

    <div class="user-box">
        Welcome, <?php echo e($admin['fullname']); ?>
    </div>

</div>

<!-- MAIN -->
<div class="main">

    <div class="dashboard-container admin-dashboard">

        <div class="admin-dashboard-heading">
            <div>
                <h3>System Overview</h3>
                <p>Monitor accounts, schedule uploads, and matching issues across SchedLink.</p>
            </div>

            <a href="create_account.php" class="admin-action-btn">
                <i class="fa-solid fa-user-plus"></i>
                Create Faculty Account
            </a>
        </div>

        <div class="stats admin-stats">

            <div class="stat-card">
                <h4>Total Users</h4>
                <p><?php echo $total_users; ?></p>
            </div>

            <div class="stat-card">
                <h4>Students</h4>
                <p><?php echo $total_students; ?></p>
            </div>

            <div class="stat-card">
                <h4>Faculty</h4>
                <p><?php echo $total_faculty; ?></p>
            </div>

            <div class="stat-card">
                <h4>Admins</h4>
                <p><?php echo $total_admins; ?></p>
            </div>

        </div>

        <div class="admin-grid">

            <div class="admin-panel">
                <div class="admin-panel-header">
                    <h4>Schedule Records</h4>
                    <i class="fa-regular fa-calendar"></i>
                </div>

                <div class="admin-metric-row">
                    <span>Student schedules</span>
                    <strong><?php echo $student_schedules; ?></strong>
                </div>

                <div class="admin-metric-row">
                    <span>Faculty schedules</span>
                    <strong><?php echo $faculty_schedules; ?></strong>
                </div>

                <div class="admin-metric-row">
                    <span>Total schedule records</span>
                    <strong><?php echo $student_schedules + $faculty_schedules; ?></strong>
                </div>
            </div>

            <div class="admin-panel">
                <div class="admin-panel-header">
                    <h4>Conflict Management</h4>
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <div class="conflict-summary">
                    <div>
                        <span>Pending Reviews</span>
                        <strong><?php echo $pending_matches; ?></strong>
                    </div>

                    <div>
                        <span>Conflicts</span>
                        <strong><?php echo $conflicts; ?></strong>
                    </div>
                </div>

                <a href="schedule_conflicts.php" class="panel-link">
                    Review schedule conflicts
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="admin-panel recent-users-panel">
                <div class="admin-panel-header">
                    <h4>Recent Users</h4>
                    <i class="fa-solid fa-users"></i>
                </div>

                <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                    <div class="recent-user-list">
                        <?php while ($row = $recent_users->fetch_assoc()): ?>
                            <div class="recent-user-item">
                                <div>
                                    <strong><?php echo e($row['fullname']); ?></strong>
                                    <span><?php echo e($row['email']); ?></span>
                                </div>
                                <em><?php echo e(ucfirst($row['role'])); ?></em>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">No users found.</p>
                <?php endif; ?>
            </div>

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
