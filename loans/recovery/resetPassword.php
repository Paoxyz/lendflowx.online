<?php
session_start();
require_once '../db.php';

// SET TIMEZONE TO MANILA
date_default_timezone_set('Asia/Manila'); 

$msg = "";
$msg_type = "";
$validToken = false;
$resetData = null;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();

    if ($resetData) {
        $expiryTime = strtotime($resetData['expires_at']);
        if (time() > $expiryTime) {
            $msg = "This link has expired.";
            $msg_type = "warning";
        } else {
            $validToken = true;
        }
    } else {
        $msg = "Invalid token.";
        $msg_type = "danger";
    }
} else {
    header("Location: forgotPassword.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters.";
        $msg_type = "danger";
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match.";
        $msg_type = "danger";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $email = $resetData['email'];
        $type = $resetData['user_type'];

        try {
            $pdo->beginTransaction();
            $loginLink = ($type === 'borrower') ? "../borrower/borrowerLogin.php" : "../lender/lenderLogin.php";

            if ($type === 'borrower') {
                $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'borrower'")->execute([$hashed, $email]);
            } elseif ($type === 'lender') {
                $pdo->prepare("UPDATE lenders SET password = ? WHERE email = ?")->execute([$hashed, $email]);
            }

            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $pdo->commit();

            $msg = "Password updated! <a href='$loginLink' class='fw-bold text-success text-decoration-none'>Login Now &rarr;</a>";
            $msg_type = "success";
            $validToken = false; 
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | LoanTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #dc2626; --bg-body: #f8fafc; --text-main: #0f172a; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-box { background: white; border-radius: 20px; padding: 40px; width: 100%; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .form-control { padding: 12px 16px; border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1); background: white; }
        .btn-primary { background: var(--primary); border: none; padding: 12px; font-weight: 700; width: 100%; border-radius: 10px; transition: 0.2s; }
        .btn-primary:hover { background: #b91c1c; transform: translateY(-2px); }
        .logo-icon { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card-box text-center">
        <div class="logo-icon"><i class="fas fa-lock-open"></i></div>
        <h3 class="fw-bold mb-1">Set New Password</h3>
        
        <?php if($validToken): ?>
            <p class="text-muted small mb-4">For <span class="badge bg-light text-dark border"><?= ucfirst(htmlspecialchars($resetData['user_type'])) ?></span> Account</p>
        <?php else: ?>
             <p class="text-muted small mb-4">Secure Account Recovery</p>
        <?php endif; ?>

        <?php if($msg): ?>
            <div class="alert alert-<?=$msg_type?> border-0 shadow-sm rounded-3 text-start small mb-4">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if($validToken): ?>
        <form method="POST" class="text-start">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">NEW PASSWORD</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-key"></i></span>
                    <input type="password" name="password" class="form-control border-start-0 ps-0" required placeholder="Min 6 chars">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">CONFIRM PASSWORD</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-check-double"></i></span>
                    <input type="password" name="confirm_password" class="form-control border-start-0 ps-0" required placeholder="Re-enter password">
                </div>
            </div>
            <button class="btn btn-primary mb-3">Update Password</button>
        </form>
        <?php endif; ?>
        
        <div class="border-top pt-3 mt-3">
            <a href="forgotPassword.php" class="text-decoration-none text-muted small">Back to Recovery</a>
        </div>
    </div>
</body>
</html>