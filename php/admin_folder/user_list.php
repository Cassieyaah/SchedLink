<?php
ob_start();
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../logIn.php"); //checks if user is logged in
    exit();
}

$admin_id = (int) $_SESSION['user_id'];

/* if user is admin*/
$role_check = mysqli_prepare($conn, "SELECT role FROM users WHERE user_id = ?"); //placeholder muna ng user, mamaya na
if (!$role_check) die("Database error.");
mysqli_stmt_bind_param($role_check, "i", $admin_id);
mysqli_stmt_execute($role_check);
$role_row = mysqli_fetch_assoc(mysqli_stmt_get_result($role_check));
mysqli_stmt_close($role_check);

if (!$role_row || $role_row['role'] !== 'admin') {
    header("Location: ../logIn.php");
    exit();
}

/* delete user (AJAX) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $target_id = (int) $_POST['delete_user_id'];
        if ($target_id === $admin_id) throw new Exception("You cannot delete your own account.");

        $check_stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE user_id = ?");
        if (!$check_stmt) throw new Exception("Database error.");
        mysqli_stmt_bind_param($check_stmt, "i", $target_id);
        mysqli_stmt_execute($check_stmt);
        $check_user = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
        mysqli_stmt_close($check_stmt);

        if (!$check_user) throw new Exception("User not found.");
        if ($check_user['role'] === 'admin') throw new Exception("Admin accounts cannot be deleted.");

        $delete_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
        if (!$delete_stmt) throw new Exception("Database error.");
        mysqli_stmt_bind_param($delete_stmt, "i", $target_id);
        mysqli_stmt_execute($delete_stmt);

        if (mysqli_stmt_affected_rows($delete_stmt) > 0) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to delete user."]);
        }
        mysqli_stmt_close($delete_stmt);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit();
}

/*faculty*/
$detect = mysqli_query($conn, "SELECT role FROM users WHERE role IN ('professor','faculty') LIMIT 1");
$detected = mysqli_fetch_assoc($detect);
$faculty_role = $detected['role'] ?? 'professor';

/* fetch users*/
$filter_role  = $_GET['role'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');

$sql = "
SELECT
    users.user_id, users.fullname, users.email, users.role, users.profile_picture,
    students.student_number, students.program,
    faculties.department
FROM users
LEFT JOIN students  ON users.user_id = students.user_id
LEFT JOIN faculties ON users.user_id = faculties.user_id
WHERE users.role != 'admin'
";

$params = []; $types = "";

if ($filter_role !== 'all' && in_array($filter_role, ['student', 'professor', 'faculty'])) {
    $sql .= " AND users.role = ?";
    $params[] = $filter_role; $types .= "s";
}
if ($search_query !== '') {
    $sql .= " AND (users.fullname = ? OR users.email = ? OR students.student_number = ?)";
    $params[] = $search_query; $params[] = $search_query; $params[] = $search_query; $types .= "sss";
}
$sql .= " ORDER BY users.role ASC, users.fullname ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) die("Database query failed.");
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$users = [];
while ($row = mysqli_fetch_assoc($result)) $users[] = $row;
mysqli_stmt_close($stmt);

/* if admin*/
$admin_stmt = mysqli_prepare($conn, "SELECT fullname, profile_picture FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($admin_stmt, "i", $admin_id);
mysqli_stmt_execute($admin_stmt);
$admin_data = mysqli_fetch_assoc(mysqli_stmt_get_result($admin_stmt));
mysqli_stmt_close($admin_stmt);

/*profile image*/
$default_image   = "../media/images.jpg";
$profile_picture = $default_image;
$stored_picture  = trim($admin_data['profile_picture'] ?? '');

if (!empty($stored_picture)) {
    $uploaded_path = "../uploads/" . basename($stored_picture);
    if (file_exists($uploaded_path)) $profile_picture = $uploaded_path;
}
if ($profile_picture === $default_image && !file_exists($default_image)) {
    $profile_picture = "https://ui-avatars.com/api/?name=" . urlencode($admin_data['fullname']) . "&size=200&background=4a90d9&color=fff";
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User List</title>
    <link rel="stylesheet" href="../css/studentDashBoard.css">
    <link rel="stylesheet" href="../css/user_list.css">
    <link rel="stylesheet" href="../fonts/css/all.min.css">
</head>
<body>


<div class="sidebar">
    <div>
        <div class="profile">
            <img src="<?php echo e($profile_picture); ?>" alt="Profile Picture">
            <h3><?php echo e($admin_data['fullname']); ?></h3>
            <p>Administrator</p>
        </div>
        <div class="section-title">GENERAL</div>
        <div class="nav">
            <a href="admindashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="adminprofile.php"><i class="fa-solid fa-user"></i> Profile</a>
            <a href="create_account.php"><i class="fa-solid fa-user-plus"></i> Create Account</a>
            <a class="active" href="user_list.php"><i class="fa-solid fa-users"></i> User List</a>
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
    <h2>User List</h2>
    <div class="user-box">Welcome, <?php echo e($admin_data['fullname']); ?></div>
</div>


<div class="main">
<div class="dashboard-container admin-dashboard">

   
    <div class="admin-dashboard-heading">
        <div>
            <h3>Manage Users</h3>

            <?php echo count($users); ?> user<?php echo count($users) !== 1 ? 's' : ''; ?> found.</p>
        </div>
        <a href="create_account.php" class="admin-action-btn">
            <i class="fa-solid fa-user-plus"></i> Create Account
        </a>
    </div>

    <!-- filter button -->
    <form class="mu-filters" method="GET" action="user_list.php">

        <div class="mu-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" placeholder="Search by name, email, or student no..."
                   value="<?php echo e($search_query); ?>">
        </div>

        <div class="mu-role-tabs">
            <a href="user_list.php?role=all<?php echo $search_query ? '&search='.urlencode($search_query) : ''; ?>"
               class="role-tab <?php echo $filter_role === 'all' ? 'active' : ''; ?>">All</a>

            <a href="user_list.php?role=student<?php echo $search_query ? '&search='.urlencode($search_query) : ''; ?>"
               class="role-tab <?php echo $filter_role === 'student' ? 'active' : ''; ?>">Students</a>

            <a href="user_list.php?role=<?php echo e($faculty_role); ?><?php echo $search_query ? '&search='.urlencode($search_query) : ''; ?>"
               class="role-tab <?php echo $filter_role === $faculty_role ? 'active' : ''; ?>">Faculty</a>
        </div>

        <button type="submit" class="mu-search-btn">
            <i class="fa-solid fa-magnifying-glass"></i> Search
        </button>

    </form>

    <!-- user table -->
    <div class="admin-panel" style="padding: 0; overflow: hidden;">

        <div class="admin-panel-header" style="padding: 16px 18px 14px;">
            <h4>User Records</h4>
            <i class="fa-solid fa-users"></i>
        </div>

        <div class="mu-table-wrap">

            <?php if (empty($users)): ?>
                <div class="mu-empty">
                    <i class="fa-solid fa-users-slash"></i>
                    <p>No users found.</p>
                </div>
            <?php else: ?>

            <table class="mu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Student No.</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $i => $user): ?>
                    <tr id="row-<?php echo (int)$user['user_id']; ?>">

                        <td class="td-num"><?php echo $i + 1; ?></td>

                        <td class="td-name">
                            <div class="user-avatar">
                                <?php
                                $words    = explode(' ', trim($user['fullname']));
                                $initials = strtoupper(
                                    ($words[0][0] ?? '') . ($words[1][0] ?? '')
                                );
                                echo e($initials);
                                ?>
                            </div>
                            <span><?php echo e($user['fullname']); ?></span>
                        </td>

                        <td><?php echo e($user['email']); ?></td>

                        <td>
                            <span class="role-badge role-<?php echo e($user['role']); ?>">
                                <?php echo ucfirst(e($user['role'])); ?>
                            </span>
                        </td>

                        <td class="td-details">
                            <?php if ($user['role'] === 'student'): ?>
                                <?php echo e($user['student_number'] ?? '—'); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <button class="delete-btn"
                                onclick="confirmDelete(<?php echo (int)$user['user_id']; ?>, '<?php echo e(addslashes($user['fullname'])); ?>')">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php endif; ?>
        </div>
    </div>

</div>
</div>

<!-- delete modal -->
<div id="deleteModal" class="modal" aria-modal="true" role="dialog">
    <div class="modal-content">

        <span class="close" onclick="closeDeleteModal()" title="Close">&times;</span>

        <div class="confirm-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <h2>Delete User</h2>
        <p id="deleteMessage">Are you sure you want to delete this user?</p>

        <div class="confirm-btns">
            <button class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
            <button class="confirm-delete-btn" id="confirmDeleteBtn">Delete</button>
        </div>

    </div>
</div>

<script>
let pendingUserId = null;

function confirmDelete(userId, fullname) {
    pendingUserId = userId;
    document.getElementById("deleteMessage").textContent =
        `Are you sure you want to delete "${fullname}"? This cannot be undone.`;
    const modal = document.getElementById("deleteModal");
    modal.classList.add("show");
    setTimeout(() => modal.querySelector(".cancel-btn").focus(), 50);
}

function closeDeleteModal() {
    const modal = document.getElementById("deleteModal");
    modal.style.opacity = "0";
    setTimeout(() => {
        modal.classList.remove("show");
        modal.style.opacity = "";
    }, 200);
    pendingUserId = null;
}

document.getElementById("confirmDeleteBtn").addEventListener("click", function () {
    if (!pendingUserId) return;
    const formData = new FormData();
    formData.append("delete_user_id", pendingUserId);
    fetch("user_list.php", { method: "POST", body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === "success") {
                const row = document.getElementById("row-" + pendingUserId);
                if (row) { row.style.transition = "0.3s"; row.style.opacity = "0"; setTimeout(() => row.remove(), 300); }
                closeDeleteModal();
            } else {
                alert(data.message || "Delete failed.");
                closeDeleteModal();
            }
        })
        .catch(() => { alert("Network error."); closeDeleteModal(); });
});

window.addEventListener("click", e => { if (e.target === document.getElementById("deleteModal")) closeDeleteModal(); });
window.addEventListener("keydown", e => { if (e.key === "Escape") closeDeleteModal(); });

// Page transition
document.querySelectorAll(".nav a").forEach(link => {
    link.addEventListener("click", function (e) {
        const target = this.getAttribute("href");
        if (!target || target === "#") return;
        e.preventDefault();
        document.querySelector(".main").classList.add("page-transition");
        setTimeout(() => { window.location.href = target; }, 180);
    });
});
</script>

</body>
</html>