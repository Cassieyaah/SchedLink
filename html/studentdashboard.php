<?php
include 'db.php';

$student_id = 1; // temporary session

$studentQuery = "SELECT * FROM students WHERE student_id = '$student_id'";
$studentResult = mysqli_query($conn, $studentQuery);
$student = mysqli_fetch_assoc($studentResult);

/* schedule */
$scheduleQuery = "SELECT * FROM student_schedules WHERE student_id = '$student_id'";
$scheduleResult = mysqli_query($conn, $scheduleQuery);

/* check profile completeness */
$hasProfile =
    $student &&
    !empty($student['student_number']) &&
    !empty($student['program']);
?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Dashboard - ProfHunt</title>

<link rel="stylesheet" href="../css/studentDashBoard.css">

</head>

<body>

<div class="sidebar">

    <div>

        <div class="profile">

            <img src="../media/studentprofile.jpg">

            <?php if ($student): ?>

                <h3><?php echo $student['full_name']; ?></h3>

                <?php if ($hasProfile): ?>
                    <p><?php echo $student['student_number']; ?></p>
                    <p><?php echo $student['program']; ?></p>
                <?php else: ?>
                    <p style="color:#ffd966;">Add Student Number</p>
                    <p>Add Student Program</p>
                <?php endif; ?>

            <?php else: ?>

                <h3>Guest User</h3>
                <p>No account found</p>
                <p>Please sign up</p>

            <?php endif; ?>
        </div>

        <div class="section-title">GENERAL</div>

        <div class="nav">

            <a class="active" href="studentdashboard.php">
                Dashboard
            </a>

            <a href="#">
                My Schedule
            </a>

        </div>

        <div class="divider"></div>

    </div>

    <div class="sidebar-footer">

        <img src="../media/cvsulogo.png">

        <p>Cavite State University</p>

    </div>

</div>

<div class="header">

    <h2>Student Dashboard</h2>
        <div class="user-box">
            Welcome, <?php echo $student ? $student['full_name'] : 'Guest'; ?>
        </div>

</div>

<div class="main">

    <?php if ($student && !$hasProfile): ?>
        <div style="
            background:#fff3cd;
            color:#856404;
            padding:15px;
            border-radius:10px;
            margin-bottom:20px;
            border:1px solid #ffeeba;
        ">
            Your profile is incomplete.

            <a href="profile.php"
            style="
                display:inline-block;
                margin-left:10px;
                color:#0b6b3a;
                font-weight:bold;
                text-decoration:none;
            ">
                Complete Profile
            </a>
        </div>
    <?php endif; ?>

    <div class="dashboard-container">
        <a href="upload_schedule.php" class="upload-btn">
            Upload Schedule (PDF / Image)
        </a>
        <div class="section-title-main">
            My Schedule
        </div>

        <div class="card-container">

            <?php if ($scheduleResult && mysqli_num_rows($scheduleResult) > 0) { ?>
                <?php while($row = mysqli_fetch_assoc($scheduleResult)) { ?>

                    <div class="card">
                        <h4>
                            <?php echo $row['course_code']; ?> -
                            <?php echo $row['course_description']; ?>
                        </h4>

                        <p>
                            <?php echo $row['day']; ?> |
                            <?php echo date("g:i A", strtotime($row['time_start'])); ?>
                            -
                            <?php echo date("g:i A", strtotime($row['time_end'])); ?>
                        </p>

                        <p>
                            <?php echo $row['room']; ?>
                        </p>
                    </div>

                <?php } ?>

            <?php } else { ?>

                <p class="empty-state">No schedules found.</p>
            <?php } ?>

        </div>

    </div>

</div>

<script>

const links = document.querySelectorAll(".nav a");

links.forEach(link => {

    link.addEventListener("click", function(e){

        const target = this.getAttribute("href");

        if(!target || target === "#") return;

        e.preventDefault();

        document.querySelector(".main")
        .classList.add("page-transition");

        setTimeout(() => {
            window.location.href = target;
        }, 180);
    });

});

</script>

</body>
</html>