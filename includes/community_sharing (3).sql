-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 14, 2025 at 12:28 PM
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
(1, 2, 'Vintage Bicycle', 'Classic red bicycle in good condition', 'Transportation', 'good', 'pending', '2025-08-21 07:11:41', NULL, '123 Main St'),
(2, 2, 'Programming Books', 'Collection of JavaScript and PHP books', 'Education', 'like_new', 'pending', '2025-08-21 07:11:41', NULL, '123 Main St'),
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
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `request_id`, `subject`, `message_content`, `sent_date`, `is_read`) VALUES
(1, 1, 3, NULL, 'for book', 'good job', '2025-08-21 18:05:49', 1),
(2, 4, 3, NULL, 'for book', 'thanks for give the book', '2025-08-21 18:08:49', 1),
(3, 3, 4, NULL, 'for book', 'you are welcome', '2025-08-21 18:10:10', 1),
(4, 2, 4, NULL, 'for book', 'kjkl', '2025-08-22 17:51:06', 1);

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
(1, 6, 'borhanuddin1902@gmail.com', '167287', '2025-08-25 12:46:58', '2025-08-25 18:39:29', '2025-08-25 18:36:58'),
(2, 6, 'borhanuddin1902@gmail.com', '596712', '2025-08-25 12:49:29', '2025-08-25 18:42:57', '2025-08-25 18:39:29'),
(3, 6, 'borhanuddin1902@gmail.com', '734673', '2025-08-25 12:52:57', '2025-08-25 18:45:20', '2025-08-25 18:42:57'),
(4, 6, 'borhanuddin1902@gmail.com', '664938', '2025-08-25 12:55:20', '2025-08-25 18:47:43', '2025-08-25 18:45:20'),
(5, 6, 'borhanuddin1902@gmail.com', '547229', '2025-08-25 12:57:43', '2025-08-25 18:56:39', '2025-08-25 18:47:43'),
(6, 6, 'borhanuddin1902@gmail.com', '746044', '2025-08-25 13:06:39', '2025-08-25 18:57:57', '2025-08-25 18:56:39'),
(7, 6, 'borhanuddin1902@gmail.com', '904340', '2025-08-25 13:07:57', '2025-08-25 18:59:04', '2025-08-25 18:57:57'),
(8, 6, 'borhanuddin1902@gmail.com', '496202', '2025-08-25 13:09:04', '2025-08-25 19:09:47', '2025-08-25 18:59:04'),
(9, 6, 'borhanuddin1902@gmail.com', '229051', '2025-08-25 13:19:47', '2025-08-25 19:09:51', '2025-08-25 19:09:47'),
(10, 6, 'borhanuddin1902@gmail.com', '892912', '2025-08-25 13:19:51', '2025-08-25 20:39:49', '2025-08-25 19:09:51'),
(11, 6, 'borhanuddin1902@gmail.com', '769534', '2025-08-25 14:49:49', '2025-08-25 21:01:26', '2025-08-25 20:39:49'),
(12, 6, 'borhanuddin1902@gmail.com', '540101', '2025-08-25 15:11:26', NULL, '2025-08-25 21:01:26');

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
(1, 4, 2, 2, NULL, 'item', 'for read', 'completed', '2025-08-21 17:47:17', '2025-08-22 04:35:21'),
(2, 2, 2, 1, NULL, 'item', 'for cycling one day', 'completed', '2025-08-21 18:38:04', '2025-08-22 04:35:19'),
(3, 4, 2, 1, NULL, 'item', 'jkl', 'completed', '2025-08-22 17:49:11', '2025-08-23 03:39:06'),
(4, 1, 2, NULL, 1, 'service', '', 'completed', '2025-09-05 20:29:58', NULL),
(5, 1, 2, 2, NULL, 'item', '', 'completed', '2025-09-05 20:30:20', NULL),
(6, 4, 3, 3, NULL, 'item', 'i need this for 1 week', 'completed', '2025-09-10 14:26:35', NULL),
(7, 9, 3, 4, NULL, 'item', '', 'pending', '2025-09-11 16:31:19', NULL);

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

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `reviewer_id`, `reviewed_user_id`, `request_id`, `rating`, `comment`, `review_date`) VALUES
(1, 4, 2, 1, 5, 'good book', '2025-08-21 18:07:57'),
(2, 2, 2, 2, 5, ';lk', '2025-08-22 17:50:50'),
(3, 2, 4, 3, 5, 'JI', '2025-08-24 11:17:22'),
(4, 2, 4, 1, 5, '...', '2025-08-25 16:33:31');

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
(1, 2, 'Computer Repair', 'Professional computer and laptop repair services', 'Technology', '2025-08-21 07:11:41', 'available', 'Downtown Area'),
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
(1, 'admin', 'admin@community.com', 0, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'System', 'Administrator', NULL, NULL, 'admin', '2025-08-21 07:11:41', NULL, 1),
(2, 'testuser', 'testuser@example.com', 0, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'John', 'Doe', NULL, NULL, 'giver', '2025-08-21 07:11:41', NULL, 1),
(3, 'giver1', 'giver1@example.com', 0, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'Alice', 'Smith', '015647979454', '408/1, Kuratoli, Khilkhet', 'giver', '2025-08-21 07:11:41', NULL, 1),
(4, 'seeker1', 'seeker1@example.com', 0, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'Bob', 'Johnson', NULL, NULL, 'seeker', '2025-08-21 07:11:41', NULL, 1),
(5, 'seeker2', 'seeker2@example.com', 0, NULL, NULL, '$2y$10$owVkxDSiLfABrmoZ9IpSN.5ARnKUGjK7QTdeQXUFtu1HDruVrLasW', 'Carol', 'Wilson', NULL, NULL, 'seeker', '2025-08-21 07:11:41', NULL, 1),
(6, 'seeker3', 'borhanuddin1902@gmail.com', 0, NULL, NULL, '$2y$12$EReGhm7yVqN7I7snMMpVz.NiR.KnSFmqBszznxDMQG6JgnGVrRHni', 'Burhan', 'Uddin', '+880 1932550019', 'Dhaka,Bangladesh', 'seeker', '2025-08-22 09:28:55', NULL, 1),
(8, 'farhana', '22-49945-3@student.aiub.edu', 0, NULL, NULL, '$2y$12$Qxk8KtCNhweT/gDS7Bgb8uhjF5BoFmjizVIpGPGh/7tuwQR26m7Ra', NULL, NULL, NULL, NULL, 'giver', '2025-09-11 15:47:52', NULL, 1),
(9, 'farhana007', 'burhanuddin49945@gmail.com', 0, NULL, NULL, '$2y$12$WFkyxmYYGKVQcSKei3zMweUaYBxHIcchAS7B3IbY9Iy63ZkuezuoS', NULL, NULL, NULL, NULL, 'seeker', '2025-09-11 16:14:04', NULL, 1),
(10, 'test123', 'mbbmbmmb1@gmail.com', 0, 'd03415bc8758ed854e65935258e0cb12db9047c41f38116fe3f7e9fe535742b0', '2025-09-14 21:00:31', '$2y$12$MA0AwNeXh2Q.3g/6Sx1M8eiTkTDGDyUFCvo/qKOMtmW5if70sV166', NULL, NULL, NULL, NULL, 'seeker', '2025-09-13 21:00:31', NULL, 1);

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
(6, 9, '4a1130d44d5839ea0327f192d8a897b216624ccd35f398db2f8138e1dc808290', '2025-09-12 16:31:26', NULL, '2025-09-11 22:31:26');

--
-- Indexes for dumped tables
--

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
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

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
