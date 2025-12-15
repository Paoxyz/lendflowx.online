<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['lender_id'])) {
    header("Location: lenderLogin.php");
    exit;
}

$msg = "";
$err = "";
$lender_id = $_SESSION['lender_id'];
$lender_name = $_SESSION['lender_name'] ?? 'Lender';

// Fetch current details
$stmt = $pdo->prepare("SELECT * FROM lenders WHERE id = ?");
$stmt->execute([$lender_id]);
$user = $stmt->fetch();

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validation
    if (!empty($password) && $password !== $confirm_password) {
        $err = "New passwords do not match.";
    } else {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE lenders SET name = ?, phone = ?, password = ? WHERE id = ?");
            $upd->execute([$name, $phone, $hashed, $lender_id]);
        } else {
            $upd = $pdo->prepare("UPDATE lenders SET name = ?, phone = ? WHERE id = ?");
            $upd->execute([$name, $phone, $lender_id]);
        }
        
        $msg = "Settings updated successfully.";
        $stmt->execute([$lender_id]);
        $user = $stmt->fetch();
        $_SESSION['lender_name'] = $name;
        $lender_name = $name;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Settings | LoanTracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #ef4444; --primary-hover: #dc2626; --primary-soft: rgba(239, 68, 68, 0.1);
            --bg-body: #f8fafc; --bg-card: #ffffff; --bg-sidebar: #ffffff;
            --text-main: #0f172a; --text-muted: #64748b;
            --border: #e2e8f0; --radius: 16px; --sidebar-w: 260px;
        }
        [data-bs-theme="dark"] {
            --bg-body: #0f172a; --bg-card: #1e293b; --bg-sidebar: #1e293b;
            --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; }
        
        /* Layout */
        .sidebar { width: var(--sidebar-w); background: var(--bg-sidebar); border-right: 1px solid var(--border); position: fixed; top:0; bottom:0; left:0; z-index: 100; padding: 24px; display: flex; flex-direction: column; }
        .main-content { flex: 1; margin-left: var(--sidebar-w); padding: 32px; width: 100%; }

        /* Sidebar Nav */
        .sidebar-brand { display: flex; align-items: center; gap: 12px; font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-bottom: 40px; padding: 0 12px; }
        .nav-link { padding: 12px; color: var(--text-muted); font-weight: 600; border-radius: 12px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
        .nav-link:hover { background: var(--bg-body); color: var(--primary); }
        .nav-link.active { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }
        .nav-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px; padding: 0 12px; }

        /* Topbar */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* --- SETTINGS STYLES --- */
        .profile-hero {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 30px; display: flex; align-items: center; gap: 24px; margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .hero-avatar {
            width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), #fca5a5);
            color: white; font-size: 2.5rem; font-weight: 800; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        /* Tab Navigation */
        .settings-tabs {
            display: flex; gap: 10px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 1px;
        }
        .tab-link {
            padding: 10px 20px; background: transparent; border: none; color: var(--text-muted); font-weight: 600;
            border-bottom: 2px solid transparent; transition: all 0.2s;
        }
        .tab-link:hover { color: var(--primary); }
        .tab-link.active { color: var(--primary); border-bottom-color: var(--primary); }

        /* Forms */
        .settings-form-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius);
            padding: 32px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); max-width: 800px;
        }
        .form-label { font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 8px; }
        .form-control { padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); transition: 0.2s; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1); background: var(--bg-card); }
        .form-text { font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; }

        /* Toggles */
        .notif-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid var(--border); }
        .notif-item:last-child { border-bottom: none; }
        .form-check-input { width: 2.5em; height: 1.25em; cursor: pointer; }
        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }

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
        <div class="nav-label">ANALYTICS & SETTINGS</div>
        <nav class="nav flex-column flex-grow-1">
            <a href="lenderReports.php" class="nav-link"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="lenderSettings.php" class="nav-link active"><i class="fas fa-cog"></i> Settings</a>
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
                <h3 class="fw-bold mb-1">Account Settings</h3>
                <p class="text-muted mb-0">Update your profile and preferences.</p>
            </div>
            <div class="d-flex gap-3 align-items-center">
                 <button class="btn btn-light border rounded-circle" id="themeToggle" style="width:40px;height:40px;"><i class="fas fa-moon text-muted"></i></button>
                 <button class="btn btn-primary d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('active')"><i class="fas fa-bars"></i></button>
                 <div class="d-flex align-items-center gap-2 d-none d-md-flex bg-white border px-3 py-2 rounded-pill">
                    <div class="avatar" style="width:28px; height:28px; font-size:0.9rem;"><?= strtoupper(substr($lender_name, 0, 1)) ?></div>
                    <span class="fw-bold text-sm"><?= htmlspecialchars($lender_name) ?></span>
                 </div>
            </div>
        </div>

        <!-- Profile Hero -->
        <div class="profile-hero">
            <div class="hero-avatar">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
                <h4 class="fw-bold mb-0"><?= htmlspecialchars($user['name']) ?></h4>
                <p class="text-muted small mb-1"><?= htmlspecialchars($user['email']) ?></p>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 border border-success border-opacity-25">Lender Account</span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if($msg): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2">
                <i class="fas fa-check-circle"></i> <?= $msg ?>
            </div>
        <?php endif; ?>
        
        <?php if($err): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-circle"></i> <?= $err ?>
            </div>
        <?php endif; ?>

        <!-- Settings Tabs (JavaScript Toggles Visibility) -->
        <div class="settings-tabs">
            <button class="tab-link active" onclick="openTab(event, 'general')">General</button>
            <button class="tab-link" onclick="openTab(event, 'security')">Security</button>
            <button class="tab-link" onclick="openTab(event, 'notifications')">Notifications</button>
        </div>

        <!-- FORM START -->
        <form method="POST">
            
            <!-- Tab 1: General -->
            <div id="general" class="tab-content" style="display: block;">
                <div class="settings-form-card">
                    <h5 class="fw-bold mb-4">Personal Information</h5>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-body border-end-0 text-muted"><i class="fas fa-user"></i></span>
                                <input type="text" name="name" class="form-control border-start-0 ps-0" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-body border-end-0 text-muted"><i class="fas fa-phone"></i></span>
                                <input type="text" name="phone" class="form-control border-start-0 ps-0" value="<?= htmlspecialchars($user['phone']) ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-body border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control border-start-0 ps-0" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                            </div>
                            <div class="form-text">Contact admin to change your email address.</div>
                        </div>
                        <div class="col-12 text-end mt-3">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Security -->
            <div id="security" class="tab-content" style="display: none;">
                <div class="settings-form-card">
                    <h5 class="fw-bold mb-4">Password & Security</h5>
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-body border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Leave blank to keep current">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-body border-end-0 text-muted"><i class="fas fa-check-circle"></i></span>
                                <input type="password" name="confirm_password" class="form-control border-start-0 ps-0" placeholder="Confirm new password">
                            </div>
                        </div>
                        <div class="col-12 text-end mt-3">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">Update Password</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Notifications -->
            <div id="notifications" class="tab-content" style="display: none;">
                <div class="settings-form-card">
                    <h5 class="fw-bold mb-4">Notification Preferences</h5>
                    
                    <div class="notif-item">
                        <div>
                            <p class="fw-bold mb-0">Email Alerts</p>
                            <p class="text-muted small mb-0">Receive updates on new loan applications.</p>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" checked>
                        </div>
                    </div>

                    <div class="notif-item">
                        <div>
                            <p class="fw-bold mb-0">Payment Reminders</p>
                            <p class="text-muted small mb-0">Get notified when a borrower makes a payment.</p>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" checked>
                        </div>
                    </div>

                    <div class="notif-item">
                        <div>
                            <p class="fw-bold mb-0">Marketing</p>
                            <p class="text-muted small mb-0">Receive news about LoanTracker features.</p>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox">
                        </div>
                    </div>
                    
                    <div class="col-12 text-end mt-4">
                        <button type="button" class="btn btn-primary px-4 fw-bold" onclick="alert('Preferences Saved!')">Save Preferences</button>
                    </div>
                </div>
            </div>

        </form>

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

        // Tab Logic
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tab-link");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
    </script>
</body>
</html>     