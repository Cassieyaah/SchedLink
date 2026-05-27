<?php
ob_start(); // Buffer output to allow ob_clean() safely in AJAX handlers
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id']; // Cast to int for safety

/* update profile ajax*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fullname'])) {

    ob_clean();
    header('Content-Type: application/json');

    try {

        $fullname       = trim($_POST['fullname']);
        $email          = trim($_POST['email']);
        $student_number = trim($_POST['student_number']);
        $program        = trim($_POST['program']);

        /* users table */
        $sql1 = "UPDATE users SET fullname = ?, email = ? WHERE user_id = ?";
        $stmt1 = mysqli_prepare($conn, $sql1);
        mysqli_stmt_bind_param($stmt1, "ssi", $fullname, $email, $user_id);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        /* check student */
        $check_sql  = "SELECT user_id FROM students WHERE user_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $exists = mysqli_num_rows($check_result) > 0;
        mysqli_stmt_close($check_stmt);

        if ($exists) {
            $sql2 = "UPDATE students SET student_number = ?, program = ? WHERE user_id = ?";
        } else {
            $sql2 = "INSERT INTO students (student_number, program, user_id) VALUES (?, ?, ?)";
        }

        $stmt2 = mysqli_prepare($conn, $sql2);
        mysqli_stmt_bind_param($stmt2, "ssi", $student_number, $program, $user_id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        echo json_encode(["status" => "success"]);

    } catch (Exception $e) {
        echo json_encode([
            "status"  => "error",
            "message" => $e->getMessage()
        ]);
    }

    exit();
}

/* upload profile pic*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {

    ob_clean();
    header('Content-Type: application/json');

    try {

        if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload failed. Error code: " . $_FILES['profile_picture']['error']);
        }

        $allowed = ["jpg", "jpeg", "png", "gif"];
        $ext     = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new Exception("Invalid file type. Allowed: jpg, jpeg, png, gif.");
        }

        // Limit file size to 5 MB
        if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
            throw new Exception("File too large. Maximum size is 5 MB.");
        }

        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true); // 0755 is safer than 0777
        }

        $filename = time() . "_" . uniqid() . "." . $ext;
        $target   = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
            throw new Exception("Failed to save file.");
        }

        // Delete old profile picture if it exists (to avoid orphaned files)
        $old_sql  = "SELECT profile_picture FROM users WHERE user_id = ?";
        $old_stmt = mysqli_prepare($conn, $old_sql);
        mysqli_stmt_bind_param($old_stmt, "i", $user_id);
        mysqli_stmt_execute($old_stmt);
        $old_result = mysqli_stmt_get_result($old_stmt);
        $old_data   = mysqli_fetch_assoc($old_result);
        mysqli_stmt_close($old_stmt);

        if (!empty($old_data['profile_picture'])) {
            $old_file = $upload_dir . $old_data['profile_picture'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        $sql  = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $filename, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "success",
            "file"   => $filename
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "status"  => "error",
            "message" => $e->getMessage()
        ]);
    }

    exit();
}

/* user data*/
$query = "
SELECT 
    users.fullname,
    users.email,
    users.profile_picture,
    students.student_number,
    students.program
FROM users
LEFT JOIN students ON users.user_id = students.user_id
WHERE users.user_id = ?
";

$stmt   = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Fallback if no user data found
if (!$data) {
    session_destroy();
    header("Location: ../php/login.php");
    exit();
}

/* profile image resolution */
$default_image   = "../media/images.jpg";
$profile_picture = $default_image;

$stored_picture = trim($data['profile_picture'] ?? '');

if ($stored_picture !== '') {
    $uploaded_path = "../uploads/" . $stored_picture;
    if (file_exists($uploaded_path)) {
        $profile_picture = $uploaded_path;
    }
    // If uploaded file no longer exists on disk, fall back to default
}

// Verify the default image itself exists; if not, use a reliable inline fallback
if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($data['fullname']) . "&size=200&background=4a90d9&color=fff";
}

// Helper: safely escape for HTML output
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="../fonts/css/all.min.css">
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <div class="profile">
            <img src="<?php echo e($profile_picture); ?>" alt="Profile picture of <?php echo e($data['fullname']); ?>">
            <h3><?php echo e($data['fullname']); ?></h3>
            <p>Student Account</p>
        </div>

        <div class="section-title">GENERAL</div>

        <div class="nav">

            <a href="studentdashboard.php">
                <i class="fa-solid fa-chart-line"></i>
                Dashboard
            </a>

            <a href="#">
                <i class="fa-regular fa-calendar"></i>
                My Schedule
            </a>

            <a href="upload_schedule.php">
                <i class="fa-solid fa-upload"></i>
                Upload Schedule
            </a>

            <a class="active" href="profile.php">
                <i class="fa-solid fa-user"></i>
                Profile
            </a>

            <a href="logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

        <div class="divider"></div>

    </div>

    <div class="sidebar-footer">
        <img src="../media/cvsulogo.png" alt="CvSU Logo">
        <p>Cavite State University</p>
    </div>

</div>

<!-- MAIN -->
<div class="main">

    <div class="profile-card">

        <div class="profile-details">

            <h1 class="title">Profile</h1>

            <div class="profile-list">

                <div class="list-item">
                    <span class="label">Full Name</span>
                    <input type="text" value="<?php echo e($data['fullname']); ?>" readonly aria-label="Full Name">
                </div>

                <div class="list-item">
                    <span class="label">Student Number</span>
                    <input type="text" value="<?php echo e($data['student_number'] ?? 'Not Assigned'); ?>" readonly aria-label="Student Number">
                </div>

                <div class="list-item">
                    <span class="label">Program</span>
                    <input type="text" value="<?php echo e($data['program'] ?? 'Not Assigned'); ?>" readonly aria-label="Program">
                </div>

                <div class="list-item">
                    <span class="label">Email</span>
                    <input type="text" value="<?php echo e($data['email']); ?>" readonly aria-label="Email">
                </div>

            </div>

            <button class="edit-btn" onclick="openModal()">Edit Profile</button>

        </div>

        <!-- IMAGE SIDE -->
        <div class="profile-aside">

            <div class="image-container">
                <img src="<?php echo e($profile_picture); ?>" class="profile-image" alt="Profile">
                <div class="profile-role">STUDENT</div>
            </div>

            <!-- No <form> wrapping needed; upload is triggered by JS -->
            <label class="change-profile-btn" title="Change profile picture">
                +
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" hidden>
            </label>

        </div>

    </div>

</div>

<!-- edit modal -->
<div id="editModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

    <div class="modal-content">

        <span class="close" onclick="closeModal()" aria-label="Close">&times;</span>

        <h2 id="modalTitle">Edit Profile</h2>

        <div id="profileForm">

            <label for="edit_fullname">Full Name</label>
            <input type="text" id="edit_fullname" name="fullname" value="<?php echo e($data['fullname']); ?>">

            <label for="edit_email">Email</label>
            <input type="email" id="edit_email" name="email" value="<?php echo e($data['email']); ?>">

            <label for="edit_student_number">Student Number</label>
            <input type="text" id="edit_student_number" name="student_number" value="<?php echo e($data['student_number'] ?? ''); ?>">

            <label for="edit_program">Program</label>
            <input type="text" id="edit_program" name="program" value="<?php echo e($data['program'] ?? ''); ?>">

            <button type="button" class="save-btn" onclick="saveProfile()">Save</button>

        </div>

    </div>

</div>

<script>
function openModal() {
    document.getElementById("editModal").classList.add("show");
}

function closeModal() {
    document.getElementById("editModal").classList.remove("show");
}

function saveProfile() {
    const fields = ["fullname", "email", "student_number", "program"];
    const data   = new FormData();

    fields.forEach(name => {
        const el = document.querySelector(`#profileForm [name="${name}"]`);
        if (el) data.append(name, el.value.trim());
    });

    fetch("profile.php", {
        method: "POST",
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === "success") {
            location.reload();
        } else {
            alert(res.message || "An error occurred. Please try again.");
        }
    })
    .catch(() => alert("Network error. Please check your connection."));
}

/* PROFILE PICTURE UPLOAD */
document.getElementById("profile_picture").addEventListener("change", function () {

    const file = this.files[0];
    if (!file) return;

    // Client-side size check (5 MB)
    if (file.size > 5 * 1024 * 1024) {
        alert("File is too large. Maximum size is 5 MB.");
        this.value = "";
        return;
    }

    const formData = new FormData();
    formData.append("profile_picture", file);

    fetch("profile.php", {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === "success") {
            location.reload();
        } else {
            alert(res.message || "Upload failed.");
        }
    })
    .catch(() => alert("Network error. Please check your connection."));
});

/* Close modal when clicking outside */
window.addEventListener("click", function (e) {
    const modal = document.getElementById("editModal");
    if (e.target === modal) {
        closeModal();
    }
});

/* Close modal with Escape key */
window.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
        closeModal();
    }
});
</script>

</body>
</html>