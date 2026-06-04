<?php
ob_start();
session_start();

include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Session expired. Please log in again."]);
    } else {
        header("Location: ../logIn.php");
    }
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* =========================
   VERIFY ADMIN
========================= */
$role_check = mysqli_prepare($conn, "SELECT role FROM users WHERE user_id = ?");
if (!$role_check) die("Database error.");
mysqli_stmt_bind_param($role_check, "i", $user_id);
mysqli_stmt_execute($role_check);
$role_row = mysqli_fetch_assoc(mysqli_stmt_get_result($role_check));
mysqli_stmt_close($role_check);

if (!$role_row || $role_row['role'] !== 'admin') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Unauthorized."]);
    } else {
        header("Location: ../logIn.php");
    }
    exit();
}

/* =========================
   HANDLE AJAX POSTS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---- PROFILE PICTURE UPLOAD ---- */
    if (isset($_FILES['profile_picture'])) {
        ob_clean();
        header('Content-Type: application/json');
        try {
            if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload failed. Error code: " . $_FILES['profile_picture']['error']);
            }
            $allowed = ["jpg", "jpeg", "png", "gif"];
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
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
            /* DELETE OLD FILE */
            $old_stmt = mysqli_prepare($conn, "SELECT profile_picture FROM users WHERE user_id = ?");
            mysqli_stmt_bind_param($old_stmt, "i", $user_id);
            mysqli_stmt_execute($old_stmt);
            $old_data = mysqli_fetch_assoc(mysqli_stmt_get_result($old_stmt));
            mysqli_stmt_close($old_stmt);
            if (!empty($old_data['profile_picture'])) {
                $old_file = $upload_dir . basename($old_data['profile_picture']);
                if (file_exists($old_file)) unlink($old_file);
            }
            /* UPDATE DB */
            $upd = mysqli_prepare($conn, "UPDATE users SET profile_picture = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($upd, "si", $filename, $user_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            echo json_encode(["status" => "success", "file" => $filename]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    $action = $_POST['action'] ?? '';

    /* ---- EDIT FULL NAME ---- */
    if ($action === 'edit_name') {
        ob_clean();
        header('Content-Type: application/json');
        try {
            $new_name = trim($_POST['fullname'] ?? '');
            if ($new_name === '') {
                throw new Exception("Full name cannot be empty.");
            }
            if (mb_strlen($new_name) > 100) {
                throw new Exception("Name is too long (max 100 characters).");
            }
            /* Allow letters, spaces, periods, hyphens, apostrophes */
            if (!preg_match("/^[\p{L}\s.\-']+$/u", $new_name)) {
                throw new Exception("Name contains invalid characters.");
            }
            $upd = mysqli_prepare($conn, "UPDATE users SET fullname = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($upd, "si", $new_name, $user_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            /* Refresh session name if stored there */
            $_SESSION['fullname'] = $new_name;
            echo json_encode(["status" => "success", "fullname" => $new_name]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }

    /* ---- CHANGE PASSWORD ---- */
    if ($action === 'change_password') {
        ob_clean();
        header('Content-Type: application/json');
        try {
            $current  = $_POST['current_password']  ?? '';
            $new_pw   = $_POST['new_password']       ?? '';
            $confirm  = $_POST['confirm_password']   ?? '';

            if ($current === '' || $new_pw === '' || $confirm === '') {
                throw new Exception("All fields are required.");
            }
            if ($new_pw !== $confirm) {
                throw new Exception("New passwords do not match.");
            }
            /* Minimum security rules */
            if (mb_strlen($new_pw) < 8) {
                throw new Exception("Password must be at least 8 characters.");
            }
            if (!preg_match('/[A-Z]/', $new_pw)) {
                throw new Exception("Password must include at least one uppercase letter.");
            }
            if (!preg_match('/[0-9]/', $new_pw)) {
                throw new Exception("Password must include at least one number.");
            }
            if (!preg_match('/[\W_]/', $new_pw)) {
                throw new Exception("Password must include at least one special character.");
            }

            /* Fetch stored hash */
            $fetch = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ?");
            mysqli_stmt_bind_param($fetch, "i", $user_id);
            mysqli_stmt_execute($fetch);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch));
            mysqli_stmt_close($fetch);

            if (!$row || !password_verify($current, $row['password'])) {
                throw new Exception("Current password is incorrect.");
            }
            if (password_verify($new_pw, $row['password'])) {
                throw new Exception("New password must be different from the current one.");
            }

            $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($upd, "si", $new_hash, $user_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit();
    }
}

/* =========================
   FETCH USER DATA
========================= */
$user_stmt = mysqli_prepare($conn, "SELECT fullname, email, profile_picture FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
mysqli_stmt_close($user_stmt);

if (!$data) {
    session_destroy();
    header("Location: ../logIn.php");
    exit();
}

/* =========================
   PROFILE IMAGE
========================= */
$default_image   = "../media/images.jpg";
$profile_picture = $default_image;
$stored_picture  = trim($data['profile_picture'] ?? '');

if (!empty($stored_picture)) {
    $uploaded_path = "../uploads/" . basename($stored_picture);
    if (file_exists($uploaded_path)) $profile_picture = $uploaded_path;
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
    <link rel="stylesheet" href="../fonts/css/all.min.css">
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div>
        <div class="profile">
            <img src="<?php echo e($profile_picture); ?>" alt="Profile Picture">
            <h3 id="sidebar-name"><?php echo e($data['fullname']); ?></h3>
            <p>Administrator</p>
        </div>
        <div class="section-title">GENERAL</div>
        <div class="nav">
            <a href="admindashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a class="active" href="adminprofile.php"><i class="fa-solid fa-user"></i> Profile</a>
            <a href="create_account.php"><i class="fa-solid fa-user-plus"></i> Create Account</a>
            <a href="user_list.php"><i class="fa-solid fa-users"></i> User List</a>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
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

        <!-- DETAILS -->
        <div class="profile-details">
            <h1 class="title">Profile</h1>
            <div class="profile-list">
                <div class="list-item">
                    <span class="label">Full Name</span>
                    <input type="text" id="display-fullname" value="<?php echo e($data['fullname']); ?>" readonly>
                </div>
                <div class="list-item">
                    <span class="label">Email</span>
                    <input type="text" value="<?php echo e($data['email']); ?>" readonly>
                </div>
                <div class="list-item">
                    <span class="label">Role</span>
                    <input type="text" value="System Administrator" readonly>
                </div>
            </div>

            <div class="profile-btn-row">
                <button class="edit-btn" onclick="openModal('edit-name-modal')">Edit Name</button>
                <button class="edit-btn change-pw-btn" onclick="openModal('change-pw-modal')">Change Password</button>
            </div>
        </div>

        <!-- IMAGE -->
        <div class="profile-aside">
            <div class="image-container">
                <img src="<?php echo e($profile_picture); ?>" class="profile-image" alt="Profile" id="profile-img">
                <div class="profile-role">ADMIN</div>
            </div>
            <label class="change-profile-btn" title="Change Profile Picture">
                +
                <input type="file" id="profile_picture" accept="image/*" hidden>
            </label>
        </div>

    </div>
</div>

<!-- ===========================
     EDIT NAME MODAL
=========================== -->
<div class="modal" id="edit-name-modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('edit-name-modal')">&times;</span>
        <h2 class="modal-title">Edit Full Name</h2>
        <p class="modal-subtitle">Update your display name.</p>

        <label class="modal-label">Full Name</label>
        <input type="text" id="new-fullname" placeholder="Enter your full name"
               value="<?php echo e($data['fullname']); ?>" maxlength="100">
        <span class="field-error" id="name-error"></span>

        <button class="save-btn" onclick="saveName()">
            <i class="fa-solid fa-check"></i> Save Changes
        </button>
    </div>
</div>

<!-- ===========================
     CHANGE PASSWORD MODAL
=========================== -->
<div id="change-pw-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="pwModalTitle">
    <div class="modal-content">
        <span class="close" onclick="closeModal('change-pw-modal')" aria-label="Close">&times;</span>
        <h2 id="pwModalTitle">Change Password</h2>

        <div id="passwordForm">

            <label for="current-password">Current Password</label>
            <div class="pw-field">
                <input type="password" id="current-password" placeholder="Enter current password" autocomplete="current-password">
                <button type="button" class="pw-toggle" onclick="togglePw('current-password', this)" tabindex="-1">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>

            <label for="new-password">New Password</label>
            <div class="pw-field">
                <input type="password" id="new-password" placeholder="At least 8 characters" autocomplete="new-password">
                <button type="button" class="pw-toggle" onclick="togglePw('new-password', this)" tabindex="-1">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>

            <label for="confirm-password">Confirm New Password</label>
            <div class="pw-field">
                <input type="password" id="confirm-password" placeholder="Repeat new password" autocomplete="new-password">
                <button type="button" class="pw-toggle" onclick="togglePw('confirm-password', this)" tabindex="-1">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>

            <div id="pw-error" class="pw-error" style="display:none;"></div>

            <button type="button" class="save-btn" onclick="savePassword()">Update Password</button>
        </div>
    </div>
</div>

<!-- ===========================
     TOAST NOTIFICATION
=========================== -->
<div class="toast" id="toast"></div>

<style>
/* ---- ACTION BUTTONS ROW ---- */
.profile-btn-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 40px;
}
.profile-btn-row .edit-btn {
    margin-top: 0;
}
.change-pw-btn {
    background: transparent !important;
    color: white !important;
    border: 2px solid rgba(255,255,255,0.6) !important;
}
.change-pw-btn:hover {
    background: rgba(255,255,255,0.12) !important;
    opacity: 1 !important;
}

/* ---- MODAL EXTRAS ---- */
.modal-content h2{
    font-size: 1.2rem;
    font-weight: 700;
    color: #06502a;
    margin-bottom: 1rem;
}
.modal-content label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #444;
    margin-bottom: 2px;
}
.pw-field {
    position: relative;
    margin: 8px 0 16px;
}
.pw-field input {
    width: 100%;
    padding: 13px 44px 13px 13px;
    border: 1px solid #ccc;
    border-radius: 10px;
    outline: none;
    font-size: 15px;
    margin: 0;
}
.pw-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #888;
    padding: 0;
    font-size: 15px;
}
.pw-toggle:hover { color: #333; }
.pw-error {
    background: #fdecea;
    color: #c0392b;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    margin-bottom: 12px;
}

/* ---- TOAST ---- */
.toast{
    position:fixed;
    bottom:2rem;
    left:50%;
    transform:translateX(-50%) translateY(20px);
    background:#06502a;
    color:white;
    padding:0.85rem 1.75rem;
    border-radius:2rem;
    font-size:0.9rem;
    font-weight:600;
    opacity:0;
    pointer-events:none;
    transition:opacity 0.3s, transform 0.3s;
    z-index:9999;
    white-space:nowrap;
}
.toast.show{
    opacity:1;
    transform:translateX(-50%) translateY(0);
}
.toast.error{
    background:#c0392b;
}
</style>

<script>
/* =========================
   MODAL HELPERS
========================= */
function openModal(id) {
    if (id === 'change-pw-modal') {
        ['current-password', 'new-password', 'confirm-password'].forEach(i => {
            const el = document.getElementById(i);
            if (el) el.value = '';
        });
        hidePwError();
    }
    document.getElementById(id).classList.add('show');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    clearErrors();
}

/* Close on backdrop click */
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

function clearErrors() {
    document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
}

/* =========================
   TOAST
========================= */
let toastTimer;
function showToast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.toggle('error', isError);
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

/* =========================
   PASSWORD VISIBILITY TOGGLE
========================= */
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

/* =========================
   PASSWORD ERROR HELPERS
========================= */
function showPwError(msg) {
    const el = document.getElementById('pw-error');
    el.textContent = msg;
    el.style.display = 'block';
}
function hidePwError() {
    document.getElementById('pw-error').style.display = 'none';
}

/* =========================
   SAVE NAME
========================= */
function saveName() {
    clearErrors();
    const name = document.getElementById('new-fullname').value.trim();
    if (!name) {
        document.getElementById('name-error').textContent = 'Full name cannot be empty.';
        return;
    }

    const formData = new FormData();
    formData.append('action',   'edit_name');
    formData.append('fullname', name);

    fetch('adminprofile.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('display-fullname').value = data.fullname;
                document.getElementById('sidebar-name').textContent = data.fullname;
                closeModal('edit-name-modal');
                
            } else {
                document.getElementById('name-error').textContent = data.message || 'Update failed.';
            }
        })
        .catch(() => {});
}

/* =========================
   SAVE PASSWORD
========================= */
function savePassword() {
    clearErrors();
    const current  = document.getElementById('current-password').value;
    const newPw    = document.getElementById('new-password').value;
    const confirm  = document.getElementById('confirm-password').value;
    let valid = true;

    if (!current) {
        document.getElementById('current-pw-error').textContent = 'Enter your current password.';
        valid = false;
    }
    if (!newPw) {
        document.getElementById('new-pw-error').textContent = 'Enter a new password.';
        valid = false;
    }
    if (newPw && confirm !== newPw) {
        document.getElementById('confirm-pw-error').textContent = 'Passwords do not match.';
        valid = false;
    }
    if (!valid) return;

    const formData = new FormData();
    formData.append('action',           'change_password');
    formData.append('current_password', current);
    formData.append('new_password',     newPw);
    formData.append('confirm_password', confirm);

    fetch('adminprofile.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                closeModal('change-pw-modal');
                /* Clear the fields */
                ['current-password', 'new-password', 'confirm-password'].forEach(id => {
                    document.getElementById(id).value = '';
                });
                document.getElementById('strength-label').textContent = '';
                
            } else {
                /* Route error to the right field */
                const msg = data.message || 'Update failed.';
                if (msg.toLowerCase().includes('current')) {
                    document.getElementById('current-pw-error').textContent = msg;
                } else if (msg.toLowerCase().includes('match')) {
                    document.getElementById('confirm-pw-error').textContent = msg;
                } else {
                    document.getElementById('new-pw-error').textContent = msg;
                }
            }
        })
        .catch(() => {});
}

/* =========================
   PROFILE PICTURE UPLOAD
========================= */
document.getElementById('profile_picture').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        this.value = '';
        return;
    }
    const formData = new FormData();
    formData.append('profile_picture', file);
    fetch('adminprofile.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                
            }
        })
        .catch(() => {});
});
</script>

</body>
</html>