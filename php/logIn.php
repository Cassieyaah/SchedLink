<?php
session_start();
include '../includes/db.php'; 

$sql_create_db = "CREATE DATABASE IF NOT EXISTS $database";
$conn->query($sql_create_db);
$conn->select_db($database);

$sql_create_table = "CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','faculty','admin') NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($sql_create_table);

$admin_email = "admin@cvsu.edu.ph";
$check_admin = $conn->query("SELECT * FROM users WHERE email = '$admin_email'");
if ($check_admin->num_rows == 0) {
    $hashed_password = password_hash("admin123", PASSWORD_DEFAULT);
    $sql_insert_admin = "INSERT INTO users (fullname, email, password, role) VALUES ('System Administrator', '$admin_email', '$hashed_password', 'admin')";
    $conn->query($sql_insert_admin);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role']; 

            $role = $user['role'];

            if ($role === 'admin') {
                $redirect_page = 'admindashboard.php';
            } elseif ($role === 'faculty') {
                $redirect_page = 'studentdashboard.php';
            } else {
                $redirect_page = 'studentdashboard.php';
            }

            echo "<script>
                    alert('Login Successful! Welcome " . $conn->real_escape_string($user['fullname']) . ".'); 
                    window.location.href='$redirect_page';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Wrong password, try again.'); 
                    window.location.href='';
                  </script>";
            exit();
        }
    } else {
        echo "<script>
                alert('No account found for this email.'); 
                window.location.href='';
              </script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Access System - Login</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <link rel="stylesheet" href="../css/logIn.css">
</head>
<body>

<div class="container">
    <div class="left-panel">
        <img src="../media/cvsulogo.png" class="logo" alt="CvSU Logo">
        <h1>Faculty Access System</h1>
        <p>
            A centralized academic platform that allows students,
            faculty members, and administrators to efficiently access
            instructor schedules, faculty assignments, and class
            information within Cavite State University.
        </p>

        <div class="features">
            <div class="feature-item"><div class="feature-icon">✓</div>View faculty schedules instantly</div>
            <div class="feature-item"><div class="feature-icon">✓</div>Search instructors by section or subject</div>
            <div class="feature-item"><div class="feature-icon">✓</div>Upload and process Excel schedules</div>
            <div class="feature-item"><div class="feature-icon">✓</div>Role-based access for students, faculty, and admins</div>
        </div>
    </div>

    <div class="right-panel">
        <h2>Welcome Back</h2>
        <p class="subtitle">Login using your institutional account</p>

        <form action="" method="POST">
            <label for="email">SCHOOL EMAIL</label>
            <input type="email" id="email" name="email" placeholder="example@cvsu.edu.ph" required>

            <label for="password">PASSWORD</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>

            <button type="submit" class="login-btn">LOGIN</button>
        </form>

        <div class="footer-text">
            Don’t have an account yet? <a href="signUp.php">Register</a>
        </div>
    </div>
</div>

</body>
</html>
