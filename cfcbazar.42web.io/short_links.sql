-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql313.infinityfree.com
-- Generation Time: Sep 08, 2025 at 06:02 PM
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
-- Table structure for table `short_links`
--

CREATE TABLE `short_links` (
  `id` int(10) UNSIGNED NOT NULL,
  `short` varchar(64) NOT NULL,
  `long` text NOT NULL,
  `clicks` int(11) DEFAULT 0,
  `email` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `short_links`
--

INSERT INTO `short_links` (`id`, `short`, `long`, `clicks`, `email`) VALUES
(1, 'https://cfcbazar.ct.ws/r.php?go=1', 'https://onelink.shein.com/13/4r0d2rkz8euv', 2, 'cfcbazar@gmail.com'),
(2, '', 'https://onelink.shein.com/13/4r0pgcq7v68u', 0, 'cfcbazar@gmail.com'),
(3, '', 'https://onelink.shein.com/13/4r0qfbjyvm74', 9, 'cfcbazar@gmail.com'),
(4, '', 'https://onelink.shein.com/13/4r1c5x6a8i69', 5, 'cfcbazar@gmail.com'),
(5, '', 'https://onelink.shein.com/13/4r1c5x6a8i69', 0, 'cfcbazar@gmail.com'),
(6, '', 'https://onelink.shein.com/13/4r1c5x6a8i69', 0, 'cfcbazar@gmail.com'),
(7, '', 'https://cc.free.bg/games.html', 27, 'cfcbazar@gmail.com'),
(8, '', 'https://onelink.shein.com/13/4rr56mk202uh', 8, 'cfcbazar@gmail.com'),
(9, '', 'https://onelink.shein.com/13/4rr7ddvguxd4', 7, 'cfcbazar@gmail.com'),
(10, '', 'https://onelink.shein.com/13/4rr916i3lpo1', 8, 'cfcbazar@gmail.com'),
(11, '', 'https://cc.free', 0, 'cfcbazar@gmail.com'),
(12, '', 'https://cc.free.bg/site', 8, 'cfcbazar@gmail.com'),
(13, '', 'https://onelink.shein.com/13/4s1j3uyuxbva', 8, 'cfcbazar@gmail.com'),
(14, '', 'https://onelink.shein.com/13/4s1j3uyuxbva', 1, 'cfcbazar@gmail.com'),
(15, '', 'https://cc.free.bg/slot.html', 4, 'CfCbazar@gmail.com'),
(16, '', 'https://cfcbazar.ct.ws/', 7, 'cfcbazar@gmail.com'),
(43, '', 'https://app.temu.com/m/nrx25pdgw5a', 5, 'cfcbazar@gmail.com'),
(17, '', 'https://cfcbazar.ct.ws', 18, 'cfcbazar@gmail.com'),
(18, '', 'https://cfcbazar.ct.ws/', 0, 'cfcbazar@gmail.com'),
(19, '', 'http://cfcbazar.ct.ws/', 0, 'cfcbazar@gmail.com'),
(20, '', 'http://cfcbazar.ct.ws', 0, 'cfcbazar@gmail.com'),
(21, '', 'https://cfcbazar.ct.ws/sa.php', 11, 'cfcbazar@gmail.com'),
(22, '', 'https://fb.com/workthrp', 6, 'cfcbazar@gmail.com'),
(23, '', 'https://cfcbazar.ct.ws/games.php', 27, 'cfcbazar@gmail.com'),
(24, '', 'https://cfcbazar.ct.ws/features.php', 28, 'cfcbazar@gmail.com'),
(25, '', 'https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu', 6, 'cfcbazar@gmail.com'),
(26, '', 'https://github.com/ArakelTheDragon/CfCbazar-Tokens', 2, 'cfcbazar@gmail.com'),
(27, '', 'https://www.mintme.com/token/WorkTH/invite', 1, 'cfcbazar@gmail.com'),
(28, '', 'https://cfcbazar.ct.ws/', 32, 'cfcbazar@gmail.com'),
(29, '', 'https://cfcbazar.ct.ws/maze.php', 0, 'cfcbazar@gmail.com'),
(30, '', 'https://x.com/cfcbazargroup', 5, 'cfcbazar@gmail.com'),
(31, '', 'http://fb.com/workthrp', 6, 'cfcbazar@gmail.com'),
(32, '', 'https://youtube.com/playlist?list=PLY4e42xsZig5Yu7GZ6VN1OSn-0cy90yJu&si=OyCuAgWZpmVPOX85', 1, 'cfcbazar@gmail.com'),
(33, '', 'https://cfcbazar.ct.ws/power.php', 6, 'cfcbazar@gmail.com'),
(34, '', 'https://onelink.shein.com/14/4txtsx4c3z0m', 57, 'cfcbazar@gmail.com'),
(35, '', 'https://cfcbazar.ct.ws/r.php?go=12', 0, 'cfcbazar@gmail.com'),
(36, '', 'https://www.paypal.com/donate?token=4VR82hItOxXbP86ZFY5n4gzbb18ODN4qZfVS-6Jwt2caAx5ICCIA_Gy80TolINvl4SG6-zFAQNXlUexr', 1, 'cfcbazar@gmail.com'),
(37, '', 'https://onelink.shein.com/15/4w4e1l3v95pn', 9, 'cfcbazar@gmail.com'),
(38, '', 'https://onelink.shein.com/15/4wm1x5qmn3ls', 5, 'cfcbazar@gmail.com'),
(39, '', 'https://onelink.shein.com/15/4ws7ivfbvnkw', 8, 'cfcbazar@gmail.com'),
(40, '', 'https://onelink.shein.com/15/4xbfrrcewenj', 6, 'cfcbazar@gmail.com'),
(41, '', 'https://onelink.shein.com/15/4xdd5ue3q0ib', 5, 'CFCbazar@gmail.com'),
(42, '', 'https://onelink.shein.com/15/4xdrjm32rnyj', 35, 'CFCbazar@gmail.com'),
(44, '', 'https://cfcbazar.ct.ws/survival.php', 1, 'cfcbazar@gmail.com'),
(45, '', 'https://cfcbazar.ct.ws/number.php', 5, 'cfcbazar@gmail.com'),
(46, '', 'https://cfcbazar.ct.ws/word.php', 4, 'cfcbazar@gmail.com'),
(47, '', 'https://onelink.shein.com/16/4yoy3wn2vyuq', 4, 'cfcbazar@gmail.com'),
(48, '', 'https://cfcbazar.ct.ws/orpg.php', 13, 'cfcbazar@gmail.com'),
(49, '', 'https://onelink.shein.com/16/4ywo3ded614e', 7, 'cfcbazar@gmail.com'),
(50, '', 'https://cfcbazar.ct.ws', 0, 'cfcbazar@gmail.com'),
(51, '', 'https://onelink.shein.com/16/4znn4dzw8mjc', 2, 'cfcbazar@gmail.com'),
(52, '', 'https://onelink.shein.com/16/501gi7f4fd35', 4, 'cfcbazar@gmail.com'),
(53, '', 'https://onelink.shein.com/16/50cnli35kg87', 14, 'cfcbazar@gmail.com'),
(54, '', 'https://onelink.shein.com/16/50cs4a4y446l', 5, 'cfcbazar@gmail.com'),
(55, '', 'https://cfcbazar.ct.ws/r.php?go=54', 0, 'cfcbazar@gmail.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `short_links`
--
ALTER TABLE `short_links`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `short_links`
--
ALTER TABLE `short_links`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
