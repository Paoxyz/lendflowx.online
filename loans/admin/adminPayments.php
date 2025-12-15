<?php
session_start();
require_once "../db.php";

// Ensure admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../adminLogin.php");
    exit;
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['payment_id'])) {
        $paymentId = intval($_POST['payment_id']);
        $action = $_POST['action'];

        // Fetch payment
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();

        if ($payment) {
            // Toggle payment status
            if ($action === 'toggle') {
                $newStatus = ($payment['status']==='Paid') ? 'Pending' : 'Paid';
                $stmt = $pdo->prepare("UPDATE payments SET status=? WHERE id=?");
                $stmt->execute([$newStatus, $paymentId]);

                // If marking as Paid, update loan_schedule (Waterfall Logic)
                if ($newStatus === 'Paid') {
                    $to_pay = $payment['amount_paid'];
                    $loan_id = $payment['loan_id'];

                    while ($to_pay > 0) {
                        $stmt = $pdo->prepare("SELECT * FROM loan_schedule WHERE loan_id=? AND status='Pending' ORDER BY installment_no ASC LIMIT 1");
                        $stmt->execute([$loan_id]);
                        $installment = $stmt->fetch();
                        if (!$installment) break;

                        $remaining = $installment['total_due'] - $installment['paid_amount'];
                        $pay_now = min($to_pay, $remaining);

                        $stmt = $pdo->prepare("UPDATE loan_schedule SET paid_amount=paid_amount+? WHERE id=?");
                        $stmt->execute([$pay_now, $installment['id']]);

                        if (($installment['paid_amount'] + $pay_now) >= $installment['total_due']) {
                            $stmt = $pdo->prepare("UPDATE loan_schedule SET status='Paid' WHERE id=?");
                            $stmt->execute([$installment['id']]);
                        }
                        $to_pay -= $pay_now;
                    }
                }
            }
            // Delete payment
            elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM payments WHERE id=?");
                $stmt->execute([$paymentId]);
            }
            // Update payment details
            elseif ($action === 'update') {
                $loanId = $_POST['loan_id'];
                $amount = $_POST['amount'];
                $paymentDate = $_POST['payment_date'];
                $status = $_POST['status'];

                $stmt = $pdo->prepare("UPDATE payments SET loan_id=?, amount_paid=?, payment_date=?, status=? WHERE id=?");
                $stmt->execute([$loanId, $amount, $paymentDate, $status, $paymentId]);

                // Re-apply waterfall if paid
                if ($status === 'Paid') {
                    $to_pay = $amount;
                    $loan_id = $loanId;
                    // ... existing waterfall logic ...
                }
            }
        }
        // Refresh to show changes
        header("Location: adminPayments.php");
        exit;
    }
}

// Fetch payments with borrower info
$payments = $pdo->query("
    SELECT payments.*, loans.loan_id AS loan_code, users.name 
    FROM payments 
    JOIN loans ON payments.loan_id = loans.id
    JOIN users ON loans.user_id = users.id
    ORDER BY payments.payment_date DESC, payments.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalPayments = count($payments);
$paidPayments = count(array_filter($payments, fn($p)=>$p['status']==='Paid'));
$pendingPayments = $totalPayments - $paidPayments;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Payments | LoanTracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #dc2626; --primary-soft: rgba(220, 38, 38, 0.1);
            --bg-body: #f8fafc; --bg-sidebar: #ffffff; --bg-card: #ffffff;
            --text-main: #0f172a; --text-muted: #64748b;
            --border: #e2e8f0; --sidebar-w: 250px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        [data-bs-theme="dark"] {
            --bg-body: #0f172a; --bg-sidebar: #1e293b; --bg-card: #1e293b;
            --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; }
        
        /* Sidebar & Layout */
        .sidebar { 
            width: var(--sidebar-w); background: var(--bg-sidebar); 
            border-right: 1px solid var(--border); 
            position: fixed; top:0; bottom:0; left:0; z-index: 1050; 
            display: flex; flex-direction: column; 
            transition: transform 0.3s ease-in-out;
        }
        .sidebar-brand { padding: 24px; font-size: 1.25rem; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .nav-link { padding: 12px 24px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background: var(--primary-soft); color: var(--primary); }
        
        .main-content { flex: 1; margin-left: var(--sidebar-w); padding: 30px; width: 100%; transition: margin-left 0.3s ease-in-out; }

        /* Backdrop */
        .sidebar-backdrop {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5); z-index: 1040; display: none;
        }
        .sidebar-backdrop.show { display: block; }

        /* Cards */
        .card-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: var(--shadow-sm); height: 100%; }
        
        /* KPI Icons */
        .icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .bg-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .bg-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .bg-orange { background: rgba(249, 115, 22, 0.1); color: #f97316; }

        /* Table */
        .table-container { border: 1px solid var(--border); border-radius: 16px; overflow: hidden; background: var(--bg-card); }
        .custom-table { margin-bottom: 0; vertical-align: middle; }
        .custom-table th { background: rgba(0,0,0,0.02); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); padding: 16px 24px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        [data-bs-theme="dark"] .custom-table th { background: rgba(255,255,255,0.02); }

        /* Mobile Card Style */
        .payment-mobile-card {
            border-bottom: 1px solid var(--border);
            padding: 16px;
            background: var(--bg-card);
        }
        .payment-mobile-card:last-child { border-bottom: none; }

        /* Badges */
        .badge-soft { padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
        .badge-soft.Paid { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-soft.Pending { background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.2); }

        /* Responsive */
        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; } 
            
            /* Toggle Views */
            .desktop-view { display: none; }
            .mobile-view { display: block; }
        }
        @media (min-width: 992px) {
            .mobile-view { display: none; }
            .desktop-view { display: block; }
        }
    </style>
</head>
<body>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-shield-alt"></i> AdminPanel
        <button class="btn btn-sm btn-light d-lg-none ms-auto" id="closeSidebar"><i class="fas fa-times"></i></button>
    </div>
    <nav class="flex-grow-1">
        <a href="adminDashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="loans.php" class="nav-link"><i class="fas fa-file-contract"></i> Loan Management</a>
        <a href="#" class="nav-link active"><i class="fas fa-money-bill-wave"></i> Payments</a>
        <a href="verifyusers.php" class="nav-link"><i class="fas fa-users-cog"></i> Verification</a>
    </nav>
    <div class="p-4">
        <button class="btn btn-outline-danger w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </button>
    </div>
</aside>

<main class="main-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light border d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div>
                <h4 class="fw-bold mb-0">Transaction History</h4>
                <p class="text-muted small mb-0">Monitor and verify loan repayments.</p>
            </div>
        </div>
        <button class="btn btn-light border rounded-circle" id="themeToggle"><i class="fas fa-moon text-muted"></i></button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card-box d-flex align-items-center justify-content-between">
                <div><h6 class="text-muted mb-1">Total Transactions</h6><h3 class="fw-bold"><?= $totalPayments ?></h3></div>
                <div class="icon-box bg-blue"><i class="fas fa-list"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card-box d-flex align-items-center justify-content-between">
                <div><h6 class="text-muted mb-1">Verified Payments</h6><h3 class="fw-bold text-success"><?= $paidPayments ?></h3></div>
                <div class="icon-box bg-green"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card-box d-flex align-items-center justify-content-between" style="<?= $pendingPayments > 0 ? 'border-color: #f97316' : '' ?>">
                <div><h6 class="text-muted mb-1">Pending Review</h6><h3 class="fw-bold text-warning"><?= $pendingPayments ?></h3></div>
                <div class="icon-box bg-orange"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="p-3 border-bottom bg-light bg-opacity-50 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="fw-bold m-0 text-dark">All Payments</h6>
            <input type="text" id="tableSearch" class="form-control form-control-sm w-auto" placeholder="Search Ref ID...">
        </div>
        
        <div class="desktop-view table-responsive">
            <table class="table custom-table table-hover" id="paymentsTable">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Borrower</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td>
                            <span class="fw-bold text-primary"><?= htmlspecialchars($p['loan_code']) ?></span>
                            <div class="text-muted text-xs">ID: #<?= $p['id'] ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                                    <i class="fas fa-user text-secondary text-xs"></i>
                                </div>
                                <span class="fw-medium"><?= htmlspecialchars($p['name']) ?></span>
                            </div>
                        </td>
                        <td class="fw-bold">₱<?= number_format($p['amount_paid'], 2) ?></td>
                        <td class="text-muted"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                        <td><span class="badge-soft <?= $p['status'] ?>"><?= $p['status'] ?></span></td>
                        <td class="text-end">
                            <form method="post" class="d-inline-flex gap-1">
                                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                <button type="submit" name="action" value="toggle" class="btn btn-sm border <?= $p['status']==='Paid' ? 'btn-light text-warning' : 'btn-success text-white' ?>" title="Toggle Status"><i class="fas <?= $p['status']==='Paid' ? 'fa-undo' : 'fa-check' ?>"></i></button>
                                <button type="button" class="btn btn-sm btn-light border text-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id'] ?>"><i class="fas fa-edit"></i></button>
                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete this record?')"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-view" id="mobileCards">
            <?php foreach ($payments as $p): ?>
            <div class="payment-mobile-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="fw-bold text-primary"><?= htmlspecialchars($p['loan_code']) ?></span>
                        <div class="text-muted text-xs">Pay ID: #<?= $p['id'] ?></div>
                    </div>
                    <span class="badge-soft <?= $p['status'] ?>"><?= $p['status'] ?></span>
                </div>
                <div class="mb-2">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Borrower</small>
                    <div class="fw-medium"><?= htmlspecialchars($p['name']) ?></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Amount</small>
                        <div class="fw-bold text-success">₱<?= number_format($p['amount_paid'], 2) ?></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Date</small>
                        <div><?= date('M d, Y', strtotime($p['payment_date'])) ?></div>
                    </div>
                </div>
                
                <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    
                    <button type="submit" name="action" value="toggle" 
                            class="btn btn-sm w-100 fw-bold <?= $p['status']==='Paid' ? 'btn-outline-warning' : 'btn-success text-white' ?>">
                        <i class="fas <?= $p['status']==='Paid' ? 'fa-undo' : 'fa-check' ?> me-1"></i> <?= $p['status']==='Paid' ? 'Revert' : 'Verify' ?>
                    </button>
                    
                    <button type="button" class="btn btn-sm btn-light border text-primary w-100" data-bs-toggle="modal" data-bs-target="#editModal<?= $p['id'] ?>">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-light border text-danger w-100" onclick="return confirm('Delete?')">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($payments as $p): ?>
        <div class="modal fade text-start" id="editModal<?= $p['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h6 class="modal-title fw-bold">Edit Payment #<?= $p['id'] ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Loan ID</label>
                            <input type="text" name="loan_id" class="form-control" value="<?= $p['loan_id'] ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Amount Paid</label>
                            <input type="number" step="0.01" name="amount" class="form-control" value="<?= $p['amount_paid'] ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= $p['payment_date'] ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Status</label>
                            <select name="status" class="form-select">
                                <option value="Pending" <?= $p['status']=='Pending'?'selected':'' ?>>Pending</option>
                                <option value="Paid" <?= $p['status']=='Paid'?'selected':'' ?>>Paid</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="update" class="btn btn-primary px-4">Update</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-danger">Sign Out</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div class="mb-3 text-danger"><i class="fas fa-sign-out-alt fa-3x"></i></div>
        <p class="mb-0 fw-medium">Are you sure you want to end your session?</p>
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
        <a href="../logout.php" class="btn btn-danger px-4">Logout</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Mobile Toggle
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('closeSidebar');

    function toggleSidebar() {
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('show');
    }

    toggleBtn.addEventListener('click', toggleSidebar);
    closeBtn.addEventListener('click', toggleSidebar);
    backdrop.addEventListener('click', toggleSidebar);

    // Theme Logic
    const html = document.documentElement;
    const themeBtn = document.getElementById('themeToggle');
    if(localStorage.getItem('theme')==='dark'){ html.setAttribute('data-bs-theme', 'dark'); }
    themeBtn.addEventListener('click', () => {
        const mode = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', mode);
        localStorage.setItem('theme', mode);
    });

    // Search Filter (Works on Table AND Cards)
    document.getElementById('tableSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        
        // Filter Table Rows
        document.querySelectorAll('#paymentsTable tbody tr').forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });

        // Filter Mobile Cards
        document.querySelectorAll('.payment-mobile-card').forEach(card => {
            let text = card.innerText.toLowerCase();
            card.style.display = text.includes(filter) ? '' : 'none';
        });
    });
</script>
</body>
</html>