-- ===================================================
-- ShareCab - Database Schema
-- Web Programming Lab Project
-- Compatible with MySQL / MariaDB (XAMPP)
-- ===================================================

CREATE DATABASE IF NOT EXISTS `sharecab` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sharecab`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `ride_drivers`;
DROP TABLE IF EXISTS `ride_members`;
DROP TABLE IF EXISTS `rides`;
DROP TABLE IF EXISTS `drivers`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------
-- 1. Table: users (Passengers / Organizers)
-- (Existing sample users deleted as requested)
-- ---------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `is_admin` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 2. Table: drivers (With dedicated Login & Registration)
-- ---------------------------------------------------
CREATE TABLE `drivers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `vehicle` VARCHAR(100) NOT NULL,
    `vehicle_number` VARCHAR(50) NOT NULL UNIQUE,
    `status` ENUM('available', 'on_trip', 'offline') DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 3. Table: rides
-- ---------------------------------------------------
CREATE TABLE `rides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `created_by` INT NOT NULL,
    `source` VARCHAR(255) NOT NULL,
    `destination` VARCHAR(255) NOT NULL,
    `ride_date` DATE NOT NULL,
    `ride_time` TIME NOT NULL,
    `max_seats` INT NOT NULL DEFAULT 3,
    `fare` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('open', 'full', 'completed', 'cancelled') DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rides_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 4. Table: ride_members
-- ---------------------------------------------------
CREATE TABLE `ride_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ride_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_ride_passenger` (`ride_id`, `user_id`),
    CONSTRAINT `fk_ride_members_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ride_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 5. Table: ride_drivers
-- ---------------------------------------------------
CREATE TABLE `ride_drivers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ride_id` INT NOT NULL UNIQUE,
    `driver_id` INT NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ride_drivers_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ride_drivers_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 6. Table: messages (Supports Passenger & Driver Chat)
-- ---------------------------------------------------
CREATE TABLE `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ride_id` INT NOT NULL,
    `user_id` INT NULL,
    `driver_id` INT NULL,
    `sender_type` ENUM('user', 'driver') DEFAULT 'user',
    `message` TEXT NOT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_messages_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_messages_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 7. Table: payments (Fare Split & Settlements)
-- ---------------------------------------------------
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ride_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('upi', 'card', 'cash') NOT NULL,
    `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
    `status` ENUM('pending', 'completed') DEFAULT 'completed',
    `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- Sample Seed Drivers (password: "password123")
-- ===================================================
INSERT INTO `drivers` (`id`, `name`, `email`, `phone`, `password`, `vehicle`, `vehicle_number`, `status`) VALUES
(1, 'Vikram Singh', 'driver1@sharecab.com', '9895012345', '$2y$10$qV9eFm7mXQfO7UqM4X6bjeR7gY4J1u.oYv3f/8A7q.7c21.8zLd4O', 'Maruti Suzuki WagonR', 'KL 07 AB 1234', 'available'),
(2, 'Abdul Kareem', 'driver2@sharecab.com', '9895098765', '$2y$10$qV9eFm7mXQfO7UqM4X6bjeR7gY4J1u.oYv3f/8A7q.7c21.8zLd4O', 'Toyota Etios', 'KL 07 CD 5678', 'available'),
(3, 'Suresh Babu', 'driver3@sharecab.com', '9847011223', '$2y$10$qV9eFm7mXQfO7UqM4X6bjeR7gY4J1u.oYv3f/8A7q.7c21.8zLd4O', 'Hyundai Aura', 'KL 01 EF 9012', 'available');

