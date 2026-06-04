<?php
session_start();
include '../includes/db.php';
include '../includes/matched_schedules.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$settings = $conn->query("SELECT semester, school_year FROM site_settings WHERE id = 1")->fetch_assoc();
$active_semester = $settings['semester'] ?? '1st Semester';
$active_school_year = $settings['school_year'] ?? '2025-2026';

$stmt = $conn->prepare("
    SELECT users.*, students.student_id, faculties.faculty_id
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
    header("Location: ../logIn.php");
    exit();
}

$role = strtolower(trim($user['role']));
if (!in_array($role, ['student', 'faculty'], true)) {
    header("Location: ../admindashboard.php");
    exit();
}

$dashboard_page = $role === 'faculty' ? '../facultydashboard.php' : 'studentdashboard.php';
$profile_page   = $role === 'faculty' ? '../facultyprofile.php' : 'Profile.php';
$profile_id     = $role === 'faculty' ? (int) ($user['faculty_id'] ?? 0) : (int) ($user['student_id'] ?? 0);

function format_time_value(string $value): string {
    return ($value === '' || $value === '00:00:00') ? '' : date('H:i', strtotime($value));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_upload_id'])) {
        $delete_id = (int) $_POST['delete_upload_id'];
        $del = $conn->prepare("DELETE FROM schedule_uploads WHERE upload_id = ? AND user_id = ?");
        $del->bind_param("ii", $delete_id, $user_id);
        $_SESSION['schedule_success'] = $del->execute() ? "Schedule upload removed successfully." : "Error eliminating schedule entry.";
        $del->close();
        header("Location: myschedule.php");
        exit();
    }

    if (isset($_POST['upload_id'], $_POST['courses'])) {
        $upload_id = (int) $_POST['upload_id'];
        $conn->begin_transaction();
        try {
            $schedule_name = trim($_POST['schedule_name'] ?? '') ?: 'Uploaded schedule';

            $name_stmt = $conn->prepare("UPDATE schedule_uploads SET original_filename = ? WHERE upload_id = ? AND user_id = ? AND role = ?");
            $name_stmt->bind_param("siis", $schedule_name, $upload_id, $user_id, $role);
            $name_stmt->execute();
            $name_stmt->close();

            if ($role === 'student') {
                $conn->query("DELETE FROM student_schedules WHERE upload_id = $upload_id AND student_id = $profile_id");
                $insert_stmt = $conn->prepare("INSERT INTO student_schedules (student_id, upload_id, schedule_code, course_code, course_description, prof_name, time_start, time_end, day, room, semester, school_year, status) SELECT ?, upload_id, ?, ?, ?, ?, ?, ?, ?, ?, semester, school_year, 'active' FROM schedule_uploads WHERE upload_id = ?");
            } else {
                $conn->query("DELETE FROM faculty_schedules WHERE upload_id = $upload_id AND faculty_id = $profile_id");
                $insert_stmt = $conn->prepare("INSERT INTO faculty_schedules (faculty_id, upload_id, schedule_code, course_code, day, time_start, time_end, room, semester, school_year, status) SELECT ?, upload_id, ?, ?, ?, ?, ?, ?, semester, school_year, 'active' FROM schedule_uploads WHERE upload_id = ?");
            }

            foreach ($_POST['courses'] as $course) {
                $sched_code = trim($course['schedule_code'] ?? '');
                $crs_code   = trim($course['course_code'] ?? '');
                if ($sched_code === '' && $crs_code === '') continue;

                $t_start = !empty($course['time_start']) ? date('H:i:s', strtotime($course['time_start'])) : '00:00:00';
                $t_end   = !empty($course['time_end'])   ? date('H:i:s', strtotime($course['time_end']))   : '00:00:00';

                if ($role === 'student') {
                    $desc = trim($course['course_description'] ?? '');
                    $prof = trim($course['prof_name'] ?? '');
                    $insert_stmt->bind_param("issssssssi", $profile_id, $sched_code, $crs_code, $desc, $prof, $t_start, $t_end, $course['day'], $course['room'], $upload_id);
                } else {
                    $insert_stmt->bind_param("isssssssi", $profile_id, $sched_code, $crs_code, $course['day'], $t_start, $t_end, $course['room'], $upload_id);
                }
                $insert_stmt->execute();
            }
            $insert_stmt->close();

            $role === 'student' ? match_student_upload_schedules($conn, $profile_id, $upload_id) : refresh_matches_for_faculty_upload($conn, $profile_id, $upload_id);
            $conn->commit();
            synchronize_schedule_matches($conn);

            $_SESSION['schedule_success'] = "Schedule upload updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['schedule_error'] = "Could not update schedule: " . $e->getMessage();
        }
        header("Location: myschedule.php");
        exit();
    }
}

$upload_success = $_SESSION['schedule_success'] ?? '';
$upload_error   = $_SESSION['schedule_error']   ?? '';
unset($_SESSION['schedule_success'], $_SESSION['schedule_error']);

$uploads = [];
$upload_stmt = $conn->prepare("SELECT upload_id, original_filename, uploaded_at FROM schedule_uploads WHERE user_id = ? AND role = ? ORDER BY uploaded_at DESC, upload_id DESC");
$upload_stmt->bind_param("is", $user_id, $role);
$upload_stmt->execute();
$res = $upload_stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $row['courses'] = [];
    $uploads[(int) $row['upload_id']] = $row;
}
$upload_stmt->close();

if (!empty($uploads)) {
    $ids = implode(',', array_keys($uploads));
    $course_query = ($role === 'student') ? 
        $conn->query("
            SELECT ss.*, ms.match_status, f.faculty_id, COALESCE(u.fullname, ss.prof_name) AS matched_prof_name, CASE WHEN u.fullname IS NOT NULL THEN 1 ELSE 0 END AS is_matched
            FROM student_schedules ss
            LEFT JOIN matched_schedules ms ON ss.student_schedule_id = ms.student_schedule_id
            LEFT JOIN faculty_schedules fs ON ms.professor_schedule_id = fs.professor_schedule_id
            LEFT JOIN faculties f ON fs.faculty_id = f.faculty_id
            LEFT JOIN users u ON f.user_id = u.user_id
            WHERE ss.student_id = $profile_id AND ss.upload_id IN ($ids)
            ORDER BY ss.upload_id DESC, ss.student_schedule_id ASC
        ") :
        $conn->query("SELECT * FROM faculty_schedules WHERE faculty_id = $profile_id AND upload_id IN ($ids) ORDER BY upload_id DESC, professor_schedule_id ASC");

    while ($course = $course_query->fetch_assoc()) {
        $uploads[(int) $course['upload_id']]['courses'][] = $course;
    }

    $day_order = ['M'=>1,'MON'=>1,'MONDAY'=>1,'T'=>2,'TUE'=>2,'TUESDAY'=>2,'W'=>3,'WED'=>3,'WEDNESDAY'=>3,'TH'=>4,'THU'=>4,'THURSDAY'=>4,'F'=>5,'FRI'=>5,'FRIDAY'=>5,'S'=>6,'SAT'=>6,'SATURDAY'=>6];
    foreach ($uploads as $uid => $data) {
        usort($uploads[$uid]['courses'], function ($a, $b) use ($day_order) {
            $orderA = $day_order[strtoupper(trim($a['day'] ?? ''))] ?? 99;
            $orderB = $day_order[strtoupper(trim($b['day'] ?? ''))] ?? 99;
            return ($orderA === $orderB) ? strcmp($a['time_start'] ?? '00:00:00', $b['time_start'] ?? '00:00:00') : $orderA <=> $orderB;
        });
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
        .prof-column-badge-wrapper { display: inline-flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
        .status-badge-capsule { display: inline-flex; align-items: center; border-radius: 30px; padding: 0 4px 0 12px; min-width: 115px; height: 30px; box-sizing: border-box !important; justify-content: space-between; border: 1px solid transparent; }
        .status-badge-capsule .status-text { font-size: 0.72rem !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; line-height: 1 !important; user-select: none; }
        .capsule-matched { background-color: rgba(39, 174, 96, 0.08) !important; border-color: rgba(39, 174, 96, 0.2) !important; }
        .capsule-matched .status-text { color: #27ae60 !important; }
        .capsule-no_match, .capsule-no-match { background-color: rgba(108, 117, 125, 0.08) !important; border-color: rgba(108, 117, 125, 0.2) !important; padding-right: 12px; justify-content: center; }
        .capsule-no_match .status-text, .capsule-no-match .status-text { color: #6c757d !important; }
        .prof-lookup-icon-btn { background: #27ae60 !important; border: none !important; color: #ffffff !important; width: 22px; height: 22px; border-radius: 50% !important; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease; font-size: 0.75rem !important; margin-left: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 0 !important; }
        .prof-lookup-icon-btn:hover { background: #219653 !important; transform: scale(1.1); box-shadow: 0 2px 5px rgba(39, 174, 96, 0.3); }

        /* Profile Modal Overlay Layout Styling */
        .faculty-info-modal { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .faculty-info-backdrop { position: absolute; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); top:0; left:0; }
        .faculty-info-dialog { position: relative; background: #ffffff; width: 90%; max-width: 380px; padding: 28px 24px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); display: flex; flex-direction: column; align-items: center; text-align: center; border: 1px solid #e2e8f0; z-index: 10000; box-sizing: border-box; }
        .faculty-info-close { position: absolute; top: 14px; right: 14px; background: none; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer; padding: 4px; line-height: 1; }
        .faculty-info-close:hover { color: #475569; }
        
        .faculty-card-avatar { width: 96px !important; height: 96px !important; border-radius: 50% !important; overflow: hidden !important; background: #f1f5f9 !important; border: 3px solid #27ae60 !important; display: flex !important; align-items: center !important; justify-content: center !important; font-size: 2.2rem !important; font-weight: 700 !important; color: #27ae60 !important; margin: 0 auto 14px auto !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); box-sizing: border-box; }
        .faculty-card-avatar img { width: 100% !important; height: 100% !important; object-fit: cover !important; border-radius: 50% !important; }
        
        .faculty-card-name { font-size: 1.3rem; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; line-height: 1.3; }
        .faculty-card-dept { font-size: 0.72rem; font-weight: 700; color: #27ae60; text-transform: uppercase; letter-spacing: 0.06em; background: rgba(39,174,96,0.08); padding: 4px 14px; border-radius: 20px; margin: 0 0 24px 0; display: inline-block; }
        .faculty-detail-list { width: 100%; display: flex; flex-direction: column; gap: 12px; margin: 0; padding: 0; }
        .faculty-detail-row { display: flex; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 12px; text-align: left; border: 1px solid #edf2f7; box-sizing: border-box; width: 100%; }
        .faculty-detail-icon { font-size: 1.1rem; color: #64748b; margin-right: 14px; width: 20px; text-align: center; flex-shrink: 0; }
        .faculty-detail-row div { display: flex; flex-direction: column; }
        .faculty-detail-row dt { font-size: 0.68rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.03em; margin: 0; line-height: 1; }
        .faculty-detail-row dd { font-size: 0.9rem; color: #334155; font-weight: 500; margin: 4px 0 0 0; word-break: break-all; line-height: 1.2; }
        .faculty-detail-row dd a { color: #2563eb; text-decoration: none; font-weight: 600; display: inline-block; }
        .faculty-detail-row dd a:hover { text-decoration: underline; }
        #fac-notfound { color: #ef4444; font-weight: 600; font-size: 0.9rem; margin: 10px 0; display: none !important; align-items: center; gap: 6px; }
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
    <div class="sidebar-footer"><img src="../media/cvsulogo.png" alt="CvSU Logo"><p>Cavite State University</p></div>
</div>

<div class="header">
    <h2>My Schedule</h2>
    <div class="user-box">Welcome, <?php echo htmlspecialchars($user['fullname']); ?></div>
</div>

<main class="main">
    <?php if ($upload_success): ?><div class="dashboard-alert success-alert"><?php echo htmlspecialchars($upload_success); ?></div><?php endif; ?>
    <?php if ($upload_error): ?><div class="dashboard-alert error-alert"><?php echo htmlspecialchars($upload_error); ?></div><?php endif; ?>

    <section class="myschedule-page">
        <div class="myschedule-title">
            <div>
                <h3>Uploaded Schedules</h3>
                <p>Most recent uploads appear first. Open an upload to view or edit the extracted rows.</p>
            </div>
            <a class="primary-upload-btn" href="<?php echo $dashboard_page; ?>#upload"><i class="fa-solid fa-upload"></i> Upload</a>
        </div>

        <?php if (!$uploads): ?>
            <div class="empty-schedule-state"><i class="fa-regular fa-calendar-xmark"></i><p>No uploaded schedules yet.</p></div>
        <?php endif; ?>

        <?php foreach ($uploads as $upload): ?>
            <article class="collapsible-container schedule-upload-group">
                <button type="button" class="schedule-upload-summary">
                    <span>
                        <strong><?php echo htmlspecialchars($upload['original_filename'] ?: 'Uploaded schedule'); ?></strong>
                        <small><?php echo date('F j, Y g:i A', strtotime($upload['uploaded_at'])); ?> &middot; <?php echo htmlspecialchars($active_semester); ?> &middot; <?php echo htmlspecialchars($active_school_year); ?></small>
                    </span>
                    <i class="fa-solid fa-chevron-down accordion-arrow"></i>
                </button>

                <div class="accordion-content">
                    <form method="POST" class="schedule-edit-form">
                        <input type="hidden" name="upload_id" value="<?php echo (int) $upload['upload_id']; ?>">
                        <label class="schedule-name-field">
                            <span>Schedule Name</span>
                            <input type="text" name="schedule_name" value="<?php echo htmlspecialchars($upload['original_filename'] ?: 'Uploaded schedule'); ?>" placeholder="Schedule name" required>
                        </label>

                        <div class="grid-table-header">
                            <span>Sched Code</span><span>Course Code</span>
                            <?php if ($role === 'student'): ?><span>Description</span><?php endif; ?>
                            <span style="text-align: center;">Status</span><span>Day</span><span>Time</span><span>Room</span>
                        </div>

                        <div class="grid-table-body">
                            <?php foreach ($upload['courses'] as $index => $course):
                                $prof_raw = $course['matched_prof_name'] ?? $course['prof_name'] ?? '';
                                $matched_fac_id = $course['faculty_id'] ?? 0;
                                $is_unknown = ($prof_raw === '' || stripos($prof_raw, 'not found') !== false);
                                $match_status = ($role === 'student') ? ($course['match_status'] ?? 'no_match') : '';
                                if ($match_status === 'active' || $match_status === 'matched') $match_status = 'matched';
                                if ($is_unknown && $role === 'student') $match_status = 'no_match';
                            ?>
                                <div class="grid-table-row editable-grid-row">
                                    <input type="text" name="courses[<?php echo $index; ?>][schedule_code]" value="<?php echo htmlspecialchars($course['schedule_code'] ?? ''); ?>">
                                    <input type="text" name="courses[<?php echo $index; ?>][course_code]"   value="<?php echo htmlspecialchars($course['course_code'] ?? ''); ?>">
                                    <?php if ($role === 'student'): ?>
                                        <input type="text" name="courses[<?php echo $index; ?>][course_description]" value="<?php echo htmlspecialchars($course['course_description'] ?? ''); ?>">
                                    <?php endif; ?>

                                    <div class="prof-column-badge-wrapper">
                                        <input type="hidden" name="courses[<?php echo $index; ?>][prof_name]" value="<?php echo htmlspecialchars($prof_raw); ?>">
                                        <div class="status-badge-capsule capsule-<?php echo $role === 'student' ? htmlspecialchars($match_status) : 'no_match'; ?>">
                                            <span class="status-text"><?php echo $role === 'student' ? ($match_status === 'matched' ? 'Matched' : 'No Match') : 'N/A'; ?></span>
                                            <?php if ($role === 'student' && !$is_unknown): ?>
                                                <button type="button" class="prof-lookup-icon-btn" title="View faculty profile" 
                                                        data-fac-id="<?php echo (int) $matched_fac_id; ?>" 
                                                        data-prof="<?php echo htmlspecialchars($prof_raw); ?>">
                                                    <i class="fa-solid fa-id-card"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <input type="text" name="courses[<?php echo $index; ?>][day]" value="<?php echo htmlspecialchars($course['day'] ?? ''); ?>">
                                    <span class="time-pair">
                                        <input type="time" name="courses[<?php echo $index; ?>][time_start]" value="<?php echo format_time_value($course['time_start'] ?? ''); ?>">
                                        <input type="time" name="courses[<?php echo $index; ?>][time_end]"   value="<?php echo format_time_value($course['time_end']   ?? ''); ?>">
                                    </span>
                                    <input type="text" name="courses[<?php echo $index; ?>][room]" value="<?php echo htmlspecialchars($course['room'] ?? ''); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="schedule-edit-actions">
                            <button type="button" class="secondary-upload-btn add-row-btn"><i class="fa-solid fa-plus"></i> Add Row</button>
                            <button type="submit" name="delete_upload_id" value="<?php echo (int) $upload['upload_id']; ?>" class="discard-btn delete-upload-btn" formnovalidate><i class="fa-solid fa-trash-can"></i> Delete Upload</button>
                            <button type="submit" class="primary-upload-btn"><i class="fa-solid fa-floppy-disk"></i> Save Update</button>
                        </div>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>

<div class="faculty-info-modal" id="facultyInfoModal" style="display: none;" aria-hidden="true">
    <div class="faculty-info-backdrop" data-faculty-info-close></div>
    <div class="faculty-info-dialog" role="dialog" aria-modal="true" aria-labelledby="facultyInfoTitle">
        <button type="button" class="faculty-info-close" data-faculty-info-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        
        <div class="faculty-card-avatar" id="facultyAvatar">
            <img id="facultyAvatarImg" src="" alt="" style="display:none;">
            <span id="facultyAvatarInitials">…</span>
        </div>
        
        <h3 class="faculty-card-name" id="facultyInfoTitle">Faculty Information</h3>
        <p class="faculty-card-dept" id="facultyInfoDept">—</p>
        
        <div id="facultyDetailsContainer" style="width: 100%; display: block;">
            <dl class="faculty-detail-list">
                <div class="faculty-detail-row">
                    <i class="fa-solid fa-envelope faculty-detail-icon"></i>
                    <div>
                        <dt>Email Address</dt>
                        <dd id="facultyInfoEmail">—</dd>
                    </div>
                </div>
                <div class="faculty-detail-row">
                    <i class="fa-brands fa-facebook faculty-detail-icon"></i>
                    <div>
                        <dt>Facebook Profile</dt>
                        <dd><a id="facultyInfoFacebook" href="#" target="_blank" rel="noopener">View Profile</a></dd>
                    </div>
                </div>
            </dl>
        </div>
        <p id="fac-notfound" style="display: none !important;"><i class="fa-regular fa-circle-question"></i> Faculty profile not found.</p>
    </div>
</div>

<script>
document.querySelectorAll(".schedule-upload-summary").forEach(btn => btn.addEventListener("click", () => btn.closest(".collapsible-container").classList.toggle("active-dropdown")));

document.querySelectorAll(".add-row-btn").forEach(button => {
    button.addEventListener("click", () => {
        const body = button.closest("form").querySelector(".grid-table-body");
        const index = body.querySelectorAll(".grid-table-row").length;
        const isStudent = <?php echo json_encode($role === 'student'); ?>;
        const row = document.createElement("div");
        row.className = "grid-table-row editable-grid-row";
        row.innerHTML = `
            <input type="text" name="courses[${index}][schedule_code]" placeholder="Sched code"><input type="text" name="courses[${index}][course_code]" placeholder="Course code">
            ${isStudent ? `<input type="text" name="courses[${index}][course_description]" placeholder="Description">` : ''}
            <div class="prof-column-badge-wrapper"><input type="hidden" name="courses[${index}][prof_name]" value=""><div class="status-badge-capsule capsule-no_match"><span class="status-text">${isStudent ? 'No Match' : 'N/A'}</span></div></div>
            <input type="text" name="courses[${index}][day]" placeholder="Day"><span class="time-pair"><input type="time" name="courses[${index}][time_start]"><input type="time" name="courses[${index}][time_end]"></span><input type="text" name="courses[${index}][room]" placeholder="Room">`;
        body.appendChild(row);
    });
});

function openFacultyInfoModal(facId, name) {
    const modal = document.getElementById('facultyInfoModal'), 
          nameEl = document.getElementById('facultyInfoTitle'), 
          deptEl = document.getElementById('facultyInfoDept'), 
          emailEl = document.getElementById('facultyInfoEmail'), 
          fbEl = document.getElementById('facultyInfoFacebook'), 
          avatarImg = document.getElementById('facultyAvatarImg'), 
          avatarInit = document.getElementById('facultyAvatarInitials'), 
          notFoundEl = document.getElementById('fac-notfound'), 
          detailsCont = document.getElementById('facultyDetailsContainer');
          
    nameEl.textContent = 'Loading…'; 
    deptEl.textContent = '—'; 
    emailEl.textContent = '—'; 
    fbEl.textContent = '—'; 
    fbEl.removeAttribute('href'); 
    avatarImg.style.display = 'none'; 
    avatarInit.textContent = '…'; 
    
    notFoundEl.setAttribute('style', 'display: none !important;');
    detailsCont.style.setProperty('display', 'block', 'important');
    
    modal.style.display = 'flex';
    modal.removeAttribute('aria-hidden');

    fetch(`get_faculty_info.php?id=${facId}&name=${encodeURIComponent(name)}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) { 
                nameEl.textContent = name; 
                deptEl.textContent = 'Unknown Department'; 
                avatarInit.textContent = '?'; 
                notFoundEl.setAttribute('style', 'display: flex !important;');
                detailsCont.style.setProperty('display', 'none', 'important');
                return; 
            }
            
            notFoundEl.setAttribute('style', 'display: none !important;');
            detailsCont.style.setProperty('display', 'block', 'important');
            
            const fullname = data.fullname || name;
            nameEl.textContent = fullname;
            deptEl.textContent = data.department ? data.department.toUpperCase() : 'GENERAL FACULTY';
            emailEl.textContent = data.email || 'No email provided';
            
            function renderInitials(userFullname) {
                const parts = userFullname.trim().split(/\s+/);
                avatarInit.textContent = parts.length >= 2 
                    ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase() 
                    : userFullname[0].toUpperCase();
            }

            if (data.profile_picture && data.profile_picture.trim() !== '') {
                // Resolved path strategy synced with profile.php storage logic ($upload_dir = "../uploads/")
                avatarImg.src = '../uploads/' + data.profile_picture.trim(); 
                avatarImg.alt = fullname;
                
                avatarImg.style.setProperty('display', 'block', 'important');
                avatarInit.style.setProperty('display', 'none', 'important');
                
                avatarImg.onerror = function() {
                    avatarImg.style.setProperty('display', 'none', 'important');
                    avatarInit.style.setProperty('display', 'block', 'important');
                    renderInitials(fullname);
                };
            } else {
                avatarImg.style.setProperty('display', 'none', 'important');
                avatarInit.style.setProperty('display', 'block', 'important');
                renderInitials(fullname);
            }
            
            const fbLink = data.fb_link || '';
            if (fbLink.trim() !== '' && /^https?:\/\//i.test(fbLink.trim())) {
                fbEl.href = fbLink.trim();
                fbEl.textContent = 'View Profile';
                fbEl.style.pointerEvents = 'auto';
                fbEl.style.color = '#2563eb';
            } else {
                fbEl.removeAttribute('href');
                fbEl.textContent = fbLink.trim() || 'Not available';
                fbEl.style.pointerEvents = 'none';
                fbEl.style.color = '#64748b';
            }
        })
        .catch(() => { 
            nameEl.textContent = 'Error loading profile'; 
            avatarInit.textContent = '!'; 
            notFoundEl.setAttribute('style', 'display: flex !important;');
            detailsCont.style.setProperty('display', 'none', 'important');
        });
}

function closeFacCard() { 
    const modal = document.getElementById('facultyInfoModal'); 
    modal.setAttribute('aria-hidden', 'true'); 
    modal.style.display = 'none'; 
}
document.addEventListener('click', e => { const btn = e.target.closest('.prof-lookup-icon-btn'); if (btn) { e.preventDefault(); const facId = btn.getAttribute('data-fac-id'); const name = btn.getAttribute('data-prof'); if (facId || name) openFacultyInfoModal(facId, name); } });
document.querySelectorAll('[data-faculty-info-close]').forEach(el => el.addEventListener('click', closeFacCard));
window.addEventListener('keydown', e => { if (e.key === 'Escape' && document.getElementById('facultyInfoModal').style.display !== 'none') closeFacCard(); });
</script>
</body>
</html>