-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 11, 2025 at 11:39 AM
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
-- Database: `buysell`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `place_bid` (IN `p_auction_id` INT, IN `p_user_id` INT, IN `p_amount` DECIMAL(10,2))   BEGIN
  DECLARE v_max   DECIMAL(10,2);
  DECLARE v_start DATETIME;
  DECLARE v_end   DATETIME;
  DECLARE v_item  INT;
  
  -- 1) Pull in auction window and item_id
  SELECT start_time, end_time, item_id
    INTO v_start, v_end, v_item
    FROM auctions
   WHERE auction_id = p_auction_id;
  
  -- 2) Time checks
  IF NOW() < v_start THEN
    SIGNAL SQLSTATE '45000' 
      SET MESSAGE_TEXT = 'Auction has not started';
  END IF;
  IF NOW() > v_end THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Auction already ended';
  END IF;
  
  -- 3) Current high bid
  SELECT MAX(amount)
    INTO v_max
    FROM bids
   WHERE auction_id = p_auction_id;
  IF v_max IS NULL THEN
    SELECT initial_price
      INTO v_max
      FROM auctions
     WHERE auction_id = p_auction_id;
  END IF;
  
  -- 4) Must exceed it
  IF p_amount <= v_max THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Bid must exceed current highest';
  END IF;
  
  -- 5) Insert the new bid
  INSERT INTO bids(auction_id, user_id, amount)
    VALUES(p_auction_id, p_user_id, p_amount);
  
  -- 6) Update auction.final_price
  UPDATE auctions
     SET final_price = p_amount
   WHERE auction_id = p_auction_id;
  
  -- 7) **New**: also bump items.price so your item grid shows the latest bid
  UPDATE items
     SET price = p_amount
   WHERE item_id = v_item;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `purchase_item` (IN `p_item_id` INT, IN `p_buyer_id` INT, IN `p_amount` DECIMAL(10,2))   BEGIN
  DECLARE v_seller INT;
  
  SELECT seller_id 
    INTO v_seller 
    FROM items 
   WHERE item_id = p_item_id 
     AND status = 'available';
  IF v_seller IS NULL THEN
    SIGNAL SQLSTATE '45000' 
      SET MESSAGE_TEXT = 'Item not available';
  END IF;
  
  INSERT INTO purchases(
    item_id,buyer_id,seller_id,auction_id,
    purchase_type,amount
  ) VALUES (
    p_item_id, p_buyer_id, v_seller, NULL,
    'direct', p_amount
  );
  
  UPDATE items 
     SET status = 'sold' 
   WHERE item_id = p_item_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `auctions`
--

CREATE TABLE `auctions` (
  `auction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `initial_price` decimal(10,2) NOT NULL,
  `final_price` decimal(10,2) DEFAULT NULL,
  `upi_id` varchar(50) DEFAULT NULL,
  `qr_image` varchar(255) DEFAULT NULL,
  `status` enum('upcoming','live','closed','unsold') NOT NULL DEFAULT 'upcoming'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auctions`
--

INSERT INTO `auctions` (`auction_id`, `item_id`, `seller_id`, `start_time`, `end_time`, `initial_price`, `final_price`, `upi_id`, `qr_image`, `status`) VALUES
(1, 5, 2, '2025-05-08 18:00:00', '2025-05-08 18:05:00', 500.00, NULL, NULL, NULL, 'closed'),
(2, 7, 1, '2025-05-08 18:35:00', '2025-05-08 18:40:00', 850.00, NULL, NULL, NULL, 'closed'),
(3, 8, 1, '2025-05-08 18:50:00', '2025-05-08 18:55:00', 900.00, NULL, NULL, NULL, 'closed'),
(4, 9, 1, '2025-05-08 19:30:00', '2025-05-08 19:35:00', 680.00, NULL, NULL, NULL, 'closed'),
(5, 11, 1, '2025-05-08 19:45:00', '2025-05-08 19:50:00', 880.00, NULL, NULL, NULL, 'closed'),
(6, 12, 1, '2025-05-08 20:02:00', '2025-05-08 20:06:00', 900.00, NULL, NULL, NULL, 'closed'),
(7, 14, 2, '2025-05-08 22:06:00', '2025-05-09 23:04:00', 950.00, 1100.00, '9063976369@ybl', 'uploads/qr_681cdd523d60c.jpg', 'closed'),
(8, 15, 1, '2025-05-08 22:43:00', '2025-05-08 22:46:00', 750.00, 1050.05, '9063976369@ybl', 'uploads/qr_681ce5d939620.jpg', 'closed'),
(9, 16, 1, '2025-05-08 22:54:00', '2025-05-08 22:57:00', 800.00, 850.00, '9063976369@ybl', 'uploads/qr_681ce8602a037.jpg', 'closed'),
(10, 17, 3, '2025-05-08 23:16:00', '2025-05-08 23:18:00', 850.00, NULL, '9063976369@ybl', 'uploads/qr_681ced7f69398.jpg', 'closed'),
(11, 18, 2, '2025-05-08 23:21:00', '2025-05-08 23:23:00', 900.00, 967.00, '9063976369@ybl', 'uploads/qr_681ceed758e4d.jpg', 'closed'),
(13, 20, 2, '2025-05-08 23:38:00', '2025-05-08 23:39:00', 500.00, 660.00, '9063976369@ybl', 'uploads/qr_681cf2d2e13c8.jpg', 'closed'),
(14, 21, 3, '2025-05-08 23:46:00', '2025-05-08 23:47:00', 400.00, 500.00, '9063976369@ybl', 'uploads/qr_681cf4e00693e.jpg', 'closed'),
(15, 22, 2, '2025-05-08 23:55:00', '2025-05-08 23:56:00', 450.00, 550.00, '9063976369@ybl', 'uploads/qr_681cf6f266a73.jpg', 'closed'),
(17, 24, 1, '2025-05-09 12:12:00', '2025-05-09 12:18:00', 600.00, 650.00, '9063976369@ybl', 'uploads/qr_681da3f165876.jpg', 'closed'),
(18, 25, 1, '2025-05-09 12:28:00', '2025-05-09 12:37:00', 1200.00, 1350.00, '9063976369@ybl', 'uploads/qr_681da7547576c.jpg', 'closed'),
(22, 33, 1, '2025-05-11 13:08:00', '2025-05-11 13:45:00', 50.00, 120.00, '9063976369@ybl', 'uploads/qr_682053b49727d.jpg', ''),
(25, 36, 3, '2025-05-11 14:03:00', '2025-05-11 14:04:00', 550.00, NULL, '9063976369@ibl', NULL, ''),
(26, 37, 3, '2025-05-11 14:35:00', '2025-05-12 14:33:00', 630.00, 730.00, '9063976369@ibl', NULL, 'live'),
(29, 40, 3, '2025-05-11 15:05:00', '2025-05-11 15:06:00', 20.00, NULL, '9063976369@ibl', NULL, ''),
(30, 41, 2, '2025-05-11 15:06:00', '2025-05-11 15:07:00', 66.00, NULL, '9063976369@ibl', NULL, 'closed');

--
-- Triggers `auctions`
--
DELIMITER $$
CREATE TRIGGER `trg_auction_closed` AFTER UPDATE ON `auctions` FOR EACH ROW BEGIN
  -- when status changes into 'closed' (from either upcoming or live)
  IF OLD.status IN ('upcoming','live') AND NEW.status = 'closed' THEN
    -- if there was at least one bid, insert the winning purchase
    IF EXISTS (SELECT 1 FROM bids WHERE auction_id = NEW.auction_id) THEN
      INSERT INTO purchases(
        item_id, buyer_id, seller_id, auction_id,
        purchase_type, amount
      )
      SELECT 
        a.item_id, b.user_id, a.seller_id, a.auction_id,
        'auction', b.amount
      FROM bids b
      JOIN auctions a ON a.auction_id = b.auction_id
      WHERE b.auction_id = NEW.auction_id
      ORDER BY b.amount DESC
      LIMIT 1;
      -- mark the item sold
      UPDATE items
         SET status = 'sold'
       WHERE item_id = NEW.item_id;
    ELSE
      -- no bids: leave item as 'available'
      UPDATE items
         SET status = 'available'
       WHERE item_id = NEW.item_id;
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bids`
--

CREATE TABLE `bids` (
  `bid_id` int(11) NOT NULL,
  `auction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bid_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bids`
--

INSERT INTO `bids` (`bid_id`, `auction_id`, `user_id`, `amount`, `bid_time`) VALUES
(1, 7, 1, 950.30, '2025-05-08 22:39:59'),
(2, 7, 2, 1000.00, '2025-05-08 22:40:50'),
(3, 7, 1, 1100.00, '2025-05-08 22:41:15'),
(4, 8, 2, 800.01, '2025-05-08 22:43:13'),
(5, 8, 1, 850.02, '2025-05-08 22:43:36'),
(6, 8, 3, 900.03, '2025-05-08 22:44:48'),
(7, 8, 1, 950.04, '2025-05-08 22:45:20'),
(8, 8, 3, 1050.05, '2025-05-08 22:45:39'),
(9, 9, 2, 850.00, '2025-05-08 22:54:43'),
(10, 11, 1, 901.01, '2025-05-08 23:21:19'),
(11, 11, 3, 967.00, '2025-05-08 23:21:39'),
(14, 13, 1, 550.00, '2025-05-08 23:38:10'),
(15, 13, 3, 660.00, '2025-05-08 23:38:25'),
(16, 14, 2, 500.00, '2025-05-08 23:46:18'),
(17, 15, 1, 500.00, '2025-05-08 23:55:06'),
(18, 15, 3, 550.00, '2025-05-08 23:55:20'),
(19, 17, 1, 650.00, '2025-05-09 12:13:34'),
(20, 18, 2, 1300.00, '2025-05-09 12:28:14'),
(21, 18, 3, 1350.00, '2025-05-09 12:28:44'),
(26, 22, 2, 70.00, '2025-05-11 13:10:11'),
(27, 22, 2, 120.00, '2025-05-11 13:25:29'),
(31, 26, 3, 680.00, '2025-05-11 14:54:28'),
(32, 26, 3, 730.00, '2025-05-11 14:54:32');

-- --------------------------------------------------------

--
-- Table structure for table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `bookmark_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookmarks`
--

INSERT INTO `bookmarks` (`bookmark_id`, `user_id`, `item_id`, `created_at`) VALUES
(3, 1, 29, '2025-05-09 12:58:22');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`) VALUES
(3, 'Books'),
(4, 'Clothes'),
(2, 'Cycle'),
(1, 'Electronics'),
(5, 'Others');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `started_at` datetime DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`conversation_id`, `user1_id`, `user2_id`, `started_at`) VALUES
(1, 1, 2, '2025-05-09 12:57:35');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `seller_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('available','sold','auction') NOT NULL DEFAULT 'available',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `qr_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `name`, `photo_url`, `details`, `seller_id`, `category_id`, `price`, `status`, `is_active`, `created_at`, `qr_url`) VALUES
(1, 'Purse', 'uploads/item_681c9ea5ded10.jpg', 'leather purse which is just bought 5 months back', 1, 5, 750.00, 'sold', 1, '2025-05-08 17:38:05', 'uploads/qr_681c9ea5df5ff.jpg'),
(2, 'purse', 'uploads/item_681c9f967157d.jpg', 'genuine leather', 1, 5, 800.00, 'sold', 1, '2025-05-08 17:42:06', 'uploads/qr_681c9f967197f.jpg'),
(3, 'purseeee', 'uploads/item_681ca141405b1.jpg', 'genuine leather', 1, 5, 900.00, 'available', 1, '2025-05-08 17:49:13', 'uploads/qr_681ca1414096a.jpg'),
(4, 'Purse', 'uploads/item_681ca20a9ac15.jpg', 'genuine leather', 2, 5, 1000.00, 'sold', 1, '2025-05-08 17:52:34', 'uploads/qr_681ca20a9af00.jpg'),
(5, 'Purse', 'uploads/auc_681ca38f2a3b3.jpg', 'leather', 2, 5, 500.00, 'sold', 1, '2025-05-08 17:59:03', 'uploads/qr_681ca38f2a804.jpg'),
(6, 'Wireless Keyboard', 'uploads/item_681cab4b1b368.jpg', 'Keyboard', 2, 1, 1000.00, 'sold', 1, '2025-05-08 18:32:03', 'uploads/qr_681cab4b1b8af.jpg'),
(7, 'Wireless Keyboard', 'uploads/auc_681caba70765a.jpg', 'Dell,Wireless', 1, 1, 850.00, 'sold', 1, '2025-05-08 18:33:35', 'uploads/qr_681caba707f7b.jpg'),
(8, 'keyboard', 'uploads/auc_681caf3593ec1.jpg', 'wireless,dell', 1, 1, 900.00, 'auction', 1, '2025-05-08 18:48:45', 'uploads/qr_681caf3594355.jpg'),
(9, 'Wireless Keyboard', 'uploads/auc_681cb882a247f.jpg', 'dell', 1, 1, 680.00, 'auction', 1, '2025-05-08 19:28:26', 'uploads/qr_681cb882a29a2.jpg'),
(10, 'keyboard', NULL, 'wireless', 1, 1, 0.00, 'sold', 1, '2025-05-08 19:39:21', NULL),
(11, 'keyboard', 'uploads/placeholder.jpg', 'wireless', 1, 1, 880.00, 'auction', 1, '2025-05-08 19:43:35', NULL),
(12, 'Wireless Keyboard', 'uploads/auc_681cbff4519dd.jpg', 'dell', 1, 1, 900.00, 'auction', 1, '2025-05-08 20:00:12', 'uploads/qr_681cbff451b4e.jpg'),
(13, 'Earbuds', 'uploads/item_681cdd0ae3996.jpg', 'boat', 2, 1, 1300.00, 'available', 1, '2025-05-08 22:04:18', 'uploads/qr_681cdd0ae3e4b.jpg'),
(14, 'Earbuds', 'uploads/photo_681cdd523d1c8.jpg', 'boat', 2, 1, 950.00, 'sold', 1, '2025-05-08 22:05:30', NULL),
(15, 'keyboard', 'uploads/photo_681ce5d938fac.jpg', 'wireless', 1, 1, 750.00, 'auction', 1, '2025-05-08 22:41:53', NULL),
(16, 'keyboard', 'uploads/photo_681ce860299a9.jpg', 'wireless', 1, 1, 800.00, 'auction', 1, '2025-05-08 22:52:40', NULL),
(17, 'keyboard', 'uploads/photo_681ced7f68d7e.jpg', 'wireless', 3, 1, 850.00, 'auction', 1, '2025-05-08 23:14:31', NULL),
(18, 'keyboard', 'uploads/photo_681ceed7587f0.jpg', 'wireless', 2, 1, 900.00, 'auction', 1, '2025-05-08 23:20:15', NULL),
(20, 'purse', 'uploads/photo_681cf2d2e0cd1.jpg', 'leather', 2, 5, 500.00, 'auction', 1, '2025-05-08 23:37:14', NULL),
(21, 'purse', 'uploads/photo_681cf4e006355.jpg', 'leather', 3, 5, 400.00, 'auction', 1, '2025-05-08 23:46:00', NULL),
(22, 'purse', 'uploads/photo_681cf6f26640e.jpg', 'leather', 2, 5, 550.00, 'auction', 1, '2025-05-08 23:54:50', NULL),
(24, 'purse', 'uploads/photo_681da3f16564a.jpg', 'leather', 1, 5, 650.00, 'auction', 1, '2025-05-09 12:12:57', NULL),
(25, 'Earbuds', 'uploads/photo_681da75475374.jpg', 'boat', 1, 1, 1350.00, 'auction', 1, '2025-05-09 12:27:24', NULL),
(27, 'purse', 'uploads/photo_681dad0445bcf.jpg', 'leather', 2, 5, 760.00, 'sold', 1, '2025-05-09 12:51:40', NULL),
(28, 'Water Bottle', 'uploads/item_681dadd7e81a7.jpg', 'stainless steel', 3, 5, 550.00, 'sold', 1, '2025-05-09 12:55:11', 'uploads/qr_681dadd7e888c.jpg'),
(29, 'Wireless Mouse', 'uploads/item_681dae5983da9.jpg', 'Dell', 2, 1, 700.00, 'sold', 1, '2025-05-09 12:57:21', 'uploads/qr_681dae59844a0.jpg'),
(30, 'purse', 'uploads/photo_681dda6b66891.jpg', 'leather', 1, 5, 700.00, 'sold', 1, '2025-05-09 16:05:23', NULL),
(31, 'Classmate Notebook', 'uploads/item_681e4e1177c5c.jpg', 'small,100 pages', 1, 3, 50.00, 'available', 1, '2025-05-10 00:18:49', 'uploads/qr_681e4e11782a2.jpg'),
(32, 'Wireless Keyboard', 'uploads/item_681e4e4e5c085.jpg', 'Dell', 1, 1, 900.00, 'available', 1, '2025-05-10 00:19:50', 'uploads/qr_681e4e4e5c24a.jpg'),
(33, 'book', 'uploads/photo_682053b496dc8.jpg', 'classmate ,short 100 pages', 1, 3, 120.00, 'auction', 1, '2025-05-11 13:07:24', NULL),
(34, 'purse', 'uploads/photo_682058e4e819e.jpg', 'leather', 3, 5, 750.00, 'sold', 1, '2025-05-11 13:29:32', NULL),
(35, 'keyboard', 'uploads/photo_68205a44d9667.jpg', 'wireless,Dell', 2, 1, 1000.00, 'sold', 1, '2025-05-11 13:35:24', NULL),
(36, 'Purse', 'uploads/WhatsApp Image 2025-05-08 at 17.36.43_9b8184d7.jpg', NULL, 3, 5, 0.00, 'auction', 1, '2025-05-11 14:03:16', NULL),
(37, 'Purse', 'uploads/WhatsApp Image 2025-05-08 at 17.36.43_9b8184d7.jpg', NULL, 3, 5, 730.00, 'auction', 1, '2025-05-11 14:33:35', NULL),
(38, 'book', 'uploads/book.jpg', NULL, 3, 3, 110.00, 'sold', 1, '2025-05-11 14:55:42', NULL),
(39, 'purse', 'uploads/WhatsApp Image 2025-05-08 at 17.36.43_9b8184d7.jpg', NULL, 2, 5, 870.00, 'sold', 1, '2025-05-11 15:01:45', NULL),
(40, 'odjesd', 'uploads/book.jpg', NULL, 3, 3, 0.00, 'auction', 1, '2025-05-11 15:05:51', NULL),
(41, 'y7yjhtgnhfbr', 'uploads/book.jpg', NULL, 2, 3, 0.00, 'available', 1, '2025-05-11 15:06:50', NULL),
(42, 'rthy', 'uploads/book.jpg', NULL, 3, 3, 80.00, 'sold', 1, '2025-05-11 15:07:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `conversation_id`, `sender_id`, `content`, `sent_at`) VALUES
(1, 1, 1, 'hi', '2025-05-09 12:57:43'),
(2, 1, 2, 'yes!!', '2025-05-09 12:58:07'),
(3, 1, 1, 'hello', '2025-05-10 00:43:06'),
(4, 1, 1, 'namaste', '2025-05-10 18:46:53'),
(5, 1, 1, 'hiii', '2025-05-10 18:47:36'),
(6, 1, 2, 'byee\\r\\n', '2025-05-11 00:01:26');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `upi_id` varchar(100) NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_time` datetime DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `purchase_id`, `item_id`, `buyer_id`, `seller_id`, `amount`, `upi_id`, `status`, `payment_time`) VALUES
(1, 9, 4, 2, 2, 1000.00, '9063976369@ibl', 'completed', '2025-05-08 17:52:47'),
(2, 11, 6, 1, 2, 1000.00, '9063976369@ibl', 'completed', '2025-05-08 18:32:39'),
(3, 12, 7, 1, 1, 850.00, '9063976369@ibl', 'completed', '2025-05-08 18:48:01'),
(4, 13, 2, 1, 1, 800.00, '9063976369@ibl', 'completed', '2025-05-08 20:07:13'),
(5, 14, 10, 3, 1, 0.00, '9063976369@ibl', 'completed', '2025-05-08 23:45:29'),
(6, 15, 27, 3, 2, 760.00, '9063976369@ibl', 'completed', '2025-05-09 12:53:10'),
(7, 16, 28, 2, 3, 550.00, '9063976369@ibl', 'completed', '2025-05-09 12:55:56'),
(8, 17, 30, 3, 1, 700.00, '9063976369@ibl', 'completed', '2025-05-09 16:06:16'),
(9, 18, 29, 1, 2, 700.00, '9063976369@ibl', 'completed', '2025-05-09 23:51:22'),
(10, 20, 34, 2, 3, 750.00, '9063976369@ibl', 'completed', '2025-05-11 13:32:19'),
(11, 21, 35, 3, 2, 1000.00, '9063976369@ibl', 'completed', '2025-05-11 13:36:16'),
(12, 22, 38, 2, 3, 110.00, '9063976369@ibl', 'completed', '2025-05-11 14:57:18'),
(13, 23, 39, 3, 2, 870.00, '9063976369@ibl', 'completed', '2025-05-11 15:03:21'),
(14, 24, 42, 2, 3, 80.00, '9063976369@ibl', 'completed', '2025-05-11 15:08:12');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `purchase_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `auction_id` int(11) DEFAULT NULL,
  `purchase_type` enum('direct','auction') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `purchase_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`purchase_id`, `item_id`, `buyer_id`, `seller_id`, `auction_id`, `purchase_type`, `amount`, `purchase_time`) VALUES
(1, 1, 1, 1, NULL, 'direct', 750.00, '2025-05-08 17:39:05'),
(9, 4, 2, 2, NULL, 'direct', 1000.00, '2025-05-08 17:52:47'),
(11, 6, 1, 2, NULL, 'direct', 1000.00, '2025-05-08 18:32:39'),
(12, 7, 1, 1, NULL, 'direct', 850.00, '2025-05-08 18:48:01'),
(13, 2, 1, 1, NULL, 'direct', 800.00, '2025-05-08 20:07:13'),
(14, 10, 3, 1, NULL, 'direct', 0.00, '2025-05-08 23:45:29'),
(15, 27, 3, 2, NULL, 'auction', 760.00, '2025-05-09 12:53:03'),
(16, 28, 2, 3, NULL, 'direct', 550.00, '2025-05-09 12:55:56'),
(17, 30, 3, 1, NULL, 'auction', 700.00, '2025-05-09 16:06:11'),
(18, 29, 1, 2, NULL, 'direct', 700.00, '2025-05-09 23:51:22'),
(19, 14, 1, 2, 7, 'auction', 1100.00, '2025-05-09 23:52:00'),
(20, 34, 2, 3, NULL, 'auction', 750.00, '2025-05-11 13:32:11'),
(21, 35, 3, 2, NULL, 'auction', 1000.00, '2025-05-11 13:36:02'),
(22, 38, 2, 3, NULL, 'auction', 110.00, '2025-05-11 14:57:04'),
(23, 39, 3, 2, NULL, 'auction', 870.00, '2025-05-11 15:03:03'),
(24, 42, 2, 3, NULL, 'auction', 80.00, '2025-05-11 15:08:02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(10) NOT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `phone`, `upi_id`, `is_active`, `created_at`) VALUES
(1, 'Gunavardhan reddy Gopireddy', 'gunavardhan_2301cs65@iitp.ac.in', '$2y$10$A5RZsMRsbCz9L9Sfxc0r7..niQlWJyxWzl2Xtg/Iuq5txDaZmoyDm', '9063976369', '9864747844@ybl', 1, '2025-05-08 17:34:27'),
(2, 'Sridhanush Suru', 'sridhanush_2301cs55@iitp.ac.in', '$2y$10$z9OAaOhn3t0v9jKQZt2t6O.jbCsdkABcmOovGXnlMvqabAus5mnWq', '8555040332', '9063976369@ibl', 1, '2025-05-08 17:42:42'),
(3, 'Vineel Kumar', 'vineel_2301cs62@iitp.ac.in', '$2y$10$ucHz2eV7t0TTiGnh5MPuTeko2LtI1oRlFctfgxo0MrYEUqn9wHr0i', '9440303932', '9063976369@ibl', 1, '2025-05-08 22:44:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auctions`
--
ALTER TABLE `auctions`
  ADD PRIMARY KEY (`auction_id`),
  ADD UNIQUE KEY `item_id` (`item_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `bids`
--
ALTER TABLE `bids`
  ADD PRIMARY KEY (`bid_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_bids_auction_amount` (`auction_id`,`amount`);

--
-- Indexes for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`bookmark_id`),
  ADD UNIQUE KEY `uq_user_item` (`user_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD UNIQUE KEY `uq_users` (`user1_id`,`user2_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_items_status` (`status`),
  ADD KEY `idx_items_seller` (`seller_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`purchase_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `auction_id` (`auction_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auctions`
--
ALTER TABLE `auctions`
  MODIFY `auction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `bids`
--
ALTER TABLE `bids`
  MODIFY `bid_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `bookmark_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `purchase_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auctions`
--
ALTER TABLE `auctions`
  ADD CONSTRAINT `auctions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auctions_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `bids`
--
ALTER TABLE `bids`
  ADD CONSTRAINT `bids_ibfk_1` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`auction_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bids_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD CONSTRAINT `bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookmarks_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`purchase_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_ibfk_4` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`auction_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
