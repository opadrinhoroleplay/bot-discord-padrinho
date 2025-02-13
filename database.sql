-- Core Tables
CREATE TABLE `discord_members` (
  `id` VARCHAR(20) PRIMARY KEY,
  `username` VARCHAR(32) NOT NULL,
  `last_online` DATETIME,
  `last_active` DATETIME,
  `admin_level` TINYINT DEFAULT NULL,
  `active` BOOLEAN DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invite System
CREATE TABLE `invites` (
  `code` VARCHAR(10) PRIMARY KEY,
  `inviter_id` VARCHAR(20) NOT NULL,
  `inviter_slug` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`inviter_id`) REFERENCES `discord_members`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `invites_used` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` VARCHAR(20) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`member_id`) REFERENCES `discord_members`(`id`),
  FOREIGN KEY (`code`) REFERENCES `invites`(`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity Tracking
CREATE TABLE `discord_counters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(20) NOT NULL,
  `count` INT DEFAULT 0,
  `day` DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `discord_afk` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` VARCHAR(20) NOT NULL,
  `time_set` DATETIME NOT NULL,
  `time_unset` DATETIME DEFAULT NULL,
  `reason` TEXT,
  FOREIGN KEY (`member_id`) REFERENCES `discord_members`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Game Sessions
CREATE TABLE `discord_games` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `discord_member_game_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` VARCHAR(20) NOT NULL,
  `game_id` INT NOT NULL,
  `game_state` VARCHAR(100),
  `start` DATETIME NOT NULL,
  `end` DATETIME DEFAULT NULL,
  FOREIGN KEY (`member_id`) REFERENCES `discord_members`(`id`),
  FOREIGN KEY (`game_id`) REFERENCES `discord_games`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin Features
CREATE TABLE `discord_settings` (
  `name` VARCHAR(50) PRIMARY KEY,
  `value` TEXT,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes
CREATE INDEX idx_member_activity ON `discord_members` (`last_active`);
CREATE INDEX idx_afk_times ON `discord_afk` (`time_set`, `time_unset`);
CREATE INDEX idx_game_sessions ON `discord_member_game_sessions` (`start`, `end`); 