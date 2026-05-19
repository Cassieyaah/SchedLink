<?php
session_start();

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm-password'];

    if ($password !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: signUp.php");
        exit();
    }

    elseif (!str_ends_with($email, '@cvsu.edu.ph')) {
        $_SESSION['error'] = "Please use your official school email.";
        header("Location: signUp.php");
        exit();
    }

    else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "An account with this email already exists.";
            header("Location: signUp.php");
            exit();
        }

        else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $conn->prepare("INSERT INTO users (fullname, email, role, password) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssss", $fullname, $email, $role, $hashedPassword);

            if ($insertStmt->execute()) {
                $_SESSION['success'] = "Account created successfully. Please log in.";
                header("Location: loginSYSTEM.html");
                exit();
            }

            else {
                $_SESSION['error'] = "Error creating account. Please try again.";
                header("Location: signUp.php");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Faculty Access System - Register</title>

<link rel="stylesheet" href="../css/signUp.css">

</head>

<body>

<!-- PRIVACY MODAL -->

<div id="privacyModal" class="modal">

    <div class="modal-content">

        <span class="close-btn" onclick="closeModal()">
            &times;
        </span>

        <h2>Data Privacy Agreement</h2>

        <p>
            By creating an account, you agree that the system
            may collect and process your academic information
            such as your school email, role, faculty assignment,
            and schedule data solely for academic and scheduling
            purposes within Cavite State University.
        </p>

        <p>
            Your information will remain protected and will not
            be shared outside authorized academic personnel.
        </p>

    </div>

</div>

<div class="container">

    <div class="left-panel">

        <img src="../media/cvsulogo.png" class="logo" alt="CvSU Logo">

        <h1>Faculty Access System</h1>

        <p>
            Create your institutional account to gain access to
            faculty schedules, class assignments, instructor
            information, and academic schedule management services
            within Cavite State University.
        </p>

        <div class="features">

            <div class="feature-item">
                <div class="feature-icon">✓</div>
                Secure school-based authentication
            </div>

            <div class="feature-item">
                <div class="feature-icon">✓</div>
                Access instructor schedules and assignments
            </div>

            <div class="feature-item">
                <div class="feature-icon">✓</div>
                Role-based dashboard for students and faculty
            </div>

            <div class="feature-item">
                <div class="feature-icon">✓</div>
                Protected academic data and privacy controls
            </div>

        </div>

    </div>

    <div class="right-panel">

        <h2>Create Account</h2>

        <p class="subtitle">
            Register using your official university credentials
        </p>

        <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="error-message">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']);
            } 

            if (isset($_SESSION['success'])) {
                echo '<div class="success-message">' . $_SESSION['success'] . '</div>';
                unset($_SESSION['success']);
            }

        ?>
        <form action="signUp.php" method="POST">

            <label for="fullname">FULL NAME</label>

            <input
                type="text"
                id="fullname"
                name="fullname"
                placeholder="Enter your full name"
                required
            >

            <label for="email">SCHOOL EMAIL</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="example@cvsu.edu.ph"
                required
            >

            <label for="role">REGISTER AS</label>

            <select id="role" name="role" required>

                <option value="">Select Role</option>
                <option value="student">Student</option>
                <option value="instructor">Instructor</option>

            </select>

            <label for="password">PASSWORD</label>

            <div class="password-wrapper">

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create password"
                    required
                >

            </div>

            <label for="confirm-password">
                CONFIRM PASSWORD
            </label>

            <input
                type="password"
                id="confirm-password"
                name="confirm-password"
                placeholder="Confirm password"
                required
            >

            <div class="checkbox-container">

                <input
                    type="checkbox"
                    id="privacy"
                    required
                >

                <label for="privacy">

                    I agree to the

                    <span class="privacy-link" onclick="openModal()">
                        Data Privacy Policy
                    </span>

                </label>

            </div>

            <button type="submit" class="register-btn">
                CREATE ACCOUNT
            </button>

        </form>

        <div class="footer-text">

            Already have an account?
            <a href="loginSYSTEM.html">Login</a>

        </div>

    </div>

</div>

<script>

function openModal(){
    document.getElementById("privacyModal").style.display = "flex";
}

function closeModal(){
    document.getElementById("privacyModal").style.display = "none";
}

window.onclick = function(event){

    let modal = document.getElementById("privacyModal");

    if(event.target == modal){
        modal.style.display = "none";
    }
}

</script>

</body>
</html>