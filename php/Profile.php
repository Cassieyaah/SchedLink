<?php
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* =========================
   UPDATE PROFILE (AJAX)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fullname'])) {

    ob_clean();
    header('Content-Type: application/json');

    try {

        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $student_number = trim($_POST['student_number']);
        $program = trim($_POST['program']);

        /* USERS TABLE */
        $sql1 = "UPDATE users SET fullname = ?, email = ? WHERE user_id = ?";
        $stmt1 = mysqli_prepare($conn, $sql1);
        mysqli_stmt_bind_param($stmt1, "ssi", $fullname, $email, $user_id);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);

        /* CHECK STUDENT */
        $check = mysqli_query($conn, "SELECT user_id FROM students WHERE user_id = $user_id");

        if (mysqli_num_rows($check) > 0) {

            $sql2 = "UPDATE students SET student_number = ?, program = ? WHERE user_id = ?";
            $stmt2 = mysqli_prepare($conn, $sql2);
            mysqli_stmt_bind_param($stmt2, "ssi", $student_number, $program, $user_id);

        } else {

            $sql2 = "INSERT INTO students (student_number, program, user_id) VALUES (?, ?, ?)";
            $stmt2 = mysqli_prepare($conn, $sql2);
            mysqli_stmt_bind_param($stmt2, "ssi", $student_number, $program, $user_id);
        }

        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);

        echo json_encode(["status" => "success"]);

    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
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

        if ($_FILES['profile_picture']['error'] !== 0) {
            throw new Exception("Upload failed.");
        }

        $allowed = ["jpg", "jpeg", "png", "gif"];
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new Exception("Invalid file type.");
        }

        if (!is_dir("../uploads")) {
            mkdir("../uploads", 0777, true);
        }

        $filename = time() . "_" . uniqid() . "." . $ext;
        $target = "../uploads/" . $filename;

        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target)) {
            throw new Exception("Failed to save file.");
        }

        $sql = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $filename, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode([
            "status" => "success",
            "file" => $filename
        ]);

    } catch (Exception $e) {

        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }

    exit();
}

/* =========================
   FETCH USER DATA
========================= */
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

$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$profile_picture = (!empty($data['profile_picture']) && file_exists("../uploads/" . $data['profile_picture']))
    ? "../uploads/" . $data['profile_picture']
    : "../media/images.jpg";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile</title>

<link rel="stylesheet" href="../css/profile.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <div class="profile">

            <img src="<?php echo $profile_picture; ?>" alt="Profile">

            <h3>
                <?php echo htmlspecialchars($data['fullname']); ?>
            </h3>

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
                <input type="text" value="<?php echo $data['fullname']; ?>" readonly>
            </div>

            <div class="list-item">
                <span class="label">Student Number</span>
                <input type="text" value="<?php echo $data['student_number'] ?? 'Not Assigned'; ?>" readonly>
            </div>

            <div class="list-item">
                <span class="label">Program</span>
                <input type="text" value="<?php echo $data['program'] ?? 'Not Assigned'; ?>" readonly>
            </div>

            <div class="list-item">
                <span class="label">Email</span>
                <input type="text" value="<?php echo $data['email']; ?>" readonly>
            </div>

        </div>

        <button class="edit-btn" onclick="openModal()">Edit Profile</button>

    </div>

    <!-- IMAGE SIDE -->
    <div class="profile-aside">

        <div class="image-container">

            <img src="<?php echo $profile_picture; ?>" class="profile-image">

            <div class="profile-role">STUDENT</div>

        </div>

        <!-- UPLOAD BUTTON -->
        <form id="uploadForm">
            <label class="change-profile-btn">
                +
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" hidden>
            </label>
        </form>

    </div>

</div>

</div>

<!-- MODAL -->
<div id="editModal" class="modal">

<div class="modal-content">

<span class="close" onclick="closeModal()">&times;</span>

<h2>Edit Profile</h2>

<form id="profileForm">

    <input type="text" name="fullname" value="<?php echo $data['fullname']; ?>">
    <input type="email" name="email" value="<?php echo $data['email']; ?>">
    <input type="text" name="student_number" value="<?php echo $data['student_number']; ?>">
    <input type="text" name="program" value="<?php echo $data['program']; ?>">

    <button type="button" class="save-btn" onclick="saveProfile()">Save</button>

</form>

</div>

</div>

<!-- JS -->
<script>

/* MODAL */
function openModal(){
    document.getElementById("editModal").classList.add("show");
}

function closeModal(){
    document.getElementById("editModal").classList.remove("show");
}

/* UPDATE PROFILE */
function saveProfile(){

    let form = document.getElementById("profileForm");
    let data = new FormData(form);

    fetch("profile.php", {
        method: "POST",
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if(res.status === "success"){
            location.reload();
        } else {
            alert(res.message || "Error");
        }
    });
}

/* PROFILE UPLOAD */
document.getElementById("profile_picture")
.addEventListener("change", function(){

    let file = this.files[0];
    let formData = new FormData();

    formData.append("profile_picture", file);

    fetch("profile.php", {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.status === "success"){
            location.reload();
        } else {
            alert(res.message);
        }
    });

});

/* CLOSE MODAL OUTSIDE CLICK */
window.onclick = function(e){
    let modal = document.getElementById("editModal");
    if(e.target === modal){
        closeModal();
    }
}

</script>

</body>
</html>