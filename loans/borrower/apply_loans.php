<?php
session_start();
require_once '../db.php';

// Only borrowers can access
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'borrower') {
    header('Location: borrowerLogin.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['user_name'] ?? 'Borrower';

$loan_error = '';
$loan_success = '';

// Maximum loan allowed
$max_amount = 250000;

// Fetch active approved loan check
$stmt = $pdo->prepare("
    SELECT l.*, COALESCE(SUM(s.total_due - IFNULL(s.paid_amount,0)),0) AS remaining
    FROM loans l
    LEFT JOIN loan_schedule s ON l.id = s.loan_id
    WHERE l.user_id = ? AND l.status IN ('Pending','Approved')
    GROUP BY l.id
    HAVING remaining > 0
");
$stmt->execute([$user_id]);
$active_loan = $stmt->fetch();

// Fetch Approved Lenders
$stmt = $pdo->query("SELECT id, name FROM lenders WHERE status = 'Approved' ORDER BY name ASC");
$available_lenders = $stmt->fetchAll();

// Handle loan submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$active_loan) {
    $amount = floatval($_POST['amount'] ?? 0);
    $term = intval($_POST['term'] ?? 0);
    $type = trim($_POST['loan_type'] ?? '');
    $lender_id = intval($_POST['lender_id'] ?? 0);

    $reason = trim($_POST['reason'] ?? '');
    $account_info = trim($_POST['account_info'] ?? '');
    $full_purpose = "Reason: " . $reason . " | Disbursement: " . $account_info;

    // Interest Logic
    $interest = 5;
    if ($term > 12) $interest = 7;
    if ($term > 24) $interest = 9;
    if ($term > 36) $interest = 11;
    if ($term > 48) $interest = 13;

    $errors = [];
    if ($amount <= 0) $errors[] = "Enter a valid loan amount.";
    if ($amount > $max_amount) $errors[] = "Maximum loan amount is ₱" . number_format($max_amount) . ".";
    if ($term <= 0) $errors[] = "Select a valid payment term.";
    if ($type === '') $errors[] = "Select a loan type.";
    if ($lender_id <= 0) $errors[] = "Please select a Lender.";
    if (empty($reason)) $errors[] = "Please tell the lender why you need this loan.";
    if (empty($account_info)) $errors[] = "Please provide an account to receive the funds.";

    if (!$errors) {
        $loan_id = 'LN-' . strtoupper(substr(md5(time() . $user_id), 0, 6));

        // Insert loan
        $stmt = $pdo->prepare("INSERT INTO loans (user_id, lender_id, loan_id, amount, term, interest_rate, loan_type, purpose, status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        $ok = $stmt->execute([$user_id, $lender_id, $loan_id, $amount, $term, $interest, $type, $full_purpose]);

        if ($ok) {
            $loan_db_id = $pdo->lastInsertId();

            // Schedule Generation
            $total_interest = $amount * ($interest / 100);
            $total_payable = $amount + $total_interest;
            $monthly_payment = $total_payable / $term;

            for ($i = 1; $i <= $term; $i++) {
                $due_date = date('Y-m-d', strtotime("+$i month"));
                $stmt2 = $pdo->prepare("INSERT INTO loan_schedule (loan_id, installment_no, due_date, total_due, paid_amount, status)
                                        VALUES (?, ?, ?, ?, 0, 'Pending')");
                $stmt2->execute([$loan_db_id, $i, $due_date, $monthly_payment]);
            }

            // Notify Lender
            $stmt = $pdo->prepare("INSERT INTO notifications (lender_id, message, type, created_at) VALUES (?, ?, 'lender', NOW())");
            $msg = "New application ($loan_id) from $username for ₱" . number_format($amount);
            $stmt->execute([$lender_id, $msg]);

            $loan_success = "Application submitted! Waiting for lender approval.";
            $_SESSION['message'] = ['text' => $loan_success, 'type' => 'success'];
            header("Location: borrowerDashboard.php");
            exit;
        } else {
            $loan_error = "Database error. Please try again.";
        }
    } else {
        $loan_error = implode("<br>", $errors);
    }
}

// Fetch history
$stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$loans = $stmt->fetchAll();

$loanTypes = ['Personal', 'Business', 'Education', 'Emergency', 'Medical', 'Debt Consolidation'];
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"> <title>Apply Loan | LoanTracker</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
  --primary: #dc2626;
  --primary-dark: #b91c1c;
  --primary-soft: rgba(220, 38, 38, 0.1);
  --primary-gradient: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
  --secondary: #64748b;
  --bg-body: #f1f5f9;
  --card-bg: #ffffff;
  --text-main: #0f172a;
  --text-muted: #64748b;
  --border-color: #e2e8f0;
  --sidebar-width: 260px;
  --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

[data-bs-theme="dark"] {
  --bg-body: #0f172a;
  --card-bg: #1e293b;
  --text-main: #f8fafc;
  --text-muted: #94a3b8;
  --border-color: #334155;
  --shadow-sm: none;
  --shadow-md: none;
}

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background-color: var(--bg-body);
  color: var(--text-main);
  padding-top: 70px;
  min-height: 100vh;
  overflow-x: hidden;
}

/* NAVBAR */
.navbar {
  background-color: var(--card-bg);
  border-bottom: 1px solid var(--border-color);
  height: 70px;
  z-index: 1050;
}
.navbar-brand {
  font-weight: 800;
  color: var(--primary);
  font-size: 1.4rem;
  letter-spacing: -0.5px;
}

/* SIDEBAR */
.sidebar {
    position: fixed; top: 70px; left: 0; bottom: 0;
    width: var(--sidebar-width);
    background: var(--card-bg); 
    border-right: 1px solid var(--border-color);
    /* Changed padding to ensure footer sticks */
    padding: 24px 16px; 
    z-index: 1040;
    transition: transform 0.3s ease-in-out;
    display: flex;
    flex-direction: column;
}
.sidebar-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 1030; display: none;
}
.sidebar-overlay.show { display: block; }
.main-wrapper { 
    width: 100%; padding: 20px; 
    transition: margin-left 0.3s ease-in-out; 
}

/* Sidebar Links */
.nav-link {
    color: var(--text-muted); font-weight: 600; padding: 12px 20px;
    margin: 4px 0; border-radius: 8px; transition: 0.2s;
}
.nav-link:hover { color: var(--primary); background: var(--primary-soft); }
.nav-link.active { background: var(--primary-gradient); color: white; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
.nav-link.active i { color: white !important; }

/* Responsive Logic */
@media (min-width: 992px) {
    .sidebar { transform: translateX(0); }
    .main-wrapper { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); }
    .sidebar-toggle { display: none; }
}
@media (max-width: 991.98px) {
    .sidebar { transform: translateX(-100%); top: 0; padding-top: 20px; } /* Reset top for mobile slide-out */
    .sidebar.active { transform: translateX(0); }
    .main-wrapper { margin-left: 0; width: 100%; }
}

/* CONTENT STYLES */
.content-card { 
    background: var(--card-bg); 
    border: 1px solid var(--border-color); 
    border-radius: 20px; 
    padding: 30px; 
    box-shadow: var(--shadow-sm); 
    max-width: 100%;
}
.card-title { font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; font-size: 1.1rem; }

/* Form Elements */
.form-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

/* FIX: Input Styles */
.form-control, .form-select { 
    padding: 12px 15px; 
    border-radius: 10px; 
    border: 1px solid var(--border-color); 
    background-color: var(--bg-body); 
    color: var(--text-main); 
    font-size: 16px; 
    width: 100%;
}
.form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }

.amount-input-group .input-group-text { background: var(--bg-body); border: 1px solid var(--border-color); border-right: none; color: var(--text-main); font-weight: bold; }
.amount-input-group .form-control { border-left: none; font-size: 1.5rem; font-weight: 700; color: var(--primary); }
.calc-box { background: var(--bg-body); border: 1px dashed var(--border-color); border-radius: 12px; padding: 20px; }
.calc-label { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px; text-transform: uppercase; display: block; margin-bottom: 4px; }
.calc-value { font-size: 1.1rem; font-weight: 800; color: var(--text-main); border: none; background: transparent; padding: 0; width: 100%; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; }
.calc-value.large { font-size: 1.5rem; color: var(--primary); }
.badge-custom { padding: 5px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }

option { max-width: 90vw; overflow: hidden; text-overflow: ellipsis; }
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid px-3 px-lg-4"> 
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link text-primary p-0 d-lg-none" id="sidebarToggle">
            <i class="fas fa-bars fs-3"></i>
        </button>
        <a class="navbar-brand" href="#">
          <i class="fas fa-wallet me-2"></i>LoanTracker
        </a>
    </div>
    
    <div class="d-flex align-items-center gap-3">
      <button id="themeToggle" class="btn btn-link text-muted p-1 fs-5 border-0"><i class="fas fa-moon"></i></button>
      
      <div class="dropdown">
        <button class="btn btn-light rounded-pill py-1 px-2 fw-bold d-flex align-items-center gap-2 border shadow-sm" type="button" data-bs-toggle="dropdown">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 14px;">
                <?= strtoupper(substr($username, 0, 1)) ?>
            </div>
            <span class="d-none d-md-block small"><?= htmlspecialchars(explode(' ', $username)[0]) ?></span>
            <i class="fas fa-chevron-down text-xs text-muted"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
            <li><a class="dropdown-item small text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<aside class="sidebar" id="sidebar">
    <div class="d-flex justify-content-between align-items-center mb-4 d-lg-none">
        <div class="fw-bold text-primary fs-4">Menu</div>
        <button class="btn btn-light btn-sm" id="closeSidebar"><i class="fas fa-times"></i></button>
    </div>

    <div class="px-2 pb-3 d-none d-lg-block">
        <h6 class="text-muted text-xs fw-bold text-uppercase opacity-75">Main Menu</h6>
    </div>
    
    <nav class="nav flex-column gap-1">
        <a href="borrowerDashboard.php" class="nav-link d-flex align-items-center gap-3"><i class="fas fa-th-large w-5"></i> Dashboard</a>
        <a href="#" class="nav-link active d-flex align-items-center gap-3"><i class="fas fa-plus-circle w-5"></i> Apply Loan</a>
        <a href="pay_loan.php" class="nav-link d-flex align-items-center gap-3"><i class="fas fa-money-bill-wave w-5"></i> Pay Loan</a>
    </nav>

    <div class="mt-auto pt-4 border-top">
        <button class="nav-link text-danger w-100 text-start border-0 bg-transparent d-flex align-items-center gap-3" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="fas fa-sign-out-alt w-5"></i> Sign Out
        </button>
    </div>
</aside>

<div class="main-wrapper">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-12">
            
            <div class="text-center mb-5 mt-3">
                <h2 class="fw-bold mb-1">New Application</h2>
                <p class="text-muted">Fill in the details to request funding.</p>
            </div>

            <?php if($loan_error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4"><i class="fas fa-exclamation-circle me-2"></i> <?= $loan_error ?></div>
            <?php endif; ?>
            
            <?php if($active_loan): ?>
                <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center p-3">
                    <i class="fas fa-lock me-3 fs-4 text-warning"></i>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Application Locked</h6>
                        <small class="text-muted">You have an active loan. Please settle it before applying again.</small>
                    </div>
                </div>
            <?php endif; ?>

            <div class="content-card position-relative mb-5">
                <?php if($active_loan): ?>
                    <div class="position-absolute top-0 start-0 w-100 h-100 bg-body opacity-50 z-1 rounded-4"></div>
                <?php endif; ?>

                <div class="card-title pb-3 border-bottom border-light">
                    <div class="bg-primary-soft rounded-circle d-flex align-items-center justify-content-center text-primary" style="width:42px; height:42px;"><i class="fas fa-file-contract"></i></div>
                    <div>
                        <div class="fw-bold text-main">Loan Terms</div>
                        <small class="text-muted fw-normal d-block" style="font-size: 0.75rem;">Set your preferred amount and duration</small>
                    </div>
                </div>

                <form method="post" id="loanForm">
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Lender</label>
                            <select name="lender_id" class="form-select" required>
                                <option value="">-- Choose Investor --</option>
                                <?php foreach($available_lenders as $lender): 
                                    $displayName = strlen($lender['name']) > 25 ? substr($lender['name'], 0, 22) . '...' : $lender['name'];
                                ?>
                                    <option value="<?= $lender['id'] ?>"><?= htmlspecialchars($displayName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Loan Type</label>
                            <select name="loan_type" class="form-select" required>
                                <?php foreach($loanTypes as $lt): ?><option value="<?= $lt ?>"><?= $lt ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label class="form-label">Amount</label>
                            <div class="input-group amount-input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="amount" id="amountInput" class="form-control" min="1000" max="250000" step="100" placeholder="0.00" required>
                            </div>
                            <small class="text-muted mt-1 d-block">Max allowed: ₱250,000</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Repayment Term</label>
                        <select name="term" class="form-select" id="termSelect" required>
                            <option value="3" selected>3 Months</option>
                            <?php for ($i = 6; $i <= 60; $i += 3): ?><option value="<?= $i ?>"><?= $i ?> Months</option><?php endfor; ?>
                        </select>
                        <div class="text-end mt-2"><span class="badge bg-soft-primary text-primary" id="interestDisplay">Interest Rate: 5%</span></div>
                    </div>

                    <div class="calc-box mb-4">
                        <div class="row g-4 text-center">
                            <div class="col-4 border-end border-secondary border-opacity-10">
                                <label class="calc-label">Net Proceeds</label>
                                <input type="text" id="netProceeds" class="calc-value text-success" value="₱0.00" readonly>
                                <small class="text-muted d-block mt-1" style="font-size: 0.6rem;">(Less 5% Fee)</small>
                            </div>
                            <div class="col-4 border-end border-secondary border-opacity-10">
                                <label class="calc-label">Monthly Bill</label>
                                <input type="text" id="monthlyPayment" class="calc-value" value="₱0.00" readonly>
                            </div>
                            <div class="col-4">
                                <label class="calc-label">Total Payment</label>
                                <input type="text" id="totalRepayment" class="calc-value large" value="₱0.00" readonly>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 border-secondary border-opacity-10">
                    <h6 class="fw-bold mb-3 text-main"><i class="fas fa-info-circle me-2 text-primary"></i>Disbursement Details</h6>

                    <div class="mb-4">
                        <label class="form-label">Purpose of Loan</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Tell the lender why you need this loan" required></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Disbursement Account</label>
                        <input type="text" name="account_info" class="form-control" placeholder="e.g. GCash: 0917-XXX-XXXX" required>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="termsCheck" required>
                        <label class="form-check-label small text-muted" for="termsCheck">
                            I confirm the details above are accurate and agree to the Terms.
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm" <?= $active_loan ? 'disabled' : '' ?>>
                        Submit Application <i class="fas fa-paper-plane ms-2"></i>
                    </button>
                </form>
            </div>

            <?php if($loans): ?>
            <div class="content-card mt-4 mb-5">
                <div class="card-title pb-2 border-bottom border-light">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-muted" style="width:36px; height:36px;"><i class="fas fa-history"></i></div>
                    <div class="fw-bold text-main">Application History</div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="bg-light">
                            <tr class="text-muted small text-uppercase">
                                <th class="py-3 ps-3 rounded-start">Ref</th>
                                <th class="py-3">Amount</th>
                                <th class="py-3">Term</th>
                                <th class="py-3 pe-3 rounded-end text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($loans as $loan): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary small">#<?= htmlspecialchars($loan['loan_id']) ?></td>
                                <td class="fw-bold">₱<?= number_format($loan['amount'],2) ?></td>
                                <td class="small text-muted"><?= $loan['term'] ?> mos</td>
                                <td class="pe-3 text-end"><span class="badge-custom bg-soft-<?= $loan['status']=='Approved'?'success':($loan['status']=='Rejected'?'danger':'warning') ?> text-<?= $loan['status']=='Approved'?'success':($loan['status']=='Rejected'?'danger':'warning') ?>"><?= $loan['status'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold text-danger">Sign Out</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="mb-3 text-danger"><i class="fas fa-sign-out-alt fa-3x"></i></div>
        <p class="mb-0 fw-medium">Are you sure you want to end your session?</p>
      </div>
      <div class="modal-footer border-0 justify-content-center pt-0 pb-4">
        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
        <a href="../logout.php" class="btn btn-danger px-4 fw-bold">Logout</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile Sidebar Logic
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const toggleBtn = document.getElementById('sidebarToggle');
const closeBtn = document.getElementById('closeSidebar');

function toggleSidebar() {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('show');
}
toggleBtn.addEventListener('click', toggleSidebar);
closeBtn.addEventListener('click', toggleSidebar);
overlay.addEventListener('click', toggleSidebar);

// Theme Toggle
const htmlEl = document.documentElement;
const themeBtn = document.getElementById('themeToggle');
const icon = themeBtn.querySelector('i');
if(localStorage.getItem('theme')==='dark'){ htmlEl.setAttribute('data-bs-theme','dark'); icon.classList.replace('fa-moon','fa-sun'); }
themeBtn.addEventListener('click',()=>{
  const mode = htmlEl.getAttribute('data-bs-theme')==='dark'?'light':'dark';
  htmlEl.setAttribute('data-bs-theme',mode);
  localStorage.setItem('theme',mode);
  icon.classList.toggle('fa-moon'); icon.classList.toggle('fa-sun');
});

// Calculator Logic
const amountInput = document.getElementById('amountInput');
const termSelect = document.getElementById('termSelect');
const netDisplay = document.getElementById('netProceeds');
const monthlyDisplay = document.getElementById('monthlyPayment');
const totalDisplay = document.getElementById('totalRepayment');
const interestText = document.getElementById('interestDisplay');
const maxAmount = 250000;

function calculateLoan(){
    let amount = parseFloat(amountInput.value) || 0;
    if(amount > maxAmount) { 
        amount = maxAmount; 
        amountInput.value = amount; // Cap visual input
    }

    const term = parseInt(termSelect.value) || 3;
    let rate = 5;
    if(term>12 && term<=24) rate=7;
    else if(term>24 && term<=36) rate=9;
    else if(term>36 && term<=48) rate=11;
    else if(term>48) rate=13;

    interestText.textContent = `Interest Rate: ${rate}%`;

    const fee = amount * 0.05;
    const net = amount - fee;
    const totalInterest = amount * (rate/100);
    const totalPayable = amount + totalInterest;
    const monthly = totalPayable / term;

    netDisplay.value = '₱'+net.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
    monthlyDisplay.value = '₱'+monthly.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
    totalDisplay.value = '₱'+totalPayable.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
}

amountInput.addEventListener('input',calculateLoan);
termSelect.addEventListener('change',calculateLoan);
calculateLoan(); // Run initially
</script>
</body>
</html>