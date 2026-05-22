<?php
session_start();
include("../includes/db.php");

/* CHECK IF USER IS LOGGED IN */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* UPLOAD PROFILE PICTURE */
if (isset($_POST['upload_picture'])) {

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {

        $file_name = time() . "_" . basename($_FILES['profile_picture']['name']);

        $target_path = "../uploads/" . $file_name;

        $image_type = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));

        $allowed_types = ["jpg", "jpeg", "png", "gif"];

        if (in_array($image_type, $allowed_types)) {

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {

                $update_query = "
                    UPDATE users
                    SET profile_picture = ?
                    WHERE user_id = ?
                ";

                $update_stmt = mysqli_prepare($conn, $update_query);

                mysqli_stmt_bind_param($update_stmt, "si", $file_name, $user_id);

                mysqli_stmt_execute($update_stmt);

                mysqli_stmt_close($update_stmt);

                header("Location: profile.php");
                exit();
            }
        }
    }
}

/* FETCH BASIC USER DATA */
$query = "
    SELECT 
        user_id,
        fullname,
        email,
        role,
        profile_picture
    FROM users
    WHERE user_id = ?
";

$stmt = mysqli_prepare($conn, $query);

mysqli_stmt_bind_param($stmt, "i", $user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/* CHECK USER ROLE */
$role = $data['role'];

/* DEFAULT VALUES */
$student_number = "";
$program = "";
$department = "";
$fb_link = "";

/* STUDENT ACCOUNT */
if ($role == "student") {

    $student_query = "
        SELECT 
            student_number,
            program
        FROM students
        WHERE user_id = ?
    ";

    $student_stmt = mysqli_prepare($conn, $student_query);

    mysqli_stmt_bind_param($student_stmt, "i", $user_id);

    mysqli_stmt_execute($student_stmt);

    $student_result = mysqli_stmt_get_result($student_stmt);

    $student_data = mysqli_fetch_assoc($student_result);

    mysqli_stmt_close($student_stmt);

    if ($student_data) {
        $student_number = $student_data['student_number'];
        $program = $student_data['program'];
    }
}

/* FACULTY ACCOUNT */
elseif ($role == "faculty") {

    $faculty_query = "
        SELECT 
            department,
            fb_link
        FROM faculties
        WHERE user_id = ?
    ";

    $faculty_stmt = mysqli_prepare($conn, $faculty_query);

    mysqli_stmt_bind_param($faculty_stmt, "i", $user_id);

    mysqli_stmt_execute($faculty_stmt);

    $faculty_result = mysqli_stmt_get_result($faculty_stmt);

    $faculty_data = mysqli_fetch_assoc($faculty_result);

    mysqli_stmt_close($faculty_stmt);

    if ($faculty_data) {
        $department = $faculty_data['department'];
        $fb_link = $faculty_data['fb_link'];
    }
}

mysqli_close($conn);

/* DEFAULT PROFILE PICTURE */
$profile_picture = (!empty($data['profile_picture']) && file_exists("../uploads/" . $data['profile_picture']))
    ? "../uploads/" . $data['profile_picture']
    : "../media/images.jpg";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
    content="width=device-width, initial-scale=1.0">

    <title>Profile</title>

    <link rel="stylesheet" href="../css/profile.css">

</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <!-- PROFILE -->
        <div class="profile">

            <img src="<?php echo $profile_picture; ?>" alt="Profile">

            <h3>
                <?php echo htmlspecialchars($data['fullname']); ?>
            </h3>

            <?php if ($role == "student"): ?>

                <p class="profile-role">
                    Student Account
                </p>

            <?php elseif ($role == "faculty"): ?>

                <p class="profile-role">
                    Faculty Account
                </p>

            <?php else: ?>

                <p class="profile-role">
                    Admin Account
                </p>

            <?php endif; ?>

        </div>

        <!-- MENU -->
        <p class="section-title">
            GENERAL
        </p>

        <div class="nav">

            <a href="../php/studentdashboard.php">
                Dashboard
            </a>

            <a href="../php/schedule.php">
                My Schedule
            </a>

            <a href="../php/logout.php" class="logout-btn">
                Logout
            </a>

        </div>

        <div class="divider"></div>

    </div>

    <!-- FOOTER -->
    <div class="sidebar-footer">

        <img src="../media/cvsulogo.png" alt="CvSU Logo">

        <p>
            Cavite State University
        </p>

    </div>

</div>

<!-- MAIN -->
<div class="main">

    <!-- PROFILE CARD -->
    <div class="profile-card">

        <!-- PROFILE DETAILS -->
        <div class="profile-details">

            <h1 class="title">
                Profile
            </h1>

            <div class="profile-list">

                <!-- FULL NAME -->
                <div class="list-item">

                    <span class="label">
                        Full Name
                    </span>

                    <input
                    type="text"
                    value="<?php echo htmlspecialchars($data['fullname']); ?>"
                    readonly>

                </div>

                <!-- ROLE -->
                <div class="list-item">

                    <span class="label">
                        Role
                    </span>

                    <input
                    type="text"
                    value="<?php echo ucfirst(htmlspecialchars($role)); ?>"
                    readonly>

                </div>

                <!-- EMAIL -->
                <div class="list-item">

                    <span class="label">
                        Email
                    </span>

                    <input
                    type="text"
                    value="<?php echo htmlspecialchars($data['email']); ?>"
                    readonly>

                </div>

                <!-- STUDENT DETAILS -->
                <?php if ($role == "student"): ?>

                    <div class="list-item">

                        <span class="label">
                            Student Number
                        </span>

                        <input
                        type="text"
                        value="<?php echo htmlspecialchars($student_number); ?>"
                        readonly>

                    </div>

                    <div class="list-item">

                        <span class="label">
                            Program
                        </span>

                        <input
                        type="text"
                        value="<?php echo htmlspecialchars($program); ?>"
                        readonly>

                    </div>

                <?php endif; ?>

                <!-- FACULTY DETAILS -->
                <?php if ($role == "faculty"): ?>

                    <div class="list-item">

                        <span class="label">
                            Department
                        </span>

                        <input
                        type="text"
                        value="<?php echo htmlspecialchars($department); ?>"
                        readonly>

                    </div>

                    <div class="list-item">

                        <span class="label">
                            Facebook Link
                        </span>

                        <input
                        type="text"
                        value="<?php echo htmlspecialchars($fb_link); ?>"
                        readonly>

                    </div>

                <?php endif; ?>

            </div>

            <button class="edit-btn">
                Edit Profile
            </button>

        </div>

        <!-- PROFILE ASIDE -->
        <div class="profile-aside">

            <div class="image-container">

                <img
                src="<?php echo $profile_picture; ?>"
                class="profile-image"
                alt="Profile Image">

                <!-- UPLOAD FORM -->
                <form method="POST" enctype="multipart/form-data">

                    <label class="camera-btn">

                        +

                        <input
                        type="file"
                        name="profile_picture"
                        accept="image/*"
                        onchange="this.form.submit()"
                        hidden>

                    </label>

                    <input
                    type="hidden"
                    name="upload_picture">

                </form>

            </div>

        </div>

    </div>

</div>

</body>
</html>