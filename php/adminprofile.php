<?php
ob_start();
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/logIn.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* Verify the logged-in user is actually an admin */
$role_check = mysqli_prepare($conn, "SELECT role FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($role_check, "i", $user_id);
mysqli_stmt_execute($role_check);
$role_result = mysqli_stmt_get_result($role_check);
$role_row    = mysqli_fetch_assoc($role_result);
mysqli_stmt_close($role_check);

if (!$role_row || $role_row['role'] !== 'admin') {
    header("Location: ../php/logIn.php");
    exit();
}

/* =========================
   UPDATE PROFILE (AJAX)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fullname'])) {

    ob_clean();
    header('Content-Type: application/json');

    try {

        $fullname = trim($_POST['fullname']);
        $email    = trim($_POST['email']);

        $sql  = "UPDATE users SET fullname = ?, email = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $fullname, $email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(["status" => "success"]);

    } catch (Exception $e) {
        echo json_encode([
            "status"  => "error",
            "message" => $e->getMessage()
        ]);
    }

    exit();
}


/* =========================
   PROFILE PICTURE UPLOAD
========================= */
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
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = time() . "_" . uniqid() . "." . $ext;
        $target   = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
            throw new Exception("Failed to save file.");
        }

        /* Delete old profile picture */
        $old_stmt = mysqli_prepare($conn, "SELECT profile_picture FROM users WHERE user_id = ?");
        mysqli_stmt_bind_param($old_stmt, "i", $user_id);
        mysqli_stmt_execute($old_stmt);
        $old_data = mysqli_fetch_assoc(mysqli_stmt_get_result($old_stmt));
        mysqli_stmt_close($old_stmt);

        if (!empty($old_data['profile_picture'])) {
            $old_file = $upload_dir . $old_data['profile_picture'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        $upd_stmt = mysqli_prepare($conn, "UPDATE users SET profile_picture = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($upd_stmt, "si", $filename, $user_id);
        mysqli_stmt_execute($upd_stmt);
        mysqli_stmt_close($upd_stmt);

        echo json_encode(["status" => "success", "file" => $filename]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

    exit();
}

/* =========================
   FETCH USER DATA
========================= */
$stmt = mysqli_prepare($conn, "SELECT fullname, email, profile_picture FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$data) {
    session_destroy();
    header("Location: ../php/logIn.php");
    exit();
}

/* =========================
   PROFILE IMAGE RESOLUTION
========================= */
$default_image   = "../media/images.jpg";
$profile_picture = $default_image;

$stored_picture = trim($data['profile_picture'] ?? '');

if ($stored_picture !== '') {
    $uploaded_path = "../uploads/" . $stored_picture;
    if (file_exists($uploaded_path)) {
        $profile_picture = $uploaded_path;
    }
}

if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($data['fullname']) . "&size=200&background=4a90d9&color=fff";
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
    <title>Admin Profile</title>

    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <div class="profile">
            <img src="<?php echo e($profile_picture); ?>" alt="Profile picture of <?php echo e($data['fullname']); ?>">
            <h3><?php echo e($data['fullname']); ?></h3>
            <p>Administrator</p>
        </div>

        <div class="section-title">GENERAL</div>

        <div class="nav">

            <a href="admindashboard.php">
                <i class="fa-solid fa-chart-line"></i>
                Dashboard
            </a>

            <a class="active" href="adminprofile.php">
                <i class="fa-solid fa-user"></i>
                Profile
            </a>

            <a href="create_account.php">
                <i class="fa-solid fa-user-plus"></i>
                Create Account
            </a>

            <a href="user_list.php">
                <i class="fa-solid fa-users"></i>
                User List
            </a>

            <a href="schedule_conflicts.php">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Schedule Conflict Management
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
                    <span class="label">Email</span>
                    <input type="text" value="<?php echo e($data['email']); ?>" readonly aria-label="Email">
                </div>

                <div class="list-item">
                    <span class="label">Role</span>
                    <input type="text" value="System Administrator" readonly aria-label="Role">
                </div>

            </div>

            <button class="edit-btn" onclick="openEditModal()">Edit Profile</button>

        </div>

        <!-- IMAGE SIDE -->
        <div class="profile-aside">

            <div class="image-container">
                <img src="<?php echo e($profile_picture); ?>" class="profile-image" alt="Profile">
                <div class="profile-role">ADMIN</div>
            </div>

            <label class="change-profile-btn" title="Change profile picture">
                +
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" hidden>
            </label>

        </div>

    </div>

</div>

<!-- EDIT PROFILE MODAL -->
<div id="editModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">

    <div class="modal-content">

        <span class="close" onclick="closeEditModal()" aria-label="Close">&times;</span>

        <h2 id="editModalTitle">Edit Profile</h2>

        <div id="profileForm">

            <label for="edit_fullname">Full Name</label>
            <input type="text" id="edit_fullname" name="fullname" value="<?php echo e($data['fullname']); ?>">

            <label for="edit_email">Email</label>
            <input type="email" id="edit_email" name="email" value="<?php echo e($data['email']); ?>">

            <button type="button" class="save-btn" onclick="saveProfile()">Save</button>

        </div>

    </div>

</div>


<script>
function openEditModal() {
    document.getElementById("editModal").classList.add("show");
}
function closeEditModal() {
    document.getElementById("editModal").classList.remove("show");
}

function saveProfile() {
    const data = new FormData();
    data.append("fullname", document.getElementById("edit_fullname").value.trim());
    data.append("email",    document.getElementById("edit_email").value.trim());

    fetch("adminprofile.php", { method: "POST", body: data })
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

    fetch("adminprofile.php", { method: "POST", body: formData })
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

/* Close modal on outside click */
window.addEventListener("click", function (e) {
    if (e.target === document.getElementById("editModal")) closeEditModal();
});

/* Close modal on Escape */
window.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeEditModal();
});
</script>

</body>
</html>
