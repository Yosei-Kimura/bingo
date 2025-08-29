-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: mysql325.phy.lolipop.lan
-- 生成日時: 2025 年 8 月 29 日 12:24
-- サーバのバージョン： 8.0.35
-- PHP のバージョン: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `LAA0956269-bingo`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `bingo_achievements`
--

CREATE TABLE `bingo_achievements` (
  `id` int NOT NULL,
  `card_id` int DEFAULT NULL,
  `achievement_type` enum('line_horizontal','line_vertical','line_diagonal','full_house') COLLATE utf8mb4_general_ci NOT NULL,
  `achieved_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `winning_numbers` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `bingo_numbers`
--

CREATE TABLE `bingo_numbers` (
  `id` int NOT NULL,
  `number` int NOT NULL,
  `called_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `bingo_numbers`
--

INSERT INTO `bingo_numbers` (`id`, `number`, `called_at`) VALUES
(22, 13, '2025-08-29 03:12:37'),
(23, 19, '2025-08-29 03:13:01'),
(24, 57, '2025-08-29 03:13:03'),
(25, 69, '2025-08-29 03:13:04'),
(26, 63, '2025-08-29 03:13:17'),
(27, 56, '2025-08-29 03:13:18'),
(28, 66, '2025-08-29 03:13:18'),
(29, 72, '2025-08-29 03:13:20'),
(30, 62, '2025-08-29 03:13:25'),
(31, 15, '2025-08-28 16:25:51'),
(32, 32, '2025-08-28 16:25:51'),
(33, 7, '2025-08-28 16:25:51'),
(34, 30, '2025-08-29 03:17:33');

-- --------------------------------------------------------

--
-- テーブルの構造 `bingo_records`
--

CREATE TABLE `bingo_records` (
  `id` int NOT NULL,
  `bingo_count` int NOT NULL DEFAULT '0',
  `recorded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_general_ci,
  `game_session` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'anniversary2025'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `bingo_records`
--

INSERT INTO `bingo_records` (`id`, `bingo_count`, `recorded_at`, `notes`, `game_session`) VALUES
(1, 0, '2025-08-28 17:03:12', '', 'anniversary2025');

-- --------------------------------------------------------

--
-- テーブルの構造 `bingo_winners`
--

CREATE TABLE `bingo_winners` (
  `id` int NOT NULL,
  `card_id` int DEFAULT NULL,
  `win_type` enum('line','full') COLLATE utf8mb4_general_ci NOT NULL,
  `completed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `online_bingo_cards`
--

CREATE TABLE `online_bingo_cards` (
  `id` int NOT NULL,
  `password` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `card_data` json NOT NULL,
  `participant_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_access` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `online_bingo_cards`
--

INSERT INTO `online_bingo_cards` (`id`, `password`, `card_data`, `participant_name`, `created_at`, `last_access`, `is_active`) VALUES
(1, '80195600', '[[13, 18, 33, 58, 62], [9, 19, 39, 49, 66], [4, 26, 0, 59, 72], [15, 30, 35, 57, 63], [2, 27, 32, 47, 69]]', '', '2025-08-29 02:42:18', '2025-08-29 03:22:18', 1),
(2, '16991651', '[[1, 28, 35, 47, 72], [15, 25, 45, 50, 65], [3, 27, 0, 46, 68], [7, 29, 34, 60, 66], [14, 19, 33, 58, 61]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1),
(3, '80122836', '[[1, 29, 36, 54, 67], [7, 28, 44, 51, 72], [12, 17, 0, 50, 66], [10, 23, 42, 48, 70], [6, 25, 33, 46, 69]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1),
(4, '86732667', '[[10, 24, 35, 47, 75], [8, 16, 44, 59, 73], [14, 22, 0, 57, 66], [3, 27, 32, 55, 61], [9, 19, 34, 48, 62]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1),
(5, '85332042', '[[10, 19, 38, 56, 73], [13, 30, 32, 48, 65], [4, 20, 0, 57, 68], [11, 23, 43, 52, 64], [6, 29, 45, 53, 63]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1),
(6, '40808890', '[[5, 22, 35, 48, 71], [14, 25, 41, 54, 68], [1, 24, 0, 53, 72], [4, 27, 33, 46, 73], [6, 26, 45, 59, 67]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1),
(7, '05208228', '[[5, 29, 41, 46, 65], [3, 27, 45, 56, 70], [14, 23, 0, 47, 75], [11, 21, 43, 53, 69], [8, 17, 31, 50, 61]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1),
(8, '93183585', '[[12, 22, 32, 49, 65], [10, 28, 36, 57, 75], [9, 30, 0, 50, 71], [11, 26, 38, 52, 63], [7, 16, 34, 56, 74]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1),
(9, '39315202', '[[10, 25, 38, 53, 65], [3, 23, 32, 60, 63], [6, 21, 0, 55, 64], [15, 26, 34, 59, 72], [8, 22, 37, 51, 75]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1),
(10, '91934192', '[[10, 23, 33, 60, 62], [9, 22, 38, 57, 63], [3, 16, 0, 53, 71], [13, 24, 36, 56, 64], [15, 28, 34, 48, 66]]', NULL, '2025-08-29 02:42:18', '2025-08-29 02:42:18', 1);

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `bingo_achievements`
--
ALTER TABLE `bingo_achievements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `card_id` (`card_id`);

--
-- テーブルのインデックス `bingo_numbers`
--
ALTER TABLE `bingo_numbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_number` (`number`);

--
-- テーブルのインデックス `bingo_records`
--
ALTER TABLE `bingo_records`
  ADD PRIMARY KEY (`id`);

--
-- テーブルのインデックス `bingo_winners`
--
ALTER TABLE `bingo_winners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `card_id` (`card_id`);

--
-- テーブルのインデックス `online_bingo_cards`
--
ALTER TABLE `online_bingo_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `password` (`password`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `bingo_achievements`
--
ALTER TABLE `bingo_achievements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `bingo_numbers`
--
ALTER TABLE `bingo_numbers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- テーブルの AUTO_INCREMENT `bingo_records`
--
ALTER TABLE `bingo_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- テーブルの AUTO_INCREMENT `bingo_winners`
--
ALTER TABLE `bingo_winners`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `online_bingo_cards`
--
ALTER TABLE `online_bingo_cards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `bingo_achievements`
--
ALTER TABLE `bingo_achievements`
  ADD CONSTRAINT `bingo_achievements_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `online_bingo_cards` (`id`);

--
-- テーブルの制約 `bingo_winners`
--
ALTER TABLE `bingo_winners`
  ADD CONSTRAINT `bingo_winners_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `online_bingo_cards` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
