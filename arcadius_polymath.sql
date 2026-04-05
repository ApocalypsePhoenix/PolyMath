-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 05, 2026 at 03:29 PM
-- Server version: 11.4.8-MariaDB-log
-- PHP Version: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `arcadius_polymath`
--

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `class_name`, `created_at`) VALUES
(1, 'Class A', '2026-03-30 17:41:48'),
(2, 'Class B', '2026-03-30 17:41:48'),
(3, 'Class C', '2026-03-30 17:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `levels`
--

CREATE TABLE `levels` (
  `level_id` int(11) NOT NULL,
  `level_name` varchar(50) NOT NULL,
  `difficulty_rank` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Dumping data for table `levels`
--

INSERT INTO `levels` (`level_id`, `level_name`, `difficulty_rank`) VALUES
(1, 'Easy', 1),
(2, 'Intermediate', 2),
(3, 'Expert', 3);

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `question_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_option` char(1) NOT NULL,
  `solution_text` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `question_image` varchar(255) DEFAULT NULL,
  `solution_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`question_id`, `level_id`, `quiz_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `solution_text`, `created_by`, `question_image`, `solution_image`) VALUES
(1, 1, 1, 'Test Q1', '1', '2', '3', '4', 'A', 'TestSol', 3, NULL, NULL),
(2, 1, 1, 'Test Q2', '1', '2', '3', '4', 'B', 'test', 3, NULL, NULL),
(3, 1, 1, 'Test Q3', '1', '2', '3', '4', 'C', 'ddsf', 3, NULL, NULL),
(4, 2, 1, 'Test Q4', '1', '2', '3', '4', 'C', 'TestSol', 3, NULL, NULL),
(5, 2, 1, 'Test Q5', '13', '44', '23', '53', 'D', 'fsgdfg', 3, NULL, NULL),
(6, 2, 1, 'Test Q6', '12', '42', '32', '57', 'C', 'fdbxdzz', 3, NULL, NULL),
(7, 2, 1, 'Test Q7', '1234', '64543', '3524', '342', 'B', 'xfgfbd', 3, NULL, NULL),
(8, 2, 1, 'Test Q8', '46', '3324', '453', '656', 'C', 'jhgfred', 3, NULL, NULL),
(9, 2, 1, 'Test Q9', '6454', '321', '234', '54353', 'B', 'nhbgfd', 3, NULL, NULL),
(11, 2, 1, 'Test Q10', '3423', '21', '553543', '3123', 'B', 'dhgfgsdg', 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quiz_id` int(11) NOT NULL,
  `quiz_name` varchar(255) NOT NULL,
  `level_id` int(11) NOT NULL,
  `is_timed` tinyint(1) DEFAULT 0,
  `time_limit` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_published` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`quiz_id`, `quiz_name`, `level_id`, `is_timed`, `time_limit`, `created_at`, `is_published`) VALUES
(1, 'Test 1', 2, 0, 300, '2026-04-04 17:12:25', 1);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `attempt_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `score` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `time_taken_seconds` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Dumping data for table `quiz_attempts`
--

INSERT INTO `quiz_attempts` (`attempt_id`, `user_id`, `level_id`, `quiz_id`, `score`, `total_questions`, `time_taken_seconds`, `completed_at`) VALUES
(5, 3, 2, 1, 4, 10, 16, '2026-04-04 20:10:55'),
(6, 3, 2, 1, 4, 10, 16, '2026-04-04 20:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `role` enum('student','admin') DEFAULT 'student'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `class_id`, `role`) VALUES
(3, 'IsacAdmin', 'xtreme4mil@gmail.com', '$2y$10$ja4ymEbCM9J8827kwDjcdu4QxzQOvlR7IFzkbO1OMz0TushoRsfa6', 1, 'admin'),
(4, 'Isac777', 'isacarcrussell777@gmail.com', '$2y$10$uFNERELZH6RUw1vE8ysmfue/dTvcGSsH.UES6/aC4DrgbHrVQIsue', 1, 'student');

-- --------------------------------------------------------

--
-- Table structure for table `user_answers`
--

CREATE TABLE `user_answers` (
  `answer_id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `chosen_option` char(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Dumping data for table `user_answers`
--

INSERT INTO `user_answers` (`answer_id`, `attempt_id`, `question_id`, `is_correct`, `chosen_option`) VALUES
(31, 5, 3, 0, 'A'),
(32, 5, 5, 0, 'B'),
(33, 5, 4, 1, 'C'),
(34, 5, 7, 0, 'D'),
(35, 5, 9, 1, 'B'),
(36, 5, 2, 1, 'B'),
(37, 5, 1, 0, 'B'),
(38, 5, 11, 0, 'A'),
(39, 5, 8, 0, 'D'),
(40, 5, 6, 1, 'C'),
(41, 6, 7, 0, 'A'),
(42, 6, 11, 1, 'B'),
(43, 6, 8, 1, 'C'),
(44, 6, 3, 0, 'D'),
(45, 6, 1, 0, 'B'),
(46, 6, 5, 0, 'B'),
(47, 6, 6, 0, 'A'),
(48, 6, 4, 1, 'C'),
(49, 6, 9, 0, 'D'),
(50, 6, 2, 1, 'B');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `class_name` (`class_name`);

--
-- Indexes for table `levels`
--
ALTER TABLE `levels`
  ADD PRIMARY KEY (`level_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quiz_id`),
  ADD KEY `level_id` (`level_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `level_id` (`level_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `user_answers`
--
ALTER TABLE `user_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `levels`
--
ALTER TABLE `levels`
  MODIFY `level_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quiz_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_answers`
--
ALTER TABLE `user_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`level_id`) REFERENCES `levels` (`level_id`);

--
-- Constraints for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`level_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_answers`
--
ALTER TABLE `user_answers`
  ADD CONSTRAINT `user_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`attempt_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
