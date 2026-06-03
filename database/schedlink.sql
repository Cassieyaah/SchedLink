-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 03, 2026 at 09:12 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `schedlink`
--

-- --------------------------------------------------------

--
-- Table structure for table `faculties`
--

CREATE TABLE `faculties` (
  `faculty_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department` varchar(255) DEFAULT NULL,
  `fb_link` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_schedules`
--

CREATE TABLE `faculty_schedules` (
  `professor_schedule_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `upload_id` int(11) DEFAULT NULL,
  `schedule_code` varchar(50) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course/year` varchar(50) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL DEFAULT '1st Semester',
  `school_year` varchar(9) NOT NULL DEFAULT '2025-2026',
  `status` enum('active','archived') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `matched_schedules`
--

CREATE TABLE `matched_schedules` (
  `matched_id` int(11) NOT NULL,
  `student_schedule_id` int(11) NOT NULL,
  `professor_schedule_id` int(11) DEFAULT NULL,
  `match_status` enum('matched','no_match','pending','conflict') NOT NULL,
  `matched_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_uploads`
--

CREATE TABLE `schedule_uploads` (
  `upload_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('student','faculty') NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `stored_file_path` varchar(255) DEFAULT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL DEFAULT '1st Semester',
  `school_year` varchar(9) NOT NULL DEFAULT '2025-2026',
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_uploads`
--

INSERT INTO `schedule_uploads` (`upload_id`, `user_id`, `role`, `original_filename`, `stored_file_path`, `semester`, `school_year`, `uploaded_at`) VALUES
(1, 3, 'student', '708992628_1541942054374463_4020655418090965201_n.png', '../uploads/schedules/student/student_schedule_3_1780394610_653529fb.png', '2nd Semester', '2025-2026', '2026-06-02 18:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_number` int(11) NOT NULL,
  `program` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `student_number`, `program`) VALUES
(1, 3, 202400441, 'BSCS');

-- --------------------------------------------------------

--
-- Table structure for table `student_schedules`
--

CREATE TABLE `student_schedules` (
  `student_schedule_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `upload_id` int(11) DEFAULT NULL,
  `schedule_code` varchar(50) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_description` varchar(255) NOT NULL,
  `prof_name` varchar(255) DEFAULT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `day` varchar(50) NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL DEFAULT '1st Semester',
  `school_year` varchar(9) NOT NULL DEFAULT '2025-2026',
  `status` enum('active','archived') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_schedules`
--

INSERT INTO `student_schedules` (`student_schedule_id`, `student_id`, `upload_id`, `schedule_code`, `course_code`, `course_description`, `prof_name`, `time_start`, `time_end`, `day`, `room`, `semester`, `school_year`, `status`) VALUES
(1, 1, 1, '202522442', 'GNED08', 'UNDERSTANDING THE SELF', NULL, '13:00:00', '14:00:00', 'W', 'TBA', '2nd Semester', '2025-2026', 'active'),
(2, 1, 1, '202522442', 'GNED08', 'UNDERSTANDING THE SELF', NULL, '15:00:00', '17:00:00', 'W', 'TBA', '2nd Semester', '2025-2026', 'active'),
(3, 1, 1, '202522443', 'GNED14', 'PANITIKANG FANLIPUNAN / SOSYEDAD AT LITERATURA', NULL, '14:00:00', '15:00:00', 'W', 'TBA', '2nd Semester', '2025-2026', 'active'),
(4, 1, 1, '202522443', 'GNED14', 'PANITIKANG FANLIPUNAN / SOSYEDAD AT LITERATURA', NULL, '13:00:00', '15:00:00', 'W', 'TBA', '2nd Semester', '2025-2026', 'active'),
(5, 1, 1, '202522444', 'MATH2C', 'CALCULUS', NULL, '16:00:00', '15:00:00', 'W', 'TBA', '2nd Semester', '2025-2026', 'active'),
(6, 1, 1, '202522444', 'MATH2C', 'CALCULUS', NULL, '16:00:00', '15:00:00', 'TH', 'TBA', '2nd Semester', '2025-2026', 'active'),
(7, 1, 1, '202522445', 'WW10', 'COSC SA ARCHITECTURE AND ORGANIZATION', NULL, '07:00:00', '09:00:00', 'F', 'TBA', '2nd Semester', '2025-2026', 'active'),
(8, 1, 1, '202522445', 'WW10', 'COSC SA ARCHITECTURE AND ORGANIZATION', NULL, '18:00:00', '19:00:00', 'TH', 'TBA', '2nd Semester', '2025-2026', 'active'),
(9, 1, 1, '202522446', 'COSC70A', 'SOFTWARE ENGINEERING', NULL, '07:00:00', '09:00:00', 'T', 'TBA', '2nd Semester', '2025-2026', 'active'),
(10, 1, 1, '202522446', 'COSC70A', 'SOFTWARE ENGINEERING', NULL, '18:00:00', '19:00:00', 'TH', 'TBA', '2nd Semester', '2025-2026', 'active'),
(11, 1, 1, '202522447', 'BEIT2S', 'DATA STRUCTURES AND ALGORITHMS', NULL, '16:00:00', '18:00:00', 'T', 'TBA', '2nd Semester', '2025-2026', 'active'),
(12, 1, 1, '202522447', 'BEIT2S', 'DATA STRUCTURES AND ALGORITHMS', NULL, '16:00:00', '17:00:00', 'W', 'TBA', '2nd Semester', '2025-2026', 'active'),
(13, 1, 1, '202522447', 'BEIT2S', 'DATA STRUCTURES AND ALGORITHMS', NULL, '13:00:00', '15:00:00', 'F', 'TBA', '2nd Semester', '2025-2026', 'active'),
(14, 1, 1, '202522448', 'TH17', 'DCIT ADVANCED DATABASE MANAGEMENT SYSTEM', NULL, '07:00:00', '09:00:00', 'T', 'TBA', '2nd Semester', '2025-2026', 'active'),
(15, 1, 1, '202522448', 'TH17', 'DCIT ADVANCED DATABASE MANAGEMENT SYSTEM', NULL, '18:00:00', '10:00:00', 'F', 'TBA', '2nd Semester', '2025-2026', 'active'),
(16, 1, 1, '202522449', 'FITT4', 'PHYSICAL ACTIVITIES TOWARDS HEALTH & FITNESS', NULL, '13:00:00', '15:00:00', 'TH', 'GYM', '2nd Semester', '2025-2026', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `current_semester` enum('1st Semester','2nd Semester') NOT NULL DEFAULT '1st Semester',
  `current_school_year` varchar(9) NOT NULL DEFAULT '2025-2026'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `current_semester`, `current_school_year`) VALUES
(1, '2nd Semester', '2025-2026');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','faculty','admin') NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `fullname`, `email`, `password`, `role`, `profile_picture`) VALUES
(1, 'System Administrator', 'admin@cvsu.edu.ph', '$2y$10$5rlbf6N5cfdOw8KKJFj/uezX68stLOEnaGwSLpKlsUulybOcEz5ry', 'admin', NULL),
(3, 'Bryron Gabriel Lim', 'bryrongabriel.lim@cvsu.edu.ph', '$2y$10$AcXmERWNiE6hzB/djznK9.gzwVYaLX6z4jUyDNJNeOQVi/qlvtDYW', 'student', NULL),
(4, 'danna', 'danna@cvsu.edu.ph', '$2y$10$eadSz1l1u91ufLmAzCDq3uggR8AXIHVSkmGb1bGOx.bNRdyPpRgSu', 'admin', NULL),
(5, 'Cassie Magistrado', 'cassie@cvsu.edu.ph', '$2y$10$UJ/HmUx0V2mfxr.eeK8QFemC.N1n5KXbGXfYOgbVl1z1W/d/E3HMm', 'faculty', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`faculty_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `faculty_schedules`
--
ALTER TABLE `faculty_schedules`
  ADD PRIMARY KEY (`professor_schedule_id`),
  ADD KEY `professor_id` (`faculty_id`),
  ADD KEY `upload_id` (`upload_id`);

--
-- Indexes for table `matched_schedules`
--
ALTER TABLE `matched_schedules`
  ADD PRIMARY KEY (`matched_id`),
  ADD KEY `student_schedule_id` (`student_schedule_id`,`professor_schedule_id`),
  ADD KEY `professor_schedule_id` (`professor_schedule_id`);

--
-- Indexes for table `schedule_uploads`
--
ALTER TABLE `schedule_uploads`
  ADD PRIMARY KEY (`upload_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `student_schedules`
--
ALTER TABLE `student_schedules`
  ADD PRIMARY KEY (`student_schedule_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `upload_id` (`upload_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `email_2` (`email`),
  ADD UNIQUE KEY `email_5` (`email`),
  ADD UNIQUE KEY `email_6` (`email`),
  ADD KEY `email_3` (`email`),
  ADD KEY `email_4` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `faculties`
--
ALTER TABLE `faculties`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty_schedules`
--
ALTER TABLE `faculty_schedules`
  MODIFY `professor_schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `matched_schedules`
--
ALTER TABLE `matched_schedules`
  MODIFY `matched_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_uploads`
--
ALTER TABLE `schedule_uploads`
  MODIFY `upload_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_schedules`
--
ALTER TABLE `student_schedules`
  MODIFY `student_schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `faculties`
--
ALTER TABLE `faculties`
  ADD CONSTRAINT `faculties_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `faculty_schedules`
--
ALTER TABLE `faculty_schedules`
  ADD CONSTRAINT `faculty_schedules_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `faculty_schedules_upload_fk` FOREIGN KEY (`upload_id`) REFERENCES `schedule_uploads` (`upload_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `matched_schedules`
--
ALTER TABLE `matched_schedules`
  ADD CONSTRAINT `matched_schedules_ibfk_1` FOREIGN KEY (`student_schedule_id`) REFERENCES `student_schedules` (`student_schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `matched_schedules_ibfk_2` FOREIGN KEY (`professor_schedule_id`) REFERENCES `faculty_schedules` (`professor_schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `schedule_uploads`
--
ALTER TABLE `schedule_uploads`
  ADD CONSTRAINT `schedule_uploads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_schedules`
--
ALTER TABLE `student_schedules`
  ADD CONSTRAINT `student_schedules_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `student_schedules_upload_fk` FOREIGN KEY (`upload_id`) REFERENCES `schedule_uploads` (`upload_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
