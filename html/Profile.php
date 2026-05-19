<?php
session_start();
include("db.php");

$data = [
    'full_name' => '',
    'email' => '',
    'student_number' => '',
    'program' => '',
    'role' => ''
];

if (isset($_SESSION['user_id'])) {

    $user_id = $_SESSION['user_id'];

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

    if ($result && mysqli_num_rows($result) > 0) {

        $data = mysqli_fetch_assoc($result);

    } else {

        exit("No student data found.");

    }

} else {

    exit("User not logged in.");

}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Student Profile</title>
    <link rel="stylesheet" href="profile-style.css">
</head>
<body>

<div class="web">

    <div class="prfl">

        <div class="header">
            <div class="pic"></div>

            <h2>
                Welcome,
                <?php echo htmlspecialchars($data['full_name']); ?>!
            </h2>
        </div>

        <div class="detail">

            <div class="dtl">
                <h5>Full Name:</h5>

                <h3>
                    <?php echo htmlspecialchars($data['full_name']); ?>
                </h3>
            </div>

            <div class="dtl">
                <h5>Email:</h5>

                <h3>
                    <?php echo htmlspecialchars($data['email']); ?>
                </h3>
            </div>

            <div class="dtl">
                <h5>Student Number:</h5>

                <h3>
                    <?php echo htmlspecialchars($data['student_number']); ?>
                </h3>
            </div>

            <div class="dtl">
                <h5>Program:</h5>

                <h3>
                    <?php echo htmlspecialchars($data['program']); ?>
                </h3>
            </div>

            <div class="dtl">
                <h5>Role:</h5>

                <h3>
                    <?php echo htmlspecialchars($data['role']); ?>
                </h3>
            </div>

        </div>

    </div>

</div>

</body>
</html>