-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 03, 2026 at 01:45 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `iexplore_laguna`
--
CREATE DATABASE IF NOT EXISTS `iexplore_laguna` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `iexplore_laguna`;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_number` varchar(20) NOT NULL COMMENT 'e.g. BKG-20240524-0001',
  `tourist_id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','confirmed','checked_in','checked_out','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `nights` tinyint(4) NOT NULL DEFAULT 1,
  `guests_count` tinyint(4) NOT NULL DEFAULT 1,
  `price_per_night` decimal(10,2) NOT NULL COMMENT 'Snapshot at booking time',
  `total_amount` decimal(10,2) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `guest_name` varchar(120) NOT NULL COMMENT 'Primary guest name',
  `guest_phone` varchar(20) DEFAULT NULL,
  `guest_email` varchar(120) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `checked_out_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_number`, `tourist_id`, `hotel_id`, `room_id`, `status`, `check_in_date`, `check_out_date`, `nights`, `guests_count`, `price_per_night`, `total_amount`, `special_requests`, `guest_name`, `guest_phone`, `guest_email`, `confirmed_at`, `checked_in_at`, `checked_out_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES(1, 'BKG-20260722-EBBC2', 5, 59, 1, 'checked_out', '2026-07-23', '2026-07-24', 1, 1, 701.00, 701.00, 'Part of package: San Pablo Deluxe', 'jem jem', NULL, NULL, '2026-07-21 16:12:06', '2026-07-21 16:12:11', '2026-07-21 16:13:41', NULL, NULL, '2026-07-21 16:11:28', '2026-07-21 16:13:41');
INSERT INTO `bookings` (`id`, `booking_number`, `tourist_id`, `hotel_id`, `room_id`, `status`, `check_in_date`, `check_out_date`, `nights`, `guests_count`, `price_per_night`, `total_amount`, `special_requests`, `guest_name`, `guest_phone`, `guest_email`, `confirmed_at`, `checked_in_at`, `checked_out_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES(2, 'BKG-20260722-663D8', 5, 59, 1, 'checked_out', '2026-07-25', '2026-07-26', 1, 1, 701.00, 701.00, 'Part of package: San Pablo Deluxe', 'jem jem', NULL, NULL, '2026-07-21 16:15:02', '2026-07-29 15:35:18', '2026-07-29 15:35:20', NULL, NULL, '2026-07-21 16:14:27', '2026-07-29 15:35:20');
INSERT INTO `bookings` (`id`, `booking_number`, `tourist_id`, `hotel_id`, `room_id`, `status`, `check_in_date`, `check_out_date`, `nights`, `guests_count`, `price_per_night`, `total_amount`, `special_requests`, `guest_name`, `guest_phone`, `guest_email`, `confirmed_at`, `checked_in_at`, `checked_out_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES(3, 'BKG-20260722-3CC2E', 5, 59, 1, 'checked_in', '2026-07-27', '2026-07-28', 1, 1, 701.00, 701.00, 'Part of package: San Pablo Deluxe', 'jem jem', NULL, NULL, '2026-07-29 15:35:24', '2026-07-29 15:35:25', NULL, NULL, NULL, '2026-07-22 01:42:59', '2026-07-29 15:35:25');

-- --------------------------------------------------------

--
-- Table structure for table `budget_estimates`
--

DROP TABLE IF EXISTS `budget_estimates`;
CREATE TABLE `budget_estimates` (
  `id` int(10) UNSIGNED NOT NULL,
  `city_id` int(10) UNSIGNED NOT NULL,
  `category` varchar(80) NOT NULL,
  `amount_php` decimal(8,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_estimates`
--

INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(1, 1, 'food_budget', 150.00, 'Carinderia meals per person per day');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(2, 1, 'food_midrange', 350.00, 'Restaurant meals per person per day');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(3, 1, 'food_upscale', 800.00, 'Fine dining per person per day');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(4, 1, 'accommodation_budget', 500.00, 'Pension house / budget inn per night');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(5, 1, 'accommodation_midrange', 1800.00, '3-star hotel per night');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(6, 1, 'accommodation_upscale', 5000.00, 'Resort/4-star per night');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(7, 1, 'local_transport_daily', 100.00, 'Jeepney/tricycle per day');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(8, 1, 'misc_budget', 200.00, 'Misc expenses per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(9, 1, 'misc_midrange', 400.00, 'Misc expenses per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(10, 1, 'misc_upscale', 800.00, 'Misc expenses per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(11, 2, 'food_budget', 150.00, 'Carinderia meals');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(12, 2, 'food_midrange', 300.00, 'Restaurant meals');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(13, 2, 'food_upscale', 750.00, 'Fine dining');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(14, 2, 'accommodation_budget', 600.00, 'Budget inn');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(15, 2, 'accommodation_midrange', 2000.00, '3-star hotel');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(16, 2, 'accommodation_upscale', 6000.00, 'Resort per night');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(17, 2, 'local_transport_daily', 100.00, 'Local transport per day');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(18, 2, 'misc_budget', 200.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(19, 2, 'misc_midrange', 400.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(20, 2, 'misc_upscale', 800.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(21, 3, 'food_budget', 120.00, 'Carinderia meals');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(22, 3, 'food_midrange', 280.00, 'Restaurant meals');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(23, 3, 'food_upscale', 700.00, 'Fine dining');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(24, 3, 'accommodation_budget', 500.00, 'Budget inn');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(25, 3, 'accommodation_midrange', 1600.00, '3-star hotel');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(26, 3, 'accommodation_upscale', 9000.00, 'Villa Escudero resort');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(27, 3, 'local_transport_daily', 80.00, 'Jeepney/tricycle');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(28, 3, 'misc_budget', 150.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(29, 3, 'misc_midrange', 350.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(30, 3, 'misc_upscale', 700.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(31, 4, 'food_budget', 130.00, 'Local eateries');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(32, 4, 'food_midrange', 300.00, 'Restaurant meals');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(33, 4, 'food_upscale', 700.00, 'Fine dining');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(34, 4, 'accommodation_budget', 700.00, 'Budget lodge');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(35, 4, 'accommodation_midrange', 2200.00, '3-star hotel');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(36, 4, 'accommodation_upscale', 5500.00, 'Resort per night');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(37, 4, 'local_transport_daily', 120.00, 'Bangka + tricycle');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(38, 4, 'misc_budget', 200.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(39, 4, 'misc_midrange', 400.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(40, 4, 'misc_upscale', 800.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(41, 8, 'food_budget', 140.00, 'Carinderia meals');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(42, 8, 'food_midrange', 300.00, 'Restaurant meals');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(43, 8, 'food_upscale', 700.00, 'Fine dining');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(44, 8, 'accommodation_budget', 600.00, 'Budget inn');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(45, 8, 'accommodation_midrange', 1800.00, '3-star hotel');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(46, 8, 'accommodation_upscale', 5000.00, 'Premium hotel');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(47, 8, 'local_transport_daily', 100.00, 'Jeepney/tricycle');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(48, 8, 'misc_budget', 200.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(49, 8, 'misc_midrange', 400.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(50, 8, 'misc_upscale', 800.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(51, 11, 'food_budget', 180.00, 'Fast food and carinderia');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(52, 11, 'food_midrange', 400.00, 'SM and mall restaurants');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(53, 11, 'food_upscale', 900.00, 'Fine dining at Nuvali');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(54, 11, 'accommodation_budget', 1200.00, 'Budget hotel');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(55, 11, 'accommodation_midrange', 3500.00, '3-star hotel');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(56, 11, 'accommodation_upscale', 8000.00, 'Seda Nuvali');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(57, 11, 'local_transport_daily', 150.00, 'Grab/tricycle per day');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(58, 11, 'misc_budget', 300.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(59, 11, 'misc_midrange', 500.00, 'Misc per person');
INSERT INTO `budget_estimates` (`id`, `city_id`, `category`, `amount_php`, `notes`) VALUES(60, 11, 'misc_upscale', 1000.00, 'Misc per person');

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

DROP TABLE IF EXISTS `cities`;
CREATE TABLE `cities` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(1, 'Calamba', 'calamba', 'Home of national hero Jose Rizal and a hub for hot spring resorts.', 14.2115000, 121.1653000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(2, 'Los Baños', 'los-banos', 'University town famous for hot springs, UPLB, and Mt. Makiling.', 14.1700000, 121.2410000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(3, 'San Pablo', 'san-pablo', 'City of Seven Lakes surrounded by volcanic crater lakes.', 14.0686000, 121.3247000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(4, 'Pagsanjan', 'pagsanjan', 'Famous for the thrilling Pagsanjan Falls and river rapids.', 14.2694000, 121.4577000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(5, 'Nagcarlan', 'nagcarlan', 'Known for its unique 19th-century underground cemetery.', 13.9209000, 121.4162000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(6, 'Lumban', 'lumban', 'Capital of embroidery and lacework in the Philippines.', 14.2989000, 121.4742000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(7, 'Pila', 'pila', 'Heritage town with well-preserved Spanish colonial architecture.', 14.2333000, 121.3667000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(8, 'Santa Cruz', 'santa-cruz', 'Capital of Laguna province, a bustling commercial center.', 14.2822000, 121.4169000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(9, 'Pangil', 'pangil', 'Scenic lakeshore town along Laguna de Bay.', 14.3989000, 121.4714000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(10, 'Majayjay', 'majayjay', 'Historical mountain town with waterfalls and natural attractions.', 14.0144000, 121.4742000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(11, 'Santa Rosa', 'santa-rosa', 'Modern city home to Enchanted Kingdom and Nuvali lifestyle district.', 14.3122000, 121.1114000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(12, 'Biñan', 'binan', 'Fast-growing city with historical heritage and industrial centers.', 14.3394000, 121.0797000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(13, 'San Pedro', 'san-pedro', 'Densely populated city bordering Metro Manila.', 14.3594000, 121.0472000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(14, 'Cabuyao', 'cabuyao', 'Industrial city along the shores of Laguna de Bay.', 14.2756000, 121.1256000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(15, 'Cavinti', 'cavinti', 'Gateway to Caliraya Lake and the Pagsanjan-Lumot river system.', 14.2467000, 121.5030000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(16, 'Calauan', 'calauan', 'Agricultural town home to resorts and the Hidden Valley Springs.', 14.1522000, 121.3197000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(17, 'Alaminos', 'alaminos', 'Agricultural town nestled at the foot of Mt. Makiling.', 14.0635000, 121.2451000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(18, 'Bay', 'bay', 'One of Laguna\'s oldest towns, once the province\'s Spanish-era capital.', 14.1800000, 121.2800000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(19, 'Famy', 'famy', 'Laguna\'s smallest municipality, a quiet gateway to the Sierra Madre.', 14.4300000, 121.4500000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(20, 'Kalayaan', 'kalayaan', 'Lakeside town on Laguna de Bay, formerly known as Longos.', 14.3280000, 121.4800000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(21, 'Liliw', 'liliw', 'Known nationwide as the \"Tsinelas (Slippers) Capital of the Philippines.\"', 14.1300000, 121.4360000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(22, 'Luisiana', 'luisiana', 'Farming town on the slopes of Mt. Banahaw.', 14.1850000, 121.5109000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(23, 'Mabitac', 'mabitac', 'Lakeside town, site of a notable 1902 battle during the Philippine-American War.', 14.4300000, 121.4200000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(24, 'Magdalena', 'magdalena', 'Small agricultural town near Majayjay, known for its rice fields.', 14.2000000, 121.4300000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(25, 'Paete', 'paete', 'Renowned as the woodcarving and papier-mâché arts capital of the Philippines.', 14.3700000, 121.4800000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(26, 'Pakil', 'pakil', 'Known as the \"Pilgrimage Capital of Laguna\" for its Turumba festival.', 14.3800000, 121.4800000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(27, 'Rizal', 'rizal', 'Named after national hero Jose Rizal; one of Laguna\'s youngest municipalities.', 14.1083000, 121.3917000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(28, 'Santa Maria', 'santa-maria', 'Agricultural municipality in northeastern Laguna.', 14.4750000, 121.4250000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(29, 'Siniloan', 'siniloan', 'Gateway town to Quezon province, known for its historic Spanish-era church.', 14.4220000, 121.4460000);
INSERT INTO `cities` (`id`, `name`, `slug`, `description`, `latitude`, `longitude`) VALUES(30, 'Victoria', 'victoria', 'Known as the \"Duck Raising Capital of the Philippines.\"', 14.2250000, 121.3250000);

-- --------------------------------------------------------

--
-- Table structure for table `hotels`
--

DROP TABLE IF EXISTS `hotels`;
CREATE TABLE `hotels` (
  `id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id (hotel_owner)',
  `city_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `star_rating` tinyint(4) DEFAULT 3,
  `price_min` decimal(8,2) DEFAULT NULL,
  `price_max` decimal(8,2) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `cover_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(1, NULL, 1, 'Sol Y Viento Mountain Hot Springs Resort', 'Pansol, Calamba', '049-545-0001', NULL, 4, 3500.00, 9000.00, 14.1960000, 121.1720000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(2, NULL, 1, 'Green Glass Hotel', 'National Highway, Calamba', '049-545-0002', NULL, 3, 1800.00, 3200.00, 14.2130000, 121.1650000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(3, NULL, 1, 'Praferosa Resort Hotel', 'Real Road 335, Km. 54, Calamba', '049-545-0003', NULL, 3, 1500.00, 2800.00, 14.2000000, 121.1600000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(4, NULL, 1, 'Chateau Bleu Resort', 'Pansol, Calamba', '049-545-0004', NULL, 4, 4500.00, 10000.00, 14.1950000, 121.1730000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(5, NULL, 1, 'La Vista Pansol Resort Complex', 'Pansol, Calamba', '049-545-0005', NULL, 3, 2000.00, 5000.00, 14.1980000, 121.1710000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(6, NULL, 1, 'Sheldan Resorts', 'Pansol, Calamba', '049-545-0006', NULL, 3, 2500.00, 6000.00, 14.1970000, 121.1700000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(7, NULL, 1, 'RedDoorz Near SM Calamba', 'Km. 54, National Highway, Calamba', '049-545-0007', NULL, 2, 1000.00, 1800.00, 14.2110000, 121.1660000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(8, NULL, 1, 'Reddoorz at Solemar Pansol', 'Pansol, Calamba', '049-545-0008', NULL, 2, 900.00, 1600.00, 14.1990000, 121.1715000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(9, NULL, 1, 'Hidden Valley Springs Resort', 'Calauan-Alaminos Road, Calamba', '049-545-0009', NULL, 4, 6000.00, 15000.00, 14.1389000, 121.1767000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(10, NULL, 1, 'My Inn Calamba', 'National Highway, Calamba', '049-545-0010', NULL, 2, 800.00, 1500.00, 14.2115000, 121.1655000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(11, NULL, 1, 'Astrotel Calamba', 'Real Road, Calamba', '049-545-0011', NULL, 3, 1600.00, 2800.00, 14.2120000, 121.1640000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(12, NULL, 1, 'Riverview Resort and Conference Center', 'Pansol Road, Calamba', '049-545-0012', NULL, 3, 2200.00, 5000.00, 14.1955000, 121.1725000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(13, NULL, 1, 'Casa Palmera Resort', 'Pansol, Calamba', '049-545-0013', NULL, 4, 3800.00, 8000.00, 14.1950000, 121.1710000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(14, NULL, 1, 'Yahweh Spring Retreat & Resort', 'Pansol, Calamba', '049-545-0014', NULL, 3, 2800.00, 6500.00, 14.1945000, 121.1705000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(15, NULL, 1, 'Princess Muffin Resort Hotspring', 'Pansol, Calamba', '049-545-0015', NULL, 3, 2000.00, 4500.00, 14.1975000, 121.1718000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(16, NULL, 1, 'Siam Oasis Resort', 'Pansol, Calamba', '049-545-0016', NULL, 3, 3000.00, 7000.00, 14.1963000, 121.1722000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(17, NULL, 1, 'Villa Tagumpay Resort', 'Pansol, Calamba', '049-545-0017', NULL, 3, 2500.00, 5500.00, 14.1968000, 121.1708000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(18, NULL, 2, 'Makiling Onsen Hotel', 'Km. 58, Los Baños', '049-536-0001', NULL, 4, 4500.00, 10000.00, 14.1700000, 121.2410000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(19, NULL, 2, 'Splash Mountain Resort Hotel', 'Banlic, Los Baños', '049-536-0002', NULL, 3, 2500.00, 5000.00, 14.1680000, 121.2380000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(20, NULL, 2, 'Ardent Hot Springs', 'Maitim, Los Baños', '049-536-0003', NULL, 4, 4000.00, 9000.00, 14.1720000, 121.2200000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(21, NULL, 2, 'FLB Apartelle Los Baños', 'Km. 58, Los Baños', '049-536-0004', NULL, 2, 1200.00, 2200.00, 14.1710000, 121.2415000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(22, NULL, 2, 'RedDoorz @ Aerial Hotel Los Baños', 'Brgy. Lalakay, Los Baños', '049-536-0005', NULL, 2, 900.00, 1700.00, 14.1695000, 121.2408000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(23, NULL, 2, 'Arabella Hot Spring Resort', 'Pansol Road, Los Baños', '049-536-0006', NULL, 3, 2000.00, 4500.00, 14.1715000, 121.2390000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(24, NULL, 2, 'Amor De Hermanas', 'Los Baños, Laguna', '049-536-0007', NULL, 3, 1800.00, 3500.00, 14.1708000, 121.2420000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(25, NULL, 2, 'Tahum Lake Resort', 'Lakeshore, Los Baños', '049-536-0008', NULL, 3, 2200.00, 4800.00, 14.1750000, 121.2450000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(26, NULL, 2, 'Laguna Hot Spring Home', 'Pansol, Los Baños', '049-536-0009', NULL, 3, 3500.00, 8000.00, 14.1725000, 121.2380000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(27, NULL, 2, 'Grand Villa Laguna', 'Los Baños, Laguna', '049-536-0010', NULL, 3, 2800.00, 6000.00, 14.1712000, 121.2430000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(28, NULL, 2, '88 Hotspring Resort', 'Los Baños, Laguna', '049-536-0011', NULL, 3, 2000.00, 5000.00, 14.1705000, 121.2395000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(29, NULL, 3, 'Villa Escudero Plantations & Resort', 'Tiaong Road, San Pablo', '042-540-0001', NULL, 5, 8000.00, 22000.00, 14.0500000, 121.2500000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(30, NULL, 3, 'Diwata Nature Resort', 'San Pablo City', '042-540-0002', NULL, 4, 4000.00, 9000.00, 14.0680000, 121.3240000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(31, NULL, 3, 'Casa de Obando B&B at Sulyap', 'San Pablo City', '042-540-0003', NULL, 3, 2500.00, 5000.00, 14.0690000, 121.3255000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(32, NULL, 3, 'Tahanan ni Aling Meding Hotel', 'San Pablo City', '042-540-0004', NULL, 3, 1800.00, 3500.00, 14.0695000, 121.3260000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(33, NULL, 3, 'Zacona Eco-Resort & Biblical Garden', 'Brgy. Sta. Monica, San Pablo', '042-540-0005', NULL, 3, 2200.00, 4500.00, 14.0710000, 121.3100000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(34, NULL, 3, 'Ridge Park Hotel', 'San Pablo City', '042-540-0006', NULL, 3, 1600.00, 3000.00, 14.0700000, 121.3270000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(35, NULL, 3, 'Rivoli Hotel', 'San Pablo City', '042-540-0007', NULL, 3, 1500.00, 2800.00, 14.0685000, 121.3265000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(36, NULL, 3, 'Seven Lakes Inn', 'Sampaloc Lake Drive, San Pablo', '042-540-0008', NULL, 2, 1200.00, 2000.00, 14.0700000, 121.3260000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(37, NULL, 3, 'Serenity Cove Resort', 'San Pablo City', '042-540-0009', NULL, 4, 5000.00, 12000.00, 14.0650000, 121.3150000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(38, NULL, 3, 'Casa San Pablo B&B', 'San Pablo City', '042-540-0010', NULL, 3, 2000.00, 4000.00, 14.0692000, 121.3258000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(39, NULL, 3, 'Mancion Bakasyunan Resort', 'San Pablo City', '042-540-0011', NULL, 3, 2500.00, 5500.00, 14.0705000, 121.3245000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(40, NULL, 3, 'Villa Soledad Garden Resort', 'San Pablo City', '042-540-0012', NULL, 3, 2000.00, 4500.00, 14.0688000, 121.3252000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(41, NULL, 3, 'Samkara Restaurant and Garden Resort', 'San Pablo City', '042-540-0013', NULL, 3, 3000.00, 6000.00, 14.0660000, 121.3180000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(42, NULL, 4, 'Pagsanjan Falls Lodge', 'Garcia St., Pagsanjan', '049-808-0001', NULL, 3, 2000.00, 3500.00, 14.2700000, 121.4580000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(43, NULL, 4, 'Almost Heaven Lake Resort', 'Pagsanjan, Laguna', '049-808-0002', NULL, 4, 4500.00, 10000.00, 14.2720000, 121.4560000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(44, NULL, 4, 'Marescoville', 'Brgy. Sabang, Pagsanjan', '049-808-0003', NULL, 3, 2500.00, 5000.00, 14.2690000, 121.4570000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(45, NULL, 5, 'RedDoorz at Hilarions Farm Majayjay', 'Nagcarlan, Laguna', '049-800-0001', NULL, 1, 700.00, 1200.00, 13.9209000, 121.4162000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(46, NULL, 8, 'Hotel Laguna', 'Quezon Ave., Santa Cruz', '049-501-0001', NULL, 3, 1600.00, 2800.00, 14.2822000, 121.4169000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(47, NULL, 8, 'Ang Tahanan ni Aling Meding Hotel', 'Santa Cruz, Laguna', '049-501-0002', NULL, 3, 1500.00, 2800.00, 14.2820000, 121.4175000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(48, NULL, 11, 'Seda Nuvali Laguna', 'Nuvali, Santa Rosa', '049-576-0001', NULL, 5, 6000.00, 15000.00, 14.2900000, 121.0750000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(49, NULL, 11, 'Microtel by Wyndham South Forbes', 'Santa Rosa, Laguna', '049-576-0002', NULL, 3, 2500.00, 5000.00, 14.3100000, 121.0900000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(50, NULL, 11, 'El Cielito Inn Santa Rosa', 'Sta. Rosa-Tagaytay Road, Santa Rosa', '049-576-0003', NULL, 3, 2000.00, 4000.00, 14.3120000, 121.1100000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(51, NULL, 11, 'Hotel Sogo Sta. Rosa', 'Sta. Rosa-Tagaytay Road, Santa Rosa', '049-576-0004', NULL, 2, 1200.00, 2200.00, 14.3115000, 121.1108000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(52, NULL, 11, 'Hotel Turista Canlubang', 'Canlubang, Santa Rosa', '049-576-0005', NULL, 3, 2200.00, 4500.00, 14.2850000, 121.0850000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(53, NULL, 11, 'Paseo Premiere Hotel', 'Santa Rosa, Laguna', '049-576-0006', NULL, 4, 4000.00, 8000.00, 14.3110000, 121.1120000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(54, NULL, 11, 'Technopark Hotel', 'Laguna Technopark, Santa Rosa', '049-576-0007', NULL, 4, 3500.00, 7000.00, 14.2970000, 121.0780000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(55, NULL, 15, 'Caliraya Mountain Lake Resort', 'Cavinti, Laguna', '049-800-0010', NULL, 4, 5000.00, 12000.00, 14.2467000, 121.5030000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(56, NULL, 15, 'Caliraya Heights Resort', 'Lumban-Cavinti Road, Cavinti', '049-800-0011', NULL, 3, 3000.00, 7000.00, 14.2500000, 121.5050000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(57, NULL, 16, 'Hidden Valley Springs', 'Calauan, Laguna', '049-800-0020', NULL, 4, 6000.00, 15000.00, 14.1389000, 121.1767000, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(58, 3, 3, 'Jems Hotel', '', '09618728715', 'Magandang Hotel', 3, 600.00, 5000.00, NULL, NULL, NULL, 1, 1);
INSERT INTO `hotels` (`id`, `owner_id`, `city_id`, `name`, `address`, `phone`, `description`, `star_rating`, `price_min`, `price_max`, `latitude`, `longitude`, `cover_url`, `is_active`, `is_verified`) VALUES(59, 6, 3, 'Sams Hotel', '', '09123456789', 'edi wow', 1, 2000.00, 10000.00, NULL, NULL, NULL, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_amenities`
--

DROP TABLE IF EXISTS `hotel_amenities`;
CREATE TABLE `hotel_amenities` (
  `id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(80) NOT NULL,
  `icon` varchar(50) DEFAULT 'bi-check-circle'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hotel_photos`
--

DROP TABLE IF EXISTS `hotel_photos`;
CREATE TABLE `hotel_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `url` varchar(255) NOT NULL,
  `caption` varchar(200) DEFAULT NULL,
  `photo_type` enum('main','gallery','room','amenity','exterior') DEFAULT 'gallery',
  `sort_order` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotel_photos`
--

INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(1, 11, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a62e9260389f5.17505184.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(2, 48, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b0a522c9b9.27923811.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(3, 29, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b0de7594b6.16815594.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(4, 1, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b0f8a00bd7.15443988.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(5, 54, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b12234cfe0.07761607.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(6, 13, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b142217831.53040519.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(7, 20, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b15706e9e8.84249791.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(8, 30, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b17c887f60.14355192.webp', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(9, 53, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b18f46e340.42888465.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(10, 4, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b1b6754db2.19539442.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(11, 18, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b1c8695541.95513311.jpg', '', 'main', 1);
INSERT INTO `hotel_photos` (`id`, `hotel_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(12, 43, 'http://localhost/iexplore-laguna/uploads/hotels/hotels_6a70b1e3af32a9.80388324.jpg', '', 'main', 1);

-- --------------------------------------------------------

--
-- Table structure for table `hotel_reviews`
--

DROP TABLE IF EXISTS `hotel_reviews`;
CREATE TABLE `hotel_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `stayed_on` date DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hotel_rooms`
--

DROP TABLE IF EXISTS `hotel_rooms`;
CREATE TABLE `hotel_rooms` (
  `id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id (hotel_owner role)',
  `room_type` varchar(80) NOT NULL COMMENT 'e.g. Standard, Deluxe, Suite',
  `description` text DEFAULT NULL,
  `capacity` tinyint(4) NOT NULL DEFAULT 2,
  `bed_type` varchar(60) DEFAULT NULL COMMENT 'e.g. Double, Twin, King',
  `floor_number` tinyint(4) DEFAULT NULL,
  `room_count` smallint(6) NOT NULL DEFAULT 1 COMMENT 'How many rooms of this type',
  `price_per_night` decimal(10,2) NOT NULL,
  `weekend_rate` decimal(10,2) DEFAULT NULL COMMENT 'Optional higher weekend rate',
  `amenities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["wifi","aircon","tv","hotshower"]' CHECK (json_valid(`amenities`)),
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_rooms`
--

INSERT INTO `hotel_rooms` (`id`, `hotel_id`, `owner_id`, `room_type`, `description`, `capacity`, `bed_type`, `floor_number`, `room_count`, `price_per_night`, `weekend_rate`, `amenities`, `image_url`, `is_available`, `created_at`, `updated_at`) VALUES(1, 59, 6, 'Classic Solo', '', 1, 'Queen', NULL, 1, 701.00, NULL, NULL, NULL, 1, '2026-07-21 16:08:29', '2026-07-21 16:08:29');

-- --------------------------------------------------------

--
-- Table structure for table `itineraries`
--

DROP TABLE IF EXISTS `itineraries`;
CREATE TABLE `itineraries` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `origin_city_id` int(10) UNSIGNED NOT NULL,
  `dest_city_id` int(10) UNSIGNED NOT NULL,
  `travel_date` date DEFAULT NULL,
  `num_days` tinyint(4) DEFAULT 1,
  `num_persons` tinyint(4) DEFAULT 1,
  `budget_level` enum('budget','midrange','upscale') DEFAULT 'midrange',
  `transport_pref` varchar(50) DEFAULT NULL,
  `itinerary_json` longtext DEFAULT NULL,
  `spot_ids` text DEFAULT NULL,
  `total_budget` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `itineraries`
--

INSERT INTO `itineraries` (`id`, `user_id`, `title`, `origin_city_id`, `dest_city_id`, `travel_date`, `num_days`, `num_persons`, `budget_level`, `transport_pref`, `itinerary_json`, `spot_ids`, `total_budget`, `created_at`) VALUES(3, 5, 'San Pablo Deluxe', 3, 3, '2026-07-27', 1, 1, 'midrange', 'any', '[{\"day\":1,\"city\":\"San Pablo\",\"spots\":[{\"name\":\"Bunot Lake\",\"city\":\"San Pablo\",\"price\":50},{\"name\":\"Palakpakin Lake\",\"city\":\"San Pablo\",\"price\":30},{\"name\":\"Sampaloc Lake\",\"city\":\"San Pablo\",\"price\":0},{\"name\":\"Villa Escudero Museum\",\"city\":\"San Pablo\",\"price\":500}],\"shops\":[]}]', NULL, 3001.00, '2026-07-22 01:42:59');
INSERT INTO `itineraries` (`id`, `user_id`, `title`, `origin_city_id`, `dest_city_id`, `travel_date`, `num_days`, `num_persons`, `budget_level`, `transport_pref`, `itinerary_json`, `spot_ids`, `total_budget`, `created_at`) VALUES(4, 5, 'San Pablo → Calamba (3d)', 3, 1, NULL, 3, 2, 'midrange', 'private_car', NULL, ',1,2,3,8,9,10,11,12,6,19,14,16,18,5,17,20,4,15,7,', 0.00, '2026-07-29 15:22:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(60) NOT NULL COMMENT 'order_placed, order_ready, booking_confirmed, etc.',
  `title` varchar(120) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL COMMENT 'Optional URL to relevant page',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(1, 2, 'order_placed', '🛍️ New Order: ORD-20260714-BA80B', 'You have a new order from jem jem — ₱99.00', 'http://localhost/iexplore-laguna/pages/shop-dashboard.php#orders', 0, '2026-07-14 04:00:29');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(2, 5, 'order_placed', '✅ Order Placed! ORD-20260714-BA80B', 'Your order at MilkTea ni Sam has been placed. Pickup code: 62E277', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-14 04:00:29');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(3, 5, 'order_cancelled', 'Order Cancelled', 'Your order #ORD-20260714-BA80B was cancelled by the shop.', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-14 04:00:56');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(4, 2, 'order_placed', '🛍️ New Order: ORD-20260717-03D12', 'You have a new order from jem jem — ₱99.00', 'http://localhost/iexplore-laguna/pages/shop-dashboard.php#orders', 0, '2026-07-17 02:17:19');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(5, 5, 'order_placed', '✅ Order Placed! ORD-20260717-03D12', 'Your order at MilkTea ni Sam has been placed. Pickup code: B2C3C0', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-17 02:17:19');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(6, 5, 'order_confirmed', 'Order Confirmed! 🎉', 'Your order #ORD-20260717-03D12 has been confirmed by the shop.', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-17 02:17:47');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(7, 5, 'order_preparing', 'Order Being Prepared 🍳', 'Your order #ORD-20260717-03D12 is now being prepared!', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-17 02:17:51');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(8, 5, 'order_ready', 'Ready for Pickup! ✅', 'Your order #ORD-20260717-03D12 is ready. Show your pickup code: B2C3C0', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-17 02:17:53');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(9, 6, 'hotel_rejected', 'Hotel Registration Rejected', '\"Sams Hotel\" was not approved. Please review your details and contact support.', 'http://localhost/iexplore-laguna/pages/hotel-dashboard.php', 0, '2026-07-18 02:24:20');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(10, 6, 'hotel_approved', '🎉 Hotel Approved!', '\"Sams Hotel\" is now live on iExplore Laguna and visible to tourists.', 'http://localhost/iexplore-laguna/pages/hotel-dashboard.php', 0, '2026-07-21 16:07:29');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(11, 6, 'booking_placed', '📦 New Package Booking: BKG-20260722-EBBC2', 'jem jem booked the \"San Pablo Deluxe\" package — check-in 2026-07-23, check-out 2026-07-24.', 'http://localhost/iexplore-laguna/pages/hotel-dashboard.php#bookings', 0, '2026-07-21 16:11:28');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(12, 5, 'package_booked', '🎉 Package Booked! San Pablo Deluxe', 'Your hotel is reserved (2026-07-23 to 2026-07-24) and your itinerary is ready.', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-21 16:11:28');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(13, 5, 'booking_confirmed', 'Booking Confirmed! 🎉', 'Your booking #BKG-20260722-EBBC2 has been confirmed by the hotel.', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-21 16:12:06');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(14, 5, 'booking_checked_in', 'Checked In ✅', 'You\'ve been checked in for booking #BKG-20260722-EBBC2. Enjoy your stay!', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-21 16:12:11');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(15, 5, 'booking_checked_out', 'Checked Out 👋', 'Thanks for staying with us! Booking #BKG-20260722-EBBC2 is now complete.', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-21 16:13:41');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(16, 6, 'booking_placed', '📦 New Package Booking: BKG-20260722-663D8', 'jem jem booked the \"San Pablo Deluxe\" package — check-in 2026-07-25, check-out 2026-07-26.', 'http://localhost/iexplore-laguna/pages/hotel-dashboard.php#bookings', 0, '2026-07-21 16:14:27');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(17, 5, 'package_booked', '🎉 Package Booked! San Pablo Deluxe', 'Your hotel is reserved (2026-07-25 to 2026-07-26) and your itinerary is ready.', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-21 16:14:27');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(18, 5, 'booking_confirmed', 'Booking Confirmed! 🎉', 'Your booking #BKG-20260722-663D8 has been confirmed by the hotel.', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-21 16:15:02');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(19, 6, 'booking_placed', '📦 New Package Booking: BKG-20260722-3CC2E', 'jem jem booked the \"San Pablo Deluxe\" package — check-in 2026-07-27, check-out 2026-07-28.', 'http://localhost/iexplore-laguna/pages/hotel-dashboard.php#bookings', 0, '2026-07-22 01:42:59');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(20, 5, 'package_booked', '🎉 Package Booked! San Pablo Deluxe', 'Your hotel is reserved (2026-07-27 to 2026-07-28) and your itinerary is ready.', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-22 01:42:59');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(21, 5, 'booking_checked_in', 'Checked In', 'You\'ve been checked in for booking #BKG-20260722-663D8. Enjoy your stay!', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-29 15:35:19');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(22, 5, 'booking_checked_out', 'Checked Out', 'Thanks for staying with us! Booking #BKG-20260722-663D8 is now complete.', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-29 15:35:20');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(23, 5, 'booking_confirmed', 'Booking Confirmed!', 'Your booking #BKG-20260722-3CC2E has been confirmed by the hotel.', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-29 15:35:24');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(24, 5, 'booking_checked_in', 'Checked In', 'You\'ve been checked in for booking #BKG-20260722-3CC2E. Enjoy your stay!', 'http://localhost/iexplore-laguna/pages/my-bookings.php', 1, '2026-07-29 15:35:25');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(25, 5, 'order_picked_up', 'Order Completed', 'Your order #ORD-20260717-03D12 has been picked up. Thanks for ordering!', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-29 15:43:18');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(26, 2, 'order_placed', '🛍️ New Order: ORD-20260730-2C2BC', 'You have a new order from jem jem — ₱99.00', 'http://localhost/iexplore-laguna/pages/shop-dashboard.php#orders', 0, '2026-07-30 07:33:45');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(27, 5, 'order_placed', '✅ Order Placed! ORD-20260730-2C2BC', 'Your order at MilkTea ni Sam has been placed. Pickup code: B597D3', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-30 07:33:45');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(28, 5, 'order_confirmed', 'Order Confirmed!', 'Your order #ORD-20260730-2C2BC has been confirmed by the shop.', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-30 07:34:11');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(29, 5, 'order_preparing', 'Order Being Prepared', 'Your order #ORD-20260730-2C2BC is now being prepared!', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-30 07:34:20');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(30, 5, 'order_ready', 'Ready for Pickup!', 'Your order #ORD-20260730-2C2BC is ready. Show your pickup code: B597D3', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-07-30 07:34:31');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(31, 7, 'shop_approved', 'Shop Approved!', '\"Jems Pasalubong\" is now live on iExplore Laguna and visible to tourists.', 'http://localhost/iexplore-laguna/pages/shop-dashboard.php', 0, '2026-07-30 07:35:22');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(32, 2, 'order_placed', 'New Order: ORD-20260804-D17E4', 'You have a new order from jem jem — ₱99.00', 'http://localhost/iexplore-laguna/pages/shop-dashboard.php#orders', 0, '2026-08-04 00:58:03');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(33, 5, 'order_placed', 'Order Placed! ORD-20260804-D17E4', 'Your order at MilkTea ni Sam has been placed. Pickup code: 4D6CD3', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-08-04 00:58:03');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(34, 2, 'order_placed', 'New Order: ORD-20260810-77C40', 'You have a new order from jem jem — ₱99.00', 'http://localhost/iexplore-laguna/pages/shop-dashboard.php#orders', 0, '2026-08-10 08:55:09');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(35, 5, 'order_placed', 'Order Placed! ORD-20260810-77C40', 'Your order at MilkTea ni Sam has been placed. Pickup code: 744492', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-08-10 08:55:09');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(36, 5, 'order_cancelled', 'Order Cancelled', 'Your order #ORD-20260810-77C40 was cancelled by the shop.', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-08-10 08:55:46');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(37, 5, 'order_cancelled', 'Order Cancelled', 'Your order #ORD-20260804-D17E4 was cancelled by the shop.', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-08-10 08:55:48');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES(38, 5, 'order_picked_up', 'Order Completed', 'Your order #ORD-20260730-2C2BC has been picked up. Thanks for ordering!', 'http://localhost/iexplore-laguna/pages/my-orders.php', 1, '2026-08-10 08:55:50');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(20) NOT NULL COMMENT 'e.g. ORD-20240524-0001',
  `tourist_id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','confirmed','preparing','ready','picked_up','cancelled') NOT NULL DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `special_notes` text DEFAULT NULL,
  `pickup_date` date DEFAULT NULL COMMENT 'Date tourist plans to pick up',
  `pickup_time` time DEFAULT NULL COMMENT 'Approximate pickup time',
  `pickup_code` varchar(8) DEFAULT NULL COMMENT 'Short code tourist shows at shop',
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `tourist_id`, `shop_id`, `status`, `subtotal`, `total_amount`, `special_notes`, `pickup_date`, `pickup_time`, `pickup_code`, `confirmed_at`, `ready_at`, `picked_up_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES(1, 'ORD-20260714-BA80B', 5, 1, 'cancelled', 99.00, 99.00, 'asda', '2026-07-14', NULL, '62E277', NULL, NULL, NULL, '2026-07-14 04:00:56', NULL, '2026-07-14 04:00:29', '2026-07-14 04:00:56');
INSERT INTO `orders` (`id`, `order_number`, `tourist_id`, `shop_id`, `status`, `subtotal`, `total_amount`, `special_notes`, `pickup_date`, `pickup_time`, `pickup_code`, `confirmed_at`, `ready_at`, `picked_up_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES(2, 'ORD-20260717-03D12', 5, 1, 'picked_up', 99.00, 99.00, NULL, '2026-07-17', '00:19:00', 'B2C3C0', '2026-07-17 02:17:47', '2026-07-17 02:17:53', '2026-07-29 15:43:18', NULL, NULL, '2026-07-17 02:17:19', '2026-07-29 15:43:18');
INSERT INTO `orders` (`id`, `order_number`, `tourist_id`, `shop_id`, `status`, `subtotal`, `total_amount`, `special_notes`, `pickup_date`, `pickup_time`, `pickup_code`, `confirmed_at`, `ready_at`, `picked_up_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES(3, 'ORD-20260730-2C2BC', 5, 1, 'picked_up', 99.00, 99.00, NULL, '2026-07-31', NULL, 'B597D3', '2026-07-30 07:34:11', '2026-07-30 07:34:31', '2026-08-10 08:55:50', NULL, NULL, '2026-07-30 07:33:45', '2026-08-10 08:55:50');
INSERT INTO `orders` (`id`, `order_number`, `tourist_id`, `shop_id`, `status`, `subtotal`, `total_amount`, `special_notes`, `pickup_date`, `pickup_time`, `pickup_code`, `confirmed_at`, `ready_at`, `picked_up_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES(4, 'ORD-20260804-D17E4', 5, 1, 'cancelled', 99.00, 99.00, NULL, '2026-08-05', NULL, '4D6CD3', NULL, NULL, NULL, '2026-08-10 08:55:48', NULL, '2026-08-04 00:58:03', '2026-08-10 08:55:48');
INSERT INTO `orders` (`id`, `order_number`, `tourist_id`, `shop_id`, `status`, `subtotal`, `total_amount`, `special_notes`, `pickup_date`, `pickup_time`, `pickup_code`, `confirmed_at`, `ready_at`, `picked_up_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES(5, 'ORD-20260810-77C40', 5, 1, 'cancelled', 99.00, 99.00, NULL, NULL, NULL, '744492', NULL, NULL, NULL, '2026-08-10 08:55:46', NULL, '2026-08-10 08:55:09', '2026-08-10 08:55:46');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(120) NOT NULL COMMENT 'Snapshot at time of order',
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` smallint(6) NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL COMMENT 'e.g. less sugar, no ice'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`, `notes`) VALUES(1, 1, 1, 'Milktea1', 99.00, 1, 99.00, NULL);
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`, `notes`) VALUES(2, 2, 2, 'Milktea2', 99.00, 1, 99.00, NULL);
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`, `notes`) VALUES(3, 3, 1, 'Milktea1', 99.00, 1, 99.00, NULL);
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`, `notes`) VALUES(4, 4, 1, 'Milktea1', 99.00, 1, 99.00, NULL);
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`, `notes`) VALUES(5, 5, 2, 'Milktea2', 99.00, 1, 99.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
CREATE TABLE `packages` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `scope` enum('single_city','multi_city') NOT NULL DEFAULT 'single_city',
  `city_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Set when scope = single_city',
  `days` tinyint(4) UNSIGNED NOT NULL DEFAULT 2,
  `estimated_price` decimal(10,2) NOT NULL COMMENT 'Total est. cost per guest for the whole trip',
  `hotel_id` int(10) UNSIGNED DEFAULT NULL,
  `room_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Specific room type booked when this package is chosen',
  `cover_emoji` varchar(10) NOT NULL DEFAULT '?',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `title`, `description`, `scope`, `city_id`, `days`, `estimated_price`, `hotel_id`, `room_id`, `cover_emoji`, `is_active`, `created_at`) VALUES(1, 'San Pablo Deluxe', 'Sample package 1', 'single_city', 3, 1, 3001.00, 59, 1, '🎒', 1, '2026-07-21 16:10:12');

-- --------------------------------------------------------

--
-- Table structure for table `package_bookings`
--

DROP TABLE IF EXISTS `package_bookings`;
CREATE TABLE `package_bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `package_id` int(10) UNSIGNED NOT NULL,
  `tourist_id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK to bookings (the hotel reservation)',
  `itinerary_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK to itineraries (the saved trip plan)',
  `start_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_bookings`
--

INSERT INTO `package_bookings` (`id`, `package_id`, `tourist_id`, `booking_id`, `itinerary_id`, `start_date`, `created_at`) VALUES(1, 1, 5, 1, NULL, '2026-07-23', '2026-07-21 16:11:28');
INSERT INTO `package_bookings` (`id`, `package_id`, `tourist_id`, `booking_id`, `itinerary_id`, `start_date`, `created_at`) VALUES(2, 1, 5, 2, NULL, '2026-07-25', '2026-07-21 16:14:27');
INSERT INTO `package_bookings` (`id`, `package_id`, `tourist_id`, `booking_id`, `itinerary_id`, `start_date`, `created_at`) VALUES(3, 1, 5, 3, 3, '2026-07-27', '2026-07-22 01:42:59');

-- --------------------------------------------------------

--
-- Table structure for table `package_spots`
--

DROP TABLE IF EXISTS `package_spots`;
CREATE TABLE `package_spots` (
  `id` int(10) UNSIGNED NOT NULL,
  `package_id` int(10) UNSIGNED NOT NULL,
  `spot_id` int(10) UNSIGNED NOT NULL,
  `day_number` tinyint(4) UNSIGNED NOT NULL DEFAULT 1,
  `sort_order` smallint(6) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_spots`
--

INSERT INTO `package_spots` (`id`, `package_id`, `spot_id`, `day_number`, `sort_order`) VALUES(1, 1, 9, 1, 0);
INSERT INTO `package_spots` (`id`, `package_id`, `spot_id`, `day_number`, `sort_order`) VALUES(2, 1, 10, 1, 1);
INSERT INTO `package_spots` (`id`, `package_id`, `spot_id`, `day_number`, `sort_order`) VALUES(3, 1, 8, 1, 2);
INSERT INTO `package_spots` (`id`, `package_id`, `spot_id`, `day_number`, `sort_order`) VALUES(4, 1, 11, 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference_type` enum('order','booking') NOT NULL,
  `reference_id` int(10) UNSIGNED NOT NULL COMMENT 'orders.id or bookings.id',
  `payer_id` int(10) UNSIGNED NOT NULL COMMENT 'FK → users.id (the tourist)',
  `method` enum('gcash','maya','credit_card','debit_card','bank_transfer','cash_on_pickup','cash_on_checkin') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'PHP',
  `status` enum('pending','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
  `transaction_ref` varchar(120) DEFAULT NULL COMMENT 'GCash ref no. / card auth code',
  `paid_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reference_type`, `reference_id`, `payer_id`, `method`, `amount`, `currency`, `status`, `transaction_ref`, `paid_at`, `refunded_at`, `notes`, `created_at`, `updated_at`) VALUES(1, 'order', 1, 5, 'cash_on_pickup', 99.00, 'PHP', 'pending', NULL, NULL, NULL, NULL, '2026-07-14 04:00:29', '2026-07-14 04:00:29');
INSERT INTO `payments` (`id`, `reference_type`, `reference_id`, `payer_id`, `method`, `amount`, `currency`, `status`, `transaction_ref`, `paid_at`, `refunded_at`, `notes`, `created_at`, `updated_at`) VALUES(2, 'order', 2, 5, 'cash_on_pickup', 99.00, 'PHP', 'pending', NULL, NULL, NULL, NULL, '2026-07-17 02:17:19', '2026-07-17 02:17:19');
INSERT INTO `payments` (`id`, `reference_type`, `reference_id`, `payer_id`, `method`, `amount`, `currency`, `status`, `transaction_ref`, `paid_at`, `refunded_at`, `notes`, `created_at`, `updated_at`) VALUES(3, 'booking', 1, 5, 'cash_on_checkin', 701.00, 'PHP', 'pending', NULL, NULL, NULL, NULL, '2026-07-21 16:11:28', '2026-07-21 16:11:28');
INSERT INTO `payments` (`id`, `reference_type`, `reference_id`, `payer_id`, `method`, `amount`, `currency`, `status`, `transaction_ref`, `paid_at`, `refunded_at`, `notes`, `created_at`, `updated_at`) VALUES(4, 'booking', 2, 5, 'cash_on_checkin', 701.00, 'PHP', 'pending', NULL, NULL, NULL, NULL, '2026-07-21 16:14:27', '2026-07-21 16:14:27');
INSERT INTO `payments` (`id`, `reference_type`, `reference_id`, `payer_id`, `method`, `amount`, `currency`, `status`, `transaction_ref`, `paid_at`, `refunded_at`, `notes`, `created_at`, `updated_at`) VALUES(5, 'booking', 3, 5, 'cash_on_checkin', 701.00, 'PHP', 'pending', NULL, NULL, NULL, NULL, '2026-07-22 01:42:59', '2026-07-22 01:42:59');
INSERT INTO `payments` (`id`, `reference_type`, `reference_id`, `payer_id`, `method`, `amount`, `currency`, `status`, `transaction_ref`, `paid_at`, `refunded_at`, `notes`, `created_at`, `updated_at`) VALUES(6, 'order', 3, 5, 'cash_on_pickup', 99.00, 'PHP', 'pending', NULL, NULL, NULL, NULL, '2026-07-30 07:33:45', '2026-07-30 07:33:45');
INSERT INTO `payments` (`id`, `reference_type`, `reference_id`, `payer_id`, `method`, `amount`, `currency`, `status`, `transaction_ref`, `paid_at`, `refunded_at`, `notes`, `created_at`, `updated_at`) VALUES(7, 'order', 4, 5, 'cash_on_pickup', 99.00, 'PHP', 'pending', NULL, NULL, NULL, NULL, '2026-08-04 00:58:03', '2026-08-04 00:58:03');
INSERT INTO `payments` (`id`, `reference_type`, `reference_id`, `payer_id`, `method`, `amount`, `currency`, `status`, `transaction_ref`, `paid_at`, `refunded_at`, `notes`, `created_at`, `updated_at`) VALUES(8, 'order', 5, 5, 'cash_on_pickup', 99.00, 'PHP', 'pending', NULL, NULL, NULL, NULL, '2026-08-10 08:55:09', '2026-08-10 08:55:09');

-- --------------------------------------------------------

--
-- Table structure for table `review_photos`
--

DROP TABLE IF EXISTS `review_photos`;
CREATE TABLE `review_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `review_type` enum('spot','hotel') NOT NULL,
  `review_id` int(10) UNSIGNED NOT NULL,
  `url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

DROP TABLE IF EXISTS `routes`;
CREATE TABLE `routes` (
  `id` int(10) UNSIGNED NOT NULL,
  `origin_city_id` int(10) UNSIGNED NOT NULL,
  `dest_city_id` int(10) UNSIGNED NOT NULL,
  `transport_type` enum('jeepney','bus','tricycle','private_car','fx_uv') NOT NULL,
  `fare_php` decimal(8,2) DEFAULT 0.00,
  `distance_km` decimal(6,2) DEFAULT NULL,
  `duration_min` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `routes`
--

INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(1, 1, 2, 'jeepney', 25.00, 14.00, 35, 'Jeepney from Calamba terminal bound for Los Baños');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(2, 1, 2, 'bus', 30.00, 14.00, 30, 'DLTB or JAC bus via South Luzon Expressway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(3, 1, 2, 'private_car', 0.00, 14.00, 20, 'Via National Highway (Manila South Road)');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(4, 2, 1, 'jeepney', 25.00, 14.00, 35, 'Jeepney from Los Baños terminal');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(5, 2, 1, 'bus', 30.00, 14.00, 30, 'Bus from Los Baños to Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(6, 2, 3, 'jeepney', 40.00, 30.00, 60, 'Jeepney from Los Baños market');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(7, 2, 3, 'bus', 55.00, 30.00, 50, 'Bus via SLEX and San Pablo junction');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(8, 2, 3, 'private_car', 0.00, 30.00, 35, 'Via Maharlika Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(9, 3, 2, 'jeepney', 40.00, 30.00, 60, 'Jeepney from San Pablo market');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(10, 3, 4, 'jeepney', 35.00, 22.00, 45, 'Jeepney from San Pablo to Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(11, 3, 4, 'fx_uv', 50.00, 22.00, 35, 'UV Express from San Pablo market');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(12, 3, 4, 'private_car', 0.00, 22.00, 28, 'Via Maharlika Highway north');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(13, 4, 3, 'jeepney', 35.00, 22.00, 45, 'Jeepney from Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(14, 1, 8, 'bus', 60.00, 45.00, 70, 'Bus via Maharlika Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(15, 1, 8, 'fx_uv', 80.00, 45.00, 55, 'UV Express, more direct route');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(16, 1, 8, 'private_car', 0.00, 45.00, 45, 'Via South Luzon Expressway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(17, 8, 1, 'bus', 60.00, 45.00, 70, 'Bus from Santa Cruz terminal');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(18, 4, 8, 'jeepney', 20.00, 8.00, 20, 'Short jeepney ride along the highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(19, 4, 8, 'tricycle', 60.00, 8.00, 25, 'Tricycle, negotiate fare');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(20, 8, 4, 'jeepney', 20.00, 8.00, 20, 'Jeepney from Santa Cruz to Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(21, 5, 3, 'jeepney', 30.00, 18.00, 40, 'Jeepney via San Pablo');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(22, 5, 8, 'jeepney', 25.00, 15.00, 35, 'Jeepney to Santa Cruz');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(23, 3, 5, 'jeepney', 30.00, 18.00, 40, 'Jeepney from San Pablo to Nagcarlan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(24, 11, 1, 'bus', 35.00, 20.00, 30, 'Bus from Santa Rosa to Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(25, 11, 1, 'fx_uv', 45.00, 20.00, 25, 'UV Express Santa Rosa to Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(26, 11, 1, 'private_car', 0.00, 20.00, 18, 'Via SLEX');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(27, 1, 11, 'bus', 35.00, 20.00, 30, 'Bus from Calamba to Santa Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(28, 15, 4, 'jeepney', 25.00, 12.00, 30, 'Jeepney from Cavinti to Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(29, 4, 15, 'jeepney', 25.00, 12.00, 30, 'Jeepney from Pagsanjan to Cavinti');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(30, 5, 1, 'jeepney', 13.00, 8.00, 20, 'Frequent jeepney from San Pedro to Biñan terminal');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(31, 5, 1, 'fx_uv', 25.00, 8.00, 15, 'UV Express via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(32, 5, 1, 'private_car', 0.00, 8.00, 15, 'Via national highway / Circumferential Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(33, 1, 6, 'jeepney', 13.00, 6.00, 15, 'Jeepney from Biñan to Balibago, Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(34, 1, 6, 'fx_uv', 25.00, 6.00, 12, 'UV Express via SLEX service road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(35, 1, 6, 'private_car', 0.00, 6.00, 12, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(36, 6, 2, 'jeepney', 13.00, 7.00, 15, 'Jeepney from Balibago Sta. Rosa to Cabuyao');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(37, 6, 2, 'fx_uv', 25.00, 7.00, 12, 'UV Express via SLEX service road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(38, 6, 2, 'private_car', 0.00, 7.00, 12, 'Via national highway or SLEX');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(40, 2, 3, 'fx_uv', 30.00, 8.00, 15, 'UV Express, drops at SM Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(42, 3, 11, 'jeepney', 20.00, 14.00, 30, 'Jeepney from Calamba Central Terminal to UPLB/Los Baños gate; fare ~₱20');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(43, 3, 11, 'fx_uv', 35.00, 14.00, 25, 'UV Express or van from Calamba to Los Baños');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(44, 3, 11, 'private_car', 0.00, 14.00, 25, 'Via national highway (Manila South Road)');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(45, 3, 15, 'jeepney', 45.00, 40.00, 55, 'Jeepney from Calamba Crossing to Sta. Cruz; fare ~₱40-50');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(46, 3, 15, 'bus', 55.00, 40.00, 50, 'HM Transport / LLI bus from SM Calamba to Sta. Cruz terminal');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(47, 3, 15, 'private_car', 0.00, 40.00, 42, 'Via Calamba-Pagsanjan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(48, 3, 4, 'bus', 70.00, 33.50, 50, 'Jam/JAC Liner bus from Turbina Calamba to San Pablo City; departs every 30 min');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(52, 3, 13, 'jeepney', 45.00, 38.00, 55, 'Jeepney from Calamba via Calamba-Pagsanjan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(53, 3, 13, 'private_car', 0.00, 38.00, 48, 'Via Calamba-Pagsanjan Road through Los Baños');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(54, 3, 6, 'jeepney', 20.00, 15.00, 25, 'Jeepney from Calamba Crossing to Balibago, Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(55, 3, 6, 'fx_uv', 35.00, 15.00, 20, 'UV Express from Calamba to Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(56, 3, 6, 'private_car', 0.00, 15.00, 20, 'Via national highway or SLEX');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(57, 3, 1, 'jeepney', 30.00, 21.00, 35, 'Jeepney from Calamba Crossing to Biñan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(58, 3, 1, 'fx_uv', 45.00, 21.00, 28, 'UV Express from Calamba to Biñan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(59, 3, 1, 'private_car', 0.00, 21.00, 28, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(61, 3, 2, 'fx_uv', 25.00, 8.00, 15, 'UV Express from Calamba to Cabuyao');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(62, 3, 2, 'private_car', 0.00, 8.00, 15, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(64, 3, 5, 'fx_uv', 55.00, 29.00, 35, 'UV Express from Calamba to San Pedro');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(65, 3, 5, 'private_car', 0.00, 29.00, 35, 'Via SLEX or national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(67, 4, 15, 'bus', 50.00, 30.00, 45, 'Bus from San Pablo to Sta. Cruz via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(68, 4, 15, 'private_car', 0.00, 30.00, 40, 'Via national road through Pila and Victoria');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(69, 4, 12, 'jeepney', 30.00, 17.00, 35, 'Jeepney from San Pablo to Nagcarlan via Liliw Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(70, 4, 12, 'tricycle', 80.00, 17.00, 40, 'Tricycle special trip San Pablo to Nagcarlan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(71, 4, 12, 'private_car', 0.00, 17.00, 30, 'Via San Pablo–Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(75, 4, 7, 'jeepney', 20.00, 11.00, 25, 'Jeepney from San Pablo Bayan to Alaminos');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(76, 4, 7, 'tricycle', 50.00, 11.00, 25, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(77, 4, 7, 'private_car', 0.00, 11.00, 20, 'Via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(78, 4, 9, 'jeepney', 25.00, 15.00, 30, 'Jeepney from San Pablo to Calauan via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(79, 4, 9, 'private_car', 0.00, 15.00, 25, 'Via national road through Calauan junction');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(83, 4, 8, 'private_car', 0.00, 20.00, 35, 'Via Calauan–Bay Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(84, 4, 1, 'bus', 80.00, 42.00, 65, 'Bus from San Pablo to Biñan via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(85, 4, 1, 'private_car', 0.00, 42.00, 55, 'Via national highway through Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(86, 4, 6, 'bus', 75.00, 37.00, 60, 'Bus from San Pablo to Balibago Sta. Rosa via Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(87, 4, 6, 'jeepney', 65.00, 37.00, 65, 'Jeepney transfer via Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(88, 4, 6, 'private_car', 0.00, 37.00, 50, 'Via national highway through Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(89, 15, 13, 'jeepney', 15.00, 8.00, 18, 'Jeepney from Sta. Cruz town center to Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(90, 15, 13, 'tricycle', 40.00, 8.00, 18, 'Tricycle from Sta. Cruz to Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(91, 15, 13, 'private_car', 0.00, 8.00, 15, 'Via Pagsanjan–Sta. Cruz Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(92, 15, 11, 'jeepney', 35.00, 25.00, 45, 'Jeepney from Sta. Cruz to Los Baños via Calamba Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(93, 15, 11, 'bus', 45.00, 25.00, 40, 'HM Liner / bus passing through Los Baños');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(94, 15, 11, 'private_car', 0.00, 25.00, 38, 'Via Calamba-Pagsanjan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(97, 15, 12, 'jeepney', 35.00, 22.00, 42, 'Jeepney from Sta. Cruz to Nagcarlan via Calumpang Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(98, 15, 12, 'private_car', 0.00, 22.00, 38, 'Via Sta. Cruz–Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(104, 15, 16, 'jeepney', 35.00, 25.00, 45, 'Jeepney from Sta. Cruz to Siniloan via Manila East Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(105, 15, 16, 'private_car', 0.00, 25.00, 38, 'Via Manila East Road along Laguna de Bay');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(106, 15, 14, 'jeepney', 22.00, 15.00, 28, 'Jeepney from Sta. Cruz to Pila via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(107, 15, 14, 'tricycle', 55.00, 15.00, 28, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(108, 15, 14, 'private_car', 0.00, 15.00, 22, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(112, 11, 8, 'jeepney', 18.00, 10.00, 22, 'Jeepney from Los Baños to Bay via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(113, 11, 8, 'tricycle', 45.00, 10.00, 22, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(114, 11, 8, 'private_car', 0.00, 10.00, 18, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(115, 11, 9, 'jeepney', 25.00, 15.00, 32, 'Jeepney from Los Baños to Calauan via Bay-Calauan Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(116, 11, 9, 'private_car', 0.00, 15.00, 28, 'Via Bay-Calauan Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(117, 11, 13, 'jeepney', 30.00, 22.00, 42, 'Jeepney from Los Baños to Pagsanjan via Sta. Cruz road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(118, 11, 13, 'private_car', 0.00, 22.00, 35, 'Via Calamba-Pagsanjan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(119, 11, 6, 'jeepney', 40.00, 29.00, 48, 'Jeepney transfer via Calamba to Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(120, 11, 6, 'fx_uv', 55.00, 29.00, 40, 'UV Express from Los Baños via Calamba to Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(121, 11, 6, 'private_car', 0.00, 29.00, 38, 'Via national highway through Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(122, 11, 4, 'jeepney', 38.00, 26.00, 48, 'Jeepney from Los Baños to San Pablo via Calauan or Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(123, 11, 4, 'private_car', 0.00, 26.00, 40, 'Via national highway or Calauan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(139, 13, 16, 'jeepney', 40.00, 30.00, 50, 'Jeepney from Pagsanjan to Siniloan via lakeshore towns');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(140, 13, 16, 'private_car', 0.00, 30.00, 42, 'Via Manila East Road through Lumban, Pangil, Pakil, Paete');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(141, 14, 4, 'jeepney', 28.00, 18.00, 35, 'Jeepney from Pila to San Pablo via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(142, 14, 4, 'private_car', 0.00, 18.00, 28, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(143, 14, 8, 'jeepney', 18.00, 10.00, 22, 'Jeepney from Pila to Bay via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(144, 14, 8, 'tricycle', 45.00, 10.00, 22, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(145, 14, 8, 'private_car', 0.00, 10.00, 18, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(149, 8, 9, 'jeepney', 15.00, 8.00, 18, 'Jeepney from Bay to Calauan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(150, 8, 9, 'tricycle', 40.00, 8.00, 18, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(151, 8, 9, 'private_car', 0.00, 8.00, 15, 'Via Bay-Calauan Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(152, 9, 12, 'jeepney', 22.00, 14.00, 28, 'Jeepney from Calauan to Nagcarlan via Calauan–Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(153, 9, 12, 'tricycle', 55.00, 14.00, 28, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(154, 9, 12, 'private_car', 0.00, 14.00, 25, 'Via Calauan–Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(168, 13, 12, 'jeepney', 13.00, 8.00, 20, 'Jeepney from San Pedro to Biñan terminal');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(169, 13, 12, 'fx_uv', 25.00, 8.00, 15, 'UV Express via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(170, 13, 12, 'private_car', 0.00, 8.00, 15, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(171, 12, 11, 'jeepney', 13.00, 6.00, 15, 'Jeepney from Biñan to Balibago, Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(172, 12, 11, 'fx_uv', 25.00, 6.00, 12, 'UV Express via SLEX service road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(173, 12, 11, 'private_car', 0.00, 6.00, 12, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(174, 11, 14, 'jeepney', 13.00, 7.00, 15, 'Jeepney from Balibago Sta. Rosa to Cabuyao');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(175, 11, 14, 'fx_uv', 25.00, 7.00, 12, 'UV Express via SLEX service road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(176, 11, 14, 'private_car', 0.00, 7.00, 12, 'Via national highway or SLEX');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(177, 14, 1, 'jeepney', 15.00, 8.00, 20, 'Jeepney from Cabuyao to Calamba Crossing');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(178, 14, 1, 'fx_uv', 30.00, 8.00, 15, 'UV Express, drops at SM Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(179, 14, 1, 'private_car', 0.00, 8.00, 15, 'Via national highway or SLEX Calamba exit');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(181, 1, 2, 'fx_uv', 35.00, 14.00, 25, 'UV Express or van from Calamba to Los Baños');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(183, 1, 8, 'jeepney', 45.00, 40.00, 55, 'Jeepney from Calamba Crossing to Sta. Cruz; fare ~₱40–50');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(186, 1, 3, 'bus', 70.00, 33.50, 50, 'Jam/JAC Liner bus from Turbina Calamba to San Pablo City; every 30 min');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(187, 1, 3, 'jeepney', 55.00, 33.50, 55, 'Jeepney from Calamba Central Terminal to San Pablo Bayan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(188, 1, 3, 'fx_uv', 80.00, 33.50, 40, 'UV Express / van from Calamba to San Pablo');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(189, 1, 3, 'private_car', 0.00, 33.50, 35, 'Via national highway ~33–34 km drive');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(190, 1, 4, 'jeepney', 45.00, 38.00, 55, 'Jeepney from Calamba via Calamba-Pagsanjan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(191, 1, 4, 'private_car', 0.00, 38.00, 48, 'Via Calamba-Pagsanjan Road through Los Baños');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(192, 1, 11, 'jeepney', 20.00, 15.00, 25, 'Jeepney from Calamba Crossing to Balibago, Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(193, 1, 11, 'fx_uv', 35.00, 15.00, 20, 'UV Express from Calamba to Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(194, 1, 11, 'private_car', 0.00, 15.00, 20, 'Via national highway or SLEX');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(195, 1, 12, 'jeepney', 30.00, 21.00, 35, 'Jeepney from Calamba Crossing to Biñan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(196, 1, 12, 'fx_uv', 45.00, 21.00, 28, 'UV Express from Calamba to Biñan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(197, 1, 12, 'private_car', 0.00, 21.00, 28, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(198, 1, 14, 'jeepney', 15.00, 8.00, 18, 'Jeepney from Calamba Crossing to Cabuyao');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(199, 1, 14, 'fx_uv', 25.00, 8.00, 15, 'UV Express from Calamba to Cabuyao');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(200, 1, 14, 'private_car', 0.00, 8.00, 15, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(201, 1, 13, 'jeepney', 40.00, 29.00, 45, 'Jeepney from Calamba to San Pedro via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(202, 1, 13, 'fx_uv', 55.00, 29.00, 35, 'UV Express via SLEX Mayapa exit; Pacita Complex fare ~₱68');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(203, 1, 13, 'private_car', 0.00, 29.00, 35, 'Via SLEX or national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(204, 1, 7, 'jeepney', 38.00, 28.00, 45, 'Jeepney from Calamba to Pila via Sta. Cruz road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(205, 1, 7, 'private_car', 0.00, 28.00, 38, 'Via national highway through Victoria');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(206, 3, 8, 'jeepney', 40.00, 30.00, 50, 'Jeepney from San Pablo Bayan to Sta. Cruz terminal; direct route');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(207, 3, 8, 'bus', 50.00, 30.00, 45, 'Bus from San Pablo to Sta. Cruz via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(208, 3, 8, 'private_car', 0.00, 30.00, 40, 'Via national road through Pila and Victoria');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(210, 3, 5, 'tricycle', 80.00, 17.00, 40, 'Tricycle special trip San Pablo to Nagcarlan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(212, 3, 10, 'jeepney', 45.00, 25.00, 50, 'Jeepney from San Pablo to Majayjay via Nagcarlan and Rizal; fare ₱40–50');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(213, 3, 10, 'private_car', 0.00, 25.00, 40, 'Via San Pablo–Majayjay Road through Nagcarlan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(214, 3, 16, 'jeepney', 25.00, 15.00, 30, 'Jeepney from San Pablo to Calauan via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(215, 3, 16, 'private_car', 0.00, 15.00, 25, 'Via national road through Calauan junction');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(216, 3, 7, 'jeepney', 28.00, 18.00, 35, 'Jeepney from San Pablo to Pila via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(217, 3, 7, 'private_car', 0.00, 18.00, 28, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(218, 3, 12, 'bus', 80.00, 42.00, 65, 'Bus from San Pablo to Biñan via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(219, 3, 12, 'private_car', 0.00, 42.00, 55, 'Via national highway through Calamba and Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(220, 3, 11, 'bus', 75.00, 37.00, 60, 'Bus from San Pablo to Balibago Sta. Rosa via Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(227, 8, 4, 'tricycle', 40.00, 8.00, 18, 'Tricycle from Sta. Cruz to Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(228, 8, 4, 'private_car', 0.00, 8.00, 15, 'Via Pagsanjan–Sta. Cruz Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(229, 8, 2, 'jeepney', 35.00, 25.00, 45, 'Jeepney from Sta. Cruz to Los Baños via Calamba Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(230, 8, 2, 'bus', 45.00, 25.00, 40, 'HM Liner / bus passing through Los Baños');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(231, 8, 2, 'private_car', 0.00, 25.00, 38, 'Via Calamba-Pagsanjan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(232, 8, 5, 'jeepney', 35.00, 22.00, 42, 'Jeepney from Sta. Cruz to Nagcarlan via Calumpang Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(233, 8, 5, 'private_car', 0.00, 22.00, 38, 'Via Sta. Cruz–Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(234, 8, 10, 'jeepney', 42.00, 32.00, 60, 'Jeepney from Sta. Cruz to Majayjay via Magdalena; 6:00 AM departure');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(235, 8, 10, 'private_car', 0.00, 32.00, 50, 'Via Sta. Cruz–Majayjay Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(236, 8, 6, 'jeepney', 28.00, 18.00, 35, 'Jeepney from Sta. Cruz to Lumban via lakeside road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(237, 8, 6, 'tricycle', 70.00, 18.00, 35, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(238, 8, 6, 'private_car', 0.00, 18.00, 28, 'Via lakeshore road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(239, 8, 7, 'jeepney', 22.00, 15.00, 28, 'Jeepney from Sta. Cruz to Pila via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(240, 8, 7, 'tricycle', 55.00, 15.00, 28, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(241, 8, 7, 'private_car', 0.00, 15.00, 22, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(244, 8, 15, 'jeepney', 40.00, 30.00, 52, 'Jeepney from Sta. Cruz to Cavinti via Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(245, 8, 15, 'private_car', 0.00, 30.00, 45, 'Via Pagsanjan or lakeshore road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(246, 2, 16, 'jeepney', 25.00, 15.00, 32, 'Jeepney from Los Baños to Calauan via Bay-Calauan Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(247, 2, 16, 'private_car', 0.00, 15.00, 28, 'Via Bay-Calauan Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(248, 2, 4, 'jeepney', 30.00, 22.00, 42, 'Jeepney from Los Baños to Pagsanjan via Sta. Cruz road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(249, 2, 4, 'private_car', 0.00, 22.00, 35, 'Via Calamba-Pagsanjan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(250, 2, 11, 'jeepney', 40.00, 29.00, 50, 'Jeepney transfer via Calamba to Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(251, 2, 11, 'fx_uv', 55.00, 29.00, 42, 'UV Express from Los Baños via Calamba to Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(252, 2, 11, 'private_car', 0.00, 29.00, 38, 'Via national highway through Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(255, 2, 5, 'jeepney', 32.00, 22.00, 42, 'Jeepney from Los Baños to Nagcarlan via Calauan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(256, 2, 5, 'private_car', 0.00, 22.00, 38, 'Via Bay-Calauan-Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(258, 4, 6, 'tricycle', 45.00, 10.00, 20, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(261, 4, 9, 'tricycle', 60.00, 15.00, 28, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(264, 4, 15, 'tricycle', 70.00, 18.00, 35, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(266, 6, 9, 'jeepney', 15.00, 8.00, 18, 'Jeepney from Lumban to Pangil via lakeshore');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(267, 6, 9, 'tricycle', 40.00, 8.00, 18, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(268, 6, 9, 'private_car', 0.00, 8.00, 15, 'Via Manila East Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(269, 6, 15, 'jeepney', 20.00, 12.00, 25, 'Jeepney from Lumban to Cavinti (Lake Caliraya area)');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(270, 6, 15, 'tricycle', 55.00, 12.00, 25, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(271, 6, 15, 'private_car', 0.00, 12.00, 22, 'Via Manila East Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(272, 5, 10, 'jeepney', 18.00, 10.00, 22, 'Jeepney from Nagcarlan to Majayjay via Rizal (Laguna)');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(273, 5, 10, 'tricycle', 50.00, 10.00, 22, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(274, 5, 10, 'private_car', 0.00, 10.00, 18, 'Via Nagcarlan–Majayjay Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(275, 5, 16, 'jeepney', 22.00, 14.00, 28, 'Jeepney from Nagcarlan to Calauan via Calauan–Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(276, 5, 16, 'tricycle', 55.00, 14.00, 28, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(277, 5, 16, 'private_car', 0.00, 14.00, 25, 'Via Calauan–Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(279, 5, 8, 'private_car', 0.00, 22.00, 38, 'Via Sta. Cruz–Nagcarlan Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(280, 10, 8, 'jeepney', 42.00, 32.00, 60, 'Jeepney Majayjay to Sta. Cruz via Magdalena; 6:00 AM departure noted');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(281, 10, 8, 'private_car', 0.00, 32.00, 50, 'Via Sta. Cruz–Majayjay Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(282, 10, 3, 'jeepney', 45.00, 25.00, 50, 'Jeepney Majayjay to San Pablo via Nagcarlan and Rizal; fare ₱40–50');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(283, 10, 3, 'private_car', 0.00, 25.00, 40, 'Via Majayjay–San Pablo Road through Nagcarlan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(284, 7, 8, 'jeepney', 22.00, 15.00, 28, 'Jeepney from Pila to Sta. Cruz via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(285, 7, 8, 'tricycle', 55.00, 15.00, 28, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(286, 7, 8, 'private_car', 0.00, 15.00, 22, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(287, 7, 3, 'jeepney', 28.00, 18.00, 35, 'Jeepney from Pila to San Pablo via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(288, 7, 3, 'private_car', 0.00, 18.00, 28, 'Via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(289, 16, 3, 'jeepney', 25.00, 15.00, 30, 'Jeepney from Calauan to San Pablo via national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(290, 16, 3, 'private_car', 0.00, 15.00, 25, 'Via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(291, 16, 2, 'jeepney', 25.00, 15.00, 32, 'Jeepney from Calauan to Los Baños via Bay-Calauan Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(292, 16, 2, 'private_car', 0.00, 15.00, 28, 'Via Bay-Calauan Highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(293, 13, 11, 'jeepney', 20.00, 14.00, 28, 'Jeepney from San Pedro to Balibago, Sta. Rosa via Biñan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(294, 13, 11, 'fx_uv', 35.00, 14.00, 22, 'UV Express from San Pedro to Sta. Rosa');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(295, 13, 11, 'private_car', 0.00, 14.00, 22, 'Via national highway or SLEX service road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(296, 13, 1, 'jeepney', 40.00, 29.00, 45, 'Jeepney from San Pedro to Calamba via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(297, 13, 1, 'fx_uv', 55.00, 29.00, 38, 'UV Express via SLEX Mayapa exit; fare ~₱68 from Pacita');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(298, 13, 1, 'private_car', 0.00, 29.00, 35, 'Via SLEX or national highway');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(299, 12, 14, 'jeepney', 20.00, 14.00, 28, 'Jeepney from Biñan to Cabuyao via national road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(300, 12, 14, 'fx_uv', 35.00, 14.00, 22, 'UV Express from Biñan to Cabuyao');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(301, 12, 14, 'private_car', 0.00, 14.00, 22, 'Via national highway or SLEX service road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(302, 12, 1, 'jeepney', 30.00, 21.00, 38, 'Jeepney from Biñan to Calamba via Sta. Rosa and Cabuyao');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(303, 12, 1, 'fx_uv', 48.00, 21.00, 30, 'UV Express from Biñan to Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(304, 12, 1, 'private_car', 0.00, 21.00, 30, 'Via national highway or SLEX');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(305, 11, 2, 'jeepney', 40.00, 29.00, 50, 'Jeepney from Sta. Rosa via Cabuyao and Calamba to Los Baños');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(306, 11, 2, 'fx_uv', 60.00, 29.00, 42, 'UV Express from Sta. Rosa to Los Baños via Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(307, 11, 2, 'private_car', 0.00, 29.00, 40, 'Via national highway through Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(308, 11, 3, 'bus', 75.00, 37.00, 60, 'Bus from Sta. Rosa to San Pablo via Calamba; Turbina terminal');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(309, 11, 3, 'private_car', 0.00, 37.00, 50, 'Via national highway through Calamba');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(311, 15, 4, 'tricycle', 70.00, 18.00, 35, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(312, 15, 4, 'private_car', 0.00, 18.00, 28, 'Via Manila East Road through Lumban');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(313, 15, 8, 'jeepney', 40.00, 30.00, 52, 'Jeepney from Cavinti to Sta. Cruz via Pagsanjan');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(314, 15, 8, 'private_car', 0.00, 30.00, 45, 'Via Pagsanjan or lakeshore road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(315, 9, 6, 'jeepney', 15.00, 8.00, 18, 'Jeepney from Pangil to Lumban');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(316, 9, 6, 'tricycle', 40.00, 8.00, 18, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(317, 9, 6, 'private_car', 0.00, 8.00, 15, 'Via Manila East Road');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(318, 9, 4, 'jeepney', 22.00, 15.00, 28, 'Jeepney from Pangil to Pagsanjan via Lumban');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(319, 9, 4, 'tricycle', 60.00, 15.00, 28, 'Tricycle special trip');
INSERT INTO `routes` (`id`, `origin_city_id`, `dest_city_id`, `transport_type`, `fare_php`, `distance_km`, `duration_min`, `notes`) VALUES(320, 9, 4, 'private_car', 0.00, 15.00, 25, 'Via Manila East Road through Lumban');

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

DROP TABLE IF EXISTS `shops`;
CREATE TABLE `shops` (
  `id` int(10) UNSIGNED NOT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `city_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('milktea','cafe','restaurant','bakery','street_food','souvenir','pasalubong','grocery','other') NOT NULL DEFAULT 'other',
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `open_days` varchar(60) DEFAULT NULL COMMENT 'e.g. Mon-Sat',
  `logo_url` varchar(255) DEFAULT NULL,
  `cover_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `owner_id`, `city_id`, `name`, `slug`, `description`, `category`, `address`, `latitude`, `longitude`, `phone`, `email`, `open_time`, `close_time`, `open_days`, `logo_url`, `cover_url`, `is_active`, `is_verified`, `created_at`, `updated_at`) VALUES(1, 2, 3, 'MilkTea ni Sam', 'milktea-ni-sam-2', 'Its a milktea', 'milktea', '123, milkteastreet', NULL, NULL, '09123456789', 'test@example.com', '08:00:00', '21:00:00', 'Mon-Sat', NULL, NULL, 1, 1, '2026-05-24 11:56:58', '2026-07-14 03:10:01');
INSERT INTO `shops` (`id`, `owner_id`, `city_id`, `name`, `slug`, `description`, `category`, `address`, `latitude`, `longitude`, `phone`, `email`, `open_time`, `close_time`, `open_days`, `logo_url`, `cover_url`, `is_active`, `is_verified`, `created_at`, `updated_at`) VALUES(2, 7, 3, 'Jems Pasalubong', 'jems-pasalubong-7', '', 'pasalubong', 'km75 Maharlika Highway', NULL, NULL, '09123456789', 'test@example.com', '08:00:00', '19:00:00', '', NULL, NULL, 1, 1, '2026-07-29 15:49:19', '2026-07-30 07:35:22');

-- --------------------------------------------------------

--
-- Table structure for table `shop_products`
--

DROP TABLE IF EXISTS `shop_products`;
CREATE TABLE `shop_products` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category` varchar(60) DEFAULT NULL COMMENT 'e.g. Drinks, Snacks, Pastries',
  `image_url` varchar(255) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 999 COMMENT '999 = unlimited',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shop_products`
--

INSERT INTO `shop_products` (`id`, `shop_id`, `name`, `description`, `price`, `category`, `image_url`, `stock`, `is_available`, `sort_order`, `created_at`, `updated_at`) VALUES(1, 1, 'Milktea1', 'A very very yummy milktea', 99.00, 'Drinks', NULL, 999, 1, 0, '2026-07-14 03:50:08', '2026-07-14 03:50:08');
INSERT INTO `shop_products` (`id`, `shop_id`, `name`, `description`, `price`, `category`, `image_url`, `stock`, `is_available`, `sort_order`, `created_at`, `updated_at`) VALUES(2, 1, 'Milktea2', 'muchm ucc', 99.00, 'Drinks', NULL, 999, 1, 0, '2026-07-17 02:15:53', '2026-07-17 02:15:53');

-- --------------------------------------------------------

--
-- Table structure for table `shop_reviews`
--

DROP TABLE IF EXISTS `shop_reviews`;
CREATE TABLE `shop_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `shop_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `tourist_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(4) NOT NULL COMMENT '1–5',
  `comment` text DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES('logo_path', '', '2026-08-14 16:58:29');
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES('theme_accent', '#e9c46a', '2026-08-14 16:28:05');
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES('theme_dark', '#6b0f14', '2026-08-14 16:28:05');
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES('theme_light', '#e2574c', '2026-08-14 16:28:05');
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES('theme_pale', '#fbdede', '2026-08-14 16:28:05');
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES('theme_primary', '#a61c1c', '2026-08-14 16:28:05');

-- --------------------------------------------------------

--
-- Table structure for table `spot_amenities`
--

DROP TABLE IF EXISTS `spot_amenities`;
CREATE TABLE `spot_amenities` (
  `id` int(10) UNSIGNED NOT NULL,
  `spot_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(80) NOT NULL,
  `icon` varchar(50) DEFAULT 'bi-check-circle'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spot_amenities`
--

INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(1, 1, 'Guided Tours', 'bi-person-walking');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(2, 1, 'Parking', 'bi-p-circle');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(3, 1, 'Restrooms', 'bi-door-closed');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(4, 2, 'Swimming Pools', 'bi-droplet-fill');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(5, 2, 'Restaurant', 'bi-cup-hot');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(6, 2, 'Parking', 'bi-p-circle');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(7, 2, 'Cottages', 'bi-house');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(8, 8, 'Boat Rentals', 'bi-water');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(9, 8, 'Picnic Area', 'bi-tree');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(10, 8, 'Restrooms', 'bi-door-closed');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(11, 12, 'Bangka Ride', 'bi-water');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(12, 12, 'Life Vests', 'bi-shield-check');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(13, 12, 'Guides Required', 'bi-person-walking');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(14, 12, 'Changing Rooms', 'bi-door-open');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(15, 19, 'Rides & Attractions', 'bi-lightning-charge');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(16, 19, 'Food Stalls', 'bi-cup-hot');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(17, 19, 'Parking', 'bi-p-circle');
INSERT INTO `spot_amenities` (`id`, `spot_id`, `label`, `icon`) VALUES(18, 19, 'Restrooms', 'bi-door-closed');

-- --------------------------------------------------------

--
-- Table structure for table `spot_checkins`
--

DROP TABLE IF EXISTS `spot_checkins`;
CREATE TABLE `spot_checkins` (
  `id` int(10) UNSIGNED NOT NULL,
  `spot_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `distance_meters` int(10) UNSIGNED NOT NULL,
  `checked_in_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spot_photos`
--

DROP TABLE IF EXISTS `spot_photos`;
CREATE TABLE `spot_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `spot_id` int(10) UNSIGNED NOT NULL,
  `url` varchar(255) NOT NULL,
  `caption` varchar(200) DEFAULT NULL,
  `photo_type` enum('main','gallery','food','activity') DEFAULT 'gallery',
  `sort_order` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spot_photos`
--

INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(1, 8, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5bb77225cbc0.16349401.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(2, 8, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5bb7d38e1ae2.37430416.jpg', '', 'gallery', 2);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(3, 8, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5bb7dc3b0bf2.04636702.jpg', '', 'gallery', 3);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(4, 2, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5d07481b7249.03346598.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(5, 19, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5d0760edd803.67976451.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(6, 6, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5d07711ea9e5.19023139.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(7, 1, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5d0780614ae9.94961767.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(8, 12, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5d07a0722ff3.56132025.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(9, 3, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da6327f0b00.37737312.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(10, 21, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da649cf3db8.85578530.webp', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(11, 7, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da6564cd9f8.84890123.webp', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(12, 5, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da661f07b96.62871469.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(13, 4, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da66d749549.52578109.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(14, 15, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da67977f8b9.65644776.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(15, 18, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da682e4f0d0.99552225.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(16, 14, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da6a468f1b5.63069497.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(17, 13, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da8735447e6.30445146.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(18, 16, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da8a25022f5.22720023.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(19, 9, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da8b03c3572.43472053.webp', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(20, 10, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da8bbf00d72.95140809.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(21, 11, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da8c4f1c5e0.01338214.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(22, 17, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da8f9ad0517.36757660.jpg', '', 'main', 1);
INSERT INTO `spot_photos` (`id`, `spot_id`, `url`, `caption`, `photo_type`, `sort_order`) VALUES(23, 20, 'http://localhost/iexplore-laguna/uploads/spots/spots_6a5da9374dd227.17418439.jpg', '', 'main', 1);

-- --------------------------------------------------------

--
-- Table structure for table `spot_reviews`
--

DROP TABLE IF EXISTS `spot_reviews`;
CREATE TABLE `spot_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `spot_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `title` varchar(120) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `visited_on` date DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spot_reviews`
--

INSERT INTO `spot_reviews` (`id`, `spot_id`, `user_id`, `rating`, `title`, `body`, `visited_on`, `is_approved`, `created_at`) VALUES(1, 11, 5, 3, NULL, 'hmmm good enough', '2026-07-15', 1, '2026-07-15 09:50:56');
INSERT INTO `spot_reviews` (`id`, `spot_id`, `user_id`, `rating`, `title`, `body`, `visited_on`, `is_approved`, `created_at`) VALUES(2, 12, 5, 1, NULL, 'p*******a', NULL, 1, '2026-07-30 00:10:25');

-- --------------------------------------------------------

--
-- Table structure for table `spot_views`
--

DROP TABLE IF EXISTS `spot_views`;
CREATE TABLE `spot_views` (
  `id` int(10) UNSIGNED NOT NULL,
  `spot_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spot_views`
--

INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(1, 19, NULL, '2026-08-01 03:07:17');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(2, 18, NULL, '2026-08-02 07:05:44');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(3, 8, NULL, '2026-08-02 07:06:06');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(4, 19, 5, '2026-08-02 13:25:13');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(5, 8, 5, '2026-08-02 13:25:21');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(6, 8, 5, '2026-08-02 13:25:39');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(7, 8, NULL, '2026-08-04 00:53:47');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(8, 8, 5, '2026-08-04 00:56:29');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(9, 8, 5, '2026-08-04 00:57:11');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(10, 8, 5, '2026-08-26 01:46:41');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(11, 8, 5, '2026-09-02 02:55:41');
INSERT INTO `spot_views` (`id`, `spot_id`, `user_id`, `viewed_at`) VALUES(12, 8, 5, '2026-09-02 02:55:54');

-- --------------------------------------------------------

--
-- Table structure for table `tourist_spots`
--

DROP TABLE IF EXISTS `tourist_spots`;
CREATE TABLE `tourist_spots` (
  `id` int(10) UNSIGNED NOT NULL,
  `city_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('nature','heritage','waterfall','hotspring','museum','religious','beach_lake','adventure','food') NOT NULL,
  `entrance_fee` decimal(8,2) DEFAULT 0.00,
  `operating_hours` varchar(100) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `rating` decimal(3,2) DEFAULT 4.00,
  `image_url` varchar(255) DEFAULT NULL,
  `google_maps_url` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `tips` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `closure_reason` varchar(255) DEFAULT NULL,
  `closed_until` date DEFAULT NULL,
  `closure_updated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tourist_spots`
--

INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(1, 1, 'Rizal Shrine', 'rizal-shrine', 'Birthplace of national hero Dr. Jose Rizal, now a national museum.', 'heritage', 30.00, '8:00 AM – 5:00 PM', NULL, 14.2115000, 121.1653000, 4.70, NULL, NULL, NULL, 'Go early to avoid crowds. Guided tours are available.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(2, 1, 'Hidden Valley Springs', 'hidden-valley-springs', 'Natural resort with hot and cold spring pools surrounded by lush rainforest.', 'hotspring', 600.00, '7:00 AM – 5:00 PM', NULL, 14.1389000, 121.1767000, 4.60, NULL, NULL, NULL, 'Entrance fee includes use of all pools. Bring extra clothes.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(3, 1, 'Museo ni Jose Rizal', 'museo-ni-jose-rizal', 'Museum dedicated to the life and works of Dr. Jose Rizal near the Rizal Shrine.', 'museum', 30.00, '8:00 AM – 5:00 PM', NULL, 14.2115000, 121.1653000, 4.50, NULL, NULL, NULL, 'Combined ticket with Rizal Shrine available.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(4, 2, 'UPLB Museum of Natural History', 'uplb-museum', 'Science museum on the UPLB campus featuring Philippine flora and fauna.', 'museum', 0.00, '8:00 AM – 5:00 PM', NULL, 14.1647000, 121.2437000, 4.30, NULL, NULL, NULL, 'Free entry. Great for students and families.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(5, 2, 'Makiling Botanic Gardens', 'makiling-botanic', 'Sprawling botanical garden inside the UPLB campus with hundreds of plant species.', 'nature', 50.00, '7:00 AM – 5:00 PM', NULL, 14.1631000, 121.2415000, 4.40, NULL, NULL, NULL, 'Wear comfortable shoes. Lots of walking involved.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(6, 2, 'Mount Makiling Forest Reserve', 'mount-makiling', 'A protected forest reserve and biodiversity hotspot straddling Los Baños and Sto. Tomas.', 'nature', 100.00, 'By appointment', NULL, 14.1500000, 121.2100000, 4.70, NULL, NULL, NULL, 'Requires a UPLB permit. Best for experienced hikers.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(7, 2, 'International Rice Research Institute', 'irri', 'World-renowned institute for rice research with an open museum and gene bank.', 'museum', 0.00, '8:00 AM – 5:00 PM', NULL, 14.1672000, 121.2555000, 4.20, NULL, NULL, NULL, 'Free guided tours available. Book in advance.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(8, 3, 'Sampaloc Lake', 'sampaloc-lake', 'The largest of the Seven Lakes of San Pablo, popular for boating and lakeside dining.', 'beach_lake', 0.00, 'Open daily', NULL, 14.0686000, 121.3247000, 4.00, NULL, NULL, NULL, 'Rent a paddle boat for a unique view of the lake.', 1, 1, 'Renovation', NULL, '2026-09-02 10:55:18', '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(9, 3, 'Bunot Lake', 'bunot-lake', 'A serene crater lake perfect for kayaking and swimming.', 'beach_lake', 50.00, 'Open daily', NULL, 14.0521000, 121.3189000, 4.30, NULL, NULL, NULL, 'Less crowded than Sampaloc. Ideal for a quiet escape.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(10, 3, 'Palakpakin Lake', 'palakpakin-lake', 'One of the Seven Crater Lakes, surrounded by lush vegetation.', 'beach_lake', 30.00, 'Open daily', NULL, 14.0483000, 121.3311000, 4.20, NULL, NULL, NULL, 'Great for kayaking and picnics.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(11, 3, 'Villa Escudero Museum', 'villa-escudero-museum', 'Museum inside Villa Escudero showcasing Filipino art, antiques, and religious artifacts.', 'museum', 500.00, '8:00 AM – 5:00 PM', NULL, 14.0500000, 121.2500000, 3.00, NULL, NULL, NULL, 'Museum fee is included in resort day tour packages.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(12, 4, 'Pagsanjan Falls', 'pagsanjan-falls', 'One of the most famous waterfalls in the Philippines, reached by thrilling boat rapids.', 'waterfall', 150.00, '7:00 AM – 4:00 PM', NULL, 14.2694000, 121.4577000, 1.00, NULL, NULL, NULL, 'Wear clothes you do not mind getting wet. Guides are mandatory.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(13, 4, 'Cavinti Underground River', 'cavinti-underground', 'A cave system with an underground river and impressive rock formations.', 'adventure', 100.00, '8:00 AM – 4:00 PM', NULL, 14.2467000, 121.5030000, 4.20, NULL, NULL, NULL, 'Bring a flashlight. Helmets are provided on-site.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(14, 5, 'Nagcarlan Underground Cemetery', 'nagcarlan-cemetery', 'A 19th-century subterranean cemetery declared a National Cultural Treasure.', 'heritage', 50.00, '8:00 AM – 5:00 PM', NULL, 13.9209000, 121.4162000, 4.60, NULL, NULL, NULL, 'One of the most unique heritage sites in all of Laguna.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(15, 6, 'Lumban Church', 'lumban-church', 'A centuries-old stone church showcasing Baroque architecture in the heart of Lumban.', 'religious', 0.00, 'Open daily', NULL, 14.2989000, 121.4742000, 4.30, NULL, NULL, NULL, 'Dress modestly when entering. Beautiful facade.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(16, 7, 'Pila Heritage Zone', 'pila-heritage-zone', 'A heritage zone with a row of ancestral houses and the oldest church ruins in Laguna.', 'heritage', 0.00, 'Open daily', NULL, 14.2333000, 121.3667000, 4.50, NULL, NULL, NULL, 'Walk around the town plaza and colonial-era streets.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(17, 8, 'Santa Cruz Cathedral', 'santa-cruz-cathedral', 'A centuries-old Baroque church at the heart of the Laguna provincial capital.', 'religious', 0.00, 'Open daily', NULL, 14.2822000, 121.4169000, 4.40, NULL, NULL, NULL, 'Dress modestly when visiting. Beautiful at sunset.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(18, 10, 'Majayjay Falls', 'majayjay-falls', 'A scenic multi-tiered waterfall in Majayjay, one of the hidden gems of Laguna.', 'waterfall', 50.00, '6:00 AM – 5:00 PM', NULL, 14.0144000, 121.4742000, 4.50, NULL, NULL, NULL, 'Wear trekking shoes. The trail can be slippery.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(19, 11, 'Enchanted Kingdom', 'enchanted-kingdom', 'The premier theme park in the Philippines with rides, shows, and family attractions.', 'adventure', 800.00, '10:00 AM – 8:00 PM', NULL, 14.3086000, 121.0939000, 4.70, NULL, NULL, NULL, 'Buy tickets online to skip the queue. Go on weekdays.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(20, 11, 'Nuvali Lakeside Park', 'nuvali-lakeside-park', 'A lifestyle and nature destination with lakeside walks, wakeboarding, and dining.', 'nature', 0.00, 'Open daily', NULL, 14.2890000, 121.0760000, 4.40, NULL, NULL, NULL, 'Great for family picnics and weekend outings.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');
INSERT INTO `tourist_spots` (`id`, `city_id`, `name`, `slug`, `description`, `category`, `entrance_fee`, `operating_hours`, `contact_number`, `latitude`, `longitude`, `rating`, `image_url`, `google_maps_url`, `website_url`, `tips`, `is_active`, `is_closed`, `closure_reason`, `closed_until`, `closure_updated_at`, `created_at`) VALUES(21, 15, 'Caliraya Lake', 'caliraya-lake', 'A large man-made lake in Cavinti, popular for wakeboarding, kayaking, and camping.', 'beach_lake', 50.00, 'Open daily', NULL, 14.2600000, 121.4900000, 4.50, NULL, NULL, NULL, 'Best visited early morning for calm waters and great views.', 1, 0, NULL, NULL, NULL, '2026-05-23 15:32:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `role` enum('tourist','shop_owner','hotel_owner','admin') NOT NULL DEFAULT 'tourist',
  `phone` varchar(20) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `birthdate` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `tourist_type` varchar(20) DEFAULT NULL COMMENT 'local or international',
  `nationality` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `phone`, `avatar_url`, `is_active`, `password`, `created_at`, `updated_at`, `birthdate`, `gender`, `tourist_type`, `nationality`, `province`, `city`) VALUES(1, 'Jemuel Jan Ballebar', 'test@example.com', 'tourist', NULL, NULL, 1, '$2y$12$qvivQz9nA3LRJnUyPV4VSOCrNfTCAt50vg5Blhus2sh0FjRv4iSXO', '2026-05-23 15:35:26', '2026-05-24 05:20:02', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `name`, `email`, `role`, `phone`, `avatar_url`, `is_active`, `password`, `created_at`, `updated_at`, `birthdate`, `gender`, `tourist_type`, `nationality`, `province`, `city`) VALUES(2, 'Jem jem', 'test@example.com', 'shop_owner', '09618728715', NULL, 1, '$2y$12$hdEbUBD.oFplx1SkVQiUE.eZ66F0GvhjQDAjPEUalLXRrN.824kZG', '2026-05-24 11:08:52', '2026-05-24 11:08:52', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `name`, `email`, `role`, `phone`, `avatar_url`, `is_active`, `password`, `created_at`, `updated_at`, `birthdate`, `gender`, `tourist_type`, `nationality`, `province`, `city`) VALUES(3, 'Jemuel Jan Ballebar', 'test@example.com', 'hotel_owner', '09618728715', NULL, 1, '$2y$12$iMgk4GZehdCMn2J3aDHGy.KNWOZO67lOPpj83558k/LkMJdpRNo3q', '2026-07-13 16:39:05', '2026-07-13 16:39:05', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `name`, `email`, `role`, `phone`, `avatar_url`, `is_active`, `password`, `created_at`, `updated_at`, `birthdate`, `gender`, `tourist_type`, `nationality`, `province`, `city`) VALUES(4, 'Admin', 'test@example.com', 'admin', NULL, NULL, 1, '$2y$12$fE8AEIHHvgL9xE2aVQ1dF.eVv81XbH8yQuW8ClPYxJPrjF8sAd3TC', '2026-07-14 03:47:12', '2026-07-14 03:47:47', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `name`, `email`, `role`, `phone`, `avatar_url`, `is_active`, `password`, `created_at`, `updated_at`, `birthdate`, `gender`, `tourist_type`, `nationality`, `province`, `city`) VALUES(5, 'jem jem', 'test@example.com', 'tourist', NULL, NULL, 1, '$2y$12$ON47rGzaGIeq5bEkHKRwy.OJv/jBVcY.IYCF9p4div542SCIwOlmG', '2026-07-14 03:48:37', '2026-07-14 03:48:37', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `name`, `email`, `role`, `phone`, `avatar_url`, `is_active`, `password`, `created_at`, `updated_at`, `birthdate`, `gender`, `tourist_type`, `nationality`, `province`, `city`) VALUES(6, 'sam', 'test@example.com', 'hotel_owner', '09123456789', NULL, 1, '$2y$12$w.HeZRXXMMrLfnNX12rF4ugNAcY2DFE3K6e7K0gn7bA6.6FS1WwYm', '2026-07-17 02:19:46', '2026-07-17 02:19:46', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `name`, `email`, `role`, `phone`, `avatar_url`, `is_active`, `password`, `created_at`, `updated_at`, `birthdate`, `gender`, `tourist_type`, `nationality`, `province`, `city`) VALUES(7, 'jem', 'test@example.com', 'shop_owner', '09123456789', NULL, 1, '$2y$12$gD0Do8W03c7Qnr5KDXXkr.WuD3eS0b8riY.mpTZRo0cncPE6fNxt.', '2026-07-29 15:47:26', '2026-07-29 15:47:26', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_booking_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_booking_summary`;
CREATE TABLE `v_booking_summary` (
`id` int(10) unsigned
,`booking_number` varchar(20)
,`status` enum('pending','confirmed','checked_in','checked_out','cancelled','no_show')
,`check_in_date` date
,`check_out_date` date
,`nights` tinyint(4)
,`guests_count` tinyint(4)
,`total_amount` decimal(10,2)
,`guest_name` varchar(120)
,`guest_phone` varchar(20)
,`created_at` timestamp
,`tourist_name` varchar(100)
,`tourist_email` varchar(150)
,`hotel_name` varchar(150)
,`hotel_id` int(10) unsigned
,`hotel_owner_id` int(10) unsigned
,`room_type` varchar(80)
,`bed_type` varchar(60)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_order_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_order_summary`;
CREATE TABLE `v_order_summary` (
`id` int(10) unsigned
,`order_number` varchar(20)
,`status` enum('pending','confirmed','preparing','ready','picked_up','cancelled')
,`total_amount` decimal(10,2)
,`pickup_date` date
,`pickup_time` time
,`pickup_code` varchar(8)
,`created_at` timestamp
,`tourist_name` varchar(100)
,`tourist_email` varchar(150)
,`tourist_phone` varchar(20)
,`shop_name` varchar(120)
,`shop_id` int(10) unsigned
,`shop_owner_id` int(10) unsigned
,`item_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure for view `v_booking_summary`
--
DROP TABLE IF EXISTS `v_booking_summary`;

DROP VIEW IF EXISTS `v_booking_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_booking_summary`  AS SELECT `b`.`id` AS `id`, `b`.`booking_number` AS `booking_number`, `b`.`status` AS `status`, `b`.`check_in_date` AS `check_in_date`, `b`.`check_out_date` AS `check_out_date`, `b`.`nights` AS `nights`, `b`.`guests_count` AS `guests_count`, `b`.`total_amount` AS `total_amount`, `b`.`guest_name` AS `guest_name`, `b`.`guest_phone` AS `guest_phone`, `b`.`created_at` AS `created_at`, `u`.`name` AS `tourist_name`, `u`.`email` AS `tourist_email`, `h`.`name` AS `hotel_name`, `h`.`id` AS `hotel_id`, `h`.`owner_id` AS `hotel_owner_id`, `r`.`room_type` AS `room_type`, `r`.`bed_type` AS `bed_type` FROM (((`bookings` `b` join `users` `u` on(`b`.`tourist_id` = `u`.`id`)) join `hotels` `h` on(`b`.`hotel_id` = `h`.`id`)) join `hotel_rooms` `r` on(`b`.`room_id` = `r`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_order_summary`
--
DROP TABLE IF EXISTS `v_order_summary`;

DROP VIEW IF EXISTS `v_order_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_order_summary`  AS SELECT `o`.`id` AS `id`, `o`.`order_number` AS `order_number`, `o`.`status` AS `status`, `o`.`total_amount` AS `total_amount`, `o`.`pickup_date` AS `pickup_date`, `o`.`pickup_time` AS `pickup_time`, `o`.`pickup_code` AS `pickup_code`, `o`.`created_at` AS `created_at`, `u`.`name` AS `tourist_name`, `u`.`email` AS `tourist_email`, `u`.`phone` AS `tourist_phone`, `s`.`name` AS `shop_name`, `s`.`id` AS `shop_id`, `s`.`owner_id` AS `shop_owner_id`, count(`oi`.`id`) AS `item_count` FROM (((`orders` `o` join `users` `u` on(`o`.`tourist_id` = `u`.`id`)) join `shops` `s` on(`o`.`shop_id` = `s`.`id`)) left join `order_items` `oi` on(`oi`.`order_id` = `o`.`id`)) GROUP BY `o`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_booking_number` (`booking_number`),
  ADD KEY `idx_booking_tourist` (`tourist_id`),
  ADD KEY `idx_booking_hotel` (`hotel_id`),
  ADD KEY `idx_booking_room` (`room_id`),
  ADD KEY `idx_booking_status` (`status`),
  ADD KEY `idx_booking_dates` (`check_in_date`,`check_out_date`);

--
-- Indexes for table `budget_estimates`
--
ALTER TABLE `budget_estimates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `city_id` (`city_id`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `hotels`
--
ALTER TABLE `hotels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `fk_hotel_owner` (`owner_id`);

--
-- Indexes for table `hotel_amenities`
--
ALTER TABLE `hotel_amenities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hotel_amenities_hotel` (`hotel_id`);

--
-- Indexes for table `hotel_photos`
--
ALTER TABLE `hotel_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hotel_photos_hotel` (`hotel_id`);

--
-- Indexes for table `hotel_reviews`
--
ALTER TABLE `hotel_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hotel_reviews_hotel` (`hotel_id`),
  ADD KEY `idx_hotel_reviews_user` (`user_id`);

--
-- Indexes for table `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_hotel` (`hotel_id`),
  ADD KEY `idx_room_owner` (`owner_id`);

--
-- Indexes for table `itineraries`
--
ALTER TABLE `itineraries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `origin_city_id` (`origin_city_id`),
  ADD KEY `dest_city_id` (`dest_city_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_read` (`is_read`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_number` (`order_number`),
  ADD UNIQUE KEY `uq_pickup_code` (`pickup_code`),
  ADD KEY `idx_order_tourist` (`tourist_id`),
  ADD KEY `idx_order_shop` (`shop_id`),
  ADD KEY `idx_order_status` (`status`),
  ADD KEY `idx_order_pickup` (`pickup_date`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_order` (`order_id`),
  ADD KEY `idx_item_product` (`product_id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pkg_city` (`city_id`),
  ADD KEY `idx_pkg_hotel` (`hotel_id`),
  ADD KEY `idx_pkg_room` (`room_id`);

--
-- Indexes for table `package_bookings`
--
ALTER TABLE `package_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pb_package` (`package_id`),
  ADD KEY `idx_pb_tourist` (`tourist_id`),
  ADD KEY `fk_pb_booking` (`booking_id`),
  ADD KEY `fk_pb_itinerary` (`itinerary_id`);

--
-- Indexes for table `package_spots`
--
ALTER TABLE `package_spots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ps_package` (`package_id`),
  ADD KEY `idx_ps_spot` (`spot_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_ref` (`reference_type`,`reference_id`),
  ADD KEY `idx_payment_payer` (`payer_id`),
  ADD KEY `idx_payment_status` (`status`);

--
-- Indexes for table `review_photos`
--
ALTER TABLE `review_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_review_photos_review` (`review_type`,`review_id`);

--
-- Indexes for table `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_routes_origin_dest_transport` (`origin_city_id`,`dest_city_id`,`transport_type`),
  ADD KEY `dest_city_id` (`dest_city_id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shop_slug` (`slug`),
  ADD KEY `idx_shop_owner` (`owner_id`),
  ADD KEY `idx_shop_city` (`city_id`),
  ADD KEY `idx_shop_cat` (`category`);

--
-- Indexes for table `shop_products`
--
ALTER TABLE `shop_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_shop` (`shop_id`),
  ADD KEY `idx_product_available` (`is_available`);

--
-- Indexes for table `shop_reviews`
--
ALTER TABLE `shop_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_order` (`order_id`) COMMENT 'One review per order',
  ADD KEY `idx_review_shop` (`shop_id`),
  ADD KEY `idx_review_tourist` (`tourist_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `spot_amenities`
--
ALTER TABLE `spot_amenities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spot_id` (`spot_id`);

--
-- Indexes for table `spot_checkins`
--
ALTER TABLE `spot_checkins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spot_checkins_spot` (`spot_id`),
  ADD KEY `idx_spot_checkins_user` (`user_id`),
  ADD KEY `idx_spot_checkins_date` (`checked_in_at`);

--
-- Indexes for table `spot_photos`
--
ALTER TABLE `spot_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spot_id` (`spot_id`);

--
-- Indexes for table `spot_reviews`
--
ALTER TABLE `spot_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`spot_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `spot_views`
--
ALTER TABLE `spot_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spot_views_spot` (`spot_id`),
  ADD KEY `idx_spot_views_date` (`viewed_at`);

--
-- Indexes for table `tourist_spots`
--
ALTER TABLE `tourist_spots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `city_id` (`city_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `budget_estimates`
--
ALTER TABLE `budget_estimates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `hotels`
--
ALTER TABLE `hotels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `hotel_amenities`
--
ALTER TABLE `hotel_amenities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hotel_photos`
--
ALTER TABLE `hotel_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `hotel_reviews`
--
ALTER TABLE `hotel_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `itineraries`
--
ALTER TABLE `itineraries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `package_bookings`
--
ALTER TABLE `package_bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `package_spots`
--
ALTER TABLE `package_spots`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `review_photos`
--
ALTER TABLE `review_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=321;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shop_products`
--
ALTER TABLE `shop_products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shop_reviews`
--
ALTER TABLE `shop_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `spot_amenities`
--
ALTER TABLE `spot_amenities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `spot_checkins`
--
ALTER TABLE `spot_checkins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `spot_photos`
--
ALTER TABLE `spot_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `spot_reviews`
--
ALTER TABLE `spot_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `spot_views`
--
ALTER TABLE `spot_views`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tourist_spots`
--
ALTER TABLE `tourist_spots`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`),
  ADD CONSTRAINT `fk_booking_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_rooms` (`id`),
  ADD CONSTRAINT `fk_booking_tourist` FOREIGN KEY (`tourist_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `budget_estimates`
--
ALTER TABLE `budget_estimates`
  ADD CONSTRAINT `budget_estimates_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `hotels`
--
ALTER TABLE `hotels`
  ADD CONSTRAINT `fk_hotel_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `hotels_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `hotel_amenities`
--
ALTER TABLE `hotel_amenities`
  ADD CONSTRAINT `fk_hotel_amenities_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hotel_photos`
--
ALTER TABLE `hotel_photos`
  ADD CONSTRAINT `fk_hotel_photos_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hotel_reviews`
--
ALTER TABLE `hotel_reviews`
  ADD CONSTRAINT `fk_hotel_reviews_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hotel_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  ADD CONSTRAINT `fk_room_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_room_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `itineraries`
--
ALTER TABLE `itineraries`
  ADD CONSTRAINT `itineraries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `itineraries_ibfk_2` FOREIGN KEY (`origin_city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `itineraries_ibfk_3` FOREIGN KEY (`dest_city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`),
  ADD CONSTRAINT `fk_order_tourist` FOREIGN KEY (`tourist_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`);

--
-- Constraints for table `packages`
--
ALTER TABLE `packages`
  ADD CONSTRAINT `fk_pkg_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pkg_hotel` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pkg_room` FOREIGN KEY (`room_id`) REFERENCES `hotel_rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `package_bookings`
--
ALTER TABLE `package_bookings`
  ADD CONSTRAINT `fk_pb_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pb_itinerary` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pb_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pb_tourist` FOREIGN KEY (`tourist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `package_spots`
--
ALTER TABLE `package_spots`
  ADD CONSTRAINT `fk_ps_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ps_spot` FOREIGN KEY (`spot_id`) REFERENCES `tourist_spots` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_payer` FOREIGN KEY (`payer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `routes`
--
ALTER TABLE `routes`
  ADD CONSTRAINT `routes_ibfk_1` FOREIGN KEY (`origin_city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `routes_ibfk_2` FOREIGN KEY (`dest_city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `shops`
--
ALTER TABLE `shops`
  ADD CONSTRAINT `fk_shop_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `fk_shop_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_products`
--
ALTER TABLE `shop_products`
  ADD CONSTRAINT `fk_product_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_reviews`
--
ALTER TABLE `shop_reviews`
  ADD CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_tourist` FOREIGN KEY (`tourist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spot_amenities`
--
ALTER TABLE `spot_amenities`
  ADD CONSTRAINT `spot_amenities_ibfk_1` FOREIGN KEY (`spot_id`) REFERENCES `tourist_spots` (`id`);

--
-- Constraints for table `spot_photos`
--
ALTER TABLE `spot_photos`
  ADD CONSTRAINT `spot_photos_ibfk_1` FOREIGN KEY (`spot_id`) REFERENCES `tourist_spots` (`id`);

--
-- Constraints for table `spot_reviews`
--
ALTER TABLE `spot_reviews`
  ADD CONSTRAINT `spot_reviews_ibfk_1` FOREIGN KEY (`spot_id`) REFERENCES `tourist_spots` (`id`),
  ADD CONSTRAINT `spot_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tourist_spots`
--
ALTER TABLE `tourist_spots`
  ADD CONSTRAINT `tourist_spots_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
