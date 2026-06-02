<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Initial Schema Check
function ensure_schedule_upload_schema(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS schedule_uploads (
            upload_id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            role ENUM('student','faculty') NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            semester ENUM('1st Semester','2nd Semester') NOT NULL DEFAULT '1st Semester',
            school_year VARCHAR(9) NOT NULL DEFAULT '2025-2026',
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (upload_id),
            KEY user_id (user_id),
            CONSTRAINT schedule_uploads_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $facultyColumn = $conn->query("SHOW COLUMNS FROM faculty_schedules LIKE 'upload_id'");
    if ($facultyColumn && $facultyColumn->num_rows === 0) {
        $conn->query("ALTER TABLE faculty_schedules ADD upload_id INT(11) DEFAULT NULL AFTER professor_id");
        $conn->query("ALTER TABLE faculty_schedules ADD KEY upload_id (upload_id)");
        $conn->query("ALTER TABLE faculty_schedules ADD CONSTRAINT faculty_schedules_upload_fk FOREIGN KEY (upload_id) REFERENCES schedule_uploads (upload_id) ON DELETE CASCADE ON UPDATE CASCADE");
    }
}

ensure_schedule_upload_schema($conn);

$stmt = $conn->prepare("SELECT users.*, faculties.professor_id FROM users LEFT JOIN faculties ON users.user_id = faculties.user_id WHERE users.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || strtolower(trim($user['role'])) !== 'faculty') {
    header("Location: ../php/logIn.php");
    exit();
}

$profile_id = (int) ($user['professor_id'] ?? 0);

// HANDLE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_id'], $_POST['courses'])) {
    $upload_id = (int) $_POST['upload_id'];

    $conn->begin_transaction();
    try {
        // Delete old entries
        $delete_stmt = $conn->prepare("DELETE FROM faculty_schedules WHERE upload_id = ? AND professor_id = ?");
        $delete_stmt->bind_param("ii", $upload_id, $profile_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        // Prepare Insert Statement
        $insert_stmt = $conn->prepare("
            INSERT INTO faculty_schedules (professor_id, upload_id, schedule_code, course_code, course_description, room, semester, school_year, status) 
            SELECT ?, upload_id, ?, ?, ?, ?, semester, school_year, 'active' 
            FROM schedule_uploads WHERE upload_id = ?
        ");

        foreach ($_POST['courses'] as $course) {
            $schedule_code = trim($course['schedule_code'] ?? '');
            $course_code = trim($course['course_code'] ?? '');
            $course_description = trim($course['course_description'] ?? '');
            $room = trim($course['room'] ?? '');

            if ($schedule_code === '' && $course_code === '' && $course_description === '') continue;

            $insert_stmt->bind_param("isssssi", $profile_id, $schedule_code, $course_code, $course_description, $room, $upload_id);
            if (!$insert_stmt->execute()) throw new Exception($insert_stmt->error);
        }
        $insert_stmt->close();
        $conn->commit();
        $_SESSION['schedule_success'] = "Schedule updated successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['schedule_error'] = "Update failed: " . $e->getMessage();
    }
    header("Location: myschedule.php");
    exit();
}

$upload_success = $_SESSION['schedule_success'] ?? '';
$upload_error = $_SESSION['schedule_error'] ?? '';
unset($_SESSION['schedule_success'], $_SESSION['schedule_error']);

// Fetch Uploads
$uploads = [];
$upload_stmt = $conn->prepare("SELECT upload_id, original_filename, semester, school_year, uploaded_at FROM schedule_uploads WHERE user_id = ? AND role = 'faculty' ORDER BY uploaded_at DESC");
$upload_stmt->bind_param("i", $user_id);
$upload_stmt->execute();
$upload_result = $upload_stmt->get_result();
while ($row = $upload_result->fetch_assoc()) {
    $row['courses'] = [];
    $uploads[(int) $row['upload_id']] = $row;
}
$upload_stmt->close();

if ($uploads) {
    $ids = implode(',', array_map('intval', array_keys($uploads)));
    $course_query = $conn->query("SELECT * FROM faculty_schedules WHERE professor_id = $profile_id AND upload_id IN ($ids) ORDER BY upload_id DESC");
    while ($course = $course_query->fetch_assoc()) {
        $uploads[(int) $course['upload_id']]['courses'][] = $course;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Schedule</title>
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../css/mysched.css">
    <link rel="stylesheet" href="../fonts/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="profile">
            <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
            <p>Faculty Account</p>
        </div>
        <div class="nav">
            <a href="facultydashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a class="active" href="myschedule.php"><i class="fa-regular fa-calendar"></i> My Schedule</a>
            <a href="facultydashboard.php#upload"><i class="fa-solid fa-upload"></i> Upload Schedule</a>
            <a href="facultyprofile.php"><i class="fa-solid fa-user"></i> Profile</a>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <main class="main">
        <section class="myschedule-page">
            <h3>Uploaded Schedules</h3>
            <?php foreach ($uploads as $upload): ?>
                <article class="collapsible-container">
                    <button type="button" class="schedule-upload-summary">
                        <span><strong><?php echo htmlspecialchars($upload['original_filename']); ?></strong></span>
                    </button>
                    <div class="accordion-content">
                        <form method="POST" class="schedule-edit-form">
                            <input type="hidden" name="upload_id" value="<?php echo (int) $upload['upload_id']; ?>">
                            
                            <div class="grid-table-header">
                                <span>Sched Code</span>
                                <span>Subjects</span>
                                <span>Course/Year</span>
                                <span>Rooms</span>
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
