<?php
require_once __DIR__ . '/db.php';
session_start();

// Redirect if they haven't just registered
if (!isset($_SESSION['verify_email'])) {
    header('Location: register.php');
    exit;
}

$email = $_SESSION['verify_email'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Combine the 6 inputs
    $entered_otp = 
        ($_POST['otp1'] ?? '') . 
        ($_POST['otp2'] ?? '') . 
        ($_POST['otp3'] ?? '') . 
        ($_POST['otp4'] ?? '') . 
        ($_POST['otp5'] ?? '') . 
        ($_POST['otp6'] ?? '');

    // Check DB
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND otp_code = ?");
    $stmt->execute([$email, $entered_otp]);
    $user = $stmt->fetch();

    if ($user) {
        // ✅ SUCCESS: Set status to Pending (waiting for Admin), Remove OTP
        $update = $pdo->prepare("UPDATE users SET status = 'Pending', otp_code = NULL WHERE id = ?");
        $update->execute([$user['id']]);

        // Clear session
        unset($_SESSION['verify_email']);
        $msg = "Verification Successful! Your account is now pending admin approval.";
    } else {
        // ❌ ERROR
        $err = "Invalid or expired code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | LoanTracker</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #dc2626; --primary-hover: #b91c1c;
            --bg-body: #f8fafc; --card-bg: #ffffff;
            --text-main: #0f172a; --text-muted: #64748b;
            --border: #e2e8f0;
        }
        [data-bs-theme="dark"] {
            --bg-body: #0f172a; --card-bg: #1e293b;
            --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verify-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            text-align: center;
        }

        .icon-circle {
            width: 80px; height: 80px;
            background: rgba(220, 38, 38, 0.1);
            color: var(--primary);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; margin: 0 auto 20px;
        }

        /* OTP Input Styling */
        .otp-inputs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
        }

        .otp-field {
            width: 50px; height: 55px;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: var(--bg-body);
            color: var(--text-main);
            transition: 0.2s;
        }

        .otp-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
            outline: none;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary); border: none; padding: 14px;
            border-radius: 12px; font-weight: 700; width: 100%;
            transition: 0.2s;
        }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }

        #themeToggle { position: fixed; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
    </style>
</head>
<body>

    <button id="themeToggle">🌙</button>

    <div class="verify-card">
        
        <?php if ($msg): ?>
            <div class="icon-circle text-success" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="fw-bold mb-2">Verified!</h2>
            <p class="text-muted mb-4"><?= htmlspecialchars($msg) ?></p>
            <a href="borrower/borrowerLogin.php" class="btn btn-primary">Go to Login</a>
        
        <?php else: ?>
            <div class="icon-circle">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h2 class="fw-bold mb-2">Verify Your Email</h2>
            <p class="text-muted small mb-0">We sent a 6-digit code to</p>
            <p class="fw-bold text-primary"><?= htmlspecialchars($email) ?></p>

            <?php if ($err): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small rounded-3 mt-3 mb-0">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= $err ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="otp-inputs">
                    <input class="otp-field" type="text" name="otp1" maxlength="1" required>
                    <input class="otp-field" type="text" name="otp2" maxlength="1" required>
                    <input class="otp-field" type="text" name="otp3" maxlength="1" required>
                    <input class="otp-field" type="text" name="otp4" maxlength="1" required>
                    <input class="otp-field" type="text" name="otp5" maxlength="1" required>
                    <input class="otp-field" type="text" name="otp6" maxlength="1" required>
                </div>

                <button type="submit" class="btn btn-primary mb-3">Verify Code</button>
                
                <div class="text-center">
                    <p class="small text-muted">Didn't receive the code? <br>
                    <a href="#" class="text-primary fw-bold text-decoration-none">Resend Code</a></p>
                </div>
            </form>
        <?php endif; ?>

    </div>

    <script>
        // 1. Theme Toggle Logic (Same as register.php)
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

        // 2. OTP Input Auto-Focus Logic
        const inputs = document.querySelectorAll('.otp-field');
        
        inputs.forEach((input, index) => {
            input.dataset.index = index;
            
            // Allow only numbers
            input.addEventListener('keydown', (e) => {
                if (e.key >= 0 && e.key <= 9) {
                    input.value = ''; // Clear value before typing new one
                    return;
                }
                // Handle Backspace
                if (e.key === 'Backspace') {
                    if (input.value === '') {
                        const prev = inputs[index - 1];
                        if (prev) prev.focus();
                    }
                }
            });

            // Move to next on input
            input.addEventListener('input', (e) => {
                if (input.value.length === 1) {
                    const next = inputs[index + 1];
                    if (next) next.focus();
                }
            });

            // Handle Paste
            input.addEventListener('paste', (e) => {
                const data = e.clipboardData.getData('text');
                const value = data.split('');
                if (value.length === inputs.length) {
                    inputs.forEach((input, index) => (input.value = value[index]));
                    inputs[inputs.length - 1].focus(); // Focus last one
                    e.preventDefault(); // Stop default paste
                }
            });
        });
        
        // Focus first input on load
        if(inputs.length > 0) inputs[0].focus();
    </script>
</body>
</html>