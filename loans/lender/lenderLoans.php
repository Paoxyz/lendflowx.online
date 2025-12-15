<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['lender_id'])) {
    header("Location: lenderLogin.php");
    exit;
}

$lender_id = $_SESSION['lender_id'];
$lender_name = $_SESSION['lender_name'] ?? 'Lender';
$filter = $_GET['status'] ?? 'All';

// --- HANDLE LOAN APPROVAL (Backend) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_loan_id'])) {
    $loanIdToApprove = $_POST['approve_loan_id'];

    // Update status to Active
    $updateSql = "UPDATE loans SET status = 'Active' WHERE id = ? AND lender_id = ?";
    $stmtUpdate = $pdo->prepare($updateSql);
    
    if ($stmtUpdate->execute([$loanIdToApprove, $lender_id])) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=" . $filter);
        exit;
    }
}
// --------------------------------------

// Build Query
$sql = "SELECT l.*, u.name as borrower_name, u.email as borrower_email 
        FROM loans l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.lender_id = ?";
$params = [$lender_id];

if ($filter !== 'All') {
    $sql .= " AND l.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Portfolio | LoanTracker</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --primary: #ef4444; --primary-dark: #dc2626; --primary-soft: rgba(239, 68, 68, 0.1);
    --secondary: #64748b;
    --bg-body: #f8fafc; --bg-card: #ffffff; --bg-sidebar: #ffffff;
    --text-main: #0f172a; --text-muted: #64748b;
    --border: #e2e8f0; --radius: 16px; --sidebar-w: 280px;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
[data-bs-theme="dark"] {
    --bg-body: #0f172a; --bg-card: #1e293b; --bg-sidebar: #1e293b;
    --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
}
body {
    font-family: 'Plus Jakarta Sans', sans-serif; 
    background: var(--bg-body); 
    color: var(--text-main); 
    display: flex; min-height: 100vh; flex-direction: column;
}

/* Sidebar */
.sidebar {
    width: var(--sidebar-w); background: var(--bg-sidebar);
    border-right:1px solid var(--border); position: fixed;
    top:0; bottom:0; left:0; z-index:100; padding:24px;
    display:flex; flex-direction: column; transition: transform 0.3s ease;
}
.sidebar-brand { display:flex; align-items:center; gap:12px; font-size:1.25rem; font-weight:800; color:var(--primary); margin-bottom:40px; padding:0 12px; }
.nav-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:12px; padding:0 12px; }
.nav-link { padding:12px; color:var(--text-muted); font-weight:600; border-radius:12px; margin-bottom:4px; display:flex; align-items:center; gap:12px; transition:0.2s; }
.nav-link:hover { background:var(--bg-body); color:var(--primary); }
.nav-link.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color:white; box-shadow:0 4px 6px -1px rgba(239,68,68,0.2); }
.nav-link i { width:20px; text-align:center; }

/* Main Content */
.main-content { flex:1; margin-left: var(--sidebar-w); padding:32px; width:100%; }
.topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:32px; }
.user-profile { display:flex; align-items:center; gap:12px; background:var(--bg-card); padding:8px 16px; border-radius:50px; border:1px solid var(--border); }
.avatar { width:36px; height:36px; border-radius:50%; background:var(--primary); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; }

/* Card Box */
.card-box { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow-sm); overflow:hidden; }

/* Table */
.custom-table { width:100%; margin-bottom:0; vertical-align:middle; table-layout:auto; }
.custom-table th { background: rgba(0,0,0,0.02); font-size:0.75rem; text-transform:uppercase; font-weight:700; color:var(--text-muted); padding:16px 12px; border-bottom:1px solid var(--border); white-space:nowrap; }
.custom-table td { padding:12px 8px; border-bottom:1px solid var(--border); vertical-align:middle; }
.custom-table tr:last-child td { border-bottom:none; }

/* Modal Customization */
.modal-content { background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border); }
.modal-header { border-bottom: 1px solid var(--border); }
.modal-footer { border-top: 1px solid var(--border); }
.btn-close { filter: var(--text-main) == '#f8fafc' ? invert(1) : none; } 

/* Badges */
.badge-soft { padding:6px 12px; border-radius:6px; font-weight:600; font-size:0.75rem; }
.badge-soft.Active { background: rgba(16,185,129,0.1); color:#10b981; }
.badge-soft.Completed { background: rgba(59,130,246,0.1); color:#3b82f6; }
.badge-soft.Overdue { background: rgba(239,68,68,0.1); color:#ef4444; }
.badge-soft.Pending { background: rgba(245,158,11,0.1); color:#f59e0b; }

/* Responsive Adjustments */
@media (max-width: 992px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.active { transform: translateX(0); }
    .main-content { margin-left:0; padding:20px; }
}
@media (max-width: 576px) {
    .custom-table { display:none; }
    .loan-card { display:block; margin-bottom:16px; padding:16px; border:1px solid var(--border); border-radius:var(--radius); background:var(--bg-card); box-shadow:var(--shadow-sm); }
    .loan-card h6 { margin:0 0 8px 0; font-size:0.95rem; font-weight: 700; }
    .loan-card p { margin:4px 0; font-size:0.8rem; color:var(--text-muted); }
    .topbar h3 { font-size: 1.5rem; }
}
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand"><i class="fas fa-wallet fa-lg"></i> LoanTracker</div>
    <div class="nav-label">MAIN MENU</div>
    <nav class="nav flex-column mb-4">
        <a href="lenderDashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="lenderLoans.php" class="nav-link active"><i class="fas fa-folder-open"></i> Portfolio</a>
        <a href="myBorrowers.php" class="nav-link"><i class="fas fa-users"></i> Borrowers</a>
    </nav>
    <div class="nav-label">ANALYTICS</div>
    <nav class="nav flex-column flex-grow-1">
        <a href="lenderReports.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports</a>
        <a href="lenderSettings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
    </nav>
    <div class="mt-auto">
        <a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
</aside>

<main class="main-content">
    <div class="topbar">
        <div>
            <h3 class="fw-bold mb-1">Loan Portfolio</h3>
            <p class="text-muted mb-0">Manage and track your investments.</p>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <button class="btn btn-light border rounded-circle" id="themeToggle" style="width:40px;height:40px;">
                <i class="fas fa-moon text-muted"></i>
            </button>
            <button class="btn btn-primary d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('active')">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-profile d-none d-md-flex">
                <div class="text-end">
                    <div class="fw-bold text-sm"><?= htmlspecialchars($lender_name) ?></div>
                    <div class="text-muted text-xs">Lender</div>
                </div>
                <div class="avatar"><?= strtoupper(substr($lender_name, 0,1)) ?></div>
            </div>
        </div>
    </div>

    <div class="card-box mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 p-md-4 border-bottom">
            <h5 class="fw-bold m-0">All Loans</h5>
            <div class="d-flex gap-2 align-items-center mt-2 mt-md-0">
                <span class="text-muted text-xs fw-bold text-uppercase">Filter:</span>
                <div class="dropdown">
                    <button class="btn btn-light border btn-sm dropdown-toggle fw-bold text-secondary" type="button" data-bs-toggle="dropdown">
                        <?= $filter ?>
                    </button>
                    <ul class="dropdown-menu shadow border-0">
                        <li><a class="dropdown-item" href="?status=All">All</a></li>
                        <li><a class="dropdown-item" href="?status=Active">Active</a></li>
                        <li><a class="dropdown-item" href="?status=Completed">Completed</a></li>
                        <li><a class="dropdown-item" href="?status=Overdue">Overdue</a></li>
                        <li><a class="dropdown-item" href="?status=Pending">Pending</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="custom-table table-hover">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Borrower</th>
                        <th>Principal</th>
                        <th>Balance</th>
                        <th>Interest</th>
                        <th>Date Issued</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($loans)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No loans found for this filter.</td></tr>
                    <?php else: ?>
                        <?php foreach($loans as $loan): $balance = $loan['amount']; ?>
                        <tr>
                            <td style="min-width:120px;">
                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($loan['loan_id']) ?></span>
                                <div class="text-xs text-muted"><?= htmlspecialchars($loan['loan_type']) ?></div>
                            </td>
                            <td style="min-width:150px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-secondary" style="width:32px;height:32px;">
                                        <i class="fas fa-user text-xs"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-sm"><?= htmlspecialchars($loan['borrower_name']) ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($loan['borrower_email'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-bold">₱<?= number_format($loan['amount'],2) ?></td>
                            <td class="text-muted">₱<?= number_format($balance,2) ?></td>
                            <td><?= $loan['interest_rate'] ?>%</td>
                            
                            <td class="text-muted text-sm">
                                <?= date('M d, Y', strtotime($loan['created_at'])) ?>
                                <small class="d-block text-xs"><?= date('h:i A', strtotime($loan['created_at'])) ?></small>
                            </td>
                            
                            <td><span class="badge-soft <?= $loan['status'] ?>"><?= $loan['status'] ?></span></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if($loan['status'] === 'Pending'): ?>
                                        <button type="button" class="btn btn-sm btn-success fw-bold text-white" 
                                                onclick="setApproveId(<?= $loan['id'] ?>)" 
                                                data-bs-toggle="modal" data-bs-target="#approveModal">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="loanDetails.php?id=<?= $loan['id'] ?>" class="btn btn-sm btn-light border text-primary fw-bold">Details</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if(!empty($loans)): ?>
            <?php foreach($loans as $loan): $balance = $loan['amount']; ?>
                <div class="loan-card d-block d-sm-none">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0 text-primary"><?= htmlspecialchars($loan['loan_id']) ?></h6>
                        <span class="badge-soft <?= $loan['status'] ?>"><?= $loan['status'] ?></span>
                    </div>
                    <p class="mb-1"><i class="fas fa-user me-1 text-muted"></i> <?= htmlspecialchars($loan['borrower_name']) ?></p>
                    <p class="mb-1"><i class="fas fa-money-bill-wave me-1 text-muted"></i> <strong>₱<?= number_format($loan['amount'],2) ?></strong> (Principal)</p>
                    
                    <p class="mb-1"><i class="far fa-clock me-1 text-muted"></i> <?= date('M d, Y • h:i A', strtotime($loan['created_at'])) ?></p>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3 gap-2">
                        <?php if($loan['status'] === 'Pending'): ?>
                            <button type="button" class="btn btn-sm btn-success w-100 fw-bold"
                                    onclick="setApproveId(<?= $loan['id'] ?>)" 
                                    data-bs-toggle="modal" data-bs-target="#approveModal">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                        <?php endif; ?>
                        <a href="loanDetails.php?id=<?= $loan['id'] ?>" class="btn btn-sm btn-light border text-primary fw-bold w-100">Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</main>

<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold text-primary"><i class="fas fa-shield-alt me-2"></i>Confirm Approval</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="mb-3">
            <i class="fas fa-check-circle text-success fa-3x"></i>
        </div>
        <p class="mb-1 fs-5 fw-bold">Approve this loan?</p>
        <p class="text-muted small">This action will change the loan status to <strong>Active</strong> and cannot be undone.</p>
        
        <form method="POST" id="approveForm" class="mt-4">
            <input type="hidden" name="approve_loan_id" id="modalLoanId">
            <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4 fw-bold">Yes, Approve</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Theme Logic
const html = document.documentElement;
const themeBtn = document.getElementById('themeToggle');
if(localStorage.getItem('theme')==='dark'){ html.setAttribute('data-bs-theme','dark'); }
themeBtn.addEventListener('click',()=>{
    const mode = html.getAttribute('data-bs-theme')==='dark'?'light':'dark';
    html.setAttribute('data-bs-theme',mode);
    localStorage.setItem('theme',mode);
});

// Pass ID to Modal
function setApproveId(id) {
    document.getElementById('modalLoanId').value = id;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>