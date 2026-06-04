<?php
session_start();

include '../../includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// --- FETCH USER & FACULTY ID ---
$stmt = $conn->prepare("
    SELECT 
        users.*, 
        faculties.faculty_id 
    FROM users
    LEFT JOIN faculties 
        ON users.user_id = faculties.user_id
    WHERE users.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../logIn.php");
    exit();
}

// --- AUTO CREATE FACULTY RECORD IF MISSING ---
if (empty($user['faculty_id'])) {
    $insertFaculty = $conn->prepare("
        INSERT INTO faculties (user_id)
        VALUES (?)
    ");

    $insertFaculty->bind_param("i", $user_id);
    $insertFaculty->execute();
    $insertFaculty->close();

    // REFRESH USER DATA
    $stmt = $conn->prepare("
        SELECT 
            users.*, 
            faculties.faculty_id 
        FROM users
        LEFT JOIN faculties 
            ON users.user_id = faculties.user_id
        WHERE users.user_id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$profile_id = (int) ($user['faculty_id'] ?? 0);
$dashboard_page = 'facultydashboard.php';
$profile_page = 'facultyprofile.php';

// --- HANDLE POST UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_id'], $_POST['courses'])) {

    $upload_id = (int) $_POST['upload_id'];

    if ($profile_id <= 0) {
        die("Invalid Faculty ID.");
    }

    $conn->begin_transaction();

    try {
        // DELETE OLD RECORDS
        $delete_stmt = $conn->prepare("
            DELETE FROM faculty_schedules
            WHERE upload_id = ?
            AND faculty_id = ?
        ");

        $delete_stmt->bind_param("ii", $upload_id, $profile_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        // INSERT UPDATED RECORDS (Using course_year instead of course_description)
        $insert_stmt = $conn->prepare("
            INSERT INTO faculty_schedules
            (
                faculty_id,
                upload_id,
                schedule_code,
                course_code,
                course_year,
                room,
                status
            )
            VALUES
            (?, ?, ?, ?, ?, ?, 'active')
        ");

        foreach ($_POST['courses'] as $course) {
            $schedule_code = trim($course['schedule_code'] ?? '');
            $course_code   = trim($course['course_code'] ?? '');
            $course_year   = trim($course['course_year'] ?? '');
            $room          = trim($course['room'] ?? '');

            // SKIP EMPTY ROWS
            if (
                $schedule_code === '' &&
                $course_code === '' &&
                $course_year === '' &&
                $room === ''
            ) {
                continue;
            }

            $insert_stmt->bind_param(
                "iissss",
                $profile_id,
                $upload_id,
                $schedule_code,
                $course_code,
                $course_year,
                $room
            );

            $insert_stmt->execute();
        }

        $insert_stmt->close();
        $conn->commit();

        $_SESSION['upload_success'] = "Schedule updated successfully.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['upload_error'] = "Database Error: " . $e->getMessage();
    }

    header("Location: faculty_schedule.php");
    exit();
}

// --- FETCH UPLOADS ---
$uploads = [];
$upload_stmt = $conn->prepare("
    SELECT *
    FROM schedule_uploads
    WHERE user_id = ?
    ORDER BY uploaded_at DESC
");

$upload_stmt->bind_param("i", $user_id);
$upload_stmt->execute();
$upload_result = $upload_stmt->get_result();
$upload_ids = [];

while ($row = $upload_result->fetch_assoc()) {
    $row['courses'] = [];
    $uploads[$row['upload_id']] = $row;
    $upload_ids[] = $row['upload_id'];
}
$upload_stmt->close();

// --- FETCH COURSES ---
if (!empty($upload_ids)) {
    $placeholders = implode(',', array_fill(0, count($upload_ids), '?'));
    $types = str_repeat('i', count($upload_ids) + 1);
    $params = array_merge([$profile_id], $upload_ids);

    $query = "
        SELECT *
        FROM faculty_schedules
        WHERE faculty_id = ?
        AND upload_id IN ($placeholders)
    ";

    $course_stmt = $conn->prepare($query);
    $course_stmt->bind_param($types, ...$params);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();

    while ($course = $course_result->fetch_assoc()) {
        if (isset($uploads[$course['upload_id']])) {
            $uploads[$course['upload_id']]['courses'][] = $course;
        }
    }
    $course_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Schedule</title>
    <link rel="stylesheet" href="../../css/studentDashBoard.css">
    <link rel="stylesheet" href="../../css/uploadSchedule.css">
    <link rel="stylesheet" href="../../css/faculty_sched.css">
    <link rel="stylesheet" href="../../fonts/css/all.min.css">
    <link rel="stylesheet" href="../../css/mysched_upgrade.css">
</head>
<body>

<div class="sidebar">
    <div>
        <div class="profile">
            <img src="../../media/images.jpg" alt="Profile Picture">
            <h3><?php echo htmlspecialchars($user['fullname'] ?? 'Faculty'); ?></h3>
            <p>Faculty Account</p>
        </div>

        <div class="section-title">GENERAL</div>
        <div class="nav">
            <a href="<?php echo $dashboard_page; ?>">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
            <a class="active" href="faculty_schedule.php">
                <i class="fa-regular fa-calendar"></i> My Schedule
            </a>
            <a href="<?php echo $dashboard_page; ?>#upload">
                <i class="fa-solid fa-upload"></i> Upload Schedule
            </a>
            <a href="<?php echo $profile_page; ?>">
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

<div class="header">
    <h2>My Schedule</h2>
    <div class="user-box">
        Welcome, <?php echo htmlspecialchars($user['fullname'] ?? 'User'); ?>
    </div>
</div>

<main class="main">
<section class="myschedule-page">
    <div class="myschedule-title">
        <div>
            <h3>Uploaded Schedules</h3>
            <p>Most recent uploads appear first.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['upload_success'])): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($_SESSION['upload_success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['upload_error'])): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($_SESSION['upload_error']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($uploads)): ?>
        <div class="empty-schedule-state">
            <i class="fa-regular fa-calendar-xmark"></i>
            <p>No uploaded schedules yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($uploads as $upload): ?>
            <article class="collapsible-container schedule-upload-group">
                <button type="button" class="schedule-upload-summary">
                    <span><strong><?php echo htmlspecialchars($upload['original_filename']); ?></strong></span>
                    <i class="fa-solid fa-chevron-down accordion-arrow"></i>
                </button>

                <div class="accordion-content">
                    <form method="POST" class="schedule-edit-form">
                        <input type="hidden" name="upload_id" value="<?php echo (int) $upload['upload_id']; ?>">

                        <div class="grid-table-header">
                            <span>Sched Code</span>
                            <span>Subject</span>
                            <span>Course/Year</span>
                            <span>Rooms</span>
                        </div>

                        <div class="grid-table-body">
                            <?php foreach ($upload['courses'] as $index => $course): ?>
                                <div class="grid-table-row editable-grid-row">
                                    <input type="text" name="courses[<?php echo $index; ?>][schedule_code]" value="<?php echo htmlspecialchars($course['schedule_code'] ?? ''); ?>">
                                    
                                    <input type="text" name="courses[<?php echo $index; ?>][course_code]" value="<?php echo htmlspecialchars($course['course_code'] ?? ''); ?>">
                                    
                                    <input type="text" name="courses[<?php echo $index; ?>][course_year]" value="<?php echo htmlspecialchars($course['course_year'] ?? ''); ?>">
                                    
                                    <input type="text" name="courses[<?php echo $index; ?>][room]" value="<?php echo htmlspecialchars($course['room'] ?? ''); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="schedule-edit-actions">
                            <button type="button" class="secondary-upload-btn add-row-btn">
                                <i class="fa-solid fa-plus"></i> Add Row
                            </button>
                            <button type="submit" class="primary-upload-btn">
                                <i class="fa-solid fa-floppy-disk"></i> Save Update
                            </button>
                        </div>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
</main>

<script>
document.querySelectorAll(".schedule-upload-summary").forEach(button => {
    button.addEventListener("click", () => {
        button.closest(".collapsible-container").classList.toggle("active-dropdown");
    });
});

document.querySelectorAll(".add-row-btn").forEach(button => {
    button.addEventListener("click", () => {
        const body = button.closest("form").querySelector(".grid-table-body");
        const index = body.querySelectorAll(".grid-table-row").length;
        const row = document.createElement("div");
        row.className = "grid-table-row editable-grid-row";

        row.innerHTML = `
            <input type="text" name="courses[${index}][schedule_code]" placeholder="Sched Code">
            <input type="text" name="courses[${index}][course_code]" placeholder="Subject">
            <input type="text" name="courses[${index}][course_year]" placeholder="Course/Year">
            <input type="text" name="courses[${index}][room]" placeholder="Room">
        `;
        body.appendChild(row);
    });
});
</script>
</body>
</html>
