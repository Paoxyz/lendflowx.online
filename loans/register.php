<?php
// 1. LOAD PHPMAILER
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust these paths if your PHPMailer folder is in a different location
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

require_once __DIR__ . '/db.php';
session_start();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. Name Processing
    $fname = trim($_POST['fname'] ?? '');
    $mname = trim($_POST['mname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    
    // Combine: "Juan M. Dela Cruz"
    $full_name = trim($fname . ($mname ? ' ' . $mname . '.' : '') . ' ' . $lname);

    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 3. Validations
    if ($fname === '' || $lname === '') $errors[] = "First and last name are required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    // 🔴 4. CHECK DUPLICATE & HANDLE REJECTED USERS (FIXED)
    if (empty($errors)) {
        // Select ID AND Status to check if they were rejected
        $stmt = $pdo->prepare("SELECT id, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // If the user exists but was REJECTED, delete the old account so they can re-apply
            if (strtolower($existingUser['status']) === 'rejected') {
                $cleanup = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $cleanup->execute([$existingUser['id']]);
            } 
            // If the user exists and is NOT rejected (Pending, Approved, Unverified), block them
            else {
                $errors[] = "Email already registered.";
            }
        }
    }

    // 5. File Upload Configuration
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $upload_base = __DIR__ . '/uploads';
    
    // Ensure directories exist
    $dirs = ['ids', 'proofs', 'selfies'];
    foreach ($dirs as $d) {
        $path = "$upload_base/$d";
        if (!is_dir($path)) mkdir($path, 0777, true);
    }

    // Helper Function
    function process_file($field, $subdir, $allowed_types, $max_size) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
        
        $f = $_FILES[$field];
        if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception("Upload error on $field");
        if ($f['size'] > $max_size) throw new Exception("File $field is too large (Max 5MB).");
        
        // Check Real Mime Type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($f['tmp_name']);
        if (!in_array($mime, $allowed_types, true)) throw new Exception("Invalid file type for $field. Use JPG, PNG, or PDF.");

        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $safe_name = uniqid($subdir . '_') . '.' . $ext;
        $dest = __DIR__ . "/uploads/$subdir/$safe_name";
        
        if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception("Failed to save $field.");
        
        return "uploads/$subdir/$safe_name";
    }

    // 6. Process Uploads & Insert & Email
    if (empty($errors)) {
        try {
            $id_path = process_file('valid_id', 'ids', $allowed_types, $max_size);
            $proof_path = process_file('proof_income', 'proofs', $allowed_types, $max_size);
            $selfie_path = process_file('selfie', 'selfies', $allowed_types, $max_size);

            if(!$id_path) throw new Exception("Valid ID is required.");
            if(!$proof_path) throw new Exception("Proof of Income is required.");
            if(!$selfie_path) throw new Exception("Selfie is required.");

            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            // --- OTP GENERATION ---
            $otp = rand(100000, 999999);

            // Insert as 'Unverified' with OTP code
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status, id_path, proof_path, selfie_path, otp_code, created_at)
                                   VALUES (?, ?, ?, ?, 'borrower', 'Unverified', ?, ?, ?, ?, NOW())");
            
            if ($stmt->execute([$full_name, $email, $phone, $hash, $id_path, $proof_path, $selfie_path, $otp])) {
                
                // --- SEND EMAIL ---
                $mail = new PHPMailer(true);
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'your_email@gmail.com'; // YOUR GMAIL
                    $mail->Password   = 'your_app_password';    // YOUR APP PASSWORD
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // Recipients
                    $mail->setFrom('no-reply@loantracker.com', 'LoanTracker Admin');
                    $mail->addAddress($email, $full_name);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Verify your Account - LoanTracker';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                            <h2 style='color: #dc2626;'>LoanTracker Verification</h2>
                            <p>Hello $fname,</p>
                            <p>Thank you for registering. Please use the code below to verify your email address:</p>
                            <h1 style='background: #f3f4f6; padding: 10px 20px; display: inline-block; letter-spacing: 5px; border-radius: 5px;'>$otp</h1>
                            <p>If you did not request this, please ignore this email.</p>
                        </div>
                    ";

                    $mail->send();

                    // --- REDIRECT TO OTP PAGE ---
                    $_SESSION['verify_email'] = $email; 
                    header('Location: verify_otp.php');
                    exit;

                } catch (Exception $e) {
                    // If email fails, we might want to delete the user or show error
                    $errors[] = "Account created, but email failed to send. Error: {$mail->ErrorInfo}";
                }

            } else {
                $errors[] = "Database insert failed.";
            }

        } catch (Exception $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Registration | LoanTracker</title>
    
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
            padding: 40px 20px;
        }

        .register-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 700px; /* Consistent with Lender Register */
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
        }

        .brand-logo {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.2);
        }

        /* Form Styles */
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 8px; }
        .form-control, .form-select { padding: 12px 16px; border-radius: 12px; border: 1px solid var(--border); background-color: var(--bg-body); color: var(--text-main); transition: 0.2s; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1); background: var(--card-bg); }
        
        .input-group-text { background: var(--bg-body); border: 1px solid var(--border); border-right: none; color: var(--text-muted); border-radius: 12px 0 0 12px; }
        .input-group .form-control { border-left: none; border-radius: 0 12px 12px 0; }
        .input-group:focus-within .input-group-text { border-color: var(--primary); color: var(--primary); }

        /* File Upload Styling */
        .file-upload-wrapper { position: relative; height: 120px; border: 2px dashed var(--border); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; cursor: pointer; transition: 0.2s; background: var(--bg-body); }
        .file-upload-wrapper:hover { border-color: var(--primary); background: rgba(220, 38, 38, 0.02); }
        .file-upload-input { position: absolute; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        
        .btn-primary { background: var(--primary); border: none; padding: 14px; border-radius: 12px; font-weight: 700; width: 100%; transition: 0.2s; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }

        #themeToggle { position: fixed; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }

        /* Modal Styling */
        .modal-content { border-radius: 20px; border: none; }
        .modal-header { border-bottom: 1px solid var(--border); padding: 20px 30px; }
        .modal-body { padding: 30px; max-height: 60vh; overflow-y: auto; font-size: 0.9rem; line-height: 1.6; color: var(--text-muted); }
        .modal-title { font-weight: 800; color: var(--text-main); }
        .modal-footer { border-top: 1px solid var(--border); padding: 20px 30px; }
    </style>
</head>
<body>

    <button id="themeToggle">🌙</button>

    <div class="register-card">
        <div class="text-center mb-5">
            <div class="brand-logo"><i class="fas fa-user-plus"></i></div>
            <h2 class="fw-bold mb-1">Borrower Registration</h2>
            <p class="text-muted small">Apply for loans with confidence.</p>
        </div>

        <?php if ($success): ?>
            <div class="text-center py-5">
                <div class="mb-3 text-success"><i class="fas fa-check-circle fa-5x"></i></div>
                <h3 class="fw-bold">Email Sent!</h3>
                <p class="text-muted mb-4"><?= $success ?></p>
                <a href="verify_otp.php" class="btn btn-primary w-50 mx-auto d-block">Enter Code</a>
            </div>
        <?php else: ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small rounded-3 mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                
                <div class="row g-3">
                    <div class="col-12"><h6 class="text-primary fw-bold text-uppercase small border-bottom pb-2 mb-0">Identity</h6></div>
                    
                    <div class="col-md-5">
                        <label class="form-label">First Name</label>
                        <input type="text" name="fname" class="form-control" placeholder="e.g. Maria" required value="<?= htmlspecialchars($_POST['fname'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-center w-100">M.I.</label>
                        <input type="text" name="mname" class="form-control text-center" placeholder="A" maxlength="1" value="<?= htmlspecialchars($_POST['mname'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="lname" class="form-control" placeholder="e.g. Clara" required value="<?= htmlspecialchars($_POST['lname'] ?? '') ?>">
                    </div>

                    <div class="col-12 mt-4"><h6 class="text-primary fw-bold text-uppercase small border-bottom pb-2 mb-0">Contact Info</h6></div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mobile Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" name="phone" class="form-control" placeholder="0917 XXX XXXX" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-12 mt-4"><h6 class="text-primary fw-bold text-uppercase small border-bottom pb-2 mb-0">Security</h6></div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-type password" required>
                        </div>
                    </div>

                    <div class="col-12 mt-4"><h6 class="text-primary fw-bold text-uppercase small border-bottom pb-2 mb-0">Verification Documents</h6></div>

                    <div class="col-md-4">
                        <div class="file-upload-wrapper">
                            <input type="file" name="valid_id" class="file-upload-input" accept=".jpg,.jpeg,.png,.pdf" required onchange="this.nextElementSibling.nextElementSibling.innerText = this.files[0].name">
                            <i class="fas fa-id-card fs-3 text-muted mb-2"></i>
                            <span class="fw-bold small text-muted px-2 text-truncate">Upload Valid ID</span>
                            <small class="text-muted" style="font-size: 0.7rem;">Govt Issued ID</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="file-upload-wrapper">
                            <input type="file" name="proof_income" class="file-upload-input" accept=".jpg,.jpeg,.png,.pdf" required onchange="this.nextElementSibling.nextElementSibling.innerText = this.files[0].name">
                            <i class="fas fa-file-invoice-dollar fs-3 text-muted mb-2"></i>
                            <span class="fw-bold small text-muted px-2 text-truncate">Proof of Income</span>
                            <small class="text-muted" style="font-size: 0.7rem;">Payslip / Bank Stat.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="file-upload-wrapper">
                            <input type="file" name="selfie" class="file-upload-input" accept="image/*" required onchange="this.nextElementSibling.nextElementSibling.innerText = this.files[0].name">
                            <i class="fas fa-camera fs-3 text-muted mb-2"></i>
                            <span class="fw-bold small text-muted px-2 text-truncate">Selfie with ID</span>
                            <small class="text-muted" style="font-size: 0.7rem;">Clear photo</small>
                        </div>
                    </div>
                    
                    <div class="col-12 mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" required id="terms">
                            <label class="form-check-label small text-muted" for="terms">
                                I agree to the <a href="#" class="text-primary fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> & Privacy Policy.
                            </label>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary shadow-sm">Create Borrower Account</button>
                    </div>
                </div>

                <div class="text-center mt-4 pt-3 border-top">
                    <p class="small text-muted mb-0">Already have an account? <a href="borrower/borrowerLogin.php" class="fw-bold text-primary text-decoration-none">Login Here</a></p>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-contract me-2 text-primary"></i> Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold text-dark">1. Acceptance of Terms</h6>
                    <p>By creating an account on LoanTracker, you agree to comply with these terms. Failure to adhere may result in account suspension.</p>
                    
                    <h6 class="fw-bold text-dark mt-4">2. Privacy & Data Usage</h6>
                    <p>We collect your personal data (Name, ID, Income Proof) solely for verification purposes. Your data is encrypted and will not be shared with third parties without consent, except as required by law.</p>

                    <h6 class="fw-bold text-dark mt-4">3. Borrower Obligations</h6>
                    <ul>
                        <li>You certify that all information provided is accurate and true.</li>
                        <li>You acknowledge that loan approval is subject to Lender review.</li>
                        <li>You agree to repay loans according to the agreed schedule.</li>
                    </ul>

                    <h6 class="fw-bold text-dark mt-4">4. Default & Penalties</h6>
                    <p>Failure to pay on time may result in additional interest charges and a negative record on your account profile.</p>

                    <hr>
                    <p class="text-center small text-muted mb-0">&copy; 2025 LoanTracker. All rights reserved.</p>
                </div>
                <div class="modal-footer">  
                    <button type="button" class="btn btn-primary px-4 fw-bold" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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