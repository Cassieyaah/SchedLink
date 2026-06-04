<?php
session_start();

header('Content-Type: application/json');

include("../includes/db.php");

/* TURN OFF HTML ERRORS */
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* CHECK LOGIN */
if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "status" => "error",
        "message" => "User not logged in"
    ]);

    exit();
}

$user_id = $_SESSION['user_id'];

/* GET DATA */
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$student_number = $_POST['student_number'] ?? '';
$program = $_POST['program'] ?? '';

try {

    /* UPDATE USERS */
    $sql1 = "UPDATE users 
             SET fullname = ?, email = ?
             WHERE user_id = ?";

    $stmt1 = mysqli_prepare($conn, $sql1);

    if (!$stmt1) {
        throw new Exception(mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt1,
        "ssi",
        $fullname,
        $email,
        $user_id
    );

    mysqli_stmt_execute($stmt1);

    mysqli_stmt_close($stmt1);

    /* CHECK IF STUDENT EXISTS */
    $check = "SELECT * FROM students WHERE user_id = ?";

    $stmtCheck = mysqli_prepare($conn, $check);

    mysqli_stmt_bind_param(
        $stmtCheck,
        "i",
        $user_id
    );

    mysqli_stmt_execute($stmtCheck);

    $result = mysqli_stmt_get_result($stmtCheck);

    if (mysqli_num_rows($result) > 0) {

        /* UPDATE STUDENT */
        $sql2 = "UPDATE students
                 SET student_number = ?, program = ?
                 WHERE user_id = ?";

        $stmt2 = mysqli_prepare($conn, $sql2);

        mysqli_stmt_bind_param(
            $stmt2,
            "ssi",
            $student_number,
            $program,
            $user_id
        );

        mysqli_stmt_execute($stmt2);

        mysqli_stmt_close($stmt2);

    } else {

        /* INSERT STUDENT */
        $sql3 = "INSERT INTO students
                 (user_id, student_number, program)
                 VALUES (?, ?, ?)";

        $stmt3 = mysqli_prepare($conn, $sql3);

        mysqli_stmt_bind_param(
            $stmt3,
            "iss",
            $user_id,
            $student_number,
            $program
        );

        mysqli_stmt_execute($stmt3);

        mysqli_stmt_close($stmt3);
    }

    echo json_encode([
        "status" => "success"
    ]);

} catch (Exception $e) {

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
