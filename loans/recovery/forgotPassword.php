    <?php
    session_start();
    require_once '../db.php';

    // 1. SET TIMEZONE TO MANILA
    date_default_timezone_set('Asia/Manila');

    // ---------------------------------------------------------
    // LOAD PHPMAILER (Manual Method)
    // ---------------------------------------------------------
    require '../PHPMailer/src/Exception.php';
    require '../PHPMailer/src/PHPMailer.php';
    require '../PHPMailer/src/SMTP.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    $msg = "";
    $msg_type = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $userFound = false;
        $userType = '';
        $userName = '';

        // 1. Check Borrowers
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'borrower'");
        $stmt->execute([$email]);
        $borrower = $stmt->fetch();

        if ($borrower) {
            $userFound = true;
            $userType = 'borrower';
            $userName = $borrower['name'];
        } else {
            // 2. Check Lenders
            $stmt = $pdo->prepare("SELECT id, name FROM lenders WHERE email = ?");
            $stmt->execute([$email]);
            $lender = $stmt->fetch();

            if ($lender) {
                $userFound = true;
                $userType = 'lender';
                $userName = $lender['name'];
            }
        }

        if ($userFound) {
            $token = bin2hex(random_bytes(32));
            $expires_at = date("Y-m-d H:i:s", strtotime('+1 hour'));

            $stmt = $pdo->prepare("INSERT INTO password_resets (email, user_type, token, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $userType, $token, $expires_at]);

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // YOUR CREDENTIALS (PRESERVED)
                $mail->Username   = 'ztwooo8825@gmail.com'; 
                $mail->Password   = 'pwpowrlooddwetac'; 

                $mail->setFrom('no-reply@loantracker.com', 'LoanTracker Security');
                $mail->addAddress($email, $userName);

                $resetLink = "http://localhost/loans/recovery/resetPassword.php?token=" . $token;
                
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your LoanTracker Password';
                $mail->Body    = "
                    <div style='font-family: sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                        <h2 style='color: #dc2626;'>Password Reset Request</h2>
                        <p>Hello <strong>$userName</strong>,</p>
                        <p>We received a request to reset your <strong>" . ucfirst($userType) . "</strong> account password.</p>
                        <p><a href='$resetLink' style='background:#dc2626; color:white; padding:12px 24px; text-decoration:none; border-radius:5px; display:inline-block; font-weight:bold;'>Reset Password</a></p>
                        <p style='color:gray; font-size:12px; margin-top:20px;'>This link expires on: $expires_at</p>
                    </div>
                ";

                $mail->send();
                $msg = "<i class='fas fa-paper-plane me-1'></i> Reset link sent! Check your email.";
                $msg_type = "success";
            } catch (Exception $e) {
                $msg = "Mailer Error: {$mail->ErrorInfo}";
                $msg_type = "danger";
            }
        } else {
            $msg = "<i class='fas fa-info-circle me-1'></i> If an account exists, we sent a link.";
            $msg_type = "info";
        }
    }
    ?>

    <!DOCTYPE html>
    <html lang="en" data-bs-theme="light">
    <head>
        <meta charset="UTF-8">
        <title>Forgot Password | LoanTracker</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root { --primary: #dc2626; --bg-body: #f8fafc; --text-main: #0f172a; }
            body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); height: 100vh; display: flex; align-items: center; justify-content: center; }
            
            @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
            .card-box { 
                background: white; border-radius: 20px; padding: 40px; width: 100%; max-width: 450px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; 
                animation: fadeIn 0.5s ease-out;
            }

            .form-control { padding: 12px 16px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; }
            .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1); background: white; }
            .btn-primary { background: var(--primary); border: none; padding: 12px; font-weight: 700; width: 100%; border-radius: 10px; transition: 0.2s; }
            .btn-primary:hover { background: #b91c1c; transform: translateY(-2px); }
            .logo-icon { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; }
        </style>
    </head>
    <body>
        <div class="card-box text-center">
            <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
            <h3 class="fw-bold mb-1">Forgot Password?</h3>
            <p class="text-muted small mb-4">Enter your email to receive a reset link.</p>

            <?php if($msg): ?>
                <div class="alert alert-<?=$msg_type?> border-0 shadow-sm rounded-3 text-start small mb-4">
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="text-start">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">EMAIL ADDRESS</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="name@example.com" required>
                    </div>
                </div>
                <button class="btn btn-primary mb-4">Send Reset Link</button>
            </form>
            
            <div class="d-flex justify-content-center gap-3 border-top pt-3">
                <a href="../borrower/borrowerLogin.php" class="text-decoration-none text-muted small fw-bold hover-primary">Borrower Login</a>
                <span class="text-muted small">•</span>
                <a href="../lender/lenderLogin.php" class="text-decoration-none text-muted small fw-bold hover-primary">Lender Login</a>
            </div>
        </div>
    </body>
    </html>