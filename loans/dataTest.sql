-- 1. Create a Test Lender (Password: 123456)
INSERT INTO lenders (id, name, email, phone, password, status, created_at) VALUES 
(1, 'Test Lender', 'lender@test.com', '09123456789', '$2y$10$abcdefghijklmnopqrstuv', 'Approved', NOW());

-- 2. Create Test Borrowers
INSERT INTO users (id, name, email, phone, password, role, status, created_at) VALUES 
(101, 'Juan Dela Cruz', 'juan@test.com', '09111111111', 'pass', 'borrower', 'Approved', NOW()),
(102, 'Maria Clara', 'maria@test.com', '09222222222', 'pass', 'borrower', 'Approved', NOW());

-- 3. Insert Loans to Test KPIs
-- Loan A: Active (Counts towards everything)
INSERT INTO loans (lender_id, user_id, loan_id, amount, interest_rate, status, created_at) 
VALUES (1, 101, 'LN-TEST-A', 1000.00, 10.00, 'Active', NOW());

-- Loan B: Completed (Counts towards Invested & Interest history)
INSERT INTO loans (lender_id, user_id, loan_id, amount, interest_rate, status, created_at) 
VALUES (1, 102, 'LN-TEST-B', 2000.00, 5.00, 'Completed', DATE_SUB(NOW(), INTERVAL 1 MONTH));

-- Loan C: Overdue (Counts towards Invested, Interest, and Overdue Alert)
INSERT INTO loans (lender_id, user_id, loan_id, amount, interest_rate, status, created_at) 
VALUES (1, 101, 'LN-TEST-C', 500.00, 20.00, 'Overdue', DATE_SUB(NOW(), INTERVAL 2 MONTH));

-- Loan D: Pending (SHOULD BE IGNORED by KPIs)
INSERT INTO loans (lender_id, user_id, loan_id, amount, interest_rate, status, created_at) 
VALUES (1, 102, 'LN-TEST-D', 5000.00, 10.00, 'Pending', NOW());

-- Loan E: Active (Small loan to test Borrower Count distinctness)
INSERT INTO loans (lender_id, user_id, loan_id, amount, interest_rate, status, created_at) 
VALUES (1, 102, 'LN-TEST-E', 100.00, 10.00, 'Active', NOW());
```csv

### 3. How to Verify
1.  Run the SQL above.
2.  Go to your **Lender Login** page.
3.  Log in with:
    * Email: `lender@test.com`
    * Password: *You will need to reset it using your Forgot Password feature since the hash above is fake, OR register a new lender and update the `lender_id` in the SQL above to match your new lender's ID.*
4.  Check the dashboard cards.

If you see **$3,600** Invested, **$310** Interest, **2** Active Borrowers, and **1** Overdue, your code is 100% correct.