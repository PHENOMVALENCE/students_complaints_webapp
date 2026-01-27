-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 17, 2025 at 10:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `complaintsystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `student_username` varchar(50) NOT NULL,
  `complaint` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `student_username`, `complaint`, `status`, `response`, `created_at`) VALUES
(4, 'Idris@Gmail.com', 'Hakuna mwalimu', 'resolved', 'Poa', '2024-01-09 11:40:30'),
(6, 'Valence', 'Uneven food distribution in the DH', 'pending', NULL, '2024-01-15 05:06:44'),
(7, 'Idris@Gmail.com', 'Car parking slots', 'pending', NULL, '2024-01-15 05:07:29'),
(10, 'Nargis', 'Food shortages', 'resolved', 'Food has been ordered', '2025-12-17 05:29:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','teacher','admin') NOT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `approved`) VALUES
(1, 'Valence', '$2y$10$59yhYNBiSNPTaTkDikjyM.Xo1KwgskkiBqmh5xVCZg6qAUpujBOPO', 'student', 0),
(3, 'Rudolf', '$2y$10$JIH.gAT.mcuGQEyCxB9roujUfU2ClhhuC1Nf25jFmxlhmsW7coA/a', 'teacher', 1),
(4, 'Admin', '$2y$10$iTj3YkBLuINxSzU4NXs1jOTGjrG7K4dXqKc6hv7eaU2q78hkVa8cO', 'admin', 0),
(5, 'Idris@Gmail.com', '$2y$10$2cg2OgyvdK.1wXLE8o1l..rQH6MX0LSLSWu3OsIpZYSR2jwnM719m', 'student', 0),
(10, 'RobinHood', '$2y$10$zN8xNbmmtcT3rW3vYdRxCu2tfMndBULHF8LJQeVucpEi6Is1XiLei', 'teacher', 0),
(11, 'RobinHood20', '$2y$10$.bnEUpq.iq2csNga.ALWoea6XCpY8dBgXs5Ynu5hXKq.sHtCChaTu', 'teacher', 0),
(12, 'Allan', '$2y$10$upr0ykKTmaFWf8cB2g9lY.T7SE9vqU94RsLxlfkw41p7buYdkN/rW', 'student', 0),
(13, 'Nargis', '$2y$10$UjesU8SBi/YHDJAE4YrOoOecdDW4RR2Nuk798g1DcuLsTvbUOcDLq', 'student', 1),
(14, 'Betty', '$2y$10$aAmc/P8OGZy.SDHbxN2BkuuDJrZKpTtNK6.95bDAdbhc/q28MvvPi', 'teacher', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `student_username` (`student_username`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`student_username`) REFERENCES `users` (`username`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
