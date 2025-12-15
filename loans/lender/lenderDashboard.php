<?php
session_start();
require_once "../db.php";

// Security
if (!isset($_SESSION['lender_id'])) {
    header("Location: lenderLogin.php");
    exit;
}

$lender_id = $_SESSION['lender_id'];
$lender_name = $_SESSION['lender_name'] ?? 'Lender';

// --- NOTIFICATION LOGIC ---
$notif_stmt = $pdo->prepare("
    SELECT l.id, l.amount, l.created_at, u.name as borrower_name 
    FROM loans l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.lender_id = ? AND l.status = 'Pending' 
    ORDER BY l.created_at DESC
");
$notif_stmt->execute([$lender_id]);
$notifications = $notif_stmt->fetchAll();
$unread_count = count($notifications);
// --------------------------

// KPI Data
$total_invested = $pdo->prepare("SELECT SUM(amount) FROM loans WHERE lender_id = ? AND status IN ('Active','Completed','Overdue')");
$total_invested->execute([$lender_id]);
$total_invested = $total_invested->fetchColumn() ?: 0;

$proj_interest = $pdo->prepare("SELECT SUM(amount*(interest_rate/100)) FROM loans WHERE lender_id = ? AND status IN ('Active','Completed','Overdue')");
$proj_interest->execute([$lender_id]);
$proj_interest = $proj_interest->fetchColumn() ?: 0;

$active_borrowers = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM loans WHERE lender_id = ? AND status='Active'");
$active_borrowers->execute([$lender_id]);
$active_borrowers = $active_borrowers->fetchColumn() ?: 0;

$overdue_loans = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE lender_id=? AND status='Overdue'");
$overdue_loans->execute([$lender_id]);
$overdue_loans = $overdue_loans->fetchColumn() ?: 0;

// Chart Data (Last 6 months)
$months=[]; $volumes=[];
for($i=5;$i>=0;$i--){
    $m=date('Y-m',strtotime("-$i months"));
    $months[]=date('M',strtotime("-$i months"));
    $stmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM loans WHERE lender_id=? AND DATE_FORMAT(created_at,'%Y-%m')=?");
    $stmt->execute([$lender_id,$m]);
    $volumes[]=$stmt->fetchColumn();
}

// Recent Activity
$stmt=$pdo->prepare("
SELECT l.*, u.name as borrower_name
FROM loans l
JOIN users u ON l.user_id=u.id
WHERE l.lender_id=?
ORDER BY l.created_at DESC
LIMIT 6
");
$stmt->execute([$lender_id]);
$recent_activity=$stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | LoanTracker</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{
--primary:#ef4444; --primary-dark:#dc2626; --secondary:#64748b; --success:#10b981; --warning:#f59e0b; --info:#3b82f6;
--bg-body:#f8fafc; --bg-card:#fff; --bg-sidebar:#fff; --text-main:#0f172a; --text-muted:#64748b; 
--border:#e2e8f0; --radius:16px; --sidebar-w:280px; --shadow-sm:0 1px 2px rgba(0,0,0,0.05);
--shadow-md:0 4px 6px rgba(0,0,0,0.1); --shadow-lg:0 10px 15px rgba(0,0,0,0.1); --glass:rgba(255,255,255,0.8);
}
[data-bs-theme="dark"]{
--bg-body:#0f172a; --bg-card:#1e293b; --bg-sidebar:#1e293b;
--text-main:#f8fafc; --text-muted:#94a3b8; --border:#334155; --glass:rgba(30,41,59,0.8);
}
body{font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg-body); color:var(--text-main); min-height:100vh; display:flex;}

/* SIDEBAR - Fixed Display Flex */
.sidebar{
    width:var(--sidebar-w); background:var(--bg-sidebar); border-right:1px solid var(--border); 
    position:fixed; top:0; bottom:0; left:0; z-index:100; padding:24px; 
    display: flex; /* ADDED THIS */
    flex-direction:column; 
    transition: transform 0.3s ease;
}
.sidebar-brand{display:flex;align-items:center;gap:12px;font-size:1.25rem;font-weight:800;color:var(--primary);margin-bottom:40px;padding:0 12px;}
.nav-label{font-size:0.7rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.5px;margin-bottom:12px;padding:0 12px;}
.nav-link{padding:12px;color:var(--text-muted);font-weight:600;border-radius:12px;margin-bottom:4px;transition:all 0.2s;display:flex;align-items:center;gap:12px;}
.nav-link:hover{background-color:var(--bg-body);color:var(--primary);transform:translateX(5px);}
.nav-link.active{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;box-shadow:var(--shadow-md);}
.nav-link i{width:20px;text-align:center;}

/* OVERLAY */
#sidebarOverlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:99;transition:opacity 0.3s ease;}

/* MAIN CONTENT */
.main-content{flex:1;margin-left:var(--sidebar-w);padding:32px;width:100%;}

/* TOPBAR */
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;}
.user-profile{display:flex;align-items:center;gap:16px;background:var(--bg-card);padding:8px 16px;border-radius:50px;border:1px solid var(--border);box-shadow:var(--shadow-sm);}
.avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#fca5a5);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;}

/* --- NOTIFICATION STYLES --- */
.notif-btn {
    position: relative;
    width: 45px; height: 45px;
    border-radius: 50%;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
    cursor: pointer;
}
.notif-btn:hover { background: var(--bg-body); color: var(--primary); }
.notif-badge {
    position: absolute; top: -2px; right: -2px;
    background: var(--primary); color: white;
    font-size: 0.65rem; font-weight: 800;
    width: 18px; height: 18px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--bg-body);
}
.notif-dropdown {
    position: absolute; top: 100%; right: 0;
    width: 320px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    z-index: 1000;
    display: none;
    margin-top: 10px;
    overflow: hidden;
}
.notif-dropdown.show { display: block; animation: slideDown 0.2s ease; }
.notif-item {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    display: flex; gap: 12px;
    text-decoration: none; color: var(--text-main);
    transition: background 0.2s;
}
.notif-item:hover { background: var(--bg-body); }
.notif-item:last-child { border-bottom: none; }
.notif-icon {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.1); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
@keyframes slideDown { from {opacity:0; transform:translateY(-10px);} to {opacity:1; transform:translateY(0);} }

/* KPI CARDS */
.kpi-card{background:var(--bg-card);border-radius:var(--radius);border:1px solid var(--border);padding:24px;position:relative;overflow:hidden;transition:all 0.3s;height:100%;}
.kpi-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-lg);border-color:var(--primary);}
.kpi-icon-wrapper{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:20px;}
.bg-icon-red{background:rgba(239,68,68,0.1);color:var(--primary);}
.bg-icon-blue{background:rgba(59,130,246,0.1);color:var(--info);}
.bg-icon-green{background:rgba(16,185,129,0.1);color:var(--success);}
.bg-icon-orange{background:rgba(245,158,11,0.1);color:var(--warning);}
.kpi-value{font-size:2rem;font-weight:800;margin-bottom:4px;letter-spacing:-1px;}
.kpi-label{color:var(--text-muted);font-weight:600;font-size:0.9rem;}

/* CONTENT SECTIONS */
.section-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow-sm);height:100%;}
.card-header-flex{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
.card-title{font-weight:700;font-size:1.1rem;margin:0;}

/* ACTIVITY FEED */
.activity-list{display:flex;flex-direction:column;gap:16px;}
.activity-item{display:flex;align-items:center;gap:16px;padding:12px;border-radius:12px;transition:background 0.2s;}
.activity-item:hover{background-color:var(--bg-body);}
.activity-icon-box{width:44px;height:44px;border-radius:12px;background:var(--bg-body);color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:1.1rem;border:1px solid var(--border);}
.activity-meta{flex:1;}
.activity-amount{font-weight:700;color:var(--text-main);}

/* RESPONSIVE */
@media(max-width:992px){
.sidebar{transform:translateX(-100%);position:fixed;height:100%;z-index:100;}
.sidebar.active{transform:translateX(0);}
.main-content{margin-left:0;padding:20px;}
.row.g-4.mb-5>.col-xl-3{margin-bottom:20px;}
.row.g-4>.col-xl-8,.row.g-4>.col-xl-4{margin-bottom:20px;}
.notif-dropdown { right: -50px; width: 300px; }
}
</style>
</head>
<body>
<div id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand"><i class="fas fa-wallet fa-lg"></i> <span>LoanTracker</span></div>
    <div class="nav-label">MAIN MENU</div>
    <nav class="nav flex-column mb-4">
        <a href="lenderDashboard.php" class="nav-link active"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="lenderLoans.php" class="nav-link"><i class="fas fa-folder-open"></i> Portfolio</a>
        <a href="myBorrowers.php" class="nav-link"><i class="fas fa-users"></i> Borrowers</a>
    </nav>
    <div class="nav-label">ANALYTICS & SETTINGS</div>
    <nav class="nav flex-column flex-grow-1">
        <a href="lenderReports.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports</a>
        <a href="lenderSettings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
    </nav>
    <div class="mt-auto">
        <a href="#" class="nav-link text-danger hover-bg-danger-soft" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
    </div>
</aside>

<main class="main-content">
    <div class="topbar">
        <div class="welcome-text"><h3>Dashboard</h3><p>Overview of your financial performance.</p></div>
        <div class="d-flex align-items-center gap-3">
            
            <div class="position-relative">
                <button class="notif-btn" id="notifToggle">
                    <i class="fas fa-bell"></i>
                    <?php if($unread_count > 0): ?>
                        <span class="notif-badge"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                    <?php endif; ?>
                </button>
                
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold">Notifications</h6>
                        <?php if($unread_count > 0): ?>
                            <small class="text-primary fw-bold"><?= $unread_count ?> Pending</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notif-list" style="max-height: 300px; overflow-y: auto;">
                        <?php if(empty($notifications)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="far fa-bell-slash fa-2x mb-2 opacity-50"></i>
                                <p class="text-xs m-0">No pending requests</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($notifications as $n): ?>
                                <a href="lenderLoans.php?status=Pending" class="notif-item">
                                    <div class="notif-icon"><i class="fas fa-user-plus"></i></div>
                                    <div>
                                        <div class="text-sm fw-bold">New Loan Request</div>
                                        <div class="text-xs text-muted">
                                            <?= htmlspecialchars($n['borrower_name']) ?> requests 
                                            <span class="text-dark fw-bold">₱<?= number_format($n['amount']) ?></span>
                                        </div>
                                        <div class="text-xs text-muted mt-1">
                                            <?= date('M d, h:i A', strtotime($n['created_at'])) ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="p-2 text-center border-top bg-light">
                        <a href="lenderLoans.php?status=Pending" class="text-xs fw-bold text-decoration-none">View All Requests</a>
                    </div>
                </div>
            </div>
            
            <button class="btn btn-primary d-lg-none" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div class="user-profile">
                <div class="text-end d-none d-md-block">
                    <div class="fw-bold text-sm"><?= htmlspecialchars($lender_name) ?></div>
                    <div class="text-muted text-xs">Investor</div>
                </div>
                <div class="avatar"><?= strtoupper(substr($lender_name,0,1)) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card"><div class="d-flex justify-content-between align-items-start"><div><div class="kpi-label">Total Invested</div><div class="kpi-value">₱<?= number_format($total_invested/1000,1) ?>k</div><div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 text-xs"><i class="fas fa-arrow-up me-1"></i> Active</div></div><div class="kpi-icon-wrapper bg-icon-blue"><i class="fas fa-wallet"></i></div></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card"><div class="d-flex justify-content-between align-items-start"><div><div class="kpi-label">Proj. Returns</div><div class="kpi-value text-success">₱<?= number_format($proj_interest,0) ?></div><div class="text-xs text-muted mt-1">Based on active loans</div></div><div class="kpi-icon-wrapper bg-icon-green"><i class="fas fa-chart-line"></i></div></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card"><div class="d-flex justify-content-between align-items-start"><div><div class="kpi-label">Active Clients</div><div class="kpi-value"><?= $active_borrowers ?></div><div class="text-xs text-muted mt-1">Unique borrowers</div></div><div class="kpi-icon-wrapper bg-icon-orange"><i class="fas fa-users"></i></div></div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card" style="<?= $overdue_loans>0?'border-color:var(--primary);box-shadow:0 0 0 4px rgba(239,68,68,0.1)':'' ?>"><div class="d-flex justify-content-between align-items-start"><div><div class="kpi-label">Action Required</div><div class="kpi-value text-danger"><?= $overdue_loans ?></div><div class="text-xs text-danger mt-1 fw-bold">Overdue Loans</div></div><div class="kpi-icon-wrapper bg-icon-red"><i class="fas fa-exclamation-circle"></i></div></div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="section-card">
                <div class="card-header-flex"><div><h5 class="card-title">Capital Deployment</h5><small class="text-muted">Monthly investment volume trends</small></div></div>
                <div style="height:320px;position:relative;"><canvas id="volumeChart"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="section-card">
                <div class="card-header-flex"><h5 class="card-title">Recent Activity</h5><a href="lenderLoans.php" class="text-decoration-none text-xs fw-bold text-primary">View All</a></div>
                <?php if(empty($recent_activity)): ?>
                    <div class="text-center py-5 text-muted"><i class="fas fa-inbox fs-1 mb-2 opacity-25"></i><p class="small">No recent activity.</p></div>
                <?php else: ?>
                <div class="activity-list">
                <?php foreach($recent_activity as $act): ?>
                    <div class="activity-item">
                        <div class="activity-icon-box"><i class="fas fa-file-invoice text-primary"></i></div>
                        <div class="activity-meta">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-sm text-dark"><?= htmlspecialchars($act['borrower_name']) ?></span>
                                <span class="activity-amount text-xs text-success">+₱<?= number_format($act['amount']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-dark border rounded-pill text-xs" style="font-weight:500;"><?= htmlspecialchars($act['loan_type']) ?></span>
                                <small class="text-xs text-muted"><?= date('M d', strtotime($act['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <button class="btn btn-light w-100 mt-4 text-primary fw-bold text-sm border-0 bg-primary bg-opacity-10">Full Transaction History</button>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold text-danger">Sign Out</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
            <i class="fas fa-sign-out-alt fs-2"></i>
        </div>
        <h6 class="fw-bold mb-2">Are you sure you want to leave?</h6>
        <p class="text-muted small mb-0">You will need to login again to access your dashboard.</p>
      </div>
      <div class="modal-footer border-top-0 pt-0 justify-content-center pb-4">
        <button type="button" class="btn btn-light px-4 fw-bold border" data-bs-dismiss="modal">Cancel</button>
        <a href="../logout.php" class="btn btn-danger px-4 fw-bold">Logout</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const html=document.documentElement;
const themeBtn=document.getElementById('themeToggle');
if(localStorage.getItem('theme')==='dark'){html.setAttribute('data-bs-theme','dark');}
themeBtn?.addEventListener('click',()=>{const mode=html.getAttribute('data-bs-theme')==='dark'?'light':'dark';html.setAttribute('data-bs-theme',mode);localStorage.setItem('theme',mode);});

const sidebar=document.getElementById('sidebar');
const overlay=document.getElementById('sidebarOverlay');
const toggleBtn=document.getElementById('sidebarToggle');
toggleBtn?.addEventListener('click',()=>{sidebar.classList.add('active');overlay.style.display='block';});
overlay.addEventListener('click',()=>{sidebar.classList.remove('active');overlay.style.display='none';});

// Notification Logic
const notifBtn = document.getElementById('notifToggle');
const notifDrop = document.getElementById('notifDropdown');
notifBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    notifDrop.classList.toggle('show');
});
window.addEventListener('click', (e) => {
    if (!notifDrop.contains(e.target) && e.target !== notifBtn) {
        notifDrop.classList.remove('show');
    }
});

// Chart
const ctx=document.getElementById('volumeChart').getContext('2d');
let gradient=ctx.createLinearGradient(0,0,0,400);
gradient.addColorStop(0,'rgba(239,68,68,0.2)'); gradient.addColorStop(1,'rgba(239,68,68,0)');
new Chart(ctx,{
    type:'line',
    data:{labels:<?= json_encode($months) ?>, datasets:[{label:'Invested',data:<?= json_encode($volumes) ?>,borderColor:'#ef4444',backgroundColor:gradient,borderWidth:3,pointBackgroundColor:'#fff',pointBorderColor:'#ef4444',pointBorderWidth:2,pointRadius:5,pointHoverRadius:7,fill:true,tension:0.4}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(15,23,42,0.9)',padding:12,titleFont:{family:'Plus Jakarta Sans',size:13},bodyFont:{family:'Plus Jakarta Sans',size:13},cornerRadius:8,displayColors:false}},scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.04)',borderDash:[5,5]},border:{display:false},ticks:{font:{family:'Plus Jakarta Sans'}}},x:{grid:{display:false},border:{display:false},ticks:{font:{family:'Plus Jakarta Sans'}}}}}
});
</script>
</body>
</html>