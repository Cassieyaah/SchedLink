<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

// --- CLEANUP ---
// Tinatanggal ang mga session data na baka nagdudulot ng conflict o pag-duplicate
unset($_SESSION['upload_success']);
unset($_SESSION['upload_error']);
unset($_SESSION['ocr_preview_data']);

$user_id = (int) $_SESSION['user_id'];

// --- FETCH USER & FACULTY ID ---
$stmt = $conn->prepare("SELECT users.*, faculties.professor_id FROM users LEFT JOIN faculties ON users.user_id = faculties.user_id WHERE users.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../php/logIn.php");
    exit();
}

$role = strtolower(trim($user['role'] ?? ''));
$profile_id = (int) ($user['professor_id'] ?? 0);
$dashboard_page = 'facultydashboard.php'; 
$profile_page = 'facultyprofile.php';

// --- HANDLE POST UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_id'], $_POST['courses'])) {
    $upload_id = (int) $_POST['upload_id'];
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM faculty_schedules WHERE upload_id = $upload_id AND professor_id = $profile_id");
        $insert_stmt = $conn->prepare("INSERT INTO faculty_schedules (professor_id, upload_id, schedule_code, course_description, course_code, room, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        
        foreach ($_POST['courses'] as $course) {
            $sc = trim($course['schedule_code'] ?? '');
            $cd = trim($course['course_description'] ?? '');
            $cc = trim($course['course_code'] ?? '');
            $rm = trim($course['room'] ?? '');
            if ($sc === '' && $cd === '' && $cc === '') continue;
            $insert_stmt->bind_param("iissss", $profile_id, $upload_id, $sc, $cd, $cc, $rm);
            $insert_stmt->execute();
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
    header("Location: faculty_schedule.php");
    exit();
}

// --- FETCH UPLOADS & SCHEDULES ---
$uploads = [];
$upload_res = $conn->query("SELECT * FROM schedule_uploads WHERE user_id = $user_id ORDER BY uploaded_at DESC");

// Kunin lahat ng upload IDs para sa query
$upload_ids = [];
while ($row = $upload_res->fetch_assoc()) {
    $row['courses'] = [];
    $uploads[$row['upload_id']] = $row;
    $upload_ids[] = $row['upload_id'];
}

if (!empty($upload_ids)) {
    $ids_string = implode(',', $upload_ids);
    
    // FIX: Gumamit ng GROUP BY para siguruhing unique ang records
    // at hindi mag-duplicate ang display kahit ano pang page ang pinanggalingan.
    $query = "SELECT * FROM faculty_schedules 
              WHERE professor_id = $profile_id 
              AND upload_id IN ($ids_string) 
              GROUP BY upload_id, schedule_code, course_code, room";
              
    $c_res = $conn->query($query);
    while ($c = $c_res->fetch_assoc()) {
        if (isset($uploads[$c['upload_id']])) {
            $uploads[$c['upload_id']]['courses'][] = $c;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Schedule</title>
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../css/uploadSchedule.css">
    <link rel="stylesheet" href="../css/mysched.css">
    <link rel="stylesheet" href="../fonts/css/all.min.css">
    <link rel="stylesheet" href="../css/mysched_upgrade.css">
</head>
<body>
    <div class="sidebar">
        <div>
            <div class="profile">
                <img src="../media/images.jpg" alt="Profile Picture">
                <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
                <p>Faculty Account</p>
            </div>
            <div class="section-title">GENERAL</div>
            <div class="nav">
                <a href="<?php echo $dashboard_page; ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                <a class="active" href="faculty_schedule.php"><i class="fa-regular fa-calendar"></i> My Schedule</a>
                <a href="<?php echo $dashboard_page; ?>#upload"><i class="fa-solid fa-upload"></i> Upload Schedule</a>
                <a href="<?php echo $profile_page; ?>"><i class="fa-solid fa-user"></i> Profile</a>
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
            <div class="divider"></div>
        </div>
        <div class="sidebar-footer">
            <img src="../media/cvsulogo.png" alt="CvSU Logo">
            <p>Cavite State University</p>
        </div>
    </div>

    <div class="header">
        <h2>My Schedule</h2>
        <div class="user-box">Welcome, <?php echo htmlspecialchars($user['fullname']); ?></div>
    </div>

    <main class="main">
        <section class="myschedule-page">
            <div class="myschedule-title">
                <div>
                    <h3>Uploaded Schedules</h3>
                    <p>Most recent uploads appear first.</p>
                </div>
            </div>

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
                                    <span>Sched Code</span><span>Subjects</span><span>Course/Year</span><span>Rooms</span>
                                </div>
                                <div class="grid-table-body">
                                    <?php foreach ($upload['courses'] as $index => $course): ?>
                                        <div class="grid-table-row editable-grid-row">
                                            <input type="text" name="courses[<?php echo $index; ?>][schedule_code]" value="<?php echo htmlspecialchars($course['schedule_code'] ?? ''); ?>">
                                            <input type="text" name="courses[<?php echo $index; ?>][course_description]" value="<?php echo htmlspecialchars($course['course_description'] ?? ''); ?>">
                                            <input type="text" name="courses[<?php echo $index; ?>][course_code]" value="<?php echo htmlspecialchars($course['course_code'] ?? ''); ?>">
                                            <input type="text" name="courses[<?php echo $index; ?>][room]" value="<?php echo htmlspecialchars($course['room'] ?? ''); ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="schedule-edit-actions">
                                    <button type="button" class="secondary-upload-btn add-row-btn"><i class="fa-solid fa-plus"></i> Add Row</button>
                                    <button type="submit" class="primary-upload-btn"><i class="fa-solid fa-floppy-disk"></i> Save Update</button>
                                </div>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <script>
        document.querySelectorAll(".schedule-upload-summary").forEach(b => b.addEventListener("click", () => b.closest(".collapsible-container").classList.toggle("active-dropdown")));
        document.querySelectorAll(".add-row-btn").forEach(button => {
            button.addEventListener("click", () => {
                const body = button.closest("form").querySelector(".grid-table-body");
                const index = body.querySelectorAll(".grid-table-row").length;
                const row = document.createElement("div");
                row.className = "grid-table-row editable-grid-row";
                row.innerHTML = `
                    <input type="text" name="courses[${index}][schedule_code]" placeholder="Sched Code">
                    <input type="text" name="courses[${index}][course_description]" placeholder="Subject">
                    <input type="text" name="courses[${index}][course_code]" placeholder="Course/Year">
                    <input type="text" name="courses[${index}][room]" placeholder="Room">
                `;
                body.appendChild(row);
            });
        });
    </script>
</body>
</html>
