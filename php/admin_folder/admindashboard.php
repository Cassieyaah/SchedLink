<?php
    session_start();
    include '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin || strtolower(trim($admin['role'])) !== 'admin') {
    header("Location: ../logIn.php");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        die('Invalid CSRF token.');
    }

    // Validate inputs
    $allowed_semesters = ['1st','2nd','Summer'];
    $new_semester = $_POST['semester'] ?? '';
    $new_school_year = trim($_POST['school_year'] ?? '');

    if (!in_array($new_semester, $allowed_semesters, true)) {
        $error_message = 'Invalid semester.';
    } elseif (!preg_match('/^\d{4}-\d{4}$/', $new_school_year)) {
        $error_message = 'School year must be in the format YYYY-YYYY (e.g., 2025-2026).';
    } else {
        // Optional: ensure SY end = start+1
        [$sy_start, $sy_end] = array_map('intval', explode('-', $new_school_year));
        if ($sy_end !== $sy_start + 1) {
            $error_message = 'School year must span exactly one year (e.g., 2025-2026).';
        } else {
            $upd = $conn->prepare("
                INSERT INTO site_settings (id, semester, school_year)
                VALUES (1, ?, ?)
                ON DUPLICATE KEY UPDATE semester = VALUES(semester), school_year = VALUES(school_year)
            ");
            $upd->bind_param('ss', $new_semester, $new_school_year);
            if ($upd->execute()) {
                $success_message = 'Settings updated.';
                $current_semester = $new_semester;
                $current_school_year = $new_school_year;
            } else {
                $error_message = 'Failed to update settings.';
            }
        }
    }
}

$total_users = countRows($conn, "SELECT COUNT(*) AS total FROM users");
$total_students = countRows($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
$total_faculty = countRows($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'faculty'");
$total_admins = countRows($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");

$total_uploads = countRows($conn, "SELECT COUNT(*) AS total FROM schedule_uploads");
$student_uploads = countRows($conn, "SELECT COUNT(*) AS total FROM schedule_uploads WHERE role = 'student'");
$faculty_uploads = countRows($conn, "SELECT COUNT(*) AS total FROM schedule_uploads WHERE role = 'faculty'");

$total_matched = countRows($conn, "SELECT COUNT(*) AS total FROM matched_schedules WHERE match_status = 'matched'");
$total_no_match = countRows($conn, "SELECT COUNT(*) AS total FROM matched_schedules WHERE match_status = 'no_match'");

$recent_users = $conn->query("
    SELECT fullname, email, role
    FROM users
    ORDER BY user_id DESC
    LIMIT 5

");

$default_image = "../../media/images.jpg";
$profile_picture = $default_image;
$stored_picture = trim($admin['profile_picture'] ?? '');

if ($stored_picture !== '') {
    $uploaded_path = "../../uploads/" . $stored_picture;
    if (file_exists($uploaded_path)) {
        $profile_picture = $uploaded_path;
    }
}

if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($admin['fullname']) . "&size=200&background=4a90d9&color=fff";
}

// After the admin/role checks and after include '../includes/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch current global semester/school year
$settings_stmt = $conn->prepare("SELECT semester, school_year FROM site_settings WHERE id = 1");
$settings_stmt->execute();
$settings = $settings_stmt->get_result()->fetch_assoc();

$current_semester = $settings['semester'] ?? '1st';
$current_school_year = $settings['school_year'] ?? '2025-2026';


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
    <link rel="stylesheet" href="../../css/studentDashBoard.css">
    <link rel="stylesheet" href="../../css/adminDashboard.css">
    <link rel="stylesheet" href="../../fonts/css/all.min.css">
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
                    <h4>Schedule Upload Summary</h4>
                    <i class="fa-solid fa-upload"></i>
                </div>

                <div class="admin-metric-row">
                    <span>Total uploads</span>
                    <strong><?php echo $total_uploads; ?></strong>
                </div>

                <div class="admin-metric-row">
                    <span>Student uploads</span>
                    <strong><?php echo $student_uploads; ?></strong>
                </div>

                <div class="admin-metric-row">
                    <span>Faculty uploads</span>
                    <strong><?php echo $faculty_uploads; ?></strong>
                </div>
            </div>

            <div class="admin-panel">
                <div class="admin-panel-header">
                    <h4>Schedule Matching Status</h4>
                    <i class="fa-solid fa-code-branch"></i>
                </div>

                <div class="conflict-summary">
                    <div>
                        <span>Matched Schedules</span>
                        <strong><?php echo $total_matched; ?></strong>
                    </div>
                </div>

            </div>
            <div class="admin-panel">
                <div class="admin-panel-header">
                    <h4>Global Term Settings</h4>
                    <i class="fa-solid fa-gear"></i>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert success"><?php echo e($success_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert error"><?php echo e($error_message); ?></div>
                <?php endif; ?>

                <div class="admin-metric-row">
                    <span>Current Semester</span>
                    <strong><?php echo e($current_semester); ?></strong>
                </div>
                <div class="admin-metric-row">
                    <span>Current School Year</span>
                    <strong><?php echo e($current_school_year); ?></strong>
                </div>

                <hr>

                <form method="POST" class="term-settings-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="update_settings" value="1">

                    <div class="form-row">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" required>
                            <option value="1st" <?php echo $current_semester==='1st'?'selected':''; ?>>1st</option>
                            <option value="2nd" <?php echo $current_semester==='2nd'?'selected':''; ?>>2nd</option>
                            <option value="Summer" <?php echo $current_semester==='Summer'?'selected':''; ?>>Summer</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="school_year">School Year</label>
                        <input id="school_year" name="school_year" type="text" placeholder="e.g., 2025-2026"
                            value="<?php echo e($current_school_year); ?>" required pattern="^\d{4}-\d{4}$">
                    </div>

                    <button type="submit" class="admin-action-btn">
                        <i class="fa-solid fa-floppy-disk"></i> Save
                    </button>
                </form>
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

