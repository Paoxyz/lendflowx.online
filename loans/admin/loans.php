<?php
session_start();
require_once "../db.php";

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../adminLogin.php");
    exit;
}

// Loan types
$loanTypes = ['Fiesta Loan', 'Birthday Loan', 'Emergency Loan', 'Business Loan', 'Education Loan'];

// --- Add Loan ---
if (isset($_POST['add'])) {
    $user_id = $_POST['user_id'];
    $amount = $_POST['amount'];
    $term = $_POST['term'];
    $interest = $_POST['interest_rate'];
    $type = $_POST['loan_type'] ?? '';
    $loan_id = 'LN-' . strtoupper(substr(md5(time()), 0, 6));

    $stmt = $pdo->prepare("INSERT INTO loans (user_id, loan_id, amount, term, interest_rate, loan_type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    $stmt->execute([$user_id, $loan_id, $amount, $term, $interest, $type]);
    $_SESSION['message'] = ['text' => "New loan #$loan_id created successfully.", 'type' => 'success'];
    header("Location: loans.php");
    exit;
}

// --- Delete Loan ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['message'] = ['text' => "Loan record deleted.", 'type' => 'danger'];
    header("Location: loans.php");
    exit;
}

// --- Approve Loan ---
if (isset($_GET['approve'])) {
    $id = $_GET['approve'];
    $stmt = $pdo->prepare("UPDATE loans SET status='Approved' WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['message'] = ['text' => "Loan approved successfully.", 'type' => 'success'];
    header("Location: loans.php");
    exit;
}

// --- Update Loan (Edit) ---
if (isset($_POST['update'])) {
    $id = $_POST['loan_id'];
    $amount = $_POST['amount'];
    $term = $_POST['term'];
    $interest = $_POST['interest_rate'];
    $status = $_POST['status'];
    $type = $_POST['loan_type'] ?? '';

    $stmt = $pdo->prepare("UPDATE loans SET amount=?, term=?, interest_rate=?, loan_type=?, status=? WHERE id=?");
    $stmt->execute([$amount, $term, $interest, $type, $status, $id]);
    $_SESSION['message'] = ['text' => "Loan details updated.", 'type' => 'info'];
    header("Location: loans.php");
    exit;
}

// Flash Messages
$msg_text = ''; $msg_type = '';
if (isset($_SESSION['message'])) {
    $msg_text = $_SESSION['message']['text'];
    $msg_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}

// --- Fetch loans ---
$stmt = $pdo->query("SELECT loans.*, users.name FROM loans JOIN users ON loans.user_id = users.id ORDER BY loans.id DESC");
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Counts for Summary ---
$totalLoans = count($loans);
$pendingLoans = count(array_filter($loans, fn($l) => $l['status'] === 'Pending'));

// --- Borrowers for dropdown ---
$stmt = $pdo->query("SELECT id, name FROM users WHERE role='borrower' AND status='Approved'");
$borrowers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Loans | LoanTracker</title>
    
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
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        [data-bs-theme="dark"] {
            --bg-body: #0f172a; --bg-sidebar: #1e293b; --bg-card: #1e293b;
            --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; }
        
        /* Sidebar */
        .sidebar { 
            width: var(--sidebar-w); background: var(--bg-sidebar); 
            border-right: 1px solid var(--border); 
            position: fixed; top:0; bottom:0; left:0; z-index: 1050; 
            display: flex; flex-direction: column; 
            transition: transform 0.3s ease-in-out;
        }
        .sidebar-brand { padding: 24px; font-size: 1.25rem; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .nav-link { padding: 12px 24px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 12px; transition: 0.2s; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { background: var(--primary-soft); color: var(--primary); border-left-color: var(--primary); }
        
        .main-content { flex: 1; margin-left: var(--sidebar-w); padding: 30px; width: 100%; transition: margin-left 0.3s ease-in-out; }

        /* Backdrop for Mobile Sidebar */
        .sidebar-backdrop {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5); z-index: 1040; display: none;
        }
        .sidebar-backdrop.show { display: block; }

        /* Cards */
        .card-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; box-shadow: var(--shadow-sm); padding: 24px; margin-bottom: 24px; }
        
        /* Forms */
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 6px; }
        .form-control, .form-select { padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background-color: var(--bg-body); color: var(--text-main); font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
        
        /* Table & List */
        .table-container { border: 1px solid var(--border); border-radius: 16px; overflow: hidden; background: var(--bg-card); box-shadow: var(--shadow-sm); }
        .custom-table { margin-bottom: 0; vertical-align: middle; }
        .custom-table th { background: rgba(0,0,0,0.02); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); padding: 16px 24px; border-bottom: 1px solid var(--border); }
        .custom-table td { padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: var(--text-main); }
        
        /* Mobile Card Style */
        .loan-mobile-card {
            border-bottom: 1px solid var(--border);
            padding: 16px;
            background: var(--bg-card);
        }
        .loan-mobile-card:last-child { border-bottom: none; }

        /* Badges */
        .badge-soft { padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
        .badge-soft.Approved { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-soft.Pending { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-soft.Rejected { background: rgba(220, 38, 38, 0.1); color: #dc2626; border: 1px solid rgba(220, 38, 38, 0.2); }
        .badge-soft.Completed { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }

        /* Responsive */
        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; } 
            
            /* Hide table on mobile, show cards */
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
        <a href="#" class="nav-link active"><i class="fas fa-file-contract"></i> Loan Management</a>
        <a href="adminPayments.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> Payments</a>
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
                <h4 class="fw-bold mb-0">Manage Loans</h4>
                <p class="text-muted small mb-0">Create, edit, and track loan applications.</p>
            </div>
        </div>
        <button class="btn btn-light border rounded-circle" id="themeToggle"><i class="fas fa-moon text-muted"></i></button>
    </div>

    <?php if($msg_text): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 small">
            <i class="fas fa-info-circle me-2"></i> <?= $msg_text ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center mb-4 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#addLoanForm">
            <h6 class="fw-bold m-0 text-primary"><i class="fas fa-plus-circle me-2"></i> Create New Loan Record</h6>
            <i class="fas fa-chevron-down text-muted"></i>
        </div>
        <div class="collapse show" id="addLoanForm">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Borrower</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select Borrower...</option>
                            <?php foreach ($borrowers as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Loan Type</label>
                        <select name="loan_type" class="form-select" required>
                            <?php foreach($loanTypes as $lt): ?>
                                <option value="<?= htmlspecialchars($lt) ?>"><?= htmlspecialchars($lt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Principal Amount</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border text-muted">₱</span>
                            <input type="number" step="0.01" name="amount" class="form-control border-start-0" required>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Term (Months)</label>
                        <input type="number" name="term" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Interest Rate (%)</label>
                        <input type="number" step="0.01" name="interest_rate" class="form-control" value="5" required>
                    </div>
                    <div class="col-12 d-flex justify-content-end mt-4">
                        <button type="submit" name="add" class="btn btn-primary px-4 fw-bold w-100 w-md-auto"><i class="fas fa-paper-plane me-2"></i> Submit Application</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="table-container">
        <div class="p-3 border-bottom bg-light bg-opacity-50 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <h6 class="fw-bold m-0 text-dark">Loan Registry</h6>
                <span class="badge bg-secondary bg-opacity-10 text-dark border"><?= $totalLoans ?> Total</span>
                <?php if($pendingLoans > 0): ?>
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning"><?= $pendingLoans ?> Pending</span>
                <?php endif; ?>
            </div>
            <input type="text" id="tableSearch" class="form-control form-control-sm w-auto" placeholder="Search reference...">
        </div>
        
        <div class="desktop-view table-responsive">
            <table class="table custom-table table-hover" id="loansTable">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Borrower</th>
                        <th>Principal</th>
                        <th>Interest</th>
                        <th>Term</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $row): ?>
                    <tr>
                        <td>
                            <span class="fw-bold text-primary"><?= htmlspecialchars($row['loan_id']) ?></span>
                            <div class="text-muted small" style="font-size: 0.7rem;"><?= htmlspecialchars($row['loan_type']) ?></div>
                        </td>
                        <td>
                            <div class="fw-medium"><?= htmlspecialchars($row['name']) ?></div>
                        </td>
                        <td class="fw-bold">₱<?= number_format($row['amount'], 2) ?></td>
                        <td><?= $row['interest_rate'] ?>%</td>
                        <td><?= $row['term'] ?> mos</td>
                        <td>
                            <span class="badge-soft <?= $row['status'] ?>"><?= $row['status'] ?></span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button class="btn btn-sm btn-light border text-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>" title="Edit Details"><i class="fas fa-pen"></i></button>
                                <?php if($row['status'] === 'Pending'): ?>
                                    <a href="?approve=<?= $row['id'] ?>" class="btn btn-sm btn-success text-white" onclick="return confirm('Approve loan application?')" title="Approve"><i class="fas fa-check"></i></a>
                                <?php endif; ?>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete this record permanently?')" title="Delete"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-view" id="mobileCards">
            <?php foreach ($loans as $row): ?>
            <div class="loan-mobile-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="fw-bold text-primary"><?= htmlspecialchars($row['loan_id']) ?></span>
                        <div class="text-muted small"><?= htmlspecialchars($row['loan_type']) ?></div>
                    </div>
                    <span class="badge-soft <?= $row['status'] ?>"><?= $row['status'] ?></span>
                </div>
                <div class="mb-2">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Borrower</small>
                    <div class="fw-medium"><?= htmlspecialchars($row['name']) ?></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Amount</small>
                        <div class="fw-bold">₱<?= number_format($row['amount']) ?></div>
                    </div>
                    <div class="col-4">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Interest</small>
                        <div><?= $row['interest_rate'] ?>%</div>
                    </div>
                    <div class="col-4">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem;">Term</small>
                        <div><?= $row['term'] ?> mos</div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-light border text-primary w-100" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>"><i class="fas fa-pen"></i> Edit</button>
                    <?php if($row['status'] === 'Pending'): ?>
                        <a href="?approve=<?= $row['id'] ?>" class="btn btn-sm btn-success text-white w-100" onclick="return confirm('Approve?')"><i class="fas fa-check"></i> Approve</a>
                    <?php endif; ?>
                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-light border text-danger w-100" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($loans as $row): ?>
        <div class="modal fade text-start" id="editModal<?= $row['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h6 class="modal-title fw-bold">Edit Loan: <?= $row['loan_id'] ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="loan_id" value="<?= $row['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Amount (₱)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" value="<?= $row['amount'] ?>">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Term (Mos)</label>
                                <input type="number" name="term" class="form-control" value="<?= $row['term'] ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Interest (%)</label>
                                <input type="number" step="0.01" name="interest_rate" class="form-control" value="<?= $row['interest_rate'] ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Pending" <?= $row['status']=='Pending'?'selected':'' ?>>Pending</option>
                                <option value="Approved" <?= $row['status']=='Approved'?'selected':'' ?>>Approved</option>
                                <option value="Rejected" <?= $row['status']=='Rejected'?'selected':'' ?>>Rejected</option>
                                <option value="Completed" <?= $row['status']=='Completed'?'selected':'' ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update" class="btn btn-sm btn-primary fw-bold px-4">Save Changes</button>
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

    // Search Filter
    document.getElementById('tableSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        
        // Filter Table Rows
        document.querySelectorAll('#loansTable tbody tr').forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });

        // Filter Mobile Cards
        document.querySelectorAll('.loan-mobile-card').forEach(card => {
            let text = card.innerText.toLowerCase();
            card.style.display = text.includes(filter) ? '' : 'none';
        });
    });
</script>
</body>
</html>