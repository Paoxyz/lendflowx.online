-- Create Database
CREATE DATABASE IF NOT EXISTS `loan_track_db`;
USE `loan_track_db`;

-- --------------------------------------------------------

-- 1. TABLE: admins
CREATE TABLE `admins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 2. TABLE: users (Borrowers)
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'borrower') NOT NULL DEFAULT 'borrower',
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `id_path` VARCHAR(255) DEFAULT NULL,
  `proof_path` VARCHAR(255) DEFAULT NULL,
  `selfie_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 3. TABLE: lenders
CREATE TABLE `lenders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `address` TEXT NOT NULL,
  `tin` VARCHAR(50) DEFAULT NULL,
  `source_of_funds` VARCHAR(100) DEFAULT NULL,
  `bank_name` VARCHAR(100) DEFAULT NULL,
  `bank_account` VARCHAR(100) DEFAULT NULL,
  `id_file` VARCHAR(255) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 4. TABLE: loans
CREATE TABLE `loans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `lender_id` INT(11) DEFAULT NULL,
  `loan_id` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `term` INT(11) NOT NULL,
  `interest_rate` DECIMAL(5,2) NOT NULL,
  `loan_type` VARCHAR(100) NOT NULL,
  `purpose` TEXT NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected', 'Completed') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `lender_id` (`lender_id`),
  CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`lender_id`) REFERENCES `lenders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 5. TABLE: loan_schedule
CREATE TABLE `loan_schedule` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `loan_id` INT(11) NOT NULL,
  `installment_no` INT(11) NOT NULL,
  `due_date` DATE NOT NULL,
  `total_due` DECIMAL(10,2) NOT NULL,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Pending', 'Paid', 'Partially Paid') NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`),
  CONSTRAINT `loan_schedule_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 6. TABLE: payments
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `loan_id` INT(11) NOT NULL,
  `amount_paid` DECIMAL(12,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 7. TABLE: notifications
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `lender_id` INT(11) DEFAULT NULL,
  `message` VARCHAR(255) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `lender_id` (`lender_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`lender_id`) REFERENCES `lenders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 8. TABLE: password_resets
CREATE TABLE `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `user_type` ENUM('borrower', 'lender') NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 9. TABLE: loan_funding
CREATE TABLE `loan_funding` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `loan_id` INT(11) NOT NULL,
  `lender_id` INT(11) NOT NULL,
  `amount_funded` DECIMAL(12,2) NOT NULL,
  `funded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`),
  KEY `lender_id` (`lender_id`),
  CONSTRAINT `loan_funding_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `loan_funding_ibfk_2` FOREIGN KEY (`lender_id`) REFERENCES `lenders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 10. TABLE: lender_payouts
CREATE TABLE `lender_payouts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lender_id` INT(11) NOT NULL,
  `loan_id` INT(11) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payout_date` DATE NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lender_id` (`lender_id`),
  KEY `loan_id` (`loan_id`),
  CONSTRAINT `lender_payouts_ibfk_1` FOREIGN KEY (`lender_id`) REFERENCES `lenders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `lender_payouts_ibfk_2` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;