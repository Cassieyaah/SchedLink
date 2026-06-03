<?php
session_start();
include '../includes/db.php';
include '../includes/matched_schedules.php'; // Option B: External matching engine

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/**
 * Ensures required schemas and columns exist dynamically
 */
function ensure_schedule_upload_schema($conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS schedule_uploads (
            upload_id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            role ENUM('student','faculty') NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            stored_file_path VARCHAR(255) DEFAULT NULL,
            semester ENUM('1st Semester','2nd Semester') NOT NULL DEFAULT '1st Semester',
            school_year VARCHAR(9) NOT NULL DEFAULT '2025-2026',
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (upload_id),
            KEY user_id (user_id),
            CONSTRAINT schedule_uploads_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $studentColumn = $conn->query("SHOW COLUMNS FROM student_schedules LIKE 'upload_id'");
    if ($studentColumn && $studentColumn->num_rows === 0) {
        $conn->query("ALTER TABLE student_schedules ADD upload_id INT(11) DEFAULT NULL AFTER student_id");
        $conn->query("ALTER TABLE student_schedules ADD KEY upload_id (upload_id)");
        $conn->query("ALTER TABLE student_schedules ADD CONSTRAINT student_schedules_upload_fk FOREIGN KEY (upload_id) REFERENCES schedule_uploads (upload_id) ON DELETE CASCADE ON UPDATE CASCADE");
    }

    $profColumnCheck = $conn->query("SHOW COLUMNS FROM student_schedules LIKE 'prof_name'");
    if ($profColumnCheck && $profColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE student_schedules ADD prof_name VARCHAR(255) DEFAULT NULL AFTER course_description");
    }

    $facultyColumn = $conn->query("SHOW COLUMNS FROM faculty_schedules LIKE 'upload_id'");
    if ($facultyColumn && $facultyColumn->num_rows === 0) {
        $conn->query("ALTER TABLE faculty_schedules ADD upload_id INT(11) DEFAULT NULL AFTER faculty_id");
        $conn->query("ALTER TABLE faculty_schedules ADD KEY upload_id (upload_id)");
        $conn->query("ALTER TABLE faculty_schedules ADD CONSTRAINT faculty_schedules_upload_fk FOREIGN KEY (upload_id) REFERENCES schedule_uploads (upload_id) ON DELETE CASCADE ON UPDATE CASCADE");
    }
}

/**
 * Formats database TIME string (HH:MM:SS) to standard input value (HH:MM)
 */
function format_time_value(string $value): string {
    if ($value === '' || $value === '00:00:00') {
        return '';
    }
    return date('H:i', strtotime($value));
}

ensure_schedule_upload_schema($conn);

// Retrieve active user contextual identity metadata
$stmt = $conn->prepare("
    SELECT
        users.*,
        students.student_id,
        students.student_number,
        students.program,
        faculties.faculty_id,
        faculties.department
    FROM users
    LEFT JOIN students ON users.user_id = students.user_id
    LEFT JOIN faculties ON users.user_id = faculties.user_id
    WHERE users.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../php/logIn.php");
    exit();
}

$role = strtolower(trim($user['role']));
if (!in_array($role, ['student', 'faculty'], true)) {
    header("Location: admindashboard.php");
    exit();
}

$dashboard_page = $role === 'faculty' ? 'facultydashboard.php' : 'studentdashboard.php';
$profile_page   = $role === 'faculty' ? 'facultyprofile.php' : 'profile.php';
$profile_id     = $role === 'faculty' ? (int) ($user['faculty_id'] ?? 0) : (int) ($user['student_id'] ?? 0);

// Process Update Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_id'], $_POST['courses'])) {
    $upload_id = (int) $_POST['upload_id'];
    $owner_stmt = $conn->prepare("SELECT upload_id FROM schedule_uploads WHERE upload_id = ? AND user_id = ? AND role = ?");
    $owner_stmt->bind_param("iis", $upload_id, $user_id, $role);
    $owner_stmt->execute();
    $owned_upload = $owner_stmt->get_result()->fetch_assoc();
    $owner_stmt->close();

    if (!$owned_upload || $profile_id === 0) {
        $_SESSION['schedule_error'] = "Schedule update failed because the upload record was not found.";
        header("Location: myschedule.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        if ($role === 'student') {
            $delete_stmt = $conn->prepare("DELETE FROM student_schedules WHERE upload_id = ? AND student_id = ?");
            $delete_stmt->bind_param("ii", $upload_id, $profile_id);
            $insert_stmt = $conn->prepare("INSERT INTO student_schedules (student_id, upload_id, schedule_code, course_code, course_description, prof_name, time_start, time_end, day, room, semester, school_year, status) SELECT ?, upload_id, ?, ?, ?, ?, ?, ?, ?, ?, semester, school_year, 'active' FROM schedule_uploads WHERE upload_id = ?");
        } else {
            $delete_stmt = $conn->prepare("DELETE FROM faculty_schedules WHERE upload_id = ? AND faculty_id = ?");
            $delete_stmt->bind_param("ii", $upload_id, $profile_id);
            $insert_stmt = $conn->prepare("INSERT INTO faculty_schedules (faculty_id, upload_id, schedule_code, course_code, day, time_start, time_end, room, semester, school_year, status) SELECT ?, upload_id, ?, ?, ?, ?, ?, ?, semester, school_year, 'active' FROM schedule_uploads WHERE upload_id = ?");
        }

        $delete_stmt->execute();
        $delete_stmt->close();

        foreach ($_POST['courses'] as $course) {
            $schedule_code      = trim($course['schedule_code'] ?? '');
            $course_code        = trim($course['course_code'] ?? '');
            $course_description = trim($course['course_description'] ?? '');
            $day                = trim($course['day'] ?? '');
            $room               = trim($course['room'] ?? '');
            $time_start         = trim($course['time_start'] ?? '');
            $time_end           = trim($course['time_end'] ?? '');
            $prof_name          = trim($course['prof_name'] ?? '');

            if ($schedule_code === '' && $course_code === '') {
                continue;
            }

            $time_start = $time_start !== '' ? date('H:i:s', strtotime($time_start)) : '00:00:00';
            $time_end   = $time_end   !== '' ? date('H:i:s', strtotime($time_end))   : '00:00:00';

            if ($role === 'student') {
                $insert_stmt->bind_param("issssssssi", $profile_id, $schedule_code, $course_code, $course_description, $prof_name, $time_start, $time_end, $day, $room, $upload_id);
            } else {
                $insert_stmt->bind_param("isssssssi", $profile_id, $schedule_code, $course_code, $day, $time_start, $time_end, $room, $upload_id);
            }

            if (!$insert_stmt->execute()) {
                throw new Exception($insert_stmt->error);
            }
        }

        $insert_stmt->close();
        $conn->commit();
        
        // Execute the tracking engine located in matched_schedules.php
        synchronize_schedule_matches($conn);

        $_SESSION['schedule_success'] = "Schedule upload updated successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['schedule_error'] = "Could not update schedule: " . $e->getMessage();
    }

    header("Location: myschedule.php");
    exit();
}

$upload_success = $_SESSION['schedule_success'] ?? '';
$upload_error   = $_SESSION['schedule_error']   ?? '';
unset($_SESSION['schedule_success'], $_SESSION['schedule_error']);

$uploads = [];
$upload_stmt = $conn->prepare("
    SELECT upload_id, original_filename, stored_file_path, semester, school_year, uploaded_at
    FROM schedule_uploads
    WHERE user_id = ? AND role = ?
    ORDER BY uploaded_at DESC, upload_id DESC
");
$upload_stmt->bind_param("is", $user_id, $role);
$upload_stmt->execute();
$upload_result = $upload_stmt->get_result();
while ($upload = $upload_result->fetch_assoc()) {
    $upload['courses'] = [];
    $uploads[(int) $upload['upload_id']] = $upload;
}
$upload_stmt->close();

if ($uploads) {
    $ids = implode(',', array_map('intval', array_keys($uploads)));

    if ($role === 'student') {
        // Read directly through the matched_schedules tracking table link
        $course_query = $conn->query("
            SELECT 
                ss.*,
                ms.match_status,
                COALESCE(u.fullname, ss.prof_name) AS matched_prof_name,
                CASE WHEN u.fullname IS NOT NULL THEN 1 ELSE 0 END AS is_matched
            FROM student_schedules ss
            LEFT JOIN matched_schedules ms ON ss.student_schedule_id = ms.student_schedule_id
            LEFT JOIN faculty_schedules fs ON ms.professor_schedule_id = fs.professor_schedule_id
            LEFT JOIN faculties f ON fs.faculty_id = f.faculty_id
            LEFT JOIN users u ON f.user_id = u.user_id
            WHERE ss.student_id = $profile_id AND ss.upload_id IN ($ids)
            ORDER BY ss.upload_id DESC, ss.student_schedule_id ASC
        ");
    } else {
        $course_query = $conn->query("
            SELECT *
            FROM faculty_schedules
            WHERE faculty_id = $profile_id AND upload_id IN ($ids)
            ORDER BY upload_id DESC, professor_schedule_id ASC
        ");
    }

    if ($course_query) {
        while ($course = $course_query->fetch_assoc()) {
            $uploads[(int) $course['upload_id']]['courses'][] = $course;
        }
    }

    $day_order = [
        'M'  => 1, 'MON' => 1, 'MONDAY'    => 1,
        'T'  => 2, 'TUE' => 2, 'TUESDAY'   => 2,
        'W'  => 3, 'WED' => 3, 'WEDNESDAY' => 3,
        'TH' => 4, 'THU' => 4, 'THURSDAY'  => 4,
        'F'  => 5, 'FRI' => 5, 'FRIDAY'    => 5,
        'S'  => 6, 'ST'  => 6, 'SAT' => 6, 'SATURDAY' => 6,
    ];

    foreach ($uploads as $uid => $upload_data) {
        if (!empty($uploads[$uid]['courses'])) {
            usort($uploads[$uid]['courses'], function ($a, $b) use ($day_order) {
                $dayA   = strtoupper(trim($a['day'] ?? ''));
                $dayB   = strtoupper(trim($b['day'] ?? ''));
                $orderA = $day_order[$dayA] ?? 99;
                $orderB = $day_order[$dayB] ?? 99;
                if ($orderA === $orderB) {
                    return strcmp($a['time_start'] ?? '00:00:00', $b['time_start'] ?? '00:00:00');
                }
                return $orderA <=> $orderB;
            });
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule</title>
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../css/uploadSchedule.css">
    <link rel="stylesheet" href="../css/mysched.css">
    <link rel="stylesheet" href="../fonts/css/all.min.css">
    <link rel="stylesheet" href="../css/mysched_upgrade.css">
    <style>
        /* Stylistic badges for tracking alignment states */
        .status-badge {
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
            margin-top: 4px;
        }
        .badge-matched { background-color: #d1e7dd; color: #0f5132; }
        .badge-conflict { background-color: #f8d7da; color: #842029; }
        .badge-no_match { background-color: #e2e3e5; color: #41464b; }
    </style>
</head>
<body>

<div class="sidebar">
    <div>
        <div class="profile">
            <img src="../media/images.jpg" alt="Profile Picture">
            <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
            <p><?php echo ucfirst($role); ?> Account</p>
        </div>
        <div class="section-title">GENERAL</div>
        <div class="nav">
            <a href="<?php echo $dashboard_page; ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a class="active" href="myschedule.php"><i class="fa-regular fa-calendar"></i> My Schedule</a>
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
    <?php if ($upload_success): ?>
        <div class="dashboard-alert success-alert"><?php echo htmlspecialchars($upload_success); ?></div>
    <?php endif; ?>
    <?php if ($upload_error): ?>
        <div class="dashboard-alert error-alert"><?php echo htmlspecialchars($upload_error); ?></div>
    <?php endif; ?>

    <section class="myschedule-page">
        <div class="myschedule-title">
            <div>
                <h3>Uploaded Schedules</h3>
                <p>Most recent uploads appear first. Open an upload to view or edit the extracted rows.</p>
            </div>
            <a class="primary-upload-btn" href="<?php echo $dashboard_page; ?>#upload">
                <i class="fa-solid fa-upload"></i> Upload
            </a>
        </div>

        <?php if (!$uploads): ?>
            <div class="empty-schedule-state">
                <i class="fa-regular fa-calendar-xmark"></i>
                <p>No uploaded schedules yet.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($uploads as $upload): ?>
            <article class="collapsible-container schedule-upload-group">
                <button type="button" class="schedule-upload-summary">
                    <span>
                        <strong><?php echo htmlspecialchars($upload['original_filename'] ?: 'Uploaded schedule'); ?></strong>
                        <small>
                            <?php echo date('F j, Y g:i A', strtotime($upload['uploaded_at'])); ?>
                            &middot; <?php echo htmlspecialchars($upload['semester']); ?>
                            &middot; <?php echo htmlspecialchars($upload['school_year']); ?>
                        </small>
                    </span>
                    <i class="fa-solid fa-chevron-down accordion-arrow"></i>
                </button>

                <div class="accordion-content">
                    <form method="POST" class="schedule-edit-form">
                        <input type="hidden" name="upload_id" value="<?php echo (int) $upload['upload_id']; ?>">

                        <div class="grid-table-header">
                            <span>Sched Code</span>
                            <span>Course Code</span>
                            <?php if ($role === 'student'): ?>
                                <span>Description</span>
                            <?php endif; ?>
                            <span>Instructor</span>
                            <span>Day</span>
                            <span>Time</span>
                            <span>Room</span>
                        </div>

                        <div class="grid-table-body">
                            <?php foreach ($upload['courses'] as $index => $course):
                                if ($role === 'student') {
                                    $prof_raw = $course['matched_prof_name'] ?? $course['prof_name'] ?? '';
                                    $is_unknown = ($prof_raw === '' || stripos($prof_raw, 'not found') !== false);
                                    $match_status = $course['match_status'] ?? 'no_match';
                                } else {
                                    $prof_raw = '';
                                    $is_unknown = true;
                                    $match_status = '';
                                }
                            ?>
                                <div class="grid-table-row editable-grid-row">
                                    <input type="text" name="courses[<?php echo $index; ?>][schedule_code]" value="<?php echo htmlspecialchars($course['schedule_code'] ?? ''); ?>">
                                    <input type="text" name="courses[<?php echo $index; ?>][course_code]" value="<?php echo htmlspecialchars($course['course_code'] ?? ''); ?>">
                                    
                                    <?php if ($role === 'student'): ?>
                                        <input type="text" name="courses[<?php echo $index; ?>][course_description]" value="<?php echo htmlspecialchars($course['course_description'] ?? ''); ?>">
                                    <?php endif; ?>

                                    <div class="prof-column-input-wrapper">
                                        <input type="text"
                                            name="courses[<?php echo $index; ?>][prof_name]"
                                            value="<?php echo htmlspecialchars(($role === 'student' && $is_unknown) ? 'Prof: not found in the uploaded image' : $prof_raw); ?>"
                                            class="prof-input-field" <?php echo ($role === 'faculty') ? 'disabled style="background:#eee;" placeholder="N/A"' : ''; ?>>
                                        
                                        <?php if ($role === 'student' && !$is_unknown): ?>
                                            <button type="button"
                                                    class="prof-lookup-icon-btn"
                                                    title="View faculty profile"
                                                    data-prof="<?php echo htmlspecialchars($prof_raw); ?>">
                                                <i class="fa-solid fa-address-card"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($role === 'student'): ?>
                                            <span class="status-badge badge-<?php echo $match_status; ?>">
                                                <?php echo str_replace('_', ' ', $match_status); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <input type="text" name="courses[<?php echo $index; ?>][day]" value="<?php echo htmlspecialchars($course['day'] ?? ''); ?>">
                                    <span class="time-pair">
                                        <input type="time" name="courses[<?php echo $index; ?>][time_start]" value="<?php echo format_time_value($course['time_start'] ?? ''); ?>">
                                        <input type="time" name="courses[<?php echo $index; ?>][time_end]" value="<?php echo format_time_value($course['time_end'] ?? ''); ?>">
                                    </span>
                                    <input type="text" name="courses[<?php echo $index; ?>][room]" value="<?php echo htmlspecialchars($course['room'] ?? ''); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="schedule-edit-actions">
                            <button type="button" class="secondary-upload-btn add-row-btn">
                                <i class="fa-solid fa-plus"></i> Add Row
                            </button>
                            <a href="myschedule.php" class="discard-btn">
                                <i class="fa-solid fa-trash-can"></i> Discard Update
                            </a>
                            <button type="submit" class="primary-upload-btn">
                                <i class="fa-solid fa-floppy-disk"></i> Save Update
                            </button>
                        </div>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>

<div class="fac-overlay" id="fac-overlay">
    <div class="fac-card" id="fac-card">
        <button class="fac-close" id="fac-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="fac-loading" id="fac-loading">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <p>Looking up faculty…</p>
        </div>

        <div class="fac-body" id="fac-body">
            <div class="fac-avatar-wrap">
                <img src="../media/images.jpg" alt="Faculty photo" id="fac-avatar-img">
            </div>

            <p class="fac-name" id="fac-name"></p>

            <hr class="fac-divider">

            <div class="fac-info" id="fac-info">
                <div class="fac-row">
                    <i class="fa-solid fa-envelope"></i>
                    <a id="fac-email" href="#">—</a>
                </div>
                <div class="fac-row" id="fac-fb-row" >
                    <i class="fa-brands fa-facebook"></i>
                    <a id="fac-fb">View Facebook profile</a>
                </div>
                <div class="fac-row" id="fac-dept-row">
                    <i class="fa-solid fa-building"></i>
                    <span id="fac-dept-text"></span>
                </div>
            </div>

            <p id="fac-notfound" hidden style="text-align:center; color: #888; margin: 1rem 0;">
                Faculty profile not found.
            </p>
        </div>

        <div class="fac-actions">
            <button type="button" onclick="closeFacCard()">Close</button>
        </div>
    </div>
</div>

<script>
// --- Accordion ---
document.querySelectorAll(".schedule-upload-summary").forEach(button => {
    button.addEventListener("click", () => {
        button.closest(".collapsible-container").classList.toggle("active-dropdown");
    });
});

// --- Add Row ---
document.querySelectorAll(".add-row-btn").forEach(button => {
    button.addEventListener("click", () => {
        const form  = button.closest("form");
        const body  = form.querySelector(".grid-table-body");
        const index = body.querySelectorAll(".grid-table-row").length;
        const row   = document.createElement("div");
        const isStudent = <?php echo json_encode($role === 'student'); ?>;
        
        row.className = "grid-table-row editable-grid-row";
        row.innerHTML = `
            <input type="text" name="courses[${index}][schedule_code]" placeholder="Sched code">
            <input type="text" name="courses[${index}][course_code]" placeholder="Course code">
            ${isStudent ? `<input type="text" name="courses[${index}][course_description]" placeholder="Description">` : ''}
            <div class="prof-column-input-wrapper">
                <input type="text" name="courses[${index}][prof_name]" value="${isStudent ? 'Prof: not found in the uploaded image' : ''}" class="prof-input-field" ${!isStudent ? 'disabled style="background:#eee;" placeholder="N/A"' : ''}>
            </div>
            <input type="text" name="courses[${index}][day]" placeholder="Day">
            <span class="time-pair">
                <input type="time" name="courses[${index}][time_start]">
                <input type="time" name="courses[${index}][time_end]">
            </span>
            <input type="text" name="courses[${index}][room]" placeholder="Room">
        `;
        body.appendChild(row);
    });
});

// --- Faculty Pop-up Card ---
const facOverlay   = document.getElementById('fac-overlay');
const facClose     = document.getElementById('fac-close');
const facLoading   = document.getElementById('fac-loading');
const facBody      = document.getElementById('fac-body');
const facName      = document.getElementById('fac-name');
const facDeptText  = document.getElementById('fac-dept-text');
const facInfo      = document.getElementById('fac-info');
const facEmail     = document.getElementById('fac-email');
const facFb        = document.getElementById('fac-fb');
const facFbRow     = document.getElementById('fac-fb-row');
const facAvatarImg = document.getElementById('fac-avatar-img');
const facNotFound  = document.getElementById('fac-notfound');

function closeFacCard() {
    facOverlay.classList.remove('open');
    document.body.style.overflow = '';
}

function resetFacCard() {
    facLoading.hidden  = false;
    facBody.hidden     = true;
    facInfo.hidden     = true;
    facFbRow.hidden    = true;
    facNotFound.hidden = true;
    facFb.href              = '#';
    facName.textContent     = '';
    facDeptText.textContent = '';
    facEmail.textContent = '—';
    facEmail.href = '#';
    facAvatarImg.src = '../media/images.jpg';
    facEmail.closest('.fac-row').hidden = true;
    facDeptText.closest('.fac-row').hidden = true;
}

function openFacCard(name) {
    resetFacCard();
    facOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch(`../php/get_faculty_info.php?name=${encodeURIComponent(name)}`)
        .then(r => r.json())
        .then(data => {
            facLoading.hidden = true;
            facBody.hidden = false;

            if (data.error) {
                facName.textContent = '';
                facNotFound.hidden = false;
                facInfo.hidden = true;
                facFbRow.hidden = true;
                facAvatarImg.src = '../media/images.jpg';
                return;
            }

            facInfo.hidden = false;
            facName.textContent = data.fullname;

            if (data.profile_picture) {
                facAvatarImg.src = `../uploads/${data.profile_picture}`;
            }

            const facEmailRow = facEmail.closest('.fac-row');
            if (data.email) {
                facEmail.href = `mailto:${data.email}`;
                facEmail.textContent = data.email;
                facEmailRow.hidden = false;
            } else {
                facEmailRow.hidden = true;
            }

            if (data.fb_link && data.fb_link.trim() !== '') {
                facFb.href = data.fb_link;
                facFb.textContent = data.fb_link;
                facFbRow.hidden = false;
            } else {
                facFbRow.hidden = true;
            }

            const facDeptRow = facDeptText.closest('.fac-row');
            if (data.department && data.department.trim() !== '') {
                facDeptText.textContent = data.department;
                facDeptRow.hidden = false;
            } else {
                facDeptRow.hidden = true;
            }
        })
        .catch(() => {
            facLoading.hidden = true;
            facBody.hidden = false;
            facName.textContent = '';
            facNotFound.hidden = false;
            facInfo.hidden = true;
            facAvatarImg.src = '../media/images.jpg';
        });
}

document.addEventListener('click', e => {
    const btn = e.target.closest('.prof-lookup-icon-btn');
    if (btn) {
        e.preventDefault();
        openFacCard(btn.dataset.prof);
    }
});

facClose.addEventListener('click', closeFacCard);
facOverlay.addEventListener('click', e => {
    if (e.target === facOverlay) {
        closeFacCard();
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeFacCard();
    }
});
</script>
</body>
</html>