-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: custsql-myd04.eigbox.net
-- Generation Time: Jul 19, 2026 at 08:41 PM
-- Server version: 5.7.44-log
-- PHP Version: 7.0.33-0ubuntu0.16.04.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `all_america_atlantic`
--
CREATE DATABASE IF NOT EXISTS `all_america_atlantic` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `all_america_atlantic`;

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `badge_color` varchar(20) DEFAULT '#003b9b',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `achievement_badges`
--

CREATE TABLE `achievement_badges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `likes` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `category_id`, `action`, `target_id`, `target_type`, `reference_id`, `created_at`) VALUES
(1, 1, NULL, 'Created a post', NULL, NULL, NULL, '2026-04-12 19:41:35'),
(2, 2, NULL, 'Liked a post', NULL, NULL, NULL, '2026-04-12 19:41:35'),
(3, 3, NULL, 'Commented on a post', NULL, NULL, NULL, '2026-04-12 19:41:35'),
(4, 15, NULL, 'create_post', 12, 'post', NULL, '2026-04-18 01:47:22'),
(5, 15, NULL, 'edit_post', 12, 'post', NULL, '2026-04-18 01:49:02'),
(6, 15, NULL, 'like_post', 12, 'post', NULL, '2026-04-18 02:06:30'),
(7, 15, NULL, 'unlike_post', 12, 'post', NULL, '2026-04-18 02:07:20'),
(8, 15, NULL, 'unlike_post', 12, 'post', NULL, '2026-04-18 02:07:22'),
(9, 15, NULL, 'like_post', 12, 'post', NULL, '2026-04-18 02:09:47'),
(10, 15, NULL, 'comment_post', 12, 'post', NULL, '2026-04-18 08:37:57'),
(11, 15, NULL, 'delete_comment', 1, 'comment', NULL, '2026-04-18 09:29:16'),
(12, 15, NULL, 'comment_post', 12, 'post', NULL, '2026-04-18 09:34:47'),
(13, 11, NULL, 'create_post', 13, 'post', NULL, '2026-04-18 09:57:51'),
(14, 15, NULL, 'comment_post', 12, 'post', NULL, '2026-04-18 10:06:04'),
(15, 15, NULL, 'create_post', 14, 'post', NULL, '2026-04-18 10:16:21'),
(16, 15, NULL, 'comment_post', 14, 'post', NULL, '2026-04-18 10:20:49'),
(17, 15, NULL, 'comment_post', 14, 'post', NULL, '2026-04-18 10:20:49'),
(18, 15, NULL, 'create_post', 15, 'post', NULL, '2026-04-18 14:41:56'),
(19, 15, NULL, 'comment_post', 15, 'post', NULL, '2026-04-18 14:51:22'),
(20, 11, NULL, 'comment_post', 15, 'post', NULL, '2026-04-18 15:06:07'),
(21, 15, NULL, 'comment_post', 15, 'post', NULL, '2026-04-18 15:59:44'),
(22, 11, NULL, 'comment_post', 15, 'post', NULL, '2026-04-18 16:07:30'),
(23, 11, NULL, 'comment_post', 15, 'post', NULL, '2026-04-18 16:13:20'),
(24, 11, NULL, 'reply_comment', 10, 'comment', NULL, '2026-04-18 16:14:56'),
(25, 11, NULL, 'reply_comment', 10, 'comment', NULL, '2026-04-18 16:21:45'),
(26, 11, NULL, 'reply_comment', 10, 'comment', NULL, '2026-04-18 16:22:08'),
(27, 15, NULL, 'reply_comment', 10, 'comment', NULL, '2026-04-18 21:18:14'),
(28, 15, NULL, 'reply_comment', 10, 'comment', NULL, '2026-04-18 21:18:28'),
(29, 15, NULL, 'reply_comment', 10, 'comment', NULL, '2026-04-18 21:20:33'),
(30, 15, NULL, 'like_post', 15, 'post', NULL, '2026-04-18 21:26:11'),
(31, 15, NULL, 'unlike_post', 15, 'post', NULL, '2026-04-18 21:27:00'),
(32, 15, NULL, 'comment_post', 15, 'post', NULL, '2026-04-18 21:32:45'),
(33, 17, NULL, 'comment_post', 15, 'post', NULL, '2026-04-20 12:35:43'),
(34, 17, NULL, 'comment_post', 15, 'post', NULL, '2026-04-20 12:36:28'),
(35, 17, NULL, 'delete_comment', 19, 'comment', NULL, '2026-04-20 12:38:09'),
(36, 17, NULL, 'like_post', 15, 'post', NULL, '2026-04-20 13:32:49'),
(37, 15, NULL, 'create_story', 1, 'story', NULL, '2026-04-20 22:03:36'),
(38, 15, NULL, 'create_story', 2, 'story', NULL, '2026-04-20 22:06:39'),
(39, 17, NULL, 'create_story', 3, 'story', NULL, '2026-04-21 00:20:44'),
(40, 17, NULL, 'create_story', 4, 'story', NULL, '2026-04-21 00:21:32'),
(41, 17, NULL, 'create_story', 5, 'story', NULL, '2026-04-21 00:21:58'),
(42, 17, NULL, 'create_story', 6, 'story', NULL, '2026-04-21 00:25:37'),
(43, 17, NULL, 'create_story', 7, 'story', NULL, '2026-04-21 00:27:02'),
(44, 17, NULL, 'create_story', 8, 'story', NULL, '2026-04-21 00:27:19'),
(45, 17, NULL, 'create_story', 9, 'story', NULL, '2026-04-22 17:56:24'),
(46, 17, NULL, 'create_story', 10, 'story', NULL, '2026-04-22 17:56:27'),
(47, 17, NULL, 'create_story', 11, 'story', NULL, '2026-04-23 02:47:48');

-- --------------------------------------------------------

--
-- Table structure for table `admin_roles`
--

CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text,
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `name`, `description`, `category_id`) VALUES
(1, 'Early Bird', 'Posted early in the morning', NULL),
(2, 'Popular', 'Received 100 likes', NULL),
(3, 'Contributor', 'Posted 50 comments', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text,
  `icon` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `icon`) VALUES
(1, 'Sports', 'Athletes striving for All American excellence', NULL),
(2, 'Business', 'Entrepreneurs and professionals building success', NULL),
(3, 'Tech', 'Developers, engineers, and innovators', NULL),
(4, 'Arts', 'Creators, designers, musicians, and performers', NULL),
(5, 'Leadership', 'People leading with character and truth', NULL),
(6, 'Fitness', 'Health, training, and personal excellence', NULL),
(7, 'Academics', 'Scholars and students pursuing mastery', NULL),
(8, 'Trades', 'Skilled workers and craftsmen', NULL),
(9, 'Service', 'Community, military, and public service', NULL),
(10, 'Tech', 'Technology related posts', NULL),
(11, 'Travel', 'Travel experiences and tips', NULL),
(12, 'Music', 'Music and audio content', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `category_progress`
--

CREATE TABLE `category_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT '1',
  `xp` int(11) DEFAULT '0',
  `xp_needed` int(11) DEFAULT '100'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `comment` text,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `user_id`, `comment`, `parent_id`, `created_at`, `updated_at`) VALUES
(2, 12, 15, 'Testing comment system again', NULL, '2026-04-18 09:34:47', NULL),
(3, 12, 15, 'Testing notifications!', NULL, '2026-04-18 10:06:04', NULL),
(4, 14, 15, 'User 2 testing notifications!', NULL, '2026-04-18 10:20:49', NULL),
(5, 14, 15, 'User 2 testing notifications!', NULL, '2026-04-18 10:20:49', NULL),
(6, 15, 15, 'User 2 comment test after fix', NULL, '2026-04-18 14:51:22', NULL),
(7, 15, 11, 'User 2 REAL comment test', NULL, '2026-04-18 15:06:07', NULL),
(8, 15, 15, 'Testing normal comment', NULL, '2026-04-18 15:59:44', NULL),
(9, 15, 11, 'Testing normal comment', NULL, '2026-04-18 16:07:30', NULL),
(10, 15, 11, 'Testing normal comment', NULL, '2026-04-18 16:13:20', NULL),
(11, 15, 11, 'Testing reply after fixing notifications', 10, '2026-04-18 16:14:56', NULL),
(12, 15, 11, '', 10, '2026-04-18 16:21:45', NULL),
(13, 15, 11, 'your reply text here', 10, '2026-04-18 16:22:08', NULL),
(14, 15, 15, '', 10, '2026-04-18 21:18:14', NULL),
(15, 15, 15, 'This is my reply from charles_test3', 10, '2026-04-18 21:18:28', NULL),
(16, 15, 15, 'Reply test from charles_test3', 10, '2026-04-18 21:20:33', NULL),
(17, 15, 15, 'New comment from charles_test3', NULL, '2026-04-18 21:32:45', NULL),
(18, 15, 17, '', NULL, '2026-04-20 12:35:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `comment_likes`
--

CREATE TABLE `comment_likes` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `followers`
--

CREATE TABLE `followers` (
  `id` int(11) NOT NULL,
  `follower_id` int(11) DEFAULT NULL,
  `following_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `friend_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `highlights`
--

CREATE TABLE `highlights` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `media_url` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) DEFAULT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `content` text,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction` varchar(10) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `message_threads`
--

CREATE TABLE `message_threads` (
  `id` int(11) NOT NULL,
  `user1_id` int(11) DEFAULT NULL,
  `user2_id` int(11) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `from_user_id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `message` text,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `from_user_id`, `type`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 0, 'follow', 'Jordan started following you', 0, '2026-04-12 19:23:55'),
(2, 1, 0, 'like', 'Maria liked your post', 0, '2026-04-12 19:23:55'),
(3, 15, 0, 'comment', 'charles_test commented on your post', 0, '2026-04-18 15:06:07'),
(4, 15, 11, 'comment', 'charles_test commented on your post', 0, '2026-04-18 16:13:20'),
(5, 11, 15, 'reply', 'charles_test2 replied to your comment', 0, '2026-04-18 21:18:14'),
(6, 11, 15, 'reply', 'charles_test2 replied to your comment', 0, '2026-04-18 21:18:28'),
(7, 11, 15, 'reply', 'charles_test2 replied to your comment', 0, '2026-04-18 21:20:33'),
(8, 15, 17, 'comment', 'charles_test3 commented on your post', 0, '2026-04-20 12:35:43'),
(9, 15, 17, 'comment', 'charles_test3 commented on your post', 0, '2026-04-20 12:36:28'),
(10, 15, 17, 'like', 'charles_test3 liked your post', 0, '2026-04-20 13:32:49');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `rating` float DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `category_id`, `title`, `content`, `rating`, `created_at`) VALUES
(1, 1, NULL, NULL, 'My first post here!', 0, '2026-04-12 19:10:23'),
(2, 2, NULL, NULL, 'Loving this new platform', 0, '2026-04-12 19:10:23'),
(3, 3, NULL, NULL, 'Working on something big...', 0, '2026-04-12 19:10:23'),
(4, 1, NULL, NULL, 'My first post on this platform!', 0, '2026-04-12 19:19:02'),
(5, 2, NULL, NULL, 'Beautiful day outside!', 0, '2026-04-12 19:19:02'),
(6, 3, NULL, NULL, 'Working on new music today.', 0, '2026-04-12 19:19:02'),
(7, 15, NULL, NULL, 'Testing my own post', 0, '2026-04-18 01:35:30'),
(8, 15, NULL, NULL, 'Testing my own post', 0, '2026-04-18 01:40:47'),
(9, 15, NULL, NULL, 'Testing my own post', 0, '2026-04-18 01:41:04'),
(10, 15, NULL, NULL, 'Testing my own post', 0, '2026-04-18 01:41:04'),
(11, 15, NULL, NULL, 'Testing my own post', 0, '2026-04-18 01:41:15'),
(12, 15, NULL, NULL, 'Updated content test', 0, '2026-04-18 01:47:22'),
(13, 11, NULL, NULL, 'Notification test', 0, '2026-04-18 09:57:51'),
(14, 15, NULL, NULL, 'Notification test from User 1', 0, '2026-04-18 10:16:21'),
(15, 15, NULL, NULL, 'Testing comments after web.config fix', 0, '2026-04-18 14:41:56');

-- --------------------------------------------------------

--
-- Table structure for table `post_images`
--

CREATE TABLE `post_images` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `thumbnail_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `post_likes`
--

CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `post_likes`
--

INSERT INTO `post_likes` (`id`, `post_id`, `user_id`, `created_at`) VALUES
(1, 1, 2, '2026-04-12 19:12:22'),
(2, 1, 3, '2026-04-12 19:12:22'),
(3, 2, 1, '2026-04-12 19:12:22'),
(4, 1, 2, '2026-04-12 19:19:59'),
(5, 1, 3, '2026-04-12 19:19:59'),
(6, 2, 1, '2026-04-12 19:19:59'),
(7, 3, 1, '2026-04-12 19:19:59'),
(9, 12, 15, '2026-04-18 02:09:47'),
(11, 15, 17, '2026-04-20 13:32:49');

-- --------------------------------------------------------

--
-- Table structure for table `post_videos`
--

CREATE TABLE `post_videos` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `video_url` varchar(255) NOT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bio` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT 'assets/img/default-avatar.svg',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`id`, `user_id`, `bio`, `avatar_url`, `created_at`, `updated_at`) VALUES
(1, 1, 'Tech lover & photographer', '/avatars/alex.png', '2026-04-12 23:17:39', '2026-04-13 21:40:40'),
(2, 2, 'Traveler & foodie', '/avatars/jordan.png', '2026-04-12 23:17:39', '2026-04-13 21:40:40'),
(3, 3, 'Music producer', '/avatars/maria.png', '2026-04-12 23:17:39', '2026-04-13 21:40:40'),
(4, 9, '', '', '2026-04-15 23:22:50', '2026-04-15 19:22:50'),
(5, 11, '', '', '2026-04-18 00:50:30', '2026-04-17 20:50:30'),
(6, 15, '', '', '2026-04-18 00:53:10', '2026-04-17 20:53:10'),
(7, 17, '', '', '2026-04-18 22:56:15', '2026-04-18 18:56:15'),
(27, 105, '', '', '2026-06-04 16:47:14', '2026-06-04 12:47:14'),
(28, 106, '', '', '2026-06-04 16:48:35', '2026-06-04 12:48:35'),
(29, 107, '', '', '2026-06-04 22:10:54', '2026-06-04 18:10:54'),
(30, 108, '', '', '2026-06-06 12:19:16', '2026-06-06 08:19:16'),
(31, 109, '', '', '2026-06-23 01:52:17', '2026-06-22 21:52:17'),
(32, 110, '', '', '2026-07-16 01:57:59', '2026-07-15 21:57:59');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) DEFAULT NULL,
  `target_type` varchar(20) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `reason` text,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `saved_posts`
--

CREATE TABLE `saved_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `stories`
--

CREATE TABLE `stories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `stories`
--

INSERT INTO `stories` (`id`, `user_id`, `media_url`, `media_type`, `caption`, `thumbnail_url`, `created_at`, `expires_at`) VALUES
(1, 15, '/uploads/stories/story_15_1776737016.jpg', 'image', 'fletch and the door he made', '/uploads/stories/thumb_story_15_1776737016.jpg', '2026-04-21 02:03:36', '2026-04-22 02:03:36'),
(2, 15, '/uploads/stories/story_15_1776737199.jpeg', 'image', 'website look', '/uploads/stories/thumb_story_15_1776737199.jpeg', '2026-04-21 02:06:39', '2026-04-22 02:06:39'),
(3, 17, '/uploads/stories/story_17_1776745244.jpg', 'image', 'fletch lives', '/uploads/stories/thumb_story_17_1776745244.jpg', '2026-04-21 04:20:44', '2026-04-22 04:20:44'),
(4, 17, '/uploads/stories/story_17_1776745292.jpg', 'image', 'fletch lives', '/uploads/stories/thumb_story_17_1776745292.jpg', '2026-04-21 04:21:32', '2026-04-22 04:21:32'),
(5, 17, '/uploads/stories/story_17_1776745318.jpeg', 'image', 'website look', '/uploads/stories/thumb_story_17_1776745318.jpeg', '2026-04-21 04:21:58', '2026-04-22 04:21:58'),
(6, 17, '/uploads/stories/story_17_1776745537.jpg', 'image', 'fletch lives', '/uploads/stories/thumb_story_17_1776745537.jpg', '2026-04-21 04:25:37', '2026-04-22 04:25:37'),
(7, 17, '/uploads/stories/story_17_1776745622.png', 'image', '', '/uploads/stories/thumb_story_17_1776745622.png', '2026-04-21 04:27:02', '2026-04-22 04:27:02'),
(8, 17, '/uploads/stories/story_17_1776745639.jpeg', 'image', '', '/uploads/stories/thumb_story_17_1776745639.jpeg', '2026-04-21 04:27:19', '2026-04-22 04:27:19'),
(9, 17, '/uploads/stories/story_17_1776894984.jpeg', 'image', '', '/uploads/stories/thumb_story_17_1776894984.jpeg', '2026-04-22 21:56:24', '2026-04-23 21:56:24'),
(10, 17, '/uploads/stories/story_17_1776894987.jpeg', 'image', '', '/uploads/stories/thumb_story_17_1776894987.jpeg', '2026-04-22 21:56:27', '2026-04-23 21:56:27'),
(11, 17, '/uploads/stories/story_17_1776926868.mov', 'video', 'my movie test', '/uploads/stories/thumb_story_17_1776926868.mov.jpg', '2026-04-23 06:47:48', '2026-04-24 06:47:48');

-- --------------------------------------------------------

--
-- Table structure for table `story_views`
--

CREATE TABLE `story_views` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `viewer_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `bio` text,
  `profile_pic` varchar(255) DEFAULT 'assets/img/default-avatar.svg',
  `rating` float DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `banned` tinyint(1) DEFAULT '0',
  `email_verified` tinyint(1) DEFAULT '0',
  `verification_token` varchar(255) DEFAULT NULL,
  `sport` varchar(255) DEFAULT NULL,
  `position` text,
  `goals` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `city`, `state`, `email`, `password`, `bio`, `profile_pic`, `rating`, `created_at`, `banned`, `email_verified`, `verification_token`, `sport`, `position`, `goals`) VALUES
(1, 'alex', NULL, NULL, NULL, NULL, 'alex@example.com', 'hashed_pw_1', NULL, NULL, 0, '2026-04-12 19:10:06', 0, 0, NULL, NULL, NULL, NULL),
(2, 'jordan', NULL, NULL, NULL, NULL, 'jordan@example.com', 'hashed_pw_2', NULL, NULL, 0, '2026-04-12 19:10:06', 0, 0, NULL, NULL, NULL, NULL),
(3, 'maria', NULL, NULL, NULL, NULL, 'maria@example.com', 'hashed_pw_3', NULL, NULL, 0, '2026-04-12 19:10:06', 0, 0, NULL, NULL, NULL, NULL),
(9, 'testuser', NULL, NULL, NULL, NULL, 'test@example.com', '$2y$12$6kzCHhKLmGIHKQ7EzmtNe.qozCz8HuopZADgIogC99ZfpJW3ikYkS', NULL, NULL, 0, '2026-04-15 19:22:50', 0, 0, NULL, NULL, NULL, NULL),
(11, 'charles_test', NULL, NULL, NULL, NULL, 'charles_test@example.com', '$2y$12$ZEy.sliAv07.sxCaYejO1OuxjvRyZ.VGUL1FcStG776P3Ewi63n2m', NULL, NULL, 0, '2026-04-17 20:50:30', 0, 0, NULL, NULL, NULL, NULL),
(15, 'charles_test2', NULL, NULL, NULL, NULL, 'charles_test2@example.com', '$2y$12$rvw9A7SMZXe4f6Rns3f.dOFx8.pLdteyAWVHlgcc3dX88ctsp8P1u', NULL, NULL, 0, '2026-04-17 20:53:10', 0, 0, NULL, NULL, NULL, NULL),
(17, 'charles_test3', NULL, NULL, NULL, NULL, 'charles_test3@example.com', '$2y$12$Bck6X0lSL7u9DA/uVH4pYuh8ZgtOdUhZurnVtdfUkcTwdTB6V8mym', NULL, NULL, 0, '2026-04-18 18:56:15', 0, 0, NULL, NULL, NULL, NULL),
(60, 'Fletchgirl', 'Beverly', 'Phillips', 'Clearwater', 'FL', 'fletchgirl1@yahoo.com', '$2y$12$zjZRVnBesW/2kDmtAjGQju8qlHxL0CRBu8u18zISFLUsViwswrxhG', 'Im a Kentucky native living in Clearwater Florida  and love the sunshine state', 'uploads/profile_pics/profile_60_1778125181_10a9b963.jpg', 0, '2026-04-30 17:01:03', 0, 1, 'null', 'Football', '', ''),
(105, 'albert', 'albert', 'fletcher', 'Seminole', 'FL', 'albert66fletcher@gmail.com', '$2y$12$aVxGdvRrGxY2sU3KQWv//.ItThPuVNSE6Ct..CUEW7OZlY3kmbUuO', NULL, NULL, 0, '2026-06-04 12:47:14', 0, 1, NULL, NULL, NULL, NULL),
(106, 'altimastr', 'charles', 'fletcher', 'Seminole', 'FL', 'charlesf426@gmail.com', '$2y$12$ZeaPgopxGLXmR/nUsVeDN.HU0qM4cR/gwFgkIhboZNLDsTNmO/us6', NULL, NULL, 0, '2026-06-04 12:48:35', 0, 1, NULL, NULL, NULL, NULL),
(107, 'altimastr1026', 'charles', 'fletcher', 'Seminole', 'FL', 'charlesf426@icloud.com', '$2y$12$LpGjlHYcFGCuTS/A6kRrhuQ0SCJNPBsOvM6cznwYutMtTOurKFxuG', 'professional athlete and computer programmer', 'uploads/profile_pics/profile_107_1780611227_a6ae688c.jpg', 0, '2026-06-04 18:10:54', 0, 1, NULL, 'horse racing', '', 'to get in better shape and finish my project'),
(108, 'jluzdigna@yahoo.com', 'Luz Digna', 'Garferio', 'Key Largo', 'FL', 'jluzdigna@yahoo.com', '$2y$12$sFE8jg/MGg0YWW08/swD5uQU7hkzbtmfwSp.OwceL8rBxI3qlYdZa', NULL, NULL, 0, '2026-06-06 08:19:16', 0, 0, '2afd00370da06b5eff2e9b985a0cf371ee2e936a053dc5b972aecd0e74abe225', NULL, NULL, NULL),
(109, 'jock1', 'charles', 'fletcher', 'Seminole', 'FL', 'fletch426@gmail.com', '$2y$12$TXUHm7w2gNYTNYEqcc63xemebGPpE4xegnv.3JvvgdjmI7pvqWygy', NULL, NULL, 0, '2026-06-22 21:52:17', 0, 0, 'd42a5f0e4a08d5f3501b1301b41684c5b9643756032b986c8b42481b2a5c9edb', NULL, NULL, NULL),
(110, 'ishikawa817', 'Ishikawa', 'Juro', 'Denvar', 'KS', 'ishikawajuro817@gmail.com', '$2y$12$52VIdEEWDkFRP2WHNhp/hO4w5flpcZnG31raZmX917AutW3Iqdtm2', '', '', 0, '2026-07-15 21:57:59', 0, 1, NULL, 'Basket Ball', 'Recuiter', '');

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `badge_id` int(11) DEFAULT NULL,
  `earned_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_badges`
--

INSERT INTO `user_badges` (`id`, `user_id`, `badge_id`, `earned_at`) VALUES
(1, 1, 1, '2026-04-12 19:25:57'),
(2, 2, 3, '2026-04-12 19:25:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_categories`
--

CREATE TABLE `user_categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `dark_mode` tinyint(1) DEFAULT '1',
  `notifications_enabled` tinyint(1) DEFAULT '1',
  `privacy_level` varchar(20) DEFAULT 'public'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `dark_mode`, `notifications_enabled`, `privacy_level`) VALUES
(1, 1, 1, 1, 'public'),
(2, 2, 0, 1, 'public'),
(3, 3, 1, 0, 'public');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `achievement_badges`
--
ALTER TABLE `achievement_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `category_progress`
--
ALTER TABLE `category_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `followers`
--
ALTER TABLE `followers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `follower_id` (`follower_id`),
  ADD KEY `following_id` (`following_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- Indexes for table `highlights`
--
ALTER TABLE `highlights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `thread_id` (`thread_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`message_id`,`user_id`);

--
-- Indexes for table `message_threads`
--
ALTER TABLE `message_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user1_id` (`user1_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `post_images`
--
ALTER TABLE `post_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `post_videos`
--
ALTER TABLE `post_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`);

--
-- Indexes for table `saved_posts`
--
ALTER TABLE `saved_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `story_views`
--
ALTER TABLE `story_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_story_view` (`story_id`,`viewer_id`),
  ADD KEY `viewer_id` (`viewer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `badge_id` (`badge_id`);

--
-- Indexes for table `user_categories`
--
ALTER TABLE `user_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `achievement_badges`
--
ALTER TABLE `achievement_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `admin_roles`
--
ALTER TABLE `admin_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `category_progress`
--
ALTER TABLE `category_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `followers`
--
ALTER TABLE `followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `highlights`
--
ALTER TABLE `highlights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_threads`
--
ALTER TABLE `message_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `post_images`
--
ALTER TABLE `post_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_likes`
--
ALTER TABLE `post_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `post_videos`
--
ALTER TABLE `post_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profiles`
--
ALTER TABLE `profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saved_posts`
--
ALTER TABLE `saved_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stories`
--
ALTER TABLE `stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `story_views`
--
ALTER TABLE `story_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_categories`
--
ALTER TABLE `user_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `activity_log_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD CONSTRAINT `admin_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `badges`
--
ALTER TABLE `badges`
  ADD CONSTRAINT `badges_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `category_progress`
--
ALTER TABLE `category_progress`
  ADD CONSTRAINT `category_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `category_progress_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`),
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`),
  ADD CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `followers`
--
ALTER TABLE `followers`
  ADD CONSTRAINT `followers_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `followers_ibfk_2` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `highlights`
--
ALTER TABLE `highlights`
  ADD CONSTRAINT `highlights_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `highlights_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_threads`
--
ALTER TABLE `message_threads`
  ADD CONSTRAINT `message_threads_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `message_threads_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `post_images`
--
ALTER TABLE `post_images`
  ADD CONSTRAINT `post_images_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`),
  ADD CONSTRAINT `post_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `post_videos`
--
ALTER TABLE `post_videos`
  ADD CONSTRAINT `post_videos_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `profiles`
--
ALTER TABLE `profiles`
  ADD CONSTRAINT `profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `saved_posts`
--
ALTER TABLE `saved_posts`
  ADD CONSTRAINT `saved_posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `saved_posts_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`);

--
-- Constraints for table `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `stories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `story_views`
--
ALTER TABLE `story_views`
  ADD CONSTRAINT `story_views_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `story_views_ibfk_2` FOREIGN KEY (`viewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`);

--
-- Constraints for table `user_categories`
--
ALTER TABLE `user_categories`
  ADD CONSTRAINT `user_categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
