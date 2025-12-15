<?php
require_once __DIR__ . '/../db.php';
session_start();

// Admin check
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../adminLogin.php");
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid = (int)($_POST['user_id'] ?? 0);
    $type = $_POST['user_type'] ?? 'borrower'; 

    $table = ($type === 'lender') ? 'lenders' : 'users';

    if ($action === 'approve') {
        // 1. Update Status
        $stmt = $pdo->prepare("UPDATE $table SET status = 'Approved' WHERE id = ?");
        $stmt->execute([$uid]);
        
        // 2. Send Notification
        $notifMsg = "Congratulations! Your account has been approved. You can now access full features.";
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmtNotif->execute([$uid, $notifMsg]);

        $_SESSION['message'] = ['text' => "User approved successfully.", 'type' => 'success'];

    } elseif ($action === 'reject') {
        // 1. Update Status
        $stmt = $pdo->prepare("UPDATE $table SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$uid]);

        // 2. Send Notification
        $notifMsg = "Your account application has been rejected. Please contact support for details.";
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmtNotif->execute([$uid, $notifMsg]);

        $_SESSION['message'] = ['text' => "User rejected.", 'type' => 'warning'];

    } elseif ($action === 'edit') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);

        // 1. Update Details
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE $table SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $hashed, $uid]);
        } else {
            $stmt = $pdo->prepare("UPDATE $table SET name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $uid]);
        }

        // 2. Send Notification
        $notifMsg = "Your account details have been updated by the administrator.";
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmtNotif->execute([$uid, $notifMsg]);

        $_SESSION['message'] = ['text' => "User details updated.", 'type' => 'info'];
    }
    header("Location: verifyusers.php");
    exit;
}

// Flash Messages
$msg_text = ''; $msg_type = '';
if (isset($_SESSION['message'])) {
    $msg_text = $_SESSION['message']['text'];
    $msg_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}

// Fetch Data
$sqlBorrowers = "SELECT id, name, email, phone, created_at, id_path, proof_path, selfie_path, status, 'borrower' as type FROM users";
$sqlLenders = "SELECT id, name, email, phone, created_at, NULL as id_path, NULL as proof_path, NULL as selfie_path, status, 'lender' as type FROM lenders";

try {
    $stmt = $pdo->query($sqlBorrowers);
    $borrowers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query($sqlLenders);
    $lenders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $borrowers = []; $lenders = []; }

$users = array_merge($borrowers, $lenders);

// Filter & Sort (Pending first)
usort($users, function($a, $b) {
    if ($a['status'] == 'Pending' && $b['status'] != 'Pending') return -1;
    if ($a['status'] != 'Pending' && $b['status'] == 'Pending') return 1;
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// KPI Counts
$totalUsers = count($users);
$pendingCount = count(array_filter($users, fn($u) => $u['status'] == 'Pending'));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Users | Admin</title>
    
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
        
        /* Layout */
        .sidebar { 
            width: var(--sidebar-w); background: var(--bg-sidebar); 
            border-right: 1px solid var(--border); 
            position: fixed; top:0; bottom:0; left:0; z-index: 1050; 
            display: flex; flex-direction: column; 
            transition: transform 0.3s ease-in-out;
        }
        .main-content { flex: 1; margin-left: var(--sidebar-w); padding: 30px; width: 100%; transition: margin-left 0.3s ease-in-out; }

        /* Backdrop */
        .sidebar-backdrop {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5); z-index: 1040; display: none;
        }
        .sidebar-backdrop.show { display: block; }

        /* Nav */
        .sidebar-brand { padding: 24px; font-size: 1.25rem; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .nav-link { padding: 12px 24px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 12px; transition: 0.2s; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { background: var(--primary-soft); color: var(--primary); border-left-color: var(--primary); }

        /* User Card */
        .user-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
            box-shadow: var(--shadow-sm); overflow: hidden; height: 100%; display: flex; flex-direction: column;
            transition: transform 0.2s;
        }
        .user-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        
        .card-header-custom { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: start; background: rgba(0,0,0,0.01); }
        .card-body-custom { padding: 20px; flex: 1; }
        .card-footer-custom { padding: 16px 20px; border-top: 1px solid var(--border); background: var(--bg-body); }

        /* Badges */
        .role-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .role-borrower { background: rgba(59, 130, 246, 0.1); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.2); }
        .role-lender { background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.2); }
        
        .status-badge { padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .status-Pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .status-Approved { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .status-Rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

        /* Documents */
        .doc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px; }
        .doc-item {
            position: relative; aspect-ratio: 1/1; border-radius: 8px; overflow: hidden; border: 1px solid var(--border);
            background: var(--bg-body); display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .doc-item:hover { border-color: var(--primary); transform: scale(1.02); }
        .doc-item img { width: 100%; height: 100%; object-fit: cover; }
        .doc-label {
            position: absolute; bottom: 0; left: 0; width: 100%; background: rgba(0,0,0,0.7); color: white;
            font-size: 0.6rem; text-align: center; padding: 2px 0; font-weight: 600;
        }

        /* Forms */
        .form-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px; }
        .form-control, .form-select { padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background-color: var(--bg-body); color: var(--text-main); font-size: 0.9rem; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }

        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; } 
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
        <a href="loans.php" class="nav-link"><i class="fas fa-file-contract"></i> Loans</a>
        <a href="adminPayments.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> Payments</a>
        <a href="#" class="nav-link active"><i class="fas fa-users-cog"></i> Verification</a>
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
                <h4 class="fw-bold mb-0">User Verification</h4>
                <p class="text-muted small mb-0">Review documents and approve accounts.</p>
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

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="bg-white border p-3 rounded-3 shadow-sm d-flex align-items-center justify-content-between h-100">
                <div>
                    <div class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Pending Users</div>
                    <div class="h4 fw-bold mb-0 text-warning"><?= $pendingCount ?></div>
                </div>
                <i class="fas fa-clock text-warning fs-3 opacity-25"></i>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="bg-white border p-3 rounded-3 shadow-sm d-flex align-items-center justify-content-between h-100">
                <div>
                    <div class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Total Users</div>
                    <div class="h4 fw-bold mb-0 text-primary"><?= $totalUsers ?></div>
                </div>
                <i class="fas fa-users text-primary fs-3 opacity-25"></i>
            </div>
        </div>
    </div>

    <?php if(empty($users)): ?>
        <div class="text-center py-5 text-muted bg-white rounded-3 border">
            <i class="fas fa-check-circle fs-1 mb-3 text-success opacity-50"></i>
            <h6 class="fw-bold">All Caught Up!</h6>
            <p class="small">No users found.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
        <?php foreach($users as $u): ?>
            <div class="col-xl-4 col-md-6">
                <div class="user-card">
                    <div class="card-header-custom">
                        <div>
                            <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($u['name']) ?></h6>
                            <span class="role-badge role-<?= strtolower($u['type']) ?>"><?= ucfirst($u['type']) ?></span>
                        </div>
                        <span class="status-badge status-<?= $u['status'] ?>">
                            <span class="dot"></span> <?= $u['status'] ?>
                        </span>
                    </div>
                    
                    <div class="card-body-custom">
                        <div class="d-flex flex-column gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2 text-sm text-muted">
                                <i class="fas fa-envelope fa-fw text-primary opacity-50"></i> 
                                <span class="text-truncate"><?= htmlspecialchars($u['email']) ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 text-sm text-muted">
                                <i class="fas fa-phone fa-fw text-primary opacity-50"></i> <?= htmlspecialchars($u['phone']) ?>
                            </div>
                            <div class="d-flex align-items-center gap-2 text-sm text-muted">
                                <i class="fas fa-calendar fa-fw text-primary opacity-50"></i> <?= date('M d, Y', strtotime($u['created_at'])) ?>
                            </div>
                        </div>

                        <?php if($u['type'] === 'borrower'): ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-xs fw-bold text-uppercase text-muted">Documents</span>
                                <?php if(empty($u['id_path']) && empty($u['proof_path'])): ?>
                                    <span class="badge bg-light text-muted border text-xs">None</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="doc-grid">
                                <?php if(!empty($u['id_path'])): ?>
                                    <a href="../<?= htmlspecialchars($u['id_path']) ?>" target="_blank" class="doc-item">
                                        <img src="../<?= htmlspecialchars($u['id_path']) ?>">
                                        <div class="doc-label">VALID ID</div>
                                    </a>
                                <?php endif; ?>
                                <?php if(!empty($u['proof_path'])): ?>
                                    <a href="../<?= htmlspecialchars($u['proof_path']) ?>" target="_blank" class="doc-item">
                                        <img src="../<?= htmlspecialchars($u['proof_path']) ?>">
                                        <div class="doc-label">PROOF</div>
                                    </a>
                                <?php endif; ?>
                                <?php if(!empty($u['selfie_path'])): ?>
                                    <a href="../<?= htmlspecialchars($u['selfie_path']) ?>" target="_blank" class="doc-item">
                                        <img src="../<?= htmlspecialchars($u['selfie_path']) ?>">
                                        <div class="doc-label">SELFIE</div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-3 bg-light rounded-3 text-center border border-dashed mt-3">
                                <i class="fas fa-briefcase text-muted mb-2"></i>
                                <p class="text-xs text-muted mb-0">Lenders do not require document verification.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer-custom">
                        <form method="post" class="d-flex gap-2 w-100">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="user_type" value="<?= $u['type'] ?>">
                            
                            <?php if($u['status'] === 'Pending'): ?>
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm flex-grow-1 fw-bold shadow-sm">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm fw-bold">Reject</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-light btn-sm flex-grow-1 border text-muted fst-italic" disabled>Action Taken</button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-light border btn-sm text-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['type'].$u['id'] ?>" title="Edit Details">
                                <i class="fas fa-pen"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editModal<?= $u['type'].$u['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="post" class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-bottom-0 pb-0">
                            <h6 class="modal-title fw-bold">Edit <?= ucfirst($u['type']) ?></h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="user_type" value="<?= $u['type'] ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($u['phone']) ?>" required>
                            </div>
                            
                            <hr class="my-3 opacity-10">
                            
                            <div class="mb-3">
                                <label class="form-label text-danger"><i class="fas fa-key me-1"></i> Reset Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Enter new password (optional)">
                                <div class="form-text">Leave blank to keep current password.</div>
                            </div>
                        </div>
                        <div class="modal-footer border-top bg-light">
                            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary px-4 fw-bold">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php endforeach; ?>
        </div>
    <?php endif; ?>

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
</script>
</body>
</html>