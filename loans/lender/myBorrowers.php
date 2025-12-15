<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['lender_id'])) {
    header("Location: lenderLogin.php");
    exit;
}

$lender_id = $_SESSION['lender_id'];
$lender_name = $_SESSION['lender_name'] ?? 'Lender';

// --- Handle Reminder ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remind_user_id'])) {
    $target_id = intval($_POST['remind_user_id']);
    $message = "Payment Reminder: Please check your outstanding balance with Lender " . htmlspecialchars($lender_name) . ".";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'borrower', 0, NOW())");
    if ($stmt->execute([$target_id, $message])) {
        $_SESSION['message'] = ['text' => "Reminder sent successfully!", 'type' => 'success'];
    } else {
        $_SESSION['message'] = ['text' => "Failed to send reminder.", 'type' => 'danger'];
    }
    header("Location: myBorrowers.php");
    exit;
}

// Flash message
$msg_text = ''; $msg_type = '';
if (isset($_SESSION['message'])) {
    $msg_text = $_SESSION['message']['text'];
    $msg_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}

// Fetch Borrowers
$sql = "
    SELECT 
        u.id, u.name, u.email, u.phone, u.created_at,
        COUNT(l.id) as total_loans_count,
        SUM(l.amount) as total_borrowed_amount,
        SUM(CASE WHEN l.status = 'Active' THEN 1 ELSE 0 END) as active_loans_count
    FROM users u
    JOIN loans l ON u.id = l.user_id
    WHERE l.lender_id = ?
    GROUP BY u.id
    ORDER BY u.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$lender_id]);
$borrowers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<title>My Borrowers | LoanTracker</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --primary: #ef4444;
    --bg-body: #f8fafc; --bg-card: #fff; --bg-sidebar: #fff;
    --text-main: #0f172a; --text-muted: #64748b;
    --border: #e2e8f0; --radius: 16px; --sidebar-w: 280px;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05); --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
}
[data-bs-theme="dark"] {
    --bg-body: #0f172a; --bg-card: #1e293b; --bg-sidebar: #1e293b;
    --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
}
body {
    font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg-body); color:var(--text-main);
    display:flex; min-height:100vh;
    overflow-x:hidden;
}
/* Sidebar */
.sidebar {
    width: var(--sidebar-w); background: var(--bg-sidebar); border-right: 1px solid var(--border);
    position: fixed; top:0; bottom:0; left:0; z-index:1050; padding:24px; display:flex; flex-direction:column;
    transition: transform 0.3s ease;
}
.sidebar-brand { display:flex; align-items:center; gap:12px; font-size:1.25rem; font-weight:800; color:var(--primary); margin-bottom:40px; }
.nav-link { padding:12px; color:var(--text-muted); font-weight:600; border-radius:12px; margin-bottom:4px; display:flex; align-items:center; gap:12px; transition:0.2s; }
.nav-link:hover { background:var(--bg-body); color:var(--primary); }
.nav-link.active { background: linear-gradient(135deg,var(--primary),#dc2626); color:white; box-shadow:0 4px 6px -1px rgba(239,68,68,0.2); }
.nav-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:12px; }

/* Overlay for mobile */
.sidebar-overlay {
    position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.4); z-index:1040; display:none;
}

/* Main content */
.main-content { flex:1; margin-left: var(--sidebar-w); padding:32px; width:100%; transition: margin 0.3s; }

/* Borrower card */
.borrower-card { background: var(--bg-card); border:1px solid var(--border); border-radius: var(--radius); padding:24px; display:flex; flex-direction:column; height:100%; transition: all 0.2s; }
.borrower-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--primary); }
.profile-img { width:80px; height:80px; border-radius:50%; background: linear-gradient(135deg,#e2e8f0,#cbd5e1); display:flex; align-items:center; justify-content:center; font-size:2rem; color:#64748b; font-weight:800; margin:0 auto 12px; border:4px solid var(--bg-body); box-shadow: var(--shadow-sm); }
.status-badge { padding:4px 12px; border-radius:50px; font-size:0.75rem; font-weight:700; }
.status-active { background: rgba(16,185,129,0.1); color:#10b981; }
.status-inactive { background: rgba(100,116,139,0.1); color:#64748b; }
.dot { width:6px; height:6px; border-radius:50%; background:currentColor; display:inline-block; margin-right:4px; }
.action-btn { flex:1; font-size:0.85rem; font-weight:600; border-radius:8px; }

/* Mobile responsive */
@media (max-width:992px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.show { transform: translateX(0); }
    .sidebar-overlay.show { display:block; }
    .main-content { margin-left:0; padding:20px; }
}
</style>
</head>
<body>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand"><i class="fas fa-wallet fa-lg"></i> LoanTracker</div>
    <div class="nav-label">MAIN MENU</div>
    <nav class="nav flex-column mb-4">
        <a href="lenderDashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="lenderLoans.php" class="nav-link"><i class="fas fa-folder-open"></i> Portfolio</a>
        <a href="myBorrowers.php" class="nav-link active"><i class="fas fa-users"></i> Borrowers</a>
    </nav>
    <div class="nav-label">ANALYTICS</div>
    <nav class="nav flex-column flex-grow-1">
        <a href="lenderReports.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports</a>
        <a href="lenderSettings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
    </nav>
    <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a></div>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Client Directory</h3>
            <p class="text-muted mb-0">Manage your relationships.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-primary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <button class="btn btn-light border rounded-circle" id="themeToggle"><i class="fas fa-moon text-muted"></i></button>
        </div>
    </div>

    <?php if($msg_text): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 small">
            <i class="fas fa-info-circle me-2"></i> <?= $msg_text ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if(count($borrowers) > 0): ?>
            <?php foreach($borrowers as $b): ?>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="borrower-card">
                    <div class="text-center mb-3">
                        <div class="profile-img"><?= strtoupper(substr($b['name'],0,1)) ?></div>
                        <h6 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($b['name']) ?></h6>
                        <div class="text-xs text-muted mb-2 text-truncate"><?= htmlspecialchars($b['email']) ?></div>
                        <?php if($b['active_loans_count']>0): ?>
                            <span class="status-badge status-active"><span class="dot"></span> Active</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive"><span class="dot"></span> Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div class="bg-light rounded p-3 mb-3 text-center border border-opacity-10">
                        <small class="text-muted d-block text-uppercase text-xs fw-bold">Total Borrowed</small>
                        <span class="fw-bold text-primary h5 mb-0">₱<?= number_format($b['total_borrowed_amount']) ?></span>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <a href="mailto:<?= htmlspecialchars($b['email']) ?>" class="btn btn-light border action-btn"><i class="fas fa-envelope"></i></a>
                        <form method="POST" class="d-inline flex-grow-1">
                            <input type="hidden" name="remind_user_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger w-100 action-btn fw-bold"><i class="fas fa-bell me-1"></i> Remind</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 text-muted">No borrowers found.</div>
        <?php endif; ?>
    </div>
</main>

<script>
const html = document.documentElement;
const themeBtn = document.getElementById('themeToggle');
if(localStorage.getItem('theme')==='dark'){ html.setAttribute('data-bs-theme','dark'); }
themeBtn.addEventListener('click',()=>{ 
    const mode = html.getAttribute('data-bs-theme')==='dark'?'light':'dark';
    html.setAttribute('data-bs-theme',mode);
    localStorage.setItem('theme',mode);
});

// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
document.getElementById('sidebarToggle').addEventListener('click', () => {
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
});
overlay.addEventListener('click', () => {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
