<?php
require_once '../db.php';
session_start();

// Check if borrower is logged in
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'borrower') {
    header('Location: borrowerLogin.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Handle "Mark All as Read" Action
if (isset($_GET['action']) && $_GET['action'] === 'read_all') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: borrowerDashboard.php");
    exit;
}

// 2. Handle Flash Messages
$msg_type = '';
$msg_text = '';
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    unset($_SESSION['message']);
}

// Fetch user info
$stmt = $pdo->prepare("SELECT name, email, phone, status, password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$status = $user['status'];
$_SESSION['user_name'] = $user['name'];

// --- SECURE PROFILE UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $current_password = $_POST['current_password'];
    
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['message'] = ['text' => "Incorrect current password. Changes NOT saved.", 'type' => 'danger'];
        header("Location: borrowerDashboard.php");
        exit;
    }

    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST['phone']));
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) throw new Exception("New passwords do not match.");
            if (strlen($new_password) < 8) throw new Exception("New password must be at least 8 characters long.");
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, password=? WHERE id=?");
            $stmt->execute([$name, $email, $phone, $hashed, $user_id]);
            $success_msg = "Profile and password updated successfully.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?");
            $stmt->execute([$name, $email, $phone, $user_id]);
            $success_msg = "Profile updated successfully.";
        }
        $_SESSION['user_name'] = $name;
        $_SESSION['message'] = ['text' => $success_msg, 'type' => 'success'];
    } catch (Exception $e) {
        $_SESSION['message'] = ['text' => $e->getMessage(), 'type' => 'danger'];
    }
    header("Location: borrowerDashboard.php");
    exit;
}

// ----------------------
// KPI CALCULATIONS
// ----------------------
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM loans WHERE user_id = ? AND status IN ('Approved','Completed')");
$stmt->execute([$user_id]);
$totalBorrowed = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(ls.total_due - ls.paid_amount), 0) FROM loan_schedule ls JOIN loans l ON ls.loan_id = l.id WHERE l.user_id = ? AND l.status = 'Approved'");
$stmt->execute([$user_id]);
$totalOutstanding = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT MIN(ls.due_date) FROM loan_schedule ls JOIN loans l ON ls.loan_id = l.id WHERE l.user_id = ? AND l.status = 'Approved' AND ls.status != 'Paid'");
$stmt->execute([$user_id]);
$nextDueDate = $stmt->fetchColumn(); 
$nextDueDateDisplay = $nextDueDate ? date('M d, Y', strtotime($nextDueDate)) : 'No Due Date';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'Approved'");
$stmt->execute([$user_id]);
$activeLoansCount = $stmt->fetchColumn();

// ----------------------
// FETCH LOANS LIST
// ----------------------
$stmt = $pdo->prepare("SELECT l.*, COALESCE(SUM(p.amount_paid), 0) as total_paid_so_far FROM loans l LEFT JOIN payments p ON l.id = p.loan_id WHERE l.user_id = ? GROUP BY l.id ORDER BY l.created_at DESC");
$stmt->execute([$user_id]);
$loans = $stmt->fetchAll();

// ----------------------
// FETCH NOTIFICATIONS
// ----------------------
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unreadCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Borrower Dashboard</title>

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
  --success: #10b981;
  --warning: #f59e0b;
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
}

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  background-color: var(--bg-body);
  color: var(--text-main);
  padding-top: 70px; /* Navbar Height */
  min-height: 100vh;
}

/* --- NAVBAR --- */
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

/* --- SIDEBAR --- */
.sidebar {
    position: fixed; top: 70px; left: 0; bottom: 0;
    width: var(--sidebar-width);
    background: var(--card-bg); 
    border-right: 1px solid var(--border-color);
    padding-top: 20px; z-index: 1040;
    transition: transform 0.3s ease-in-out;
}
.sidebar-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 1030; display: none;
}
.sidebar-overlay.show { display: block; }

/* Main Content Area */
.main-wrapper {
    width: 100%;
    padding: 20px;
    transition: margin-left 0.3s ease-in-out;
}

/* --- RESPONSIVE LOGIC --- */
/* Desktop */
@media (min-width: 992px) {
    .sidebar { transform: translateX(0); }
    .main-wrapper { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); }
    .sidebar-toggle { display: none; }
}
/* Mobile/Tablet */
@media (max-width: 991.98px) {
    .sidebar { transform: translateX(-100%); } /* Hide sidebar */
    .sidebar.active { transform: translateX(0); } /* Show sidebar */
    .main-wrapper { margin-left: 0; width: 100%; } /* Full width content */
}

/* Sidebar Links */
.nav-link {
    color: var(--text-muted); font-weight: 600; padding: 12px 20px;
    margin: 4px 12px; border-radius: 8px; transition: 0.2s;
}
.nav-link:hover { color: var(--primary); background: var(--primary-soft); }
.nav-link.active { background: var(--primary-gradient); color: white; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
.nav-link.active i { color: white !important; }

/* --- CARDS --- */
.kpi-card, .loan-card {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 24px;
  height: 100%;
  box-shadow: var(--shadow-sm);
  transition: transform 0.2s;
}
.kpi-card:hover, .loan-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

/* Loan Specifics */
.loan-header { padding-bottom: 15px; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.loan-icon-lg { width: 48px; height: 48px; background: var(--bg-body); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: var(--primary); }
.progress-modern { height: 8px; border-radius: 10px; background-color: var(--bg-body); overflow: hidden; }
.progress-bar-modern { background: var(--primary-gradient); border-radius: 10px; }

/* --- NOTIFICATIONS --- */
#notifPanel {
  position: absolute; top: 55px; right: 0; width: 350px;
  background: var(--card-bg); border: 1px solid var(--border-color);
  border-radius: 16px; box-shadow: var(--shadow-md); display: none;
  z-index: 1060; max-height: 450px; overflow-y: auto;
}
@media (max-width: 576px) {
    #notifPanel { position: fixed; top: 75px; left: 5%; right: 5%; width: 90%; max-width: 400px; }
}
.notif-item { padding: 12px 16px; border-bottom: 1px solid var(--border-color); display: flex; gap: 12px; text-decoration: none; color: var(--text-main); border-left: 3px solid transparent; }
.notif-item:hover { background-color: var(--bg-body); }
.notif-item.unread { background-color: var(--primary-soft); border-left-color: var(--primary); }
.notif-icon { width: 36px; height: 36px; border-radius: 50%; background: var(--bg-body); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid px-3"> 
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
      
      <div class="position-relative">
        <button id="notifBell" class="btn btn-link text-muted p-1 fs-5 border-0 position-relative">
          <i class="fas fa-bell"></i>
          <?php if($unreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.6rem;">
                <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
            </span>
          <?php endif; ?>
        </button>
        
        <div id="notifPanel">
          <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-card sticky-top">
            <h6 class="fw-bold m-0">Notifications</h6>
            <?php if($unreadCount > 0): ?>
                <a href="?action=read_all" class="text-xs text-primary text-decoration-none fw-bold">Mark all read</a>
            <?php endif; ?>
          </div>
          <?php if(!$notifications): ?>
            <div class="text-center py-5 text-muted"><p class="small mb-0">No notifications.</p></div>
          <?php else: ?>
            <div class="notif-list">
            <?php foreach($notifications as $n): $isUnread = $n['is_read'] == 0; ?>
              <a href="#" class="notif-item <?= $isUnread ? 'unread' : '' ?>">
                <div class="notif-icon"><i class="fas <?= $isUnread ? 'fa-envelope' : 'fa-envelope-open' ?> text-secondary"></i></div>
                <div>
                    <p class="mb-1 small <?= $isUnread?'fw-bold':'fw-medium'?>"><?= htmlspecialchars($n['message']) ?></p>
                    <small class="text-muted text-xs"><?= date('M d, h:i A', strtotime($n['created_at'])) ?></small>
                </div>
              </a>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="dropdown">
        <button class="btn btn-light rounded-pill py-1 px-2 fw-bold d-flex align-items-center gap-2 border shadow-sm" type="button" data-bs-toggle="dropdown">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 14px;">
                <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
            </div>
            <span class="d-none d-md-block small"><?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?></span>
            <i class="fas fa-chevron-down text-xs text-muted"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
            <li><a class="dropdown-item small" href="#" data-bs-toggle="modal" data-bs-target="#profileModal"><i class="fas fa-user-cog me-2"></i> Edit Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item small text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<aside class="sidebar d-flex flex-column" id="sidebar">
    <div class="px-4 py-3">
        <h6 class="text-muted text-xs fw-bold text-uppercase mb-3 opacity-75">Main Menu</h6>
        <nav class="nav flex-column gap-1">
            <a href="borrowerDashboard.php" class="nav-link active d-flex align-items-center gap-3"><i class="fas fa-th-large w-5"></i> Dashboard</a>
            <a href="apply_loans.php" class="nav-link d-flex align-items-center gap-3"><i class="fas fa-plus-circle w-5"></i> Apply Loan</a>
            <a href="pay_loan.php" class="nav-link d-flex align-items-center gap-3"><i class="fas fa-money-bill-wave w-5"></i> Pay Loan</a>
        </nav>
    </div>
    <div class="mt-auto p-4 border-top">
        <a href="#" class="nav-link text-danger d-flex align-items-center gap-3" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt w-5"></i> Sign Out</a>
    </div>
</aside>

<div class="main-wrapper">
    <div class="row align-items-end mb-4">
        <div class="col-md-8">
            <h6 class="text-primary fw-bold text-uppercase mb-1" style="font-size: 0.8rem;">Overview</h6>
            <h2 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>!</h2>
            <p class="text-muted mb-0">Financial Summary</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
            <?php if($activeLoansCount > 0): ?>
                <a href="pay_loan.php" class="btn btn-light border shadow-sm fw-bold px-3"><i class="fas fa-paper-plane me-2 text-primary"></i>Pay</a>
            <?php endif; ?>
            <a href="apply_loans.php" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="fas fa-plus me-2"></i>Apply</a>
        </div>
    </div>

    <?php if($msg_text): ?>
        <div class="alert alert-<?=$msg_type?> alert-dismissible fade show shadow-sm border-0 mb-4 rounded-3 p-3">
            <i class="fas fa-<?= $msg_type == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i> <strong><?=$msg_text?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="kpi-card" style="border-bottom: 3px solid var(--primary);">
                <div class="text-muted text-uppercase fw-bold text-xs mb-1">Outstanding</div>
                <div class="h3 fw-bold mb-0">₱<?=number_format($totalOutstanding,2)?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi-card" style="border-bottom: 3px solid var(--warning);">
                <div class="text-muted text-uppercase fw-bold text-xs mb-1">Next Due</div>
                <div class="h3 fw-bold mb-0"><?= $nextDueDateDisplay ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi-card" style="border-bottom: 3px solid var(--success);">
                <div class="text-muted text-uppercase fw-bold text-xs mb-1">Active Loans</div>
                <div class="h3 fw-bold mb-0"><?=$activeLoansCount?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi-card" style="border-bottom: 3px solid var(--secondary);">
                <div class="text-muted text-uppercase fw-bold text-xs mb-1">Total Borrowed</div>
                <div class="h3 fw-bold mb-0">₱<?=number_format($totalBorrowed,2)?></div>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold m-0">Loans</h5>
        <?php if($loans): ?>
            <span class="badge bg-light text-dark border"><?= count($loans) ?> Total</span>
        <?php endif; ?>
    </div>

    <?php if(!$loans): ?>
        <div class="text-center py-5 bg-white rounded-4 border border-dashed">
            <div class="mb-3 opacity-25 text-primary"><i class="fas fa-folder-open fa-3x"></i></div>
            <h6 class="fw-bold">No Loans Yet</h6>
            <a href="apply_loans.php" class="btn btn-sm btn-outline-primary mt-2">Apply Now</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
        <?php foreach($loans as $loan): 
            $percent = 0;
            if($loan['amount'] > 0) {
                $total_payable = $loan['amount'] + ($loan['amount'] * $loan['interest_rate']/100);
                $percent = min(100, ($loan['total_paid_so_far'] / $total_payable) * 100);
            }
            $statusColor = $loan['status']=='Approved'?'primary':($loan['status']=='Completed'?'success':($loan['status']=='Rejected'?'danger':'warning'));
        ?>
          <div class="col-lg-6">
            <div class="loan-card">
              <div class="loan-header">
                 <div class="d-flex align-items-center gap-3">
                    <div class="loan-icon-lg"><i class="fas fa-receipt"></i></div>
                    <div>
                        <h6 class="mb-0 fw-bold text-main">#<?=htmlspecialchars($loan['loan_id'])?></h6>
                        <small class="text-muted"><?=htmlspecialchars($loan['loan_type'])?></small>
                    </div>
                 </div>
                 <span class="badge bg-<?=$statusColor?> bg-opacity-10 text-<?=$statusColor?> px-3 py-1 rounded-pill"><?=$loan['status']?></span>
              </div>

              <div class="loan-body p-0 pt-3">
                <div class="row g-3 mb-3">
                    <div class="col-6 detail-item"><small>Principal</small><p>₱<?=number_format($loan['amount'],2)?></p></div>
                    <div class="col-6 detail-item"><small>Paid</small><p class="text-success">₱<?=number_format($loan['total_paid_so_far'],2)?></p></div>
                    <div class="col-6 detail-item"><small>Duration</small><p><?= $loan['term'] ?> Months</p></div>
                    <div class="col-6 detail-item"><small>Interest</small><p><?= $loan['interest_rate'] ?>%</p></div>
                </div>
                
                <div class="d-flex justify-content-between align-items-end mb-1">
                    <small class="text-muted fw-bold" style="font-size: 0.7rem;">PROGRESS</small>
                    <small class="fw-bold text-primary"><?= number_format($percent, 0) ?>%</small>
                </div>
                <div class="progress progress-modern mb-3">
                    <div class="progress-bar progress-bar-modern" style="width: <?= $percent ?>%"></div>
                </div>
                
                <?php if($loan['status'] === 'Approved'): ?>
                    <a href="pay_loan.php" class="btn btn-primary w-100 fw-bold shadow-sm py-2">Make Payment</a>
                <?php else: ?>
                     <button class="btn btn-light w-100 text-muted border fw-bold py-2" disabled>Unavailable</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="profileModal">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">Profile Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-warning border-warning border-opacity-25 bg-warning bg-opacity-10 small text-dark mb-3 p-2">
            <i class="fas fa-lock me-1"></i> Enter current password to save changes.
        </div>
        <input type="hidden" name="update_profile" value="1">
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">CURRENT PASSWORD <span class="text-danger">*</span></label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <hr class="my-3 opacity-10">
        <div class="row g-2 mb-2">
            <div class="col-12"><label class="form-label small text-muted fw-bold">NAME</label><input type="text" name="name" class="form-control" value="<?=$user['name']?>" required></div>
            <div class="col-md-6"><label class="form-label small text-muted fw-bold">EMAIL</label><input type="email" name="email" class="form-control" value="<?=$user['email']?>" required></div>
            <div class="col-md-6"><label class="form-label small text-muted fw-bold">PHONE</label><input type="text" name="phone" class="form-control" value="<?=$user['phone']?>" required></div>
        </div>
        <div class="accordion mt-3" id="passwordAccordion">
            <div class="accordion-item border-0">
                <h2 class="accordion-header"><button class="accordion-button collapsed p-0 bg-transparent text-primary small fw-bold shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#changePass">Change Password?</button></h2>
                <div id="changePass" class="accordion-collapse collapse" data-bs-parent="#passwordAccordion">
                    <div class="accordion-body px-0 pt-2">
                        <div class="mb-2"><label class="form-label small text-muted fw-bold">NEW PASSWORD</label><input type="password" name="new_password" class="form-control"></div>
                        <div><label class="form-label small text-muted fw-bold">CONFIRM PASSWORD</label><input type="password" name="confirm_password" class="form-control"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-grid mt-3"><button class="btn btn-primary fw-bold">Save Securely</button></div>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <div class="text-danger mb-2"><i class="fas fa-sign-out-alt fa-2x"></i></div>
        <h6 class="fw-bold">Sign Out?</h6>
        <div class="d-flex gap-2 justify-content-center mt-4">
            <button type="button" class="btn btn-light border btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
            <a href="../logout.php" class="btn btn-danger btn-sm px-3">Logout</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile Sidebar Toggle
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const toggleBtn = document.getElementById('sidebarToggle');

function toggleSidebar() {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('show');
}
toggleBtn.addEventListener('click', toggleSidebar);
overlay.addEventListener('click', toggleSidebar);

// Notification Panel
const bell = document.getElementById("notifBell");
const panel = document.getElementById("notifPanel");
bell.onclick = (e) => { e.stopPropagation(); panel.style.display = panel.style.display === "block" ? "none" : "block"; };
window.onclick = (e) => { if (!panel.contains(e.target)) { panel.style.display = "none"; } };

// Theme Toggle
const html = document.documentElement;
const themeBtn = document.getElementById('themeToggle');
if(localStorage.getItem('theme')==='dark'){ html.setAttribute('data-bs-theme', 'dark'); }
themeBtn.addEventListener('click', () => {
    const mode = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', mode);
    localStorage.setItem('theme', mode);
});
</script>
</body>
</html>