<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['lender_id'])) {
    header("Location: lenderLogin.php");
    exit;
}

$lender_id = $_SESSION['lender_id'];
$lender_name = $_SESSION['lender_name'] ?? 'Lender';
$year = $_GET['year'] ?? date('Y');

// 1. Financial Summary KPIs
// Total Lent (All time, valid loans)
$stmt = $pdo->prepare("SELECT SUM(amount) FROM loans WHERE lender_id = ? AND status != 'Rejected'");
$stmt->execute([$lender_id]);
$total_lent = $stmt->fetchColumn() ?: 0;

// Total Collected (Actual Payments from payments table)
$stmt = $pdo->prepare("
    SELECT SUM(p.amount_paid) 
    FROM payments p 
    JOIN loans l ON p.loan_id = l.id 
    WHERE l.lender_id = ?
");
$stmt->execute([$lender_id]);
$total_collected = $stmt->fetchColumn() ?: 0;

// Total Interest Potential (Projected)
$stmt = $pdo->prepare("SELECT SUM(amount * (interest_rate/100)) FROM loans WHERE lender_id = ? AND status != 'Rejected'");
$stmt->execute([$lender_id]);
$total_interest = $stmt->fetchColumn() ?: 0;

// Default Rate Calculation
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE lender_id = ?");
$stmt->execute([$lender_id]);
$total_count = $stmt->fetchColumn() ?: 1; // Avoid division by zero

$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE lender_id = ? AND status = 'Overdue'");
$stmt->execute([$lender_id]);
$overdue_count = $stmt->fetchColumn() ?: 0;

$default_rate = ($overdue_count / $total_count) * 100;

// 2. Chart Data: Monthly Collections (Selected Year)
$monthly_collections = [];
$months_labels = [];
for ($m = 1; $m <= 12; $m++) {
    $months_labels[] = date('M', mktime(0, 0, 0, $m, 1));
    $month_str = sprintf("%s-%02d", $year, $m);
    
    $stmt = $pdo->prepare("
        SELECT SUM(p.amount_paid) 
        FROM payments p 
        JOIN loans l ON p.loan_id = l.id 
        WHERE l.lender_id = ? AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?
    ");
    $stmt->execute([$lender_id, $month_str]);
    $monthly_collections[] = $stmt->fetchColumn() ?: 0;
}

// 3. Chart Data: Loan Status Distribution
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM loans WHERE lender_id = ? GROUP BY status");
$stmt->execute([$lender_id]);
$status_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$statuses = ['Active', 'Completed', 'Overdue', 'Pending'];
$status_counts = [];
foreach ($statuses as $s) {
    $status_counts[] = $status_data[$s] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Reports | LoanTracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #ef4444; --primary-soft: rgba(239, 68, 68, 0.1);
            --secondary: #64748b;
            --bg-body: #f8fafc; --bg-card: #ffffff; --bg-sidebar: #ffffff;
            --text-main: #0f172a; --text-muted: #64748b;
            --border: #e2e8f0; --radius: 16px; --sidebar-w: 280px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        [data-bs-theme="dark"] {
            --bg-body: #0f172a; --bg-card: #1e293b; --bg-sidebar: #1e293b;
            --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; }
        
        /* Sidebar & Layout */
        .sidebar { width: var(--sidebar-w); background: var(--bg-sidebar); border-right: 1px solid var(--border); position: fixed; top:0; bottom:0; left:0; z-index: 100; padding: 24px; display: flex; flex-direction: column; transition: transform 0.3s ease; }
        .main-content { flex: 1; margin-left: var(--sidebar-w); padding: 32px; width: 100%; }
        
        .sidebar-brand { display: flex; align-items: center; gap: 12px; font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 40px; padding: 0 12px; }
        .nav-link { padding: 12px; color: var(--text-muted); font-weight: 600; border-radius: 12px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
        .nav-link:hover { background: var(--bg-body); color: var(--primary); }
        .nav-link.active { background: linear-gradient(135deg, var(--primary), #dc2626); color: white; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2); }
        .nav-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px; padding: 0 12px; }

        /* Topbar */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .user-profile { display: flex; align-items: center; gap: 12px; background: var(--bg-card); padding: 8px 16px; border-radius: 50px; border: 1px solid var(--border); }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* Cards */
        .card-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-sm); height: 100%; }
        
        /* KPI Styles */
        .kpi-value { font-size: 2rem; font-weight: 800; margin-bottom: 4px; letter-spacing: -1px; }
        .kpi-label { color: var(--text-muted); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 16px; }

        .bg-icon-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .bg-icon-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .bg-icon-orange { background: rgba(249, 115, 22, 0.1); color: #f97316; }
        .bg-icon-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* Print Styling */
        @media print {
            .sidebar, .topbar, .no-print { display: none !important; }
            .main-content { margin: 0; padding: 0; }
            body { background: white; }
            .card-box { border: 1px solid #ddd; box-shadow: none; }
        }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-wallet fa-lg"></i> <span>LoanTracker</span>
        </div>
        <div class="nav-label">MAIN MENU</div>
        <nav class="nav flex-column mb-4">
            <a href="lenderDashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="lenderLoans.php" class="nav-link"><i class="fas fa-folder-open"></i> Portfolio</a>
            <a href="myBorrowers.php" class="nav-link"><i class="fas fa-users"></i> Borrowers</a>
        </nav>
        <div class="nav-label">ANALYTICS</div>
        <nav class="nav flex-column flex-grow-1">
            <a href="lenderReports.php" class="nav-link active"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="lenderSettings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
        </nav>
        <div class="mt-auto">
            <a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h3 class="fw-bold mb-1">Financial Reports</h3>
                <p class="text-muted mb-0">Detailed breakdown of your lending performance.</p>
            </div>
            <div class="d-flex gap-3 align-items-center">
                 <button class="btn btn-light border rounded-circle" id="themeToggle" style="width:40px;height:40px;"><i class="fas fa-moon text-muted"></i></button>
                 <button class="btn btn-primary d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('active')"><i class="fas fa-bars"></i></button>
                 <div class="user-profile d-none d-md-flex">
                    <div class="text-end">
                        <div class="fw-bold text-sm"><?= htmlspecialchars($lender_name) ?></div>
                        <div class="text-muted text-xs">Lender</div>
                    </div>
                    <div class="avatar"><?= strtoupper(substr($lender_name, 0, 1)) ?></div>
                 </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div class="d-flex align-items-center gap-2 bg-white border rounded-pill p-1 ps-3 shadow-sm">
                <span class="fw-bold text-xs text-muted text-uppercase">Fiscal Year:</span>
                <form id="yearForm" class="m-0">
                    <select name="year" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary py-0" onchange="this.form.submit()" style="box-shadow:none; cursor:pointer;">
                        <?php for($y = date('Y'); $y >= date('Y')-4; $y--): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
            <button class="btn btn-light border fw-bold text-muted shadow-sm no-print" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print / PDF
            </button>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card-box border-bottom border-4 border-primary">
                    <div class="kpi-icon-box bg-icon-blue"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="kpi-label">Total Lent</div>
                    <div class="kpi-value">₱<?= number_format($total_lent, 0) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-box border-bottom border-4 border-success">
                    <div class="kpi-icon-box bg-icon-green"><i class="fas fa-coins"></i></div>
                    <div class="kpi-label">Total Collected</div>
                    <div class="kpi-value text-success">₱<?= number_format($total_collected, 0) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-box border-bottom border-4 border-warning">
                    <div class="kpi-icon-box bg-icon-orange"><i class="fas fa-chart-line"></i></div>
                    <div class="kpi-label">Est. Profit</div>
                    <div class="kpi-value text-warning">₱<?= number_format($total_interest, 0) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-box border-bottom border-4 border-danger">
                    <div class="kpi-icon-box bg-icon-red"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="kpi-label">Default Rate</div>
                    <div class="kpi-value text-danger"><?= number_format($default_rate, 1) ?>%</div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card-box">
                    <h5 class="fw-bold mb-4 text-sm">Monthly Cash Flow (<?= $year ?>)</h5>
                    <div style="height: 320px;">
                        <canvas id="collectionsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card-box h-100">
                    <h5 class="fw-bold mb-4 text-sm">Portfolio Health</h5>
                    <div style="height: 250px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="mt-4 text-center text-xs text-muted p-3 bg-light rounded">
                        <i class="fas fa-info-circle me-1"></i> Breakdown of all loan records by current status.
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        // Theme Logic
        const html = document.documentElement;
        const themeBtn = document.getElementById('themeToggle');
        if(localStorage.getItem('theme')==='dark'){ html.setAttribute('data-bs-theme', 'dark'); }
        themeBtn.addEventListener('click', () => {
            const mode = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', mode);
            localStorage.setItem('theme', mode);
        });

        // Chart Global Config
        Chart.defaults.font.family = 'Plus Jakarta Sans';
        Chart.defaults.color = '#64748b';
        Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.03)';

        // 1. Bar Chart (Collections)
        const ctxColl = document.getElementById('collectionsChart').getContext('2d');
        let gradBar = ctxColl.createLinearGradient(0, 0, 0, 400);
        gradBar.addColorStop(0, '#10b981');
        gradBar.addColorStop(1, '#059669');

        new Chart(ctxColl, {
            type: 'bar',
            data: {
                labels: <?= json_encode($months_labels) ?>,
                datasets: [{
                    label: 'Collected',
                    data: <?= json_encode($monthly_collections) ?>,
                    backgroundColor: gradBar,
                    borderRadius: 6,
                    barThickness: 16
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                }
            }
        });

        // 2. Doughnut Chart (Status)
        const ctxStat = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStat, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statuses) ?>,
                datasets: [{
                    data: <?= json_encode($status_counts) ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#ef4444', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 11 } } }
                }
            }
        });
    </script>
</body>
</html>