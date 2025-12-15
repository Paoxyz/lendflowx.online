-- 1. Initialize Database
CREATE DATABASE IF NOT EXISTS loan_track_db;
USE loan_track_db;

-- ---------------------------------------------------------
-- CORE ENTITIES (Independent Tables)
-- ---------------------------------------------------------

-- 2. ADMINS
CREATE TABLE admins (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    password_hash VARCHAR(255),
    fullname VARCHAR(255)
);

-- 3. USERS (Borrowers)
CREATE TABLE users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(50),
    password VARCHAR(255),
    role ENUM('admin','borrower') DEFAULT 'borrower',
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    id_path VARCHAR(255),
    proof_path VARCHAR(255),
    selfie_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. LENDERS (Investors)
CREATE TABLE lenders (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(50),
    password VARCHAR(255) NOT NULL,
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- LOAN SYSTEM (Dependent on Users & Lenders)
-- ---------------------------------------------------------

-- 5. LOANS
-- Connects a Borrower (user_id) with a Lender (lender_id)
CREATE TABLE loans (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11),
    lender_id INT(11) NULL,
    loan_id VARCHAR(30),
    amount DECIMAL(12,2),
    term INT(11),
    interest_rate DECIMAL(5,2),
    loan_type VARCHAR(100),
    purpose TEXT,
    status ENUM('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (lender_id) REFERENCES lenders(id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- 6. LOAN SCHEDULE
-- Connects to a specific Loan
CREATE TABLE loan_schedule (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    loan_id INT(11),
    installment_no INT(11),
    due_date DATE,
    total_due DECIMAL(10,2),
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('Pending','Paid','Partially Paid') DEFAULT 'Pending',
    
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 7. PAYMENTS
-- Connects to a specific Loan
CREATE TABLE payments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    loan_id INT(11),
    amount_paid DECIMAL(12,2),
    payment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20),
    
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ---------------------------------------------------------
-- SYSTEM UTILITIES
-- ---------------------------------------------------------

-- 8. NOTIFICATIONS
-- Can be sent to either a User OR a Lender (Nullable FKs)
CREATE TABLE notifications (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NULL,
    lender_id INT(11) NULL,
    message VARCHAR(255),
    type ENUM('admin','borrower','lender'),
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lender_id) REFERENCES lenders(id) ON DELETE CASCADE
);

-- 9. PASSWORD RESETS
-- Independent table for security tokens
CREATE TABLE password_resets (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    user_type ENUM('borrower', 'lender') NOT NULL, 
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- FINANCIAL TRACKING (Crowdfunding Logic)
-- ---------------------------------------------------------

-- 10. LOAN FUNDING
-- Records exactly which Lender funded which Loan
CREATE TABLE loan_funding (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    loan_id INT(11) NOT NULL,
    lender_id INT(11) NOT NULL,
    amount_funded DECIMAL(12,2) NOT NULL,
    funded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (lender_id) REFERENCES lenders(id) ON DELETE CASCADE
);

-- 11. LENDER PAYOUTS
-- Records payments back to the Lender
CREATE TABLE lender_payouts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    lender_id INT(11) NOT NULL,
    loan_id INT(11) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payout_date DATE NOT NULL,
    
    FOREIGN KEY (lender_id) REFERENCES lenders(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);