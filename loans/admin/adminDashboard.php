<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../adminLogin.php");
    exit;
}

// 1. Handle Actions (Approve/Reject/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['user_id'])) {
        $userId = intval($_POST['user_id']);
        $action = $_POST['action'];
        try {
            if ($action === 'approve') {
                $pdo->prepare("UPDATE users SET status='Approved' WHERE id=?")->execute([$userId]);
                $_SESSION['message'] = ['text' => "User #$userId approved.", 'type' => 'success'];
            } elseif ($action === 'reject') {
                $pdo->prepare("UPDATE users SET status='Rejected' WHERE id=?")->execute([$userId]);
                $_SESSION['message'] = ['text' => "User #$userId rejected.", 'type' => 'warning'];
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);
                $_SESSION['message'] = ['text' => "User deleted.", 'type' => 'danger'];
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = ['text' => "Error: " . $e->getMessage(), 'type' => 'danger'];
        }
        header("Location: adminDashboard.php");
        exit;
    }
}

// 2. Message Handling
$msg_text = ''; $msg_type = '';
if (isset($_SESSION['message'])) {
    $msg_text = $_SESSION['message']['text'];
    $msg_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}

// ---------------------------------------------------------
// 3. DATA ANALYTICS ENGINE
// ---------------------------------------------------------

// A. Top KPIs
$totalOutstanding = $pdo->query("SELECT COALESCE(SUM(total_due - paid_amount), 0) FROM loan_schedule WHERE status != 'Paid'")->fetchColumn();
$totalPrincipal = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM loans WHERE status IN ('Approved', 'Completed', 'Overdue')")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount * (interest_rate / 100)), 0) FROM loans WHERE status IN ('Approved', 'Completed')")->fetchColumn();
$pendingUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status='Pending'")->fetchColumn();

// B. Financial Flow Chart (Last 6 Months)
$chartMonths = [];
$dataDisbursed = [];
$dataCollected = [];

for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-$i months"));
    $chartMonths[] = date('M', strtotime("-$i months"));
    
    // Disbursed
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM loans WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status != 'Rejected'");
    $stmt->execute([$monthKey]);
    $dataDisbursed[] = $stmt->fetchColumn();

    // Collected
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?");
    $stmt->execute([$monthKey]);
    $dataCollected[] = $stmt->fetchColumn();
}

// C. Loan Composition
$stmt = $pdo->query("SELECT loan_type, COUNT(*) as count FROM loans GROUP BY loan_type");
$loanTypes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$typeLabels = array_keys($loanTypes);
$typeData = array_values($loanTypes);

// D. Portfolio Health
$stmt = $pdo->query("SELECT status, COUNT(*) FROM loans GROUP BY status");
$statusRaw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$statusCounts = [
    $statusRaw['Approved'] ?? 0,
    $statusRaw['Completed'] ?? 0,
    $statusRaw['Overdue'] ?? 0,
    $statusRaw['Pending'] ?? 0
];

// E. Recent Transactions
$stmt = $pdo->query("SELECT p.*, u.name as borrower_name FROM payments p JOIN loans l ON p.loan_id = l.id JOIN users u ON l.user_id = u.id ORDER BY p.payment_date DESC LIMIT 4");
$recentTransactions = $stmt->fetchAll();

// F. Users List
$users = $pdo->query("SELECT * FROM users WHERE role='borrower' ORDER BY id DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #dc2626; --primary-soft: rgba(220, 38, 38, 0.1);
            --bg-body: #f8fafc; --bg-card: #ffffff;
            --text-main: #0f172a; --text-muted: #64748b; --border: #e2e8f0;
            --sidebar-w: 250px;
        }
        [data-bs-theme="dark"] {
            --bg-body: #0f172a; --bg-card: #1e293b;
            --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; }
        
        /* Layout */
        .sidebar { 
            width: var(--sidebar-w); background: var(--bg-card); 
            border-right: 1px solid var(--border); 
            position: fixed; top:0; bottom:0; left:0; z-index: 1050; 
            display: flex; flex-direction: column; 
            transition: transform 0.3s ease-in-out;
        }
        .main-content { flex: 1; margin-left: var(--sidebar-w); padding: 24px; width: 100%; transition: margin-left 0.3s ease-in-out; }
        
        /* Backdrop for Mobile Sidebar */
        .sidebar-backdrop {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5); z-index: 1040; display: none;
        }
        .sidebar-backdrop.show { display: block; }

        /* Nav */
        .nav-link { padding: 12px 20px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 10px; border-radius: 8px; margin: 4px 12px; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background: var(--primary-soft); color: var(--primary); }
        .nav-link i { width: 20px; text-align: center; }

        /* Cards */
        .card-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); height: 100%; position: relative; }
        .card-box h6 { font-weight: 700; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .card-box h3 { font-weight: 800; margin: 0; color: var(--text-main); font-size: 1.5rem; }
        
        .icon-circle { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        
        /* Table */
        .custom-table { --bs-table-bg: transparent; --bs-table-color: var(--text-main); white-space: nowrap; }
        .custom-table th { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border); padding: 12px; }
        .custom-table td { padding: 12px; vertical-align: middle; border-bottom: 1px solid var(--border); font-size: 0.9rem; }

        /* Util */
        .text-xs { font-size: 0.75rem; }
        .bg-soft-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .bg-soft-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .bg-soft-orange { background: rgba(249, 115, 22, 0.1); color: #f97316; }
        
        /* RESPONSIVE LOGIC */
        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; } 
            .card-box { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="sidebar" id="sidebar">
    <div class="p-4 d-flex align-items-center justify-content-between text-danger fw-bold fs-5">
        <span><i class="fas fa-chart-pie me-2"></i>AdminPortal</span>
        <button class="btn btn-sm btn-light d-lg-none" id="closeSidebar"><i class="fas fa-times"></i></button>
    </div>
    <nav class="flex-grow-1">
        <a href="#" class="nav-link active"><i class="fas fa-home"></i> Overview</a>
        <a href="loans.php" class="nav-link"><i class="fas fa-file-invoice"></i> Loans</a>
        <a href="adminPayments.php" class="nav-link"><i class="fas fa-wallet"></i> Payments</a>
        <a href="verifyusers.php" class="nav-link"><i class="fas fa-users"></i> Users</a>
    </nav>
    <div class="p-3 border-top border-color">
        <a href="#" class="nav-link text-danger hover-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
    </div>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light border d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div>
                <h4 class="fw-bold mb-0">Dashboard</h4>
                <p class="text-muted small mb-0">System performance & statistics</p>
            </div>
        </div>
        <button class="btn btn-light border btn-sm rounded-circle" id="themeToggle"><i class="fas fa-moon"></i></button>
    </div>

    <?php if($msg_text): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 small">
            <i class="fas fa-info-circle me-2"></i> <?= $msg_text ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card-box d-flex justify-content-between align-items-center">
                <div style="min-width: 0;"> <h6 class="text-truncate">Net Outstanding</h6>
                    <h3 class="text-truncate">₱<?= number_format($totalOutstanding/1000, 1) ?>k</h3>
                </div>
                <div class="icon-circle bg-soft-orange"><i class="fas fa-hand-holding-usd"></i></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card-box d-flex justify-content-between align-items-center">
                <div style="min-width: 0;">
                    <h6 class="text-truncate">Total Lent</h6>
                    <h3 class="text-truncate">₱<?= number_format($totalPrincipal/1000, 1) ?>k</h3>
                </div>
                <div class="icon-circle bg-soft-blue"><i class="fas fa-paper-plane"></i></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card-box d-flex justify-content-between align-items-center">
                <div style="min-width: 0;">
                    <h6 class="text-truncate">Total Revenue</h6>
                    <h3 class="text-truncate">₱<?= number_format($totalRevenue, 0) ?></h3>
                </div>
                <div class="icon-circle bg-soft-green"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card-box d-flex justify-content-between align-items-center" style="<?= $pendingUsers>0?'border-color:#ef4444':'' ?>">
                <div>
                    <h6 class="text-truncate">Pending Users</h6>
                    <h3><?= $pendingUsers ?></h3>
                </div>
                <div class="icon-circle bg-primary-soft"><i class="fas fa-user-clock text-danger"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold m-0 text-sm">Cash Flow Analysis</h5>
                    <small class="text-muted">6 Months</small>
                </div>
                <div style="height: 280px;"><canvas id="cashFlowChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card-box">
                <h5 class="fw-bold mb-3 text-sm">Recent Transactions</h5>
                <?php if(empty($recentTransactions)): ?>
                    <div class="text-center py-4 text-muted small">No recent data.</div>
                <?php else: ?>
                    <div class="vstack gap-3">
                        <?php foreach($recentTransactions as $rt): ?>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-soft-green rounded-circle p-2 flex-shrink-0"><i class="fas fa-arrow-down text-xs"></i></div>
                                <div style="line-height: 1.1; overflow: hidden;">
                                    <div class="fw-bold text-xs text-truncate" style="max-width: 120px;"><?= htmlspecialchars($rt['borrower_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;"><?= date('M d', strtotime($rt['payment_date'])) ?></div>
                                </div>
                            </div>
                            <div class="fw-bold text-success text-sm">+₱<?= number_format($rt['amount_paid']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card-box">
                <h5 class="fw-bold mb-3 text-sm text-center">Loan Type Distribution</h5>
                <div style="height: 200px; position: relative;"><canvas id="typeChart"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-box">
                <h5 class="fw-bold mb-3 text-sm text-center">Portfolio Health</h5>
                <div style="height: 200px; position: relative;"><canvas id="statusChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="card-box p-0 overflow-hidden">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light bg-opacity-50 flex-wrap gap-2">
            <h6 class="m-0 fw-bold text-dark">User Management</h6>
            <input type="text" id="userSearch" class="form-control form-control-sm" style="width: 150px;" placeholder="Filter users...">
        </div>
        <div class="table-responsive">
            <table class="table custom-table table-hover mb-0" id="userTable">
                <thead>
                    <tr><th>User</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($u['name']) ?></div>
                            <small class="text-muted">#<?= $u['id'] ?></small>
                        </td>
                        <td>
                            <div class="text-xs"><?= htmlspecialchars($u['email']) ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars($u['phone']) ?></div>
                        </td>
                        <td>
                            <?php 
                                $cls = $u['status']=='Approved'?'success':($u['status']=='Pending'?'warning text-dark':'danger');
                            ?>
                            <span class="badge bg-<?= $cls ?> bg-opacity-10 text-<?= explode(' ',$cls)[0] ?> border border-<?= explode(' ',$cls)[0] ?>"><?= $u['status'] ?></span>
                        </td>
                        <td class="text-end">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <?php if($u['status'] === 'Pending'): ?>
                                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-light text-success border" title="Approve"><i class="fas fa-check"></i></button>
                                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-light text-danger border" title="Reject"><i class="fas fa-times"></i></button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete" class="btn btn-sm btn-light text-secondary border" onclick="return confirm('Delete?')" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
    // Sidebar Mobile Toggle Logic
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
        html.setAttribute('data-bs-theme', mode); localStorage.setItem('theme', mode);
    });

    // Search Logic
    document.getElementById('userSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        document.querySelectorAll('#userTable tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
        });
    });

    // --- CHARTS ---
    Chart.defaults.font.family = 'Plus Jakarta Sans';
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.03)';
    // MaintainAspectRatio set to false allows charts to resize within responsive containers
    const commonOptions = { responsive: true, maintainAspectRatio: false };

    // 1. Cash Flow
    new Chart(document.getElementById('cashFlowChart'), {
        data: {
            labels: <?= json_encode($chartMonths) ?>,
            datasets: [
                { type: 'bar', label: 'Lent Out', data: <?= json_encode($dataDisbursed) ?>, backgroundColor: '#dc2626', borderRadius: 4, barThickness: 12 },
                { type: 'line', label: 'Collected', data: <?= json_encode($dataCollected) ?>, borderColor: '#10b981', borderWidth: 2, tension: 0.4, pointRadius: 3, fill: true, backgroundColor: 'rgba(16, 185, 129, 0.05)' }
            ]
        },
        options: { ...commonOptions, plugins: { legend: { position: 'top', align: 'end' } }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } }
    });

    // 2. Loan Types
    new Chart(document.getElementById('typeChart'), {
        type: 'polarArea',
        data: {
            labels: <?= json_encode($typeLabels) ?>,
            datasets: [{ data: <?= json_encode($typeData) ?>, backgroundColor: ['rgba(220, 38, 38, 0.6)', 'rgba(59, 130, 246, 0.6)', 'rgba(16, 185, 129, 0.6)', 'rgba(245, 158, 11, 0.6)', 'rgba(139, 92, 246, 0.6)'], borderWidth: 1 }]
        },
        options: { ...commonOptions, plugins: { legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true } } }, scales: { r: { ticks: { display: false }, grid: { color: 'rgba(0,0,0,0.05)' } } } }
    });

    // 3. Status
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Paid', 'Overdue', 'Pending'],
            datasets: [{ data: [<?= $statusCounts[0] ?>, <?= $statusCounts[1] ?>, <?= $statusCounts[2] ?>, <?= $statusCounts[3] ?>], backgroundColor: ['#3b82f6', '#10b981', '#ef4444', '#f59e0b'], borderWidth: 0, hoverOffset: 5 }]
        },
        options: { ...commonOptions, cutout: '75%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, usePointStyle: true } } } }
    });
</script>
</body>
</html>