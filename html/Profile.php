<?php
session_start();
include("db.php");

$user_id = 1;

$query = "
    SELECT 
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

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile</title>
    <link rel="stylesheet" href="Profile.css">
</head>

<body>

<div class="web">

    <!-- SIDEBAR -->
    <div class="sidebar">

        <div class="profile">
            <img src="images.jpg" alt="Profile">
            <h1><?php echo htmlspecialchars($data['full_name']); ?></h1>
            <p><?php echo htmlspecialchars($data['role']); ?></p>
        </div>

        <hr>

        <div class="menu">

            <a href="dashboard.php">Dashboard</a>
            <a href="settings.php">Edit</a>

        </div>

        <div class="logout menu">
            <a href="logout.php">Logout</a>
        </div>

        <div class="bottom-logo">
            <img src="cvsulogo.png" alt="Logo">

        </div>

    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <div class="content-card">

            <h1>Student Profile</h1>

            <div class="info">

                <div class="box">
                    <h3>Full Name</h3>
                    <p><?php echo htmlspecialchars($data['full_name']); ?></p>
                </div>

                <div class="box">
                    <h3>Email</h3>
                    <p><?php echo htmlspecialchars($data['email']); ?></p>
                </div>

                <div class="box">
                    <h3>Student Number</h3>
                    <p><?php echo htmlspecialchars($data['student_number']); ?></p>
                </div>

                <div class="box">
                    <h3>Program</h3>
                    <p><?php echo htmlspecialchars($data['program']); ?></p>
                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
