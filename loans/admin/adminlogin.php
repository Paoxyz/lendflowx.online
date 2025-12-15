<?php
session_start();

// --- Database connection ---
// Note: In production, it's better to put this in a separate db.php file
$DB_HOST = '127.0.0.1';
$DB_NAME = 'loan_track_db';
$DB_USER = 'root';
$DB_PASS = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Optional: Create default admin if missing (run once)
$defaultAdminPassword = 'Admin123!'; 
$hash = password_hash($defaultAdminPassword, PASSWORD_DEFAULT);
$pdo->exec("INSERT INTO admins (username, password_hash, fullname)
            VALUES ('admin', '$hash', 'System Admin')
            ON DUPLICATE KEY UPDATE password_hash='$hash';");

// --- Login logic ---
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['role'] = 'admin';
        $_SESSION['admin_name'] = $admin['fullname'];
        header('Location: adminDashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Loan Tracker</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #dc2626;
            --primary-hover: #ef4444;
            --bg-light: #f9fafb;
            --bg-dark: #121622;
            --card-light: rgba(255,255,255,0.9);
            --card-dark: rgba(30,40,59,0.9);
            --text-light: #111827;
            --text-dark: #e5e7eb;
            --transition: 0.4s ease-in-out;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, var(--bg-light), #e5e7eb);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background var(--transition);
            overflow: hidden;
            position: relative;
        }

        html[data-bs-theme="dark"] body {
            background: linear-gradient(180deg, var(--bg-dark), #0f172a);
        }

        /* Animated Background */
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            animation: float 10s ease-in-out infinite;
            opacity: 0.4;
        }
        .shape1 { width: 400px; height: 400px; background: var(--primary); top: -100px; left: -100px; }
        .shape2 { width: 300px; height: 300px; background: #3b82f6; bottom: -50px; right: -50px; animation-delay: -5s; }
        @keyframes float { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(20px, -20px); } }

        /* Login Card */
        .login-card {
            background: var(--card-light);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            transition: var(--transition);
        }
        
        [data-bs-theme="dark"] .login-card {
            background: var(--card-dark);
            border-color: rgba(255,255,255,0.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            color: var(--text-dark);
        }

        .brand-logo {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: bold; margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
        }

        /* Input Groups */
        .input-group-text {
            background: transparent; border: 1px solid #d1d5db; color: #6b7280; border-right: none;
            border-top-left-radius: 10px; border-bottom-left-radius: 10px;
        }
        .form-control {
            border: 1px solid #d1d5db; border-left: none; padding: 12px;
            border-top-right-radius: 10px; border-bottom-right-radius: 10px;
        }
        .form-control:focus { box-shadow: none; border-color: var(--primary); }
        .input-group:focus-within .input-group-text { border-color: var(--primary); color: var(--primary); }

        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .input-group-text {
            background-color: rgba(0,0,0,0.2); border-color: #374151; color: #e5e7eb;
        }

        .btn-primary {
            background: var(--primary); border: none; padding: 12px; font-weight: 600; width: 100%; border-radius: 10px;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); transition: transform 0.2s;
        }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }

        /* Theme Toggle */
        #themeToggle {
            position: fixed; top: 20px; right: 20px; width: 45px; height: 45px;
            border-radius: 50%; border: 1px solid rgba(0,0,0,0.1);
            background: var(--card-light); display: flex; align-items: center; justify-content: center;
            font-size: 20px; cursor: pointer; transition: var(--transition); z-index: 100;
        }
        [data-bs-theme="dark"] #themeToggle { background: var(--card-dark); border-color: rgba(255,255,255,0.1); color: white; }
    </style>
</head>
<body>

    <button id="themeToggle" title="Toggle Theme">🌙</button>
    
    <div class="floating-shape shape1"></div>
    <div class="floating-shape shape2"></div>

    <div class="container">
        <div class="login-card mx-auto">
            <div class="brand-logo">
                <i class="fas fa-user-shield"></i>
            </div>
            
            <h3 class="text-center fw-bold mb-1">Admin Portal</h3>
            <p class="text-center text-muted mb-4 small">Restricted access for system administrators.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger text-center py-2 small rounded-3 border-0 bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus />
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required />
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mb-3">
                    Access Dashboard
                </button>
                
                <div class="text-center mt-3 pt-3 border-top border-secondary border-opacity-10">
                    <a href="../index.php" class="small text-muted text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Back to Main Site
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const html = document.documentElement;
        const btn = document.getElementById("themeToggle");

        if (localStorage.getItem("theme") === "dark") {
            html.setAttribute("data-bs-theme", "dark");
            btn.textContent = "☀️";
        }

        btn.onclick = () => {
            let newTheme = html.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
            html.setAttribute("data-bs-theme", newTheme);
            localStorage.setItem("theme", newTheme);
            btn.textContent = newTheme === "dark" ? "☀️" : "🌙";
        };
    </script>

</body>
</html>