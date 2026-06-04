<?php
session_start();
include '../includes/db.php';

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

function e($v)
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$default_image = "../media/images.jpg";
$profile_picture = $default_image;
$stored_picture = trim($admin['profile_picture'] ?? '');

if ($stored_picture !== '') {
    $uploaded_path = "../uploads/" . $stored_picture;
    if (file_exists($uploaded_path)) {
        $profile_picture = $uploaded_path;
    }
}

$showModal = false;
$modalType = "";
$generatedPassword = "";
$generatedEmail = "";
$generatedRole = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $role = $_POST['role'] ?? 'faculty';

    // simple readable password generator
    $generatedPassword = "Fac" . rand(1000, 9999);

    if (!str_ends_with($email, '@cvsu.edu.ph')) {
        $modalType = "error";
        $message = "Invalid school email domain.";
        $showModal = true;
    } else {

        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $modalType = "error";
            $message = "Email already exists.";
            $showModal = true;
        } else {

            $hashed = password_hash($generatedPassword, PASSWORD_DEFAULT);

            $insert = $conn->prepare("INSERT INTO users (fullname, email, role, password) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $fullname, $email, $role, $hashed);

            if ($insert->execute()) {
                $modalType = "success";
                $message = "Account successfully created.";
                $generatedEmail = $email;
                $generatedRole = $role;
                $showModal = true;
            } else {
                $modalType = "error";
                $message = "Database error.";
                $showModal = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Account</title>

    <link rel="stylesheet" href="../css/adminDashboard.css">
    <link rel="stylesheet" href="../css/create_account.css">
    <link rel="stylesheet" href="../fonts/css/all.min.css">
    <link rel="stylesheet" href="../css/studentDashBoard.css">
</head>

<body>

    <!-- SIDEBAR (UNCHANGED FROM DASHBOARD STYLE) -->
    <div class="sidebar">

        <div>

            <div class="profile">
                <img src="<?php echo e($profile_picture); ?>">
                <h3><?php echo e($admin['fullname']); ?></h3>
                <p>Administrator</p>
            </div>

            <div class="section-title">GENERAL</div>

            <div class="nav">
                <a href="admindashboard.php">
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a>

                <a href="adminprofile.php">
                    <i class="fa-solid fa-user"></i> Profile
                </a>

                <a class="active" href="create_account.php">
                    <i class="fa-solid fa-user-plus"></i> Create Account
                </a>

                <a href="user_list.php">
                    <i class="fa-solid fa-users"></i> User List
                </a>

                <a href="schedule_conflicts.php">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Schedule Conflict Management
                </a>

                <a href="logout.php" class="logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>

            <div class="divider"></div>

        </div>

        <div class="sidebar-footer">
            <img src="../media/cvsulogo.png">
            <p>Cavite State University</p>
        </div>

    </div>

    <!-- HEADER (UNCHANGED) -->
    <div class="header">
        <h2>Create Account</h2>
        <div class="user-box">Welcome, <?php echo e($admin['fullname']); ?></div>
    </div>

    <!-- MAIN (DASHBOARD STYLE CONTAINER) -->
    <div class="main">

        <div class="dashboard-container">

            <div class="admin-dashboard-heading">
                <div>
                    <h3>Create User Account</h3>
                    <p>Create faculty or admin accounts for system access.</p>
                </div>
            </div>

            <div class="admin-panel">

                <form method="POST">

                    <div class="form-grid">

                        <div>
                            <label>Full Name</label>
                            <input type="text" name="fullname" required>
                        </div>

                        <div>
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>

                        <div>
                            <label>Role</label>
                            <select name="role">
                                <option value="faculty" selected>Faculty</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                    </div>

                    <button class="admin-action-btn" type="submit" style="margin-top:20px;">
                        Create Account
                    </button>

                </form>

            </div>

        </div>

    </div>

    <!-- MODAL -->
    <?php if ($showModal): ?>
        <div class="modal-overlay" id="modal">

            <div class="modal">

                <h3>
                    <?php if ($modalType === 'success'): ?>
                        <i class="fa-solid fa-circle-check"></i>
                        Account Created
                    <?php else: ?>
                        <i class="fa-solid fa-circle-xmark"></i>
                        Creation Failed
                    <?php endif; ?>
                </h3>

                <p><?= e($message) ?></p>

                <?php if ($modalType === 'success'): ?>
                    <div class="cred-box">
                        <p><b>Email:</b> <?= e($generatedEmail) ?></p>
                        <p><b>Role:</b> <?= e($generatedRole) ?></p>
                        <p><b>Password:</b> <?= e($generatedPassword) ?></p>
                    </div>
                <?php endif; ?>

                <button onclick="document.getElementById('modal').style.display='none'">
                    Close
                </button>

            </div>

        </div>
    <?php endif; ?>

</body>

</html>