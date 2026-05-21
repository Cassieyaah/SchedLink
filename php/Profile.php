<?php
session_start();
include("../includes/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: loginSYSTEM.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* FETCH USER DATA */
$query = "
    SELECT 
        users.username,
        users.email,
        users.role,
        students.full_name,
        students.student_number,
        students.program
    FROM users
    INNER JOIN students
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
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
    content="width=device-width, initial-scale=1.0">

    <title>Profile</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/profile.css">

</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div>

        <!-- PROFILE -->
        <div class="profile">

            <img src="../media/images.jpg" alt="Profile">

            <!-- FULL NAME -->
            <h3>
                <?php echo htmlspecialchars($data['full_name']); ?>
            </h3>

            <!-- ROLE -->
            <?php if ($data['role'] == "student"): ?>

                <p class="profile-role">
                    Student Account
                </p>

            <?php elseif ($data['role'] == "professor"): ?>

                <p class="profile-role">
                    Faculty Account
                </p>

            <?php else: ?>

                <p class="profile-role">
                    User Account
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

        <!-- DIVIDER -->
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
                    value="<?php echo htmlspecialchars($data['full_name']); ?>"
                    readonly>

                </div>

                <!-- STUDENT NUMBER -->
                <div class="list-item">

                    <span class="label">
                        Student Number
                    </span>

                    <input
                    type="text"
                    value="<?php echo htmlspecialchars($data['student_number']); ?>"
                    readonly>

                </div>

                <!-- PROGRAM -->
                <div class="list-item">

                    <span class="label">
                        Program
                    </span>

                    <input
                    type="text"
                    value="<?php echo htmlspecialchars($data['program']); ?>"
                    readonly>

                </div>

                <!-- ROLE -->
                <div class="list-item">

                    <span class="label">
                        Role
                    </span>

                    <input
                    type="text"
                    value="<?php echo ucfirst(htmlspecialchars($data['role'])); ?>"
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

            </div>

            <!-- EDIT BUTTON -->
            <button class="edit-btn">
                Edit Profile
            </button>

        </div>

        <!-- PROFILE ASIDE -->
        <div class="profile-aside">

            <div class="image-container">

                <img
                src="../media/images.jpg"
                class="profile-image"
                alt="Profile Image">

                <button class="camera-btn">
                    +
                </button>

            </div>

        </div>

    </div>

</div>

</body>
</html>
