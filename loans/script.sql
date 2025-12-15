-- Create database
CREATE DATABASE IF NOT EXISTS loan_track_db;
USE loan_track_db;

-- USERS TABLE
CREATE TABLE users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(50),
    password VARCHAR(255),
    role ENUM('admin','borrower'),
    status ENUM('Pending','Approved','Rejected'),
    id_path VARCHAR(255),
    proof_path VARCHAR(255),
    selfie_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ADMINS TABLE
CREATE TABLE admins (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    password_hash VARCHAR(255),
    fullname VARCHAR(255)
);

-- LOANS TABLE
CREATE TABLE loans (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11),
    loan_id VARCHAR(30),
    amount DECIMAL(12,2),
    term INT(11),
    interest_rate DECIMAL(5,2),
    loan_type VARCHAR(100),
    purpose TEXT,
    status ENUM('Pending','Approved','Rejected'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- LOAN_SCHEDULE TABLE
CREATE TABLE loan_schedule (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    loan_id INT(11),
    installment_no INT(11),
    due_date DATE,
    total_due DECIMAL(10,2),
    paid_amount DECIMAL(10,2),
    status ENUM('Pending','Paid'),
    FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- PAYMENTS TABLE
CREATE TABLE payments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    loan_id INT(11),
    amount_paid DECIMAL(12,2),
    payment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20),
    FOREIGN KEY (loan_id) REFERENCES loans(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message VARCHAR(255),
    type ENUM('admin','borrower'),
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE lenders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(50),
    password VARCHAR(255) NOT NULL,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE loan_funding (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    lender_id INT NOT NULL,
    amount_funded DECIMAL(12,2) NOT NULL,
    funded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (lender_id) REFERENCES lenders(id) ON DELETE CASCADE
);


CREATE TABLE lender_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lender_id INT NOT NULL,
    loan_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payout_date DATE NOT NULL,
    FOREIGN KEY (lender_id) REFERENCES lenders(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);
 
ALTER TABLE loans ADD lender_id INT NULL AFTER user_id;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);