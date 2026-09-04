-- =========================================================
-- College Canteen Management System - Unified Database Setup
-- Database Name: canteen_db
-- Compatible with: MySQL 5.7+, MySQL 8.0+, MariaDB 10.4+
-- =========================================================

CREATE DATABASE IF NOT EXISTS `canteen_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `canteen_db`;

-- --------------------------------------------------------
-- Table structure for `admins` & `admin`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default admin: user: admin / pass: 12345 (supports bcrypt & plaintext)
INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '12345', NOW())
ON DUPLICATE KEY UPDATE `password` = '12345';

-- Compatibility table/alias for legacy 'admin'
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '12345', NOW())
ON DUPLICATE KEY UPDATE `password` = '12345';


-- --------------------------------------------------------
-- Table structure for `categories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Breakfast'),
(2, 'Snacks'),
(3, 'Beverages'),
(4, 'Lunch'),
(5, 'Desserts')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);


-- --------------------------------------------------------
-- Table structure for `products`
-- Note: Includes both `product_name` and `name` for full compatibility
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(200) NOT NULL,
  `name` varchar(200) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'Snacks',
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT 'Burger.jpg',
  `description` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` (`id`, `product_name`, `name`, `category`, `price`, `image`, `description`, `status`) VALUES
(1, 'Chicken Biryani', 'Chicken Biryani', 'Lunch', 120.00, 'Chicken Biryani.jpg', 'Spicy chicken biryani with rice and fresh herbs.', 'Available'),
(2, 'Sandwich', 'Sandwich', 'Snacks', 110.00, 'Sandwich.jpg', 'Fresh veggie sandwich with crispy toast.', 'Available'),
(3, 'Noodles', 'Noodles', 'Lunch', 130.00, 'Noodles.jpg', 'Hot noodles with vegetables and sauces.', 'Available'),
(4, 'Tea', 'Tea', 'Beverages', 10.00, 'Tea.jpg', 'Hot refreshing tea.', 'Available'),
(5, 'Samosa', 'Samosa', 'Snacks', 25.00, 'Samosa.jpg', 'Crispy samosa with filling.', 'Available'),
(6, 'Cookies', 'Cookies', 'Snacks', 20.00, 'Cookies.jpg', 'Butter cookies and snacks.', 'Available'),
(7, 'Fresh Juice', 'Fresh Juice', 'Beverages', 50.00, 'Fresh Juice.jpg', 'Orange or mixed fruit juice.', 'Available'),
(8, 'French Fries', 'French Fries', 'Snacks', 60.00, 'French Fries.jpg', 'Crispy golden fries.', 'Available'),
(9, 'Idli', 'Idli', 'Breakfast', 40.00, 'Idli.jpg', 'Soft idli with sambar and chutney.', 'Available'),
(10, 'Vada', 'Vada', 'Breakfast', 15.00, 'Vada.jpg', 'Crispy vada snack.', 'Available'),
(11, 'Cake Slice', 'Cake Slice', 'Desserts', 25.00, 'Cake Slice.jpg', 'Fresh baked cake slice.', 'Available'),
(12, 'Burger', 'Burger', 'Snacks', 120.00, 'Burger.jpg', 'Juicy grilled burger with fresh lettuce and tomato.', 'Available'),
(13, 'Pizza', 'Pizza', 'Snacks', 180.00, 'pizza.jpg', 'Cheesy pizza with tomato sauce and herb toppings.', 'Available'),
(14, 'Dosa', 'Dosa', 'Breakfast', 90.00, 'dosa.jpg', 'Crispy masala dosa served with chutney and sambar.', 'Available'),
(15, 'Coffee', 'Coffee', 'Beverages', 60.00, 'coffee.jpg', 'Fresh hot coffee for a quick energy boost.', 'Available')
ON DUPLICATE KEY UPDATE `product_name` = VALUES(`product_name`), `name` = VALUES(`name`);


-- --------------------------------------------------------
-- Table structure for `users` (Students / Customers)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `regno` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `regno` (`regno`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Demo Student: mohanraj.s4211@gmail.com / pass: 12345
INSERT INTO `users` (`id`, `name`, `regno`, `department`, `email`, `password`, `created_at`) VALUES
(1, 'mohanraj', '13562', 'BCA', 'mohanraj.s4211@gmail.com', '12345', NOW())
ON DUPLICATE KEY UPDATE `password` = '12345';


-- --------------------------------------------------------
-- Table structure for `orders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `bank_utr` varchar(100) DEFAULT NULL,
  `merchant_order_id` varchar(255) DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT 'UroPay',
  `qr_code` mediumtext DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `food_status` varchar(50) NOT NULL DEFAULT 'Preparing',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `payment_id` (`payment_id`),
  KEY `bank_utr` (`bank_utr`),
  KEY `merchant_order_id` (`merchant_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table structure for `order_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table structure for `contacts` (Contact Feedback)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Compatibility table for `messages`
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(200) DEFAULT 'Feedback',
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table structure for `password_resets` (OTP Password Reset)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- End of Database Setup
-- =========================================================
