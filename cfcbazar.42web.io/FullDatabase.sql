-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql313.infinityfree.com
-- Generation Time: Oct 25, 2025 at 02:14 AM
-- Server version: 11.4.7-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_39103611_db1`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `achievement_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `target` int(11) NOT NULL,
  `reward` decimal(10,4) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `claimed` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `achievements`
--

INSERT INTO `achievements` (`id`, `email`, `achievement_name`, `description`, `target`, `reward`, `completed`, `created_at`, `updated_at`, `claimed`) VALUES
(32, 'cfcbazar.payments@gmail.com', 'Solve 5 Equations', 'Solve 5 math challenges', 5, '0.0000', 1, '2025-10-06 17:41:59', '2025-10-06 17:41:59', 0),
(31, 'cfcbazar.payments@gmail.com', 'Beginner\'s Challenge', 'Earn 50 XP', 50, '0.0000', 1, '2025-10-06 17:41:59', '2025-10-06 17:41:59', 0),
(30, 'cfcbazar@gmail.com', 'Beginner\'s Challenge', 'Earn 50 XP', 50, '0.0000', 1, '2025-09-19 22:42:09', '2025-09-19 22:42:09', 0),
(29, 'cfcbazar@gmail.com', 'Solve 5 Equations', 'Solve 5 math challenges', 5, '0.0000', 1, '2025-09-19 22:42:09', '2025-09-19 22:42:09', 0),
(28, 'cfcbazar@gmail.com', 'Rookie Solver', 'Solve 10 math challenges', 10, '0.0000', 1, '2025-09-19 22:42:09', '2025-09-19 22:42:09', 0),
(27, 'cfcbazar@gmail.com', 'XP Hunter', 'Reach 200 XP total', 200, '0.0000', 1, '2025-09-19 22:42:09', '2025-09-19 22:42:09', 0),
(33, 'cfcbazar.payments@gmail.com', 'Beginner\'s Challenge', 'Earn 50 XP', 50, '0.0000', 1, '2025-10-06 17:46:12', '2025-10-06 17:46:12', 0),
(34, 'cfcbazar.payments@gmail.com', 'Solve 5 Equations', 'Solve 5 math challenges', 5, '0.0000', 1, '2025-10-06 17:46:12', '2025-10-06 17:46:12', 0),
(35, 'cfcbazar.payments@gmail.com', 'Rookie Solver', 'Solve 10 math challenges', 10, '0.0000', 1, '2025-10-18 11:35:17', '2025-10-18 11:35:17', 0);

-- --------------------------------------------------------

--
-- Table structure for table `click_logs`
--

CREATE TABLE `click_logs` (
  `id` int(11) NOT NULL,
  `short_id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `referrer` text DEFAULT NULL,
  `platform` varchar(20) DEFAULT 'unknown',
  `utm_source` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `click_logs`
--

INSERT INTO `click_logs` (`id`, `short_id`, `ip`, `user_agent`, `referrer`, `platform`, `utm_source`, `created_at`) VALUES
(17, 58, '176.222.7.195', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/orpg.php', 'mobile', '', '2025-09-17 19:26:45'),
(18, 58, '176.222.7.195', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/', 'mobile', '', '2025-09-17 21:52:48'),
(19, 58, '176.222.7.195', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/r.php', 'mobile', '', '2025-09-18 06:45:41'),
(20, 57, '176.222.7.195', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/r.php', 'mobile', '', '2025-09-18 06:45:58'),
(21, 1, '176.222.10.62', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/r.php', 'mobile', '', '2025-09-21 07:57:12'),
(22, 1, '176.222.10.62', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/r.php', 'mobile', '', '2025-09-21 12:57:35'),
(23, 59, '66.249.83.130', 'Google-Safety', '', 'unknown', '', '2025-09-28 10:18:57'),
(24, 59, '103.189.123.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=59', 'windows', '', '2025-09-28 10:19:00'),
(25, 59, '1.37.67.201', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=59', 'windows', '', '2025-09-28 10:19:02'),
(26, 59, '1.37.67.135', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=59', 'windows', '', '2025-09-28 10:21:42'),
(27, 59, '85.118.78.182', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/r.php', 'mobile', '', '2025-09-28 15:30:09'),
(28, 59, '85.118.78.182', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', 'mobile', '', '2025-09-28 15:32:09'),
(29, 60, '64.233.172.107', 'Google-Safety', '', 'unknown', '', '2025-09-28 16:53:11'),
(30, 60, '103.130.118.36', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=60', 'windows', '', '2025-09-28 16:53:17'),
(31, 60, '202.173.122.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=60', 'windows', '', '2025-09-28 16:53:20'),
(32, 60, '103.130.118.36', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=60', 'windows', '', '2025-09-28 16:57:15'),
(33, 39, '40.77.167.25', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', '', 'unknown', '', '2025-10-04 16:16:20'),
(34, 41, '40.77.167.25', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', '', 'unknown', '', '2025-10-11 02:50:09'),
(35, 61, '74.125.210.201', 'Google-Safety', '', 'unknown', '', '2025-10-12 16:15:40'),
(36, 61, '66.249.87.5', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.96 Mobile Safari/537.36 (compatible; Google-Safety; +http://www.google.com/bot.html)', '', 'mobile', '', '2025-10-12 16:15:43'),
(37, 61, '199.16.157.181', 'Twitterbot/1.0', '', 'unknown', '', '2025-10-12 16:16:54'),
(38, 61, '170.23.26.191', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=61', 'mac', '', '2025-10-12 16:18:13'),
(39, 61, '69.171.249.116', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:19'),
(40, 61, '69.171.249.114', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:35'),
(41, 61, '69.171.249.4', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:39'),
(42, 61, '69.171.249.5', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:39'),
(43, 61, '31.13.127.113', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:42'),
(44, 61, '31.13.127.114', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:42'),
(45, 61, '173.252.95.19', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:42'),
(46, 61, '173.252.95.19', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:42'),
(47, 61, '31.13.127.41', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:43'),
(48, 61, '31.13.127.4', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:18:43'),
(49, 61, '31.13.115.7', 'Mozilla/5.0 (Linux; Android 12; T431A Build/SP1A.210812.016; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/140.0.7339.207 Mobile Safari/537.36 [FBAN/EMA;FBLC/en_US;FBAV/318.0.0.16.105;FBDM/DisplayMetrics{density=1.5, width=480, height=888,', 'https://www.facebook.com/', 'mobile', '', '2025-10-12 16:19:14'),
(50, 61, '173.252.127.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'https://www.facebook.com/', 'windows', '', '2025-10-12 16:19:30'),
(51, 61, '173.252.95.17', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'http://m.facebook.com', 'mobile', '', '2025-10-12 16:19:33'),
(52, 61, '31.13.127.19', 'Mozilla/5.0 (Linux; Android 12; Hisense E32 Pro Build/SP1A.210812.016; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/141.0.7390.43 Mobile Safari/537.36 [FBAN/AudienceNetworkForWindows;FBDV/Hisense E32 Pro;FBSV/12;FBAV/399.0.0.16.120;FBLC/e', 'https://www.facebook.com/', 'mobile', '', '2025-10-12 16:19:58'),
(53, 61, '17.241.219.87', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)', 'https://cfcbazar.ct.ws/r.php?go=61', 'mac', '', '2025-10-12 16:21:10'),
(54, 61, '69.171.234.9', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:33:48'),
(55, 61, '31.13.103.116', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:33:59'),
(56, 61, '31.13.103.10', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', '', 'unknown', '', '2025-10-12 16:34:00'),
(57, 61, '66.249.69.35', 'Googlebot-Image/1.0', '', 'unknown', '', '2025-10-12 19:40:41'),
(58, 61, '222.254.96.235', 'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US) AppleWebKit/537.36 (KHTML, like Gecko)  VivoBrowser/14.2.2.6 Chrome/123.0.6312.118 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=61', 'windows', '', '2025-10-13 03:53:39'),
(59, 61, '222.254.96.235', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=61', 'mobile', '', '2025-10-13 03:54:29'),
(60, 61, '222.254.96.235', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '', 'mobile', '', '2025-10-13 04:00:41'),
(61, 7, '40.77.167.5', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', '', 'unknown', '', '2025-10-13 14:39:28'),
(62, 61, '66.249.69.34', 'Googlebot-Image/1.0', '', 'unknown', '', '2025-10-14 20:52:27'),
(63, 65, '66.249.93.232', 'Google-Safety', '', 'unknown', '', '2025-10-19 11:22:39'),
(64, 65, '66.249.87.234', 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.96 Mobile Safari/537.36 (compatible; Google-Safety; +http://www.google.com/bot.html)', '', 'mobile', '', '2025-10-19 11:22:42'),
(65, 65, '49.44.80.137', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=65', 'windows', '', '2025-10-19 11:22:42'),
(66, 65, '123.176.33.198', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=65', 'windows', '', '2025-10-19 11:22:42'),
(67, 65, '90.198.220.203', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 'https://cfcbazar.ct.ws/r.php?go=65', 'mobile', '', '2025-10-20 16:32:35'),
(68, 65, '94.195.232.137', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0.1 Mobile/15E148 Safari/604.1', 'https://cfcbazar.ct.ws/r.php?go=65', 'mobile', '', '2025-10-21 09:28:47'),
(69, 44, '40.77.167.116', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', '', 'unknown', '', '2025-10-21 21:36:00');

-- --------------------------------------------------------

--
-- Table structure for table `deposit_amounts`
--

CREATE TABLE `deposit_amounts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `token` varchar(100) DEFAULT NULL,
  `address` varchar(100) DEFAULT NULL,
  `tx_hash` varchar(66) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `valid_until` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `deposit_amounts`
--

INSERT INTO `deposit_amounts` (`id`, `email`, `amount`, `token`, `address`, `tx_hash`, `created_at`, `valid_until`, `used`) VALUES
(106, 'cfcbazar.payments@gmail.com', '0.00076028', 'BNB', '0xaea49be813e1d013bb01ed76bcdf6978823298b2', '0x748d7795e24a086641b4a9418146ae5c0dcc4a85843bb61555e0e2549bf94721', '2025-10-11 23:06:21', '0000-00-00 00:00:00', 0),
(105, 'cfcbazar.payments@gmail.com', '0.00094716', 'BNB', '0xaea49be813e1d013bb01ed76bcdf6978823298b2', '0xa08a8e8064acb1b79566b7483d740c1c2b878ab5588c69341400edf342b0df6e', '2025-10-11 18:14:01', '0000-00-00 00:00:00', 0),
(104, 'cfcbazar.payments@gmail.com', '0.00076028', 'BNB', '0xaea49be813e1d013bb01ed76bcdf6978823298b2', '0x748d7795e24a086641b4a9418146ae5c0dcc4a85843bb61555e0e2549bf94728', '2025-10-11 18:14:01', '0000-00-00 00:00:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mac_address` varchar(17) NOT NULL,
  `last_mine_time` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `email`, `mac_address`, `last_mine_time`, `active`, `created_at`, `updated_at`) VALUES
(1, 'cfcbazar.payments@gmail.com', 'AA:BB:CC:DD:EE:FF', '2025-10-05 13:16:38', 0, '2025-10-05 08:42:56', '2025-10-18 17:05:27'),
(2, 'cfcbazar.payments@gmail.com', 'AA:BB:CC:DD:EE:EE', '2025-10-05 06:00:47', 0, '2025-10-05 12:39:22', '2025-10-05 12:52:16'),
(3, 'cfcbazar.payments@gmail.com', 'C8:2B:96:23:11:1D', '2025-10-11 11:20:58', 0, '2025-10-05 13:17:12', '2025-10-11 12:43:49'),
(4, 'cfcbazar@gmail.com', 'AA:BB:CC:DD:EE:FF', '2025-10-04 17:05:49', 0, '2025-10-07 00:25:52', '2025-10-07 00:25:52'),
(5, '', 'demo-mac', '2025-10-13 03:50:56', 1, '2025-10-13 10:48:25', '2025-10-13 10:50:56'),
(6, 'cfcbazar.payments@gmail.com', 'Moto', '2025-10-14 23:55:17', 0, '2025-10-15 06:55:17', '2025-10-15 11:53:10'),
(7, 'cfcbazar.payments@gmail.com', 'MotoG', '2025-10-14 23:55:22', 0, '2025-10-15 06:55:20', '2025-10-15 11:53:10'),
(8, 'cfcbazar.payments@gmail.com', 'MotoG32', '2025-10-17 22:44:11', 0, '2025-10-15 06:55:24', '2025-10-18 17:05:27'),
(9, 'fatoumaviktoria@gmail.com', '0xCfA6141C8D83fB7', '2025-10-20 12:41:56', 0, '2025-10-19 21:51:23', '2025-10-20 19:41:56'),
(10, 'fatoumaviktoria@gmail.com', '0x', '2025-10-19 14:58:52', 0, '2025-10-19 21:58:52', '2025-10-19 21:58:52'),
(11, 'fatoumaviktoria@gmail.com', '0xCfA6141C8D83fB', '2025-10-19 14:59:23', 0, '2025-10-19 21:59:23', '2025-10-19 21:59:23'),
(12, 'fatoumaviktoria@gmail.com', '4', '2025-10-20 12:22:03', 0, '2025-10-20 19:22:03', '2025-10-20 19:22:03'),
(13, 'fatoumaviktoria@gmail.com', '47', '2025-10-20 12:22:05', 0, '2025-10-20 19:22:04', '2025-10-20 19:22:05'),
(14, 'fatoumaviktoria@gmail.com', '474', '2025-10-20 12:22:06', 0, '2025-10-20 19:22:06', '2025-10-20 19:22:06'),
(15, 'fatoumaviktoria@gmail.com', '4748', '2025-10-20 12:22:07', 0, '2025-10-20 19:22:07', '2025-10-20 19:22:07'),
(16, 'fatoumaviktoria@gmail.com', '474865', '2025-10-20 12:22:08', 0, '2025-10-20 19:22:08', '2025-10-20 19:22:08'),
(17, 'fatoumaviktoria@gmail.com', '4748656', '2025-10-20 12:22:09', 0, '2025-10-20 19:22:09', '2025-10-20 19:22:09'),
(18, 'fatoumaviktoria@gmail.com', '474865600', '2025-10-20 12:22:10', 0, '2025-10-20 19:22:10', '2025-10-20 19:22:10'),
(19, 'fatoumaviktoria@gmail.com', '4748656001', '2025-10-20 12:41:02', 0, '2025-10-20 19:22:11', '2025-10-20 19:41:02'),
(20, 'fatoumaviktoria@gmail.com', '0xCfA6141C', '2025-10-20 12:41:57', 0, '2025-10-20 19:41:57', '2025-10-20 19:41:57'),
(21, 'fatoumaviktoria@gmail.com', '0xFBd767f6454bCd0', '2025-10-23 15:30:00', 0, '2025-10-20 19:42:07', '2025-10-23 22:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `miner`
--

CREATE TABLE `miner` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `coin` varchar(50) NOT NULL,
  `rate_per_duco` decimal(20,10) NOT NULL,
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duco_mined` decimal(20,10) DEFAULT 0.0000000000,
  `coin_earned` decimal(20,10) DEFAULT 0.0000000000,
  `balance` decimal(20,10) DEFAULT 0.0000000000,
  `address` varchar(255) DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `miner`
--

INSERT INTO `miner` (`id`, `email`, `coin`, `rate_per_duco`, `last_updated`, `duco_mined`, `coin_earned`, `balance`, `address`) VALUES
(61, 'cfcbazar@gmail.com', 'trx', '0.0000001000', '2025-07-14 14:29:56', '0.0000620000', '0.0000000000', '0.0000000000', '0xFBd767f6454bCd07c959da2E48fD429531A1323A'),
(60, 'cfcbazar@gmail.com', 'bnb', '0.0000000500', '2025-07-14 14:44:54', '0.0000690000', '0.0000000000', '0.0000000000', '0xFBd767f6454bCd07c959da2E48fD429531A1323A');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'Human-readable page title',
  `slug` varchar(255) NOT NULL COMMENT 'URL slug, e.g. "about-us"',
  `path` varchar(255) NOT NULL COMMENT 'Relative path, e.g. "/about-us"',
  `parent_id` int(11) DEFAULT NULL COMMENT 'Supports nested pages',
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'published',
  `template` varchar(100) DEFAULT NULL COMMENT 'Optional template name',
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_desc` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `visits` int(11) NOT NULL DEFAULT 0 COMMENT 'Total page visits',
  `last_referrer` varchar(255) DEFAULT NULL COMMENT 'Last known HTTP referrer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `title`, `slug`, `path`, `parent_id`, `status`, `template`, `meta_title`, `meta_desc`, `created_at`, `updated_at`, `visits`, `last_referrer`) VALUES
(1, 'Work value.php', 'work_value.php', '/work_value.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 01:51:56', '2025-10-19 12:39:58', 0, NULL),
(2, 'Buy.php', 'buy.php', '/buy.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 07:43:36', '2025-10-20 12:40:15', 19, NULL),
(3, 'D.php', 'd.php', '/d.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 07:45:54', '2025-10-19 12:39:58', 0, NULL),
(4, 'Dino Runner Game', 'dino.php', '/dino.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 07:52:30', '2025-10-19 12:39:58', 0, NULL),
(5, 'Features & DIY Tools', 'features.php', '/features.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 07:55:55', '2025-10-22 11:08:03', 1, NULL),
(6, 'Flappy Bird Game', 'flop.php', '/flop.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:00:57', '2025-10-19 12:39:58', 0, NULL),
(7, 'WorkToken Dashboard', 'games.php', '/games.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:04:46', '2025-10-21 10:24:49', 8, NULL),
(8, 'CfCbazar Homepage', 'index.php', '/index.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:06:57', '2025-10-24 23:06:17', 69, NULL),
(9, 'Maze Escape', 'maze.php', '/maze.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:10:35', '2025-10-19 12:39:58', 0, NULL),
(10, 'Equation Challenge', 'number.php', '/number.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:30:03', '2025-10-19 12:39:58', 0, NULL),
(11, 'Numbers Directory', 'numbers.php', '/numbers.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:32:00', '2025-10-19 12:39:58', 0, NULL),
(12, 'TokenQuest ORPG', 'orpg.php', '/orpg.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:35:20', '2025-10-19 12:39:58', 0, NULL),
(13, 'Power Consumption Calculator', 'power.php', '/power.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:44:41', '2025-10-19 12:39:58', 0, NULL),
(14, 'URL Shortener', 'r.php', '/r.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:48:01', '2025-10-20 09:23:04', 3, NULL),
(15, 'Item Search & Supplier Links', 'sa.php', '/sa.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:50:58', '2025-10-19 12:39:58', 0, NULL),
(16, 'Slot Machine', 'slot.php', '/slot.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:53:37', '2025-10-21 10:23:17', 1, NULL),
(17, 'Survival Budget Tool', 'survival.php', '/survival.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 08:57:49', '2025-10-21 14:36:00', 1, NULL),
(18, 'Internet Speed Test', 'speed.php', '/speed.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 09:04:01', '2025-10-23 00:29:06', 4, NULL),
(19, 'Wheel of Fortune', 'wheel.php', '/wheel.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 09:11:39', '2025-10-21 10:24:48', 2, NULL),
(20, 'Guess the Word', 'word.php', '/word.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 09:14:28', '2025-10-19 12:39:58', 0, NULL),
(21, 'Admin Portal', 'admin.php', '/admin.php', NULL, 'published', NULL, NULL, NULL, '2025-08-17 23:45:50', '2025-10-24 22:41:29', 13, 'https://cfcbazar.ct.ws/login.php?return_url=%2Fadmin_v3.php'),
(22, 'Numbers Directory', 'numbers.php/', '/numbers.php/', NULL, 'published', NULL, NULL, NULL, '2025-08-18 00:04:33', '2025-10-19 12:39:58', 0, NULL),
(23, 'TokenQuest ORPG', 'orpg_v2.php', '/orpg_v2.php', NULL, 'published', NULL, NULL, NULL, '2025-09-02 05:56:51', '2025-10-19 12:39:58', 0, NULL),
(24, 'TokenQuest ORPG', 'orpg_v3.php', '/orpg_v3.php', NULL, 'published', NULL, NULL, NULL, '2025-09-02 08:06:46', '2025-10-19 12:39:58', 0, NULL),
(25, 'TokenQuest ORPG', 'orpg_v4.php', '/orpg_v4.php', NULL, 'published', NULL, NULL, NULL, '2025-09-04 07:03:07', '2025-10-19 12:39:58', 0, NULL),
(26, 'Admin Portal', 'admin_v2.php', '/admin_v2.php', NULL, 'published', NULL, NULL, NULL, '2025-09-04 08:01:54', '2025-10-19 12:39:58', 0, NULL),
(27, 'Deposit Tokens', 'buy_v3.php', '/buy_v3.php', NULL, 'published', NULL, NULL, NULL, '2025-09-07 05:52:34', '2025-10-19 12:39:58', 0, NULL),
(28, 'Buy v4.php', 'buy_v4.php', '/buy_v4.php', NULL, 'published', NULL, NULL, NULL, '2025-09-07 10:00:37', '2025-10-19 12:39:58', 0, NULL),
(29, 'Buy v6.php', 'buy_v6.php', '/buy_v6.php', NULL, 'published', NULL, NULL, NULL, '2025-09-07 10:13:21', '2025-10-19 12:39:58', 0, NULL),
(30, 'URL Shortener', 'r_v3.php', '/r_v3.php', NULL, 'published', NULL, NULL, NULL, '2025-09-08 15:11:31', '2025-10-19 12:39:58', 0, NULL),
(31, 'URL Shortener', 'r_v6.php', '/r_v6.php', NULL, 'published', NULL, NULL, NULL, '2025-09-09 13:14:37', '2025-10-19 12:39:58', 0, NULL),
(32, 'Orpg V7', 'orpg_v7', '/orpg_v7.php', NULL, 'published', NULL, NULL, NULL, '2025-09-15 23:23:38', '2025-10-19 12:39:58', 0, NULL),
(33, 'Orpg V13', 'orpg_v13', '/orpg_v13.php', NULL, 'published', NULL, NULL, NULL, '2025-09-17 17:16:26', '2025-10-19 12:39:58', 0, NULL),
(34, 'CfCbazar', 'cfcbazar', '/', NULL, '', NULL, NULL, NULL, '2025-09-20 20:15:57', '2025-10-19 12:39:58', 0, NULL),
(1085, 'Buy v8.php', 'buy_v8.php', '/buy_v8.php', NULL, 'published', NULL, NULL, NULL, '2025-10-11 10:44:19', '2025-10-19 12:39:58', 0, NULL),
(86718, 'Guess the Word', 'word2.php', '/word2.php', NULL, 'published', NULL, NULL, NULL, '2025-10-18 11:47:09', '2025-10-19 12:39:58', 0, NULL),
(86719, 'Miner', 'miner', '/miner', NULL, 'published', NULL, NULL, NULL, '2025-10-19 12:50:14', '2025-10-23 15:20:37', 11, 'https://cfcbazar.ct.ws/miner/'),
(86720, 'Battle', 'battle.php', '/battle.php', NULL, 'published', NULL, NULL, NULL, '2025-10-20 03:33:23', '2025-10-21 10:20:22', 3, 'https://cfcbazar.ct.ws/games.php'),
(86721, 'Index-alt', 'index-alt.php', '/index-alt.php', NULL, 'published', NULL, NULL, NULL, '2025-10-22 00:49:23', '2025-10-24 23:13:33', 12, 'https://cfcbazar.42web.io/'),
(86722, 'Admin_v3', 'admin_v3.php', '/admin_v3.php', NULL, 'published', NULL, NULL, NULL, '2025-10-22 22:35:52', '2025-10-24 22:41:20', 10, NULL),
(86723, 'Index-main', 'index-main.php', '/index-main.php', NULL, 'published', NULL, NULL, NULL, '2025-10-23 05:07:20', '2025-10-24 22:37:20', 6, 'https://cfcbazar.ct.ws/');

-- --------------------------------------------------------

--
-- Table structure for table `quests`
--

CREATE TABLE `quests` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `quest_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `target` int(11) NOT NULL,
  `reward` decimal(10,4) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `progress` int(11) DEFAULT 0,
  `claimed` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `quests`
--

INSERT INTO `quests` (`id`, `email`, `quest_name`, `description`, `target`, `reward`, `completed`, `created_at`, `updated_at`, `progress`, `claimed`) VALUES
(1, '', 'Beginner\'s Challenge', 'Earn 50 XP', 50, '0.0500', 0, '2025-09-02 08:08:37', '2025-09-16 03:36:56', 0, 0),
(2, '', 'Solve 5 Equations', 'Solve 5 math challenges', 5, '0.1000', 0, '2025-09-02 08:08:37', '2025-09-16 03:00:56', 0, 0),
(3, '', 'Rookie Solver', 'Solve 10 math challenges', 10, '0.0500', 0, '2025-09-03 13:33:00', '2025-09-16 03:00:56', 0, 1),
(4, '', 'XP Hunter', 'Reach 200 XP total', 200, '0.1000', 1, '2025-09-03 13:33:00', '2025-09-16 03:07:56', 610, 1),
(37, 'cfcbazar.payments@gmail.com', 'Beginner\'s Challenge', 'Earn 50 XP', 50, '0.0000', 1, '2025-10-06 17:39:07', '2025-10-06 17:46:12', 50, 0),
(36, 'cfcbazar@gmail.com', 'XP Hunter', 'Reach 200 XP total', 200, '0.0000', 1, '2025-09-19 22:42:09', '2025-09-19 22:42:09', 200, 0),
(35, 'cfcbazar@gmail.com', 'Rookie Solver', 'Solve 10 math challenges', 10, '0.0000', 1, '2025-09-19 22:42:09', '2025-09-19 22:42:09', 10, 0),
(34, 'cfcbazar@gmail.com', 'Solve 5 Equations', 'Solve 5 math challenges', 5, '0.0000', 1, '2025-09-19 22:42:09', '2025-09-19 22:42:09', 5, 0),
(33, 'cfcbazar@gmail.com', 'Beginner\'s Challenge', 'Earn 50 XP', 50, '0.0000', 1, '2025-09-19 22:42:09', '2025-09-19 22:42:09', 50, 0),
(38, 'cfcbazar.payments@gmail.com', 'Solve 5 Equations', 'Solve 5 math challenges', 5, '0.0000', 1, '2025-10-06 17:39:07', '2025-10-06 17:46:12', 5, 0),
(39, 'cfcbazar.payments@gmail.com', 'Rookie Solver', 'Solve 10 math challenges', 10, '0.0000', 1, '2025-10-06 17:39:07', '2025-10-18 11:35:17', 10, 0),
(40, 'cfcbazar.payments@gmail.com', 'XP Hunter', 'Reach 200 XP total', 200, '0.0000', 0, '2025-10-06 17:39:07', '2025-10-18 11:36:19', 120, 0),
(41, 'nguyenhaianh181182@gmail.com', 'Beginner\'s Challenge', 'Earn 50 XP', 50, '0.0000', 0, '2025-10-12 21:04:24', '2025-10-12 21:04:24', 0, 0),
(42, 'nguyenhaianh181182@gmail.com', 'Solve 5 Equations', 'Solve 5 math challenges', 5, '0.0000', 0, '2025-10-12 21:04:24', '2025-10-12 21:04:24', 0, 0),
(43, 'nguyenhaianh181182@gmail.com', 'Rookie Solver', 'Solve 10 math challenges', 10, '0.0000', 0, '2025-10-12 21:04:24', '2025-10-12 21:04:24', 0, 0),
(44, 'nguyenhaianh181182@gmail.com', 'XP Hunter', 'Reach 200 XP total', 200, '0.0000', 0, '2025-10-12 21:04:24', '2025-10-12 21:04:24', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `scheduler`
--

CREATE TABLE `scheduler` (
  `id` int(11) NOT NULL,
  `last_run` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `scheduler`
--

INSERT INTO `scheduler` (`id`, `last_run`) VALUES
(1, 1750557161);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `maintenance` tinyint(1) DEFAULT 0,
  `disable_registration` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `maintenance`, `disable_registration`) VALUES
(1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `short_links`
--

CREATE TABLE `short_links` (
  `id` int(10) UNSIGNED NOT NULL,
  `short` varchar(64) NOT NULL,
  `long` text NOT NULL,
  `clicks` int(11) DEFAULT 0,
  `email` varchar(255) DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `user_agents` text DEFAULT NULL,
  `referrers` text DEFAULT NULL,
  `platforms` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `short_links`
--

INSERT INTO `short_links` (`id`, `short`, `long`, `clicks`, `email`, `last_ip`, `user_agents`, `referrers`, `platforms`) VALUES
(1, 'https://cfcbazar.ct.ws/r.php?go=1', 'https://onelink.shein.com/13/4r0d2rkz8euv', 5, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(2, '', 'https://onelink.shein.com/13/4r0pgcq7v68u', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(3, '', 'https://onelink.shein.com/13/4r0qfbjyvm74', 10, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(4, '', 'https://onelink.shein.com/13/4r1c5x6a8i69', 5, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(5, '', 'https://onelink.shein.com/13/4r1c5x6a8i69', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(6, '', 'https://onelink.shein.com/13/4r1c5x6a8i69', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(7, '', 'https://cc.free.bg/games.html', 28, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(8, '', 'https://onelink.shein.com/13/4rr56mk202uh', 8, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(9, '', 'https://onelink.shein.com/13/4rr7ddvguxd4', 7, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(10, '', 'https://onelink.shein.com/13/4rr916i3lpo1', 8, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(11, '', 'https://cc.free', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(12, '', 'https://cc.free.bg/site', 8, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(13, '', 'https://onelink.shein.com/13/4s1j3uyuxbva', 8, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(14, '', 'https://onelink.shein.com/13/4s1j3uyuxbva', 1, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(15, '', 'https://cc.free.bg/slot.html', 4, 'CfCbazar@gmail.com', NULL, NULL, NULL, NULL),
(16, '', 'https://cfcbazar.ct.ws/', 8, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(43, '', 'https://app.temu.com/m/nrx25pdgw5a', 5, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(17, '', 'https://cfcbazar.ct.ws', 18, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(18, '', 'https://cfcbazar.ct.ws/', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(19, '', 'http://cfcbazar.ct.ws/', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(20, '', 'http://cfcbazar.ct.ws', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(21, '', 'https://cfcbazar.ct.ws/sa.php', 11, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(22, '', 'https://fb.com/workthrp', 6, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(23, '', 'https://cfcbazar.ct.ws/games.php', 27, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(24, '', 'https://cfcbazar.ct.ws/features.php', 28, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(25, '', 'https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu', 6, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(26, '', 'https://github.com/ArakelTheDragon/CfCbazar-Tokens', 2, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(27, '', 'https://www.mintme.com/token/WorkTH/invite', 1, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(28, '', 'https://cfcbazar.ct.ws/', 32, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(29, '', 'https://cfcbazar.ct.ws/maze.php', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(30, '', 'https://x.com/cfcbazargroup', 5, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(31, '', 'http://fb.com/workthrp', 6, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(32, '', 'https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu&si=OyCuAgWZpmVPOX85', 1, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(33, '', 'https://cfcbazar.ct.ws/power.php', 6, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(34, '', 'https://onelink.shein.com/14/4txtsx4c3z0m', 57, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(35, '', 'https://cfcbazar.ct.ws/r.php?go=12', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(36, '', 'https://www.paypal.com/donate?token=4VR82hItOxXbP86ZFY5n4gzbb18ODN4qZfVS-6Jwt2caAx5ICCIA_Gy80TolINvl4SG6-zFAQNXlUexr', 1, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(37, '', 'https://onelink.shein.com/15/4w4e1l3v95pn', 9, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(38, '', 'https://onelink.shein.com/15/4wm1x5qmn3ls', 5, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(39, '', 'https://onelink.shein.com/15/4ws7ivfbvnkw', 9, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(40, '', 'https://onelink.shein.com/15/4xbfrrcewenj', 6, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(41, '', 'https://onelink.shein.com/15/4xdd5ue3q0ib', 6, 'CFCbazar@gmail.com', NULL, NULL, NULL, NULL),
(42, '', 'https://onelink.shein.com/15/4xdrjm32rnyj', 36, 'CFCbazar@gmail.com', NULL, NULL, NULL, NULL),
(44, '', 'https://cfcbazar.ct.ws/survival.php', 3, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(45, '', 'https://cfcbazar.ct.ws/number.php', 5, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(46, '', 'https://cfcbazar.ct.ws/word.php', 4, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(47, '', 'https://onelink.shein.com/16/4yoy3wn2vyuq', 4, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(48, '', 'https://cfcbazar.ct.ws/orpg.php', 13, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(49, '', 'https://onelink.shein.com/16/4ywo3ded614e', 8, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(50, '', 'https://cfcbazar.ct.ws', 0, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(51, '', 'https://onelink.shein.com/16/4znn4dzw8mjc', 2, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(52, '', 'https://onelink.shein.com/16/501gi7f4fd35', 4, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(53, '', 'https://onelink.shein.com/16/50cnli35kg87', 16, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(54, '', 'https://onelink.shein.com/16/50cs4a4y446l', 15, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(55, '', 'https://cfcbazar.ct.ws/r.php?go=54', 9, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(56, 'https://cfcbazar.ct.ws/r.php?go=56', 'https://cfcbazar.ct.ws/r.php?go=55', 3, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(57, '', 'https://temu.to/k/e63ofbm7wsp', 28, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(58, '', 'https://temu.to/k/efkzyxfhdbv', 3, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(59, 'https://cfcbazar.ct.ws/r.php?go=59', 'https://ebay.us/m/5KUoQY', 6, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(60, 'https://cfcbazar.ct.ws/r.php?go=60', 'https://ebay.us/m/mCR9F1', 4, 'cfcbazar@gmail.com', NULL, NULL, NULL, NULL),
(61, 'https://cfcbazar.ct.ws/r.php?go=61', 'https://cfcbazar.ct.ws/miner/', 27, 'cfcbazar.payments@gmail.com', NULL, NULL, NULL, NULL),
(62, 'https://cfcbazar.ct.ws/r.php?go=62', 'https://onelink.shein.com/17/53w3vpkf20wj', 0, 'cfcbazar.payments@gmail.com', NULL, NULL, NULL, NULL),
(63, 'https://cfcbazar.ct.ws/r.php?go=63', 'https://www.sky.com/help/articles/your-sky-glass-remote', 0, 'cfcbazar.payments@gmail.com', NULL, NULL, NULL, NULL),
(64, 'https://cfcbazar.ct.ws/r.php?go=64', 'https://onelink.shein.com/13/4r1c5x6a8i69', 0, 'cfcbazar.payments@gmail.com', NULL, NULL, NULL, NULL),
(65, 'https://cfcbazar.ct.ws/r.php?go=65', 'https://www.sky.com/help/sky-tv/sky-tv-stream/fixing-a-problem-sky-stream/fixing-sky-glass-sky-stream-puck/articles/setting-up-sky-glass-updating-software', 6, 'cfcbazar.payments@gmail.com', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `speed_results`
--

CREATE TABLE `speed_results` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `max_speed` float DEFAULT NULL,
  `avg_speed` float DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `speed_results`
--

INSERT INTO `speed_results` (`id`, `ip`, `max_speed`, `avg_speed`, `timestamp`, `user_agent`) VALUES
(1, '85.118.79.148', 6.57796, 3.10656, '2025-07-11 10:25:28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36'),
(2, '85.118.79.148', 11.0356, 3.55829, '2025-07-11 10:27:59', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36'),
(3, '85.118.79.148', 75.5343, 56.7081, '2025-07-11 10:32:28', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `email_verified` tinyint(4) DEFAULT 0,
  `verify_token` varchar(64) DEFAULT NULL,
  `verify_code` varchar(6) DEFAULT NULL,
  `verify_expires` datetime DEFAULT NULL,
  `status` int(11) DEFAULT 5,
  `wallet_address` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `email_verified`, `verify_token`, `verify_code`, `verify_expires`, `status`, `wallet_address`) VALUES
(152, 'cfcbazar@gmail.com', '$2y$10$EdiktLaoWZqJCtIqIzJYm.0YnFcRJeUzHiD/lGaB29iBBnNxrWrsK', 1, '', NULL, NULL, 1, ''),
(181, 'cfcbazar.payments@gmail.com', '$2y$10$OaFb45hQnY1eSg1ZFLhYreRe6Mf7/gcLZVKWkQkzfx37SyEhm/tLG', 1, '9b839ce041a316987bde12db8d4069c6', NULL, '2025-10-04 19:08:11', 1, ''),
(182, 'nguyenhaianh181182@gmail.com', '$2y$10$LPvTCmvUgvBfzYQeYEhlQe.tojF1wgiVoi5PulzuIOpI8uZWfmD9S', 1, 'c75623e3effce96d454c19bf05b9d5b8', NULL, '2025-10-13 05:56:04', 0, ''),
(184, 'arakelthedragon@gmail.com', '$2y$10$rvR/TGHbGcuauBTQyhKD3u47fAEb0q8bJFdeslonar8ss9.AuQVMm', 1, 'd152532ab388be5a336db456232516a3', NULL, '2025-10-14 18:57:42', 0, ''),
(185, 'fatoumaviktoria@gmail.com', '$2y$10$nUauhEn/BQzOfWbqX5Y0lu2Q.gAfSYDmSGlBtWDeN3vK2soP5EU12', 1, 'aa5e1f936acaa7ec26c47153080e8953', NULL, '2025-10-19 18:42:03', 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `visit_logs`
--

CREATE TABLE `visit_logs` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_bot` tinyint(1) DEFAULT NULL,
  `is_returning` tinyint(1) DEFAULT NULL,
  `maintenance` tinyint(1) DEFAULT NULL,
  `visited_at` datetime DEFAULT NULL,
  `referrer` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `visit_logs`
--

INSERT INTO `visit_logs` (`id`, `ip`, `user_agent`, `is_bot`, `is_returning`, `maintenance`, `visited_at`, `referrer`) VALUES
(1, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-16 04:55:52', NULL),
(2, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-16 04:58:56', NULL),
(3, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-16 05:00:42', NULL),
(4, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-16 05:00:43', NULL),
(5, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-16 05:00:45', NULL),
(6, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-16 05:00:46', NULL),
(7, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 0, 0, '2025-10-16 05:00:54', NULL),
(8, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-16 05:16:35', NULL),
(9, '90.216.134.194', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 0, 0, 0, '2025-10-16 06:31:53', NULL),
(10, '74.125.210.200', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36 (compatible; Google-Read-Aloud; +https://support.google.com/webmasters/answer/1061943)', 0, 0, 0, '2025-10-17 09:05:06', NULL),
(11, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 0, 0, '2025-10-17 09:05:07', NULL),
(12, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:16:20', NULL),
(13, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:17:33', NULL),
(14, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:17:35', NULL),
(15, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:17:36', NULL),
(16, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:17:37', NULL),
(17, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:22:43', NULL),
(18, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:22:44', NULL),
(19, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:22:45', NULL),
(20, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:22:48', NULL),
(21, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:39:29', NULL),
(22, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 09:39:30', NULL),
(23, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 10:01:25', NULL),
(24, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 10:01:53', NULL),
(25, '176.222.8.207', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', 0, 1, 0, '2025-10-17 10:07:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `withdraws`
--

CREATE TABLE `withdraws` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `wallet_address` varchar(255) NOT NULL,
  `amount` decimal(18,8) NOT NULL,
  `fee` decimal(18,8) DEFAULT 1.00000000,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `direction` enum('withdraw','deposit') NOT NULL DEFAULT 'withdraw',
  `token_type` varchar(10) DEFAULT 'WorkTH',
  `tx_hash` varchar(100) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `withdraws`
--

INSERT INTO `withdraws` (`id`, `email`, `wallet_address`, `amount`, `fee`, `status`, `created_at`, `direction`, `token_type`, `tx_hash`) VALUES
(1, 'cfcbazar@gmail.com', '0x123abcDEF4567890fedcba3210ABCDEF12345678', '10.00000000', '1.00000000', 'completed', '2025-06-23 11:21:35', 'withdraw', 'WorkTH', NULL),
(2, 'cfcbazar@gmail.com', '0x1111111111111111111111111111111111111111', '5.00000000', '1.00000000', 'completed', '2025-06-23 15:26:58', 'withdraw', 'WorkTH', NULL),
(3, 'cfcbazar@gmail.com', '0x1111111111111111111111111111111111111111', '5.00000000', '1.00000000', 'completed', '2025-06-23 15:27:47', 'withdraw', 'WorkTH', NULL),
(4, 'arakelthedragon@gmail.com', '0x1111111111111111111111111111111111111111', '5.00000000', '1.00000000', 'completed', '2025-06-23 15:36:02', 'withdraw', 'WorkTH', NULL),
(5, 'cfcbazar@gmail.com', '0xe8911e98a00d36a1841945d6270611510f1c7e88', '500.00000000', '1.00000000', 'completed', '2025-06-23 19:11:14', 'withdraw', 'WorkTH', NULL),
(6, 'cfcbazar@gmail.com', '0xe8911e98a00d36a1841945d6270611510f1c7e88', '500.00000000', '1.00000000', 'completed', '2025-06-23 19:12:10', 'withdraw', 'WorkTH', NULL),
(7, 'cfcbazar@gmail.com', '0xd05a0cf460bb91b49f9103228dd188024e68edea', '10000.00000000', '1.00000000', 'pending', '2025-06-27 00:52:13', 'withdraw', 'WorkTH', NULL),
(8, 'cfcbazar@gmail.com', '0xFBd767f6454bCd07c959da2E48fD429531A1323A', '1.00000000', '1.00000000', 'pending', '2025-08-31 11:26:45', 'withdraw', 'WorkTH', NULL),
(9, 'cfcbazar@gmail.com', '0xFBd767f6454bCd07c959da2E48fD429531A1323A', '1.00000000', '1.00000000', 'pending', '2025-08-31 11:30:09', 'withdraw', 'WorkTH', NULL),
(10, 'cfcbazar@gmail.com', '0xFBd767f6454bCd07c959da2E48fD429531A1323A', '1.00000000', '1.00000000', 'pending', '2025-08-31 11:42:41', 'withdraw', 'WorkTH', NULL),
(11, 'CFCbazar@gmail.com', '0xA67F9a40B41ca630DBD7c637b4e53a7C79Af6D04', '1.00000000', '1.00000000', 'pending', '2025-08-31 12:14:11', 'withdraw', 'WorkTH', NULL),
(12, 'CFCbazar@gmail.com', '0xA67F9a40B41ca630DBD7c637b4e53a7C79Af6D04', '1.00000000', '1.00000000', 'pending', '2025-08-31 12:19:02', 'withdraw', 'WorkTH', NULL),
(13, 'CFCbazar@gmail.com', '0xA67F9a40B41ca630DBD7c637b4e53a7C79Af6D04', '1.00000000', '1.00000000', 'pending', '2025-08-31 12:20:54', 'withdraw', 'WorkTH', NULL),
(14, 'CFCbazar@gmail.com', '0xA67F9a40B41ca630DBD7c637b4e53a7C79Af6D04', '100.00000000', '1.00000000', 'pending', '2025-08-31 12:21:52', 'withdraw', 'WorkTH', NULL),
(15, 'CFCbazar@gmail.com', '0xA67F9a40B41ca630DBD7c637b4e53a7C79Af6D04', '156.00000000', '1.00000000', 'pending', '2025-08-31 12:26:21', 'withdraw', 'WorkTH', NULL),
(16, 'CFCbazar@gmail.com', '0xA67F9a40B41ca630DBD7c637b4e53a7C79Af6D04', '5849.00000000', '1.00000000', 'pending', '2025-08-31 12:28:27', 'withdraw', 'WorkTH', NULL),
(17, 'CFCbazar@gmail.com', '0xA67F9a40B41ca630DBD7c637b4e53a7C79Af6D04', '364.00000000', '1.00000000', 'pending', '2025-08-31 12:29:35', 'withdraw', 'WorkTH', NULL),
(18, 'CFCbazar@gmail.com', '0xA67F9a40B41ca630DBD7c637b4e53a7C79Af6D04', '6457.00000000', '1.00000000', 'pending', '2025-08-31 12:30:48', 'withdraw', 'WorkTH', NULL),
(19, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '123.00000000', '1.00000000', 'pending', '2025-10-13 14:30:08', 'withdraw', 'WorkTH', NULL),
(20, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '100.00000000', '1.00000000', 'pending', '2025-10-13 14:52:32', 'withdraw', 'WorkTHR', NULL),
(21, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '100.00000000', '1.00000000', 'pending', '2025-10-13 14:54:08', 'withdraw', 'WorkTHR', NULL),
(22, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '2.00000000', '1.00000000', 'pending', '2025-10-13 15:01:15', 'withdraw', 'WorkToken', NULL),
(23, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '12.00000000', '1.00000000', 'pending', '2025-10-13 15:03:54', 'withdraw', 'WorkToken', NULL),
(24, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '12.00000000', '1.00000000', 'pending', '2025-10-13 15:03:58', 'withdraw', 'WorkToken', NULL),
(25, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '12.00000000', '1.00000000', 'pending', '2025-10-13 15:04:00', 'withdraw', 'WorkTHR', NULL),
(26, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '12.00000000', '1.00000000', 'pending', '2025-10-13 15:04:25', 'withdraw', 'WorkTH', NULL),
(27, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '123.00000000', '1.00000000', 'pending', '2025-10-13 15:07:12', 'withdraw', 'WorkToken', NULL),
(28, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '11.00000000', '1.00000000', 'pending', '2025-10-13 15:16:28', 'withdraw', 'WorkToken', NULL),
(29, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '0.13000000', '1.00000000', 'pending', '2025-10-13 15:19:38', 'withdraw', 'WorkTHR', NULL),
(30, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '0.13000000', '1.00000000', 'pending', '2025-10-13 15:20:16', 'withdraw', 'WorkTHR', NULL),
(31, 'cfcbazar.payments@gmail.com', '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '0.01000000', '1.00000000', 'pending', '2025-10-13 15:20:40', 'withdraw', 'WorkToken', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `workers`
--

CREATE TABLE `workers` (
  `id` int(11) NOT NULL,
  `worker_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `hr2` int(11) DEFAULT 0,
  `mintme` decimal(20,8) DEFAULT 0.00000000,
  `tokens_earned` decimal(20,8) DEFAULT NULL,
  `helmet` varchar(255) DEFAULT NULL,
  `armour` varchar(255) DEFAULT NULL,
  `weapon` varchar(255) DEFAULT NULL,
  `second_weapon` varchar(255) DEFAULT NULL,
  `pants` varchar(255) DEFAULT NULL,
  `boots` varchar(255) DEFAULT NULL,
  `gloves` varchar(255) DEFAULT NULL,
  `base_location` varchar(255) DEFAULT NULL,
  `exp` int(11) DEFAULT 0,
  `level` int(11) DEFAULT 1,
  `address` varchar(255) DEFAULT NULL,
  `dHr` decimal(20,8) NOT NULL DEFAULT 100000.00000000,
  `last_mine_time` timestamp NULL DEFAULT NULL,
  `last_tx_hash` varchar(66) DEFAULT NULL,
  `payout_requested` tinyint(1) NOT NULL DEFAULT 0,
  `last_submission` datetime DEFAULT NULL,
  `accepted_shares` bigint(20) DEFAULT 0,
  `accepted_shares_temp` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `dropdown` enum('WorkToken','WorkTHR') DEFAULT 'WorkToken'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workers`
--

INSERT INTO `workers` (`id`, `worker_name`, `email`, `hr2`, `mintme`, `tokens_earned`, `helmet`, `armour`, `weapon`, `second_weapon`, `pants`, `boots`, `gloves`, `base_location`, `exp`, `level`, `address`, `dHr`, `last_mine_time`, `last_tx_hash`, `payout_requested`, `last_submission`, `accepted_shares`, `accepted_shares_temp`, `dropdown`) VALUES
(1, 'CfCbazar', 'cfcbazar@gmail.com', 0, '1.23000000', '102.09100000', 'Helmet +100', 'Armour +110', 'Weapon +100', 'Second Weapon +99', 'Pants +99', 'Boots +99', 'Gloves +99', 'Base Alpha', 580, 6, '0xFBd767f6454bCd07c959da2E48fD429531A1323A', '0.00000000', '2025-09-19 16:23:40', '', 0, '2025-09-19 09:23:40', 0, 0, 'WorkToken'),
(7374, 'cfcbazar.payments@gmail.com', 'cfcbazar.payments@gmail.com', 0, '4.48400000', '255.38630889', 'Helmet +4', 'Armour +29', 'Weapon +7', 'Second Weapon +4', 'Pants +9', 'Boots +22', NULL, NULL, 120, 2, '0xAEA49Be813E1D013bb01ed76BcDf6978823298B2', '100000.00000000', NULL, NULL, 0, NULL, 1465, 386, 'WorkToken'),
(7375, 'nguyenhaianh181182@gmail.com', 'nguyenhaianh181182@gmail.com', 0, '0.00000000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '0', '100000.00000000', NULL, NULL, 0, NULL, 0, 0, 'WorkToken'),
(7376, 'arakelthedragon@gmail.com', 'arakelthedragon@gmail.com', 0, '0.00000000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '0xFBd767f6454bCd07c959da2E48fD429531A1323A', '100000.00000000', NULL, NULL, 0, NULL, 0, 0, 'WorkToken'),
(7377, 'fatoumaviktoria@gmail.com', 'fatoumaviktoria@gmail.com', 0, '0.00000000', '0.00000000', '', '', '', '', '', '', '', '', 0, 1, '0x4A4395a8cB4Ae6A9099e25368Fa0e9741aDf9E9D', '100000.00000000', '0000-00-00 00:00:00', '', 0, '0000-00-00 00:00:00', 0, 0, 'WorkTHR'),
(7379, '', 'nsrmagazin@gmail.com', 0, '1.00000000', '1.00000000', '', '', '', '', '', '', '', '', 0, 1, '0xe8911e98a00d36a1841945d6270611510f1c7e88', '100000.00000000', '0000-00-00 00:00:00', '', 0, '0000-00-00 00:00:00', 1, 1, 'WorkTHR');

-- --------------------------------------------------------

--
-- Table structure for table `work_value`
--

CREATE TABLE `work_value` (
  `id` int(11) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `type` enum('profession','product','service') DEFAULT NULL,
  `hourly_usd` decimal(10,2) DEFAULT NULL,
  `hourly_wtk` bigint(20) DEFAULT NULL,
  `hours` int(11) DEFAULT NULL,
  `total_usd` decimal(12,2) DEFAULT NULL,
  `total_wtk` bigint(20) DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `region` varchar(100) DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `work_value`
--

INSERT INTO `work_value` (`id`, `title`, `type`, `hourly_usd`, `hourly_wtk`, `hours`, `total_usd`, `total_wtk`, `status`, `region`) VALUES
(1, 'JS Developer', 'profession', '30.00', 300000000, 1666, '50000.00', 500000000000, 'approved', 'General'),
(2, 'CFCBazar Website', 'product', '30.00', 300000000, 400, '12000.00', 120000000000, 'approved', 'Czech Republic '),
(3, 'C Developer', 'profession', '30.00', 300000000, 1700, '51000.00', 510000000000, 'approved', 'General'),
(9, 'Washing dishes', 'profession', '5.45', 54500000, 2000, '10900.00', 109000000000, 'approved', 'Czech Republic'),
(10, 'Storehouse Worker', 'profession', '4.25', 42500000, 1000, '4250.00', 42500000000, 'approved', 'Prague, Czech Republic'),
(11, 'Storehouse', 'profession', '6.22', 62200000, 2880, '17913.00', 179136000, 'approved', 'Prague, CZ');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `click_logs`
--
ALTER TABLE `click_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `short_id` (`short_id`);

--
-- Indexes for table `deposit_amounts`
--
ALTER TABLE `deposit_amounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valid_until` (`valid_until`),
  ADD KEY `email` (`email`) USING BTREE;

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device` (`email`,`mac_address`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_active` (`active`);

--
-- Indexes for table `miner`
--
ALTER TABLE `miner`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `uniq_path` (`path`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `quests`
--
ALTER TABLE `quests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `scheduler`
--
ALTER TABLE `scheduler`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `short_links`
--
ALTER TABLE `short_links`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `speed_results`
--
ALTER TABLE `speed_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `visit_logs`
--
ALTER TABLE `visit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `withdraws`
--
ALTER TABLE `withdraws`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `workers`
--
ALTER TABLE `workers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `worker_name` (`worker_name`),
  ADD KEY `email` (`email`) USING BTREE;

--
-- Indexes for table `work_value`
--
ALTER TABLE `work_value`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `click_logs`
--
ALTER TABLE `click_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `deposit_amounts`
--
ALTER TABLE `deposit_amounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `miner`
--
ALTER TABLE `miner`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86724;

--
-- AUTO_INCREMENT for table `quests`
--
ALTER TABLE `quests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `scheduler`
--
ALTER TABLE `scheduler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `short_links`
--
ALTER TABLE `short_links`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `speed_results`
--
ALTER TABLE `speed_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=186;

--
-- AUTO_INCREMENT for table `visit_logs`
--
ALTER TABLE `visit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `withdraws`
--
ALTER TABLE `withdraws`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `workers`
--
ALTER TABLE `workers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7395;

--
-- AUTO_INCREMENT for table `work_value`
--
ALTER TABLE `work_value`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pages`
--
ALTER TABLE `pages`
  ADD CONSTRAINT `pages_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
