<?php
ob_start();
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* update profile ajax */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_number'])) {

    ob_clean();
    header('Content-Type: application/json');

    try {

        $student_number = trim($_POST['student_number']) ?: null;
        $program        = trim($_POST['program']) ?: null;

        /* check student */
        $check_stmt = mysqli_prepare($conn, "SELECT user_id FROM students WHERE user_id = ?");
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $exists = mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0;
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
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

    exit();
}

/* upload profile pic */
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

        if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
            throw new Exception("File too large. Maximum size is 5 MB.");
        }

        $upload_dir = "../uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $filename = time() . "_" . uniqid() . "." . $ext;
        $target   = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
            throw new Exception("Failed to save file.");
        }

        $old_stmt = mysqli_prepare($conn, "SELECT profile_picture FROM users WHERE user_id = ?");
        mysqli_stmt_bind_param($old_stmt, "i", $user_id);
        mysqli_stmt_execute($old_stmt);
        $old_data = mysqli_fetch_assoc(mysqli_stmt_get_result($old_stmt));
        mysqli_stmt_close($old_stmt);

        if (!empty($old_data['profile_picture'])) {
            $old_file = $upload_dir . $old_data['profile_picture'];
            if (file_exists($old_file)) unlink($old_file);
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET profile_picture = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $filename, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(["status" => "success", "file" => $filename]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

    exit();
}

/* user data */
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

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$data) {
    session_destroy();
    header("Location: ../php/login.php");
    exit();
}

/* profile image resolution */
$default_image   = "../media/images.jpg";
$profile_picture = $default_image;
$stored_picture  = trim($data['profile_picture'] ?? '');

if ($stored_picture !== '') {
    $uploaded_path = "../uploads/" . $stored_picture;
    if (file_exists($uploaded_path)) $profile_picture = $uploaded_path;
}

if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($data['fullname']) . "&size=200&background=4a90d9&color=fff";
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function na(mixed $value): string {
    $v = trim((string)($value ?? ''));
    return $v !== '' ? $v : 'N/A';
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
            <a href="studentdashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="myschedule.php"><i class="fa-regular fa-calendar"></i> My Schedule</a>
            <a href="studentdashboard.php#upload"><i class="fa-solid fa-upload"></i> Upload Schedule</a>
            <a class="active" href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
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
                    <input type="text" value="<?php echo e(na($data['student_number'])); ?>" readonly aria-label="Student Number">
                </div>
                <div class="list-item">
                    <span class="label">Program</span>
                    <input type="text" value="<?php echo e(na($data['program'])); ?>" readonly aria-label="Program">
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
            <label class="change-profile-btn" title="Change profile picture">
                +
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" hidden>
            </label>
        </div>

    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-content">

        <span class="close" onclick="closeModal()" aria-label="Close">&times;</span>
        <h2 id="modalTitle">Edit Profile</h2>

        <div id="profileForm">

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
    const data = new FormData();
    data.append("student_number", document.getElementById("edit_student_number").value.trim());
    data.append("program",        document.getElementById("edit_program").value.trim());

    fetch("profile.php", { method: "POST", body: data })
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

    if (file.size > 5 * 1024 * 1024) {
        alert("File is too large. Maximum size is 5 MB.");
        this.value = "";
        return;
    }

    const formData = new FormData();
    formData.append("profile_picture", file);

    fetch("profile.php", { method: "POST", body: formData })
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

window.addEventListener("click", function (e) {
    if (e.target === document.getElementById("editModal")) closeModal();
});

window.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeModal();
});
</script>

</body>
</html>