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

    <div class="main-content">

        <div class="content-card">

            <div class="left-side">

                <img src="images.jpg" class="profile-image">

                <h2 class="student-name">
                    <?php echo htmlspecialchars($data['full_name']); ?>
                </h2>

                <p class="student-role">
                    <?php echo htmlspecialchars($data['role']); ?>
                </p>

                <img src="cvsulogo.png" class="school-logo">

            </div>

            <div class="right-side">

                <div class="profile-list">

                    <div class="list-item">
                        <span class="label">Full Name</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($data['full_name']); ?>
                        </span>
                    </div>

                    <div class="list-item">
                        <span class="label">Email</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($data['email']); ?>
                        </span>
                    </div>

                    <div class="list-item">
                        <span class="label">Student Number</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($data['student_number']); ?>
                        </span>
                    </div>

                    <div class="list-item">
                        <span class="label">Program</span>
                        <span class="colon">:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($data['program']); ?>
                        </span>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
