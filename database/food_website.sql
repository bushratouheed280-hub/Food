-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 13, 2026 at 07:22 PM
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
-- Database: `food_website`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_charges` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Cash on Delivery',
  `delivery_name` varchar(100) NOT NULL,
  `delivery_phone` varchar(30) NOT NULL,
  `delivery_address` text NOT NULL,
  `delivery_instructions` text DEFAULT NULL,
  `status` enum('Pending','Confirmed','Preparing','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `food_id` int(10) UNSIGNED DEFAULT NULL,
  `item_name` varchar(150) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `item_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `menu_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` text NOT NULL,
  `rating` decimal(3,2) NOT NULL DEFAULT 5.00,
  `popularity` int(11) NOT NULL DEFAULT 0,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`menu_id`, `name`, `description`, `category`, `price`, `image_url`, `rating`, `popularity`, `discount_percent`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 'Classic Burger', 'Classic, juicy, and satisfying with a golden grill finish.', 'burger', 12.99, 'https://images.unsplash.com/photo-1571091718767-18b5b1457add?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MTB8fGJ1cmdlcnxlbnwwfHwwfHx8MA%3D%3D', 5.00, 98, 20.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(2, 'Chicken Chowmein', 'Wok-tossed noodles with savory chicken and fresh vegetables.', 'burger', 10.50, 'https://media.istockphoto.com/id/1267070690/photo/japanese-local-food-hita-yakisoba-hita-city-oita-prefecture.jpg?s=612x612&w=0&k=20&c=tRCXSP9y-KCuLDeusTr4Nj7AlSQlqFE7L6vj-srWJ5k=', 5.00, 88, 0.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(3, 'Chicken Karahi', 'Rich, aromatic chicken karahi with bold Pakistani spices.', 'burger', 13.20, 'https://images.unsplash.com/photo-1759392773285-0f86affdf1df?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDU0fHx8ZW58MHx8fHx8', 5.00, 91, 0.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(4, 'Spicy Chicken Grill', 'Char-grilled chicken with a fiery kick and smoky finish.', 'burger', 10.99, 'https://images.unsplash.com/photo-1708657950896-1acb4ede7fad?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDY2fHx8ZW58MHx8fHx8', 5.00, 92, 20.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(5, 'Smoky Chicken Plate', 'A hearty chicken plate with smoky flavor and fresh sides.', 'burger', 14.60, 'https://images.unsplash.com/photo-1736239093375-34f0a2f33c7d?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MTZ8fGNoaWNrZW4lMjBrYXJhaGl8ZW58MHx8MHx8fDA%3D', 5.00, 90, 0.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(6, 'Crispy Chicken Bites', 'Golden, crunchy chicken bites made for sharing and snacking.', 'fries', 9.90, 'https://images.unsplash.com/photo-1750190624513-020c9df74b13?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MjB8fGNoaWNrZW4lMjBrYXJhaGl8ZW58MHx8MHx8fDA%3D', 5.00, 84, 0.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(7, 'Italian Pizza Slice', 'Fresh pizza slice with a rich sauce and melted cheese finish.', 'pizza', 15.50, 'https://images.unsplash.com/photo-1613564834361-9436948817d1?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8N3x8cGl6emF8ZW58MHx8MHx8fDA%3D', 5.00, 95, 20.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(8, 'Chicken Biryani', 'Aromatic biryani layered with rice, chicken, and spices.', 'dessert', 16.40, 'https://plus.unsplash.com/premium_photo-1694141251673-1758913ade48?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MXx8Y2hpY2tlbiUyMGJpcmlhbml8ZW58MHx8MHx8fDA%3D', 5.00, 87, 0.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(9, 'Chicken Pasta', 'Creamy, savory pasta with grilled chicken and herbs.', 'burger', 12.20, 'https://images.unsplash.com/photo-1551134488-df8e2b6e2861?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDE1OXx8fGVufDB8fHx8fA%3D%3D', 5.00, 89, 0.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(10, 'Thai Noodles', 'Comforting noodles with vibrant flavors and a light aromatic finish.', 'drinks', 10.80, 'https://images.unsplash.com/photo-1594489883219-010b0e5eeb9d?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDE4fHx8ZW58MHx8fHx8', 5.00, 86, 0.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(11, 'Signature Platter', 'A grand platter with assorted favorites for a premium feast.', 'burger', 14.90, 'https://media.istockphoto.com/id/1050665234/photo/thai-and-western-food.webp?a=1&b=1&s=612x612&w=0&k=20&c=iQ9chRqNdtlHT-wRMT-pW-Xi5Jp6K1hdpxfj-HfGivw=', 5.00, 94, 20.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00'),
(12, 'Shawarma Wrap', 'A flavorful wrap packed with tender meat and fresh toppings.', 'burger', 11.00, 'https://images.unsplash.com/photo-1559847844-5315695dadae?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDE5fHx8ZW58MHx8fHx8', 5.00, 93, 0.00, 1, '2026-08-13 00:00:00', '2026-08-13 00:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `idx_admins_email` (`email`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `idx_customers_email` (`email`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`menu_id`),
  ADD KEY `idx_menu_items_category` (`category`),
  ADD KEY `idx_menu_items_is_available` (`is_available`),
  ADD KEY `idx_menu_items_popularity` (`popularity`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `uq_orders_order_number` (`order_number`),
  ADD KEY `idx_orders_customer_id` (`customer_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `idx_order_items_order_id` (`order_id`),
  ADD KEY `idx_order_items_food_id` (`food_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
