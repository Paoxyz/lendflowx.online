<?php
require_once '../db.php';
session_start();

// If already logged in as borrower, redirect
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'borrower') {
    header('Location: borrowerDashboard.php');
    exit;
}

$err = '';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $err = "Enter email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // 🔴 BLOCK NOT APPROVED USERS
            if ($user['status'] !== 'Approved') {
                $err = "Your account is " . strtolower($user['status']) . ". Please wait for admin approval.";
            }
            // 🔴 BLOCK WRONG ROLE
            else if ($user['role'] !== 'borrower') {
                $err = "Access denied. Only borrowers can login here.";
            }
            // 🟢 LOGIN SUCCESS
            else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = 'borrower';
                $_SESSION['user_name'] = $user['name'];
                header('Location: borrowerDashboard.php');
                exit;
            }
        } else {
            $err = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Login | Loan Tracker</title>
    
    <!-- Bootstrap & Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            overflow: hidden; /* Prevent scrollbars from blobs */
            position: relative;
        }

        html[data-bs-theme="dark"] body {
            background: linear-gradient(180deg, var(--bg-dark), #0f172a);
        }

        /* Floating Background Shapes */
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            animation: float 10s ease-in-out infinite;
            opacity: 0.4;
        }

        .shape1 {
            width: 400px; height: 400px;
            background: var(--primary);
            top: -100px; left: -100px;
        }

        .shape2 {
            width: 300px; height: 300px;
            background: #3b82f6;
            bottom: -50px; right: -50px;
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, -20px); }
        }

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

        html[data-bs-theme="dark"] .login-card {
            background: var(--card-dark);
            border-color: rgba(255,255,255,0.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            color: var(--text-dark);
        }

        .brand-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
        }

        /* Form Controls */
        .form-control {
            padding: 12px 15px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            background-color: rgba(255,255,255,0.5);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
        }

        html[data-bs-theme="dark"] .form-control {
            background-color: rgba(0,0,0,0.2);
            border-color: #374151;
            color: white;
        }

        html[data-bs-theme="dark"] .form-control:focus {
            border-color: var(--primary);
        }

        .input-group-text {
            border-radius: 12px 0 0 12px;
            border: 1px solid #e5e7eb;
            background: transparent;
            color: #6b7280;
        }

        .form-control {
            border-radius: 0 12px 12px 0;
            border-left: none;
        }

        html[data-bs-theme="dark"] .input-group-text {
            border-color: #374151;
            color: #9ca3af;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }
        
        .btn-outline-secondary {
            border-radius: 12px;
            padding: 12px;
            width: 100%;
            font-weight: 600;
            border-color: #e5e7eb;
            color: var(--text-light);
        }
        
        .btn-outline-secondary:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            color: var(--text-light);
        }

        /* Theme Toggle */
        #themeToggle {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.1);
            background: var(--card-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: var(--transition);
            z-index: 100;
        }
        
        #themeToggle:hover {
            transform: rotate(15deg);
            color: var(--primary);
        }

        html[data-bs-theme="dark"] #themeToggle {
            background: var(--card-dark);
            border-color: rgba(255,255,255,0.1);
            color: white;
        }
    </style>
</head>
<body>

    <button id="themeToggle" title="Toggle Theme">🌙</button>
    
    <!-- Animated Backgrounds -->
    <div class="floating-shape shape1"></div>
    <div class="floating-shape shape2"></div>

    <div class="container">
        <div class="login-card mx-auto">
            <div class="brand-logo">
                <i class="fas fa-wallet"></i>
            </div>
            
            <h3 class="text-center fw-bold mb-1">Borrower Portal</h3>
            <p class="text-center text-muted mb-4 small">Welcome back! Please login to your account.</p>

            <?php if ($err): ?>
                <div class="alert alert-danger text-center py-2 small rounded-3 border-0 bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="name@company.com" required />
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-muted mb-0">Password</label>
                        <a href="../recovery/forgotPassword.php" class="text-decoration-none small fw-bold" style="color: var(--primary);">Forgot Password?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required />
                    </div>
                </div>

                <button type="submit" name="login" class="btn btn-primary w-100 mb-3 shadow-sm">
                    Sign In
                </button>

                <div class="d-grid gap-2 mb-3">
                    <a href="../lender/lenderLogin.php" class="btn btn-outline-secondary text-decoration-none text-center">
                        <i class="fas fa-briefcase me-2"></i> Login as Lender
                    </a>
                </div>

                <div class="text-center">
                    <p class="small text-muted mb-2">Don't have an account?</p>
                    <a href="../register.php" class="text-decoration-none fw-bold" style="color: var(--primary);">
                        Create Borrower Account
                    </a>
                </div>
                
                <div class="text-center mt-3 pt-3 border-top border-secondary border-opacity-10">
                    <a href="../index.php" class="small text-muted text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Back to Home
                    </a>
                </div>
            </form>
        </div>
    </div>

<script>
    const html = document.documentElement;
    const btn = document.getElementById("themeToggle");

    // Check local storage on load
    if (localStorage.getItem("theme") === "dark") {
        html.setAttribute("data-bs-theme", "dark");
        btn.textContent = "☀️";
    }

    // Toggle functionality
    btn.onclick = () => {
        let newTheme = html.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
        html.setAttribute("data-bs-theme", newTheme);
        localStorage.setItem("theme", newTheme);
        btn.textContent = newTheme === "dark" ? "☀️" : "🌙";
    };
</script>

</body>
</html>