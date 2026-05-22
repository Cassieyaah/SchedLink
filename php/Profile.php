<?php
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* =========================
   UPDATE PROFILE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $student_number = $_POST['student_number'];
    $program = $_POST['program'];

    $sql1 = "UPDATE users SET fullname = ?, email = ? WHERE user_id = ?";
    $stmt1 = mysqli_prepare($conn, $sql1);
    mysqli_stmt_bind_param($stmt1, "ssi", $fullname, $email, $user_id);
    mysqli_stmt_execute($stmt1);
    mysqli_stmt_close($stmt1);

    $sql2 = "UPDATE students SET student_number = ?, program = ? WHERE user_id = ?";
    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, "ssi", $student_number, $program, $user_id);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);

    header("Location: ../php/profile.php");
    exit();
}

/* =========================
   PROFILE PICTURE UPLOAD
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {

    if ($_FILES['profile_picture']['error'] === 0) {

        $file_name = time() . "_" . basename($_FILES['profile_picture']['name']);
        $target_path = "../uploads/" . $file_name;

        $ext = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));
        $allowed = ["jpg", "jpeg", "png", "gif"];

        if (in_array($ext, $allowed)) {

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {

                $update = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update);
                mysqli_stmt_bind_param($stmt, "si", $file_name, $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                header("Location: profile.php");
                exit();
            }
        }
    }
}

/* =========================
   FETCH DATA
========================= */
$query = "
    SELECT 
        users.fullname,
        users.email,
        users.profile_picture,
        students.student_number,
        students.program
    FROM users
    LEFT JOIN students 
        ON users.user_id = students.user_id
    WHERE users.user_id = ?
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);
mysqli_close($conn);

if (!$data) {
    die("User not found.");
}

$profile_picture = (!empty($data['profile_picture']) && file_exists("../uploads/" . $data['profile_picture']))
    ? "../uploads/" . $data['profile_picture']
    : "../media/images.jpg";
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Profile</title>

<link rel="stylesheet" href="../css/profile.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <div class="profile">
            <img src="<?php echo $profile_picture; ?>" alt="Profile">
            <h3><?php echo htmlspecialchars($data['fullname']); ?></h3>
        </div>

        <p class="section-title">GENERAL</p>

        <div class="nav">
            <a href="../php/studentdashboard.php">Dashboard</a>
            <a href="../php/schedule.php">My Schedule</a>
            <a href="../php/logout.php" class="logout-btn">Logout</a>
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
                    <input type="text" value="<?php echo htmlspecialchars($data['fullname']); ?>" readonly>
                </div>

                <div class="list-item">
                    <span class="label">Student Number</span>
                    <input type="text" value="<?php echo htmlspecialchars($data['student_number'] ?? 'Not Assigned'); ?>" readonly>
                </div>

                <div class="list-item">
                    <span class="label">Program</span>
                    <input type="text" value="<?php echo htmlspecialchars($data['program'] ?? 'Not Assigned'); ?>" readonly>
                </div>

                <div class="list-item">
                    <span class="label">Email</span>
                    <input type="text" value="<?php echo htmlspecialchars($data['email']); ?>" readonly>
                </div>

            </div>

            <button class="edit-btn" onclick="openModal()">Edit Profile</button>

        </div>

        <div class="profile-aside">

    <div class="image-container">

        <img src="<?php echo $profile_picture; ?>" class="profile-image" alt="Profile">

        <div class="profile-role">STUDENT</div>

    </div>

    <!-- CHANGE PROFILE BUTTON (OUTSIDE IMAGE) -->
    <form method="POST" enctype="multipart/form-data">

        <label class="change-profile-btn">
            +
            <input type="file" name="profile_picture" accept="image/*"
                   onchange="this.form.submit()" hidden>
        </label>

    </form>

</div>

        </div>

    </div>

</div>

<!-- =========================
     MODAL (AJAX FIXED)
========================= -->
<div id="editModal" class="modal">

    <div class="modal-content">

        <span class="close" onclick="closeModal()">&times;</span>

        <h2>Edit Profile</h2>

        <form id="profileForm">

            <label>Full Name</label>
            <input type="text" name="fullname"
                   value="<?php echo htmlspecialchars($data['fullname']); ?>">

            <label>Email</label>
            <input type="email" name="email"
                   value="<?php echo htmlspecialchars($data['email']); ?>">

            <label>Student Number</label>
            <input type="text" name="student_number"
                   value="<?php echo htmlspecialchars($data['student_number'] ?? ''); ?>">

            <label>Program</label>
            <input type="text" name="program"
                   value="<?php echo htmlspecialchars($data['program'] ?? ''); ?>">

            <button type="button" class="save-btn" onclick="saveProfile()">
                Save Changes
            </button>

        </form>

    </div>

</div>

<!-- =========================
     JS (AJAX)
========================= -->
<script>
function openModal(){
    document.getElementById("editModal").classList.add("show");
}

function closeModal(){
    document.getElementById("editModal").classList.remove("show");
}

function saveProfile() {

    let form = document.getElementById("profileForm");
    let formData = new FormData(form);

    fetch("update_profile.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {

        if (data.status === "success") {

            closeModal();

            // refresh updated data
            location.reload();
        }

    })
    .catch(err => {
        console.error(err);
    });
}

window.onclick = function(event){
    let modal = document.getElementById("editModal");

    if(event.target === modal){
        closeModal();
    }
}
</script>

</body>
</html>