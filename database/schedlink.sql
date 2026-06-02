-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 27, 2026 at 01:08 PM
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
  `professor_id` int(11) NOT NULL,
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
  `professor_id` int(11) NOT NULL,
  `upload_id` int(11) DEFAULT NULL,
  `schedule_code` varchar(50) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_description` varchar(255) NOT NULL,
  `day` varchar(50) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
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
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_number` int(11) NOT NULL,
  `program` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `day` varchar(50) NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL DEFAULT '1st Semester',
  `school_year` varchar(9) NOT NULL DEFAULT '2025-2026',
  `status` enum('active','archived') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 'Bryron Gabriel Lim', 'bryrongabriel.lim@cvsu.edu.ph', '$2y$10$Q6m.EaHsmivr6mfSi6WJweBODmkOZNlBGTaIHAWwwjhWY/pRTleKK', 'student', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`professor_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `faculty_schedules`
--
ALTER TABLE `faculty_schedules`
  ADD PRIMARY KEY (`professor_schedule_id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `upload_id` (`upload_id`);

--
-- Indexes for table `matched_schedules`
--
ALTER TABLE `matched_schedules`
  ADD PRIMARY KEY (`matched_id`),
  ADD KEY `student_schedule_id` (`student_schedule_id`,`professor_schedule_id`),
  ADD KEY `professor_schedule_id` (`professor_schedule_id`);

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
-- Indexes for table `schedule_uploads`
--
ALTER TABLE `schedule_uploads`
  ADD PRIMARY KEY (`upload_id`),
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `professor_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_schedules`
--
ALTER TABLE `student_schedules`
  MODIFY `student_schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_uploads`
--
ALTER TABLE `schedule_uploads`
  MODIFY `upload_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  ADD CONSTRAINT `faculty_schedules_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `faculties` (`professor_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `faculty_schedules_upload_fk` FOREIGN KEY (`upload_id`) REFERENCES `schedule_uploads` (`upload_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `matched_schedules`
--
ALTER TABLE `matched_schedules`
  ADD CONSTRAINT `matched_schedules_ibfk_1` FOREIGN KEY (`student_schedule_id`) REFERENCES `student_schedules` (`student_schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `matched_schedules_ibfk_2` FOREIGN KEY (`professor_schedule_id`) REFERENCES `faculty_schedules` (`professor_schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE;

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

--
-- Constraints for table `schedule_uploads`
--
ALTER TABLE `schedule_uploads`
  ADD CONSTRAINT `schedule_uploads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
