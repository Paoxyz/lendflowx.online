-- Create database
CREATE DATABASE IF NOT EXISTS loan_track_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE loan_track_db;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(50),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','borrower') NOT NULL DEFAULT 'borrower',
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    id_path VARCHAR(255),
    proof_path VARCHAR(255),
    selfie_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ADMINS TABLE
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    fullname VARCHAR(255) NOT NULL
);

-- LOANS TABLE
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_id VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    term INT NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    loan_type VARCHAR(100),
    purpose TEXT,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- LOAN SCHEDULE TABLE
CREATE TABLE IF NOT EXISTS loan_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    installment_no INT NOT NULL,
    due_date DATE NOT NULL,
    total_due DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending',
    FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- PAYMENTS TABLE
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Completed',
    FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- DEFAULT ADMIN ACCOUNT
INSERT INTO admins (username, password_hash, fullname)
VALUES (
    'admin',
    '$2y$10$wH6jK6Gk1Yj4S2n5lXQfduGkQOZ7hQK7q8Q6Yn17b6lQnYJQf8Yz2',
    'System Administrator'
);
