-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2025 at 06:00 PM
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
-- Database: `community_sharing`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `action` varchar(60) NOT NULL,
  `target_table` varchar(60) NOT NULL,
  `target_id` int(11) NOT NULL DEFAULT 0,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(254) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `ip`, `user_agent`, `created_at`) VALUES
(1, 'Burhan Uddin', 'borhanuddin1902@gmail.com', 'for book', 'thanks for this services', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-14 18:19:06'),
(2, 'Burhan Uddin', 'borhanuddin1902@gmail.com', 'for book', 'thanks for this services', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-14 18:23:53'),
(3, 'Burhan Uddin', 'borhanuddin1902@gmail.com', 'for book', 'thanks for this services', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-14 18:25:48');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`conversation_id`, `created_at`, `updated_at`) VALUES
(1, '2025-09-15 01:38:04', '2025-09-15 01:38:04'),
(2, '2025-09-15 01:38:04', '2025-09-15 01:38:04'),
(3, '2025-09-15 01:38:04', '2025-09-15 01:38:04');

-- --------------------------------------------------------

--
-- Table structure for table `conversation_participants`
--

CREATE TABLE `conversation_participants` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read_at` datetime DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversation_participants`
--

INSERT INTO `conversation_participants` (`id`, `conversation_id`, `user_id`, `last_read_at`, `joined_at`) VALUES
(1, 1, 1, NULL, '2025-09-15 01:38:04'),
(2, 1, 3, '2025-09-15 02:56:04', '2025-09-15 01:38:04'),
(3, 2, 3, '2025-09-15 02:56:23', '2025-09-15 01:38:04'),
(4, 2, 4, NULL, '2025-09-15 01:38:04'),
(5, 3, 3, '2025-09-15 02:53:52', '2025-09-15 01:38:04'),
(6, 3, 8, NULL, '2025-09-15 01:38:04');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `giver_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `condition_status` enum('new','like_new','good','fair','poor') DEFAULT 'good',
  `availability_status` enum('available','unavailable','pending') DEFAULT 'available',
  `posting_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_url` varchar(255) DEFAULT NULL,
  `pickup_location` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `giver_id`, `title`, `description`, `category`, `condition_status`, `availability_status`, `posting_date`, `image_url`, `pickup_location`) VALUES
(3, 3, 'Kitchen Appliances', 'Blender and toaster set', 'Kitchen', 'good', 'pending', '2025-08-21 07:11:41', 'uploads/items/6009f0b30bc58c629debe9f6c14c1984.jpg', '456 Oak Ave'),
(4, 3, 'php book', 'book for read', 'Books', 'good', 'available', '2025-08-23 10:08:11', 'uploads/items/7937dddd6bb013f98f2b47fc2d8665a0.jpg', '456 Oak Ave');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `message_content` text NOT NULL,
  `sent_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `conversation_id` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `sender_deleted_at` datetime DEFAULT NULL,
  `recipient_deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `request_id`, `subject`, `message_content`, `sent_date`, `is_read`, `conversation_id`, `read_at`, `sender_deleted_at`, `recipient_deleted_at`) VALUES
(1, 1, 3, NULL, 'for book', 'good job', '2025-08-21 18:05:49', 1, 1, '2025-09-15 01:38:07', NULL, NULL),
(2, 4, 3, NULL, 'for book', 'thanks for give the book', '2025-08-21 18:08:49', 1, 2, '2025-09-15 01:38:10', NULL, NULL),
(3, 3, 4, NULL, 'for book', 'you are welcome', '2025-08-21 18:10:10', 1, 2, NULL, NULL, NULL),
(5, 3, 8, NULL, 'just hi', 'hello! whats up?', '2025-09-14 17:43:00', 1, 3, NULL, NULL, NULL),
(6, 8, 3, NULL, 'just hi', 'Hi!', '2025-09-14 17:53:23', 1, 3, '2025-09-15 01:38:12', NULL, NULL),
(7, 3, 4, NULL, 'just hi', 'hello!', '2025-09-14 18:19:21', 0, 2, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(40) NOT NULL,
  `subject` varchar(160) NOT NULL,
  `body` text DEFAULT NULL,
  `related_type` varchar(40) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `otp_code`, `expires_at`, `used_at`, `created_at`) VALUES
(20, 13, 'mbbmbmmb1@gmail.com', '619260', '2025-09-14 13:28:47', '2025-09-14 18:29:05', '2025-09-14 18:28:47'),
(21, 13, 'mbbmbmmb1@gmail.com', '880075', '2025-09-14 13:29:05', '2025-09-14 18:33:02', '2025-09-14 18:29:05'),
(22, 13, 'mbbmbmmb1@gmail.com', '030293', '2025-09-14 13:33:02', '2025-09-14 18:34:42', '2025-09-14 18:33:02'),
(23, 13, 'mbbmbmmb1@gmail.com', '606493', '2025-09-14 13:34:42', '2025-09-14 18:42:39', '2025-09-14 18:34:42'),
(24, 13, 'mbbmbmmb1@gmail.com', '030425', '2025-09-14 13:42:39', '2025-09-14 18:49:54', '2025-09-14 18:42:39'),
(25, 13, 'mbbmbmmb1@gmail.com', '748093', '2025-09-14 13:49:54', '2025-09-14 19:00:59', '2025-09-14 18:49:54'),
(26, 13, 'mbbmbmmb1@gmail.com', '086176', '2025-09-14 14:00:59', NULL, '2025-09-14 19:00:59');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `giver_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `request_type` enum('item','service') NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `response_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`request_id`, `requester_id`, `giver_id`, `item_id`, `service_id`, `request_type`, `message`, `status`, `request_date`, `response_date`) VALUES
(6, 4, 3, 3, NULL, 'item', 'i need this for 1 week', 'completed', '2025-09-10 14:26:35', NULL),
(8, 4, 3, 4, NULL, 'item', 'i need these book for read', 'pending', '2025-09-14 18:21:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewed_user_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `review_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `giver_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `posting_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `availability` enum('available','busy','unavailable') DEFAULT 'available',
  `location` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `giver_id`, `title`, `description`, `category`, `posting_date`, `availability`, `location`) VALUES
(2, 3, 'Tutoring Service', 'Math and science tutoring for high school students', 'Education', '2025-08-21 07:11:41', 'available', 'City Center');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token_hash` char(64) DEFAULT NULL,
  `verification_expires_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','giver','seeker') NOT NULL DEFAULT 'seeker',
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `email_verified`, `verification_token_hash`, `verification_expires_at`, `password_hash`, `first_name`, `last_name`, `phone`, `address`, `role`, `registration_date`, `last_login`, `status`) VALUES
(1, 'admin', 'admin@community.com', 1, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'System', 'Administrator', NULL, NULL, 'admin', '2025-08-21 07:11:41', NULL, 1),
(3, 'giver1', 'giver1@example.com', 1, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'Alice', 'Smith', '015647979454', '408/1, Kuratoli, Khilkhet', 'giver', '2025-08-21 07:11:41', NULL, 1),
(4, 'seeker1', 'seeker1@example.com', 1, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'Bob', 'Johnson', NULL, NULL, 'seeker', '2025-08-21 07:11:41', NULL, 1),
(5, 'seeker2', 'seeker2@example.com', 1, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'Carol', 'Wilson', NULL, NULL, 'seeker', '2025-08-21 07:11:41', NULL, 1),
(6, 'seeker3', 'borhanuddin1902@gmail.com', 1, NULL, NULL, '$2y$12$EReGhm7yVqN7I7snMMpVz.NiR.KnSFmqBszznxDMQG6JgnGVrRHni', 'Burhan', 'Uddin', '+880 1932550019', 'Dhaka,Bangladesh', 'seeker', '2025-08-22 09:28:55', NULL, 1),
(8, 'farhana', '22-49945-3@student.aiub.edu', 1, NULL, NULL, '$2y$12$Qxk8KtCNhweT/gDS7Bgb8uhjF5BoFmjizVIpGPGh/7tuwQR26m7Ra', NULL, NULL, NULL, NULL, 'giver', '2025-09-11 15:47:52', NULL, 1),
(12, 'test123', 'burhanuddin49945@gmail.com', 1, NULL, NULL, '$2y$12$xRYp58oPP6z4aixXNozVNuf/A8s24zRXcQ3SB5Q2c5rUznD74eKUa', NULL, NULL, NULL, NULL, 'giver', '2025-09-14 11:00:45', NULL, 1),
(13, 'test456', 'mbbmbmmb1@gmail.com', 1, NULL, NULL, '$2y$12$9geEWUROG.jzSKusJBGK5ulWqfcUj0U4r4qIIQQBqK.fJNa5DRDSm', NULL, NULL, NULL, NULL, 'seeker', '2025-09-14 11:02:23', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `verification_tokens`
--

CREATE TABLE `verification_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_tokens`
--

INSERT INTO `verification_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 8, 'c808a317b7df8c40fd5d425414d7f42c672b30e7124bc81080e5fcf7175f66a3', '2025-09-12 15:47:52', '2025-09-11 21:49:12', '2025-09-11 21:47:52'),
(2, 9, 'a117211e4ecdbcfd4b96c7932d7a795b203aa55f2ba6db355b13b376626f422a', '2025-09-12 16:14:04', NULL, '2025-09-11 22:14:04'),
(3, 9, '539d99c17e7cdb3c25cb748fd68814559ec5db9ebc47585d219733d1e2755913', '2025-09-12 16:16:23', NULL, '2025-09-11 22:16:23'),
(4, 9, '4aafa4e69c94c9d0597bfd156a9d002075c517e30291e36dbe23eccdea83fc39', '2025-09-12 16:28:08', NULL, '2025-09-11 22:28:08'),
(5, 9, '89c74c91ad5778c9c7956fc5f1229c904e070b4b88191ffd4da24735f8114648', '2025-09-12 16:29:23', NULL, '2025-09-11 22:29:23'),
(6, 9, '4a1130d44d5839ea0327f192d8a897b216624ccd35f398db2f8138e1dc808290', '2025-09-12 16:31:26', NULL, '2025-09-11 22:31:26'),
(7, 11, '8fc024adf4ddaf146fd50a96f480e5f8aec50523e60bda05178e5694ce3f36dc', '2025-09-15 10:47:48', '2025-09-14 16:54:02', '2025-09-14 16:47:48'),
(8, 11, '3ff1ff3fb56239ae052411e1531e8755da1bbe793fa3005fa9017f8516a6afd0', '2025-09-15 10:54:02', '2025-09-14 16:54:19', '2025-09-14 16:54:02'),
(9, 12, '1bc483f98ef44b7d0bd2790f526b0d2690484052797b14c77dad4ba5a1990330', '2025-09-15 11:00:45', '2025-09-14 17:01:17', '2025-09-14 17:00:45'),
(10, 13, '128ad1f91d3a0b03383ac54e4217a0602313a0ec99a01219ee2625fd3a8c3768', '2025-09-15 11:02:23', '2025-09-14 17:03:22', '2025-09-14 17:02:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin` (`admin_user_id`),
  ADD KEY `idx_target` (`target_table`,`target_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`);

--
-- Indexes for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_conv_user` (`conversation_id`,`user_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_items_status` (`availability_status`),
  ADD KEY `idx_items_category` (`category`),
  ADD KEY `idx_items_giver` (`giver_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `idx_conv` (`conversation_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_related` (`related_type`,`related_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_user_used` (`user_id`,`used_at`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_requester` (`requester_id`),
  ADD KEY `idx_requests_item` (`item_id`),
  ADD KEY `idx_requests_service` (`service_id`),
  ADD KEY `fk_requests_giver` (`giver_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `reviewed_user_id` (`reviewed_user_id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `idx_services_status` (`availability`),
  ADD KEY `idx_services_category` (`category`),
  ADD KEY `idx_services_giver` (`giver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token_hash` (`token_hash`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD CONSTRAINT `fk_cp_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_giver` FOREIGN KEY (`giver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE SET NULL;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `fk_requests_giver` FOREIGN KEY (`giver_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_requests_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_requests_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_requests_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewed_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE SET NULL;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_giver` FOREIGN KEY (`giver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
