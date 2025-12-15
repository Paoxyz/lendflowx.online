<?php
session_start();
require_once "../db.php";

// If already logged in as lender -> go to dashboard
if (isset($_SESSION['lender_id'])) {
    header("Location: lenderDashboard.php");
    exit;
}

$err = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Concatenate Name with Middle Initial
    $first_name = trim($_POST['first_name']);
    $middle_initial = trim($_POST['middle_initial']);
    $last_name = trim($_POST['last_name']);
    
    // Format: "Juan M. Dela Cruz" or "Juan Dela Cruz"
    $name = $first_name . ($middle_initial ? ' ' . $middle_initial . '.' : '') . ' ' . $last_name;

    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // New Financial Fields
    $tin = trim($_POST['tin']);
    $source = trim($_POST['source_of_funds']);
    $bank_name = trim($_POST['bank_name']);
    $bank_acc = trim($_POST['bank_account']);
    
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    // File Upload Handling
    $id_file_name = $_FILES['id_file']['name'];
    $id_file_tmp = $_FILES['id_file']['tmp_name'];
    $id_path = '';

    // Validation
    if ($pass !== $confirm_pass) {
        $err = "Passwords do not match.";
    } else {
        // Check if email exists
        $check = $pdo->prepare("SELECT id FROM lenders WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            $err = "Email already registered.";
        } else {
            // Upload ID
            if ($id_file_name) {
                // Create uploads directory if it doesn't exist
                $target_dir = "../uploads/lenders/";
                if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                
                $unique_name = time() . "_" . basename($id_file_name);
                $target_file = $target_dir . $unique_name;
                
                if (move_uploaded_file($id_file_tmp, $target_file)) {
                    $id_path = "uploads/lenders/" . $unique_name; // Save relative path
                } else {
                    $err = "Failed to upload ID. Check folder permissions.";
                }
            }

            if (!$err) {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                
                // Insert with new financial fields
                $stmt = $pdo->prepare("INSERT INTO lenders (name, email, phone, address, tin, source_of_funds, bank_name, bank_account, id_file, password, status)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                
                if ($stmt->execute([$name, $email, $phone, $address, $tin, $source, $bank_name, $bank_acc, $id_path, $hashed])) {
                    $success = "Registration successful! Your account is under review.";
                } else {
                    $err = "Database error occurred.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lender Register | Loan Tracker</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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
            max-width: 700px; /* Widened for better layout */
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

        .btn-primary { background: var(--primary); border: none; padding: 14px; border-radius: 12px; font-weight: 700; width: 100%; transition: 0.2s; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }

        #themeToggle { position: fixed; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        
        /* File Input Styling */
        .file-upload-wrapper { position: relative; height: 120px; border: 2px dashed var(--border); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; cursor: pointer; transition: 0.2s; background: var(--bg-body); }
        .file-upload-wrapper:hover { border-color: var(--primary); background: rgba(220, 38, 38, 0.02); }
        .file-upload-input { position: absolute; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    </style>
</head>
<body>

    <button id="themeToggle">🌙</button>

    <div class="register-card">
        <div class="brand-logo"><i class="fas fa-hand-holding-usd"></i></div>
        <h3 class="text-center fw-bold mb-1">Investor Registration</h3>
        <p class="text-center text-muted mb-4 small">Join our platform to fund loans securely.</p>

        <?php if ($err): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2 text-sm">
                <i class="fas fa-exclamation-circle"></i> <?= $err ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 text-center">
                <div class="mb-2 text-success"><i class="fas fa-check-circle fa-3x"></i></div>
                <h6 class="fw-bold">Registration Submitted!</h6>
                <p class="small mb-0"><?= $success ?></p>
                <a href="lenderLogin.php" class="btn btn-sm btn-outline-success mt-3 fw-bold px-4">Proceed to Login</a>
            </div>
        <?php else: ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <!-- Section Header -->
                <div class="col-12"><h6 class="text-primary fw-bold text-uppercase small border-bottom pb-2 mb-0">Profile Details</h6></div>
                
                <!-- Personal Info -->
                <div class="col-md-5">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" placeholder="e.g. Juan" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">M.I.</label>
                    <input type="text" name="middle_initial" class="form-control text-center" placeholder="D" maxlength="1">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" placeholder="e.g. Cruz" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="name@company.com" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="0917 XXX XXXX" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Home Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Street, Barangay, City, Province" required>
                </div>

                <!-- New Financial Section -->
                <div class="col-12 mt-4"><h6 class="text-primary fw-bold text-uppercase small border-bottom pb-2 mb-0">Financial Information</h6></div>

                <div class="col-md-6">
                    <label class="form-label">Tax ID (TIN)</label>
                    <input type="text" name="tin" class="form-control" placeholder="000-000-000-000" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Source of Funds</label>
                    <select name="source_of_funds" class="form-select" required>
                        <option value="">-- Select Source --</option>
                        <option value="Business">Business / Trade</option>
                        <option value="Employment">Employment / Salary</option>
                        <option value="Investments">Investments / Savings</option>
                        <option value="Inheritance">Remittance / Inheritance</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="e.g. BDO, BPI, Metrobank" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bank Account No.</label>
                    <input type="text" name="bank_account" class="form-control" placeholder="Account Number" required>
                </div>

                <!-- File Upload -->
                <div class="col-12 mt-4">
                    <label class="form-label">Upload Valid ID</label>
                    <div class="file-upload-wrapper">
                        <input type="file" name="id_file" class="file-upload-input" accept="image/*,application/pdf" required onchange="document.getElementById('fileName').textContent = this.files[0].name">
                        <i class="fas fa-cloud-upload-alt fs-3 text-muted mb-2"></i>
                        <span id="fileName" class="text-muted small fw-bold">Click to upload Government ID</span>
                        <small class="text-muted" style="font-size: 0.7rem;">(PNG, JPG, PDF - Max 5MB)</small>
                    </div>
                </div>

                <!-- Security -->
                <div class="col-12 mt-4"><h6 class="text-primary fw-bold text-uppercase small border-bottom pb-2 mb-0">Security</h6></div>

                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-check"></i></span>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <!-- Terms -->
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" required id="terms">
                        <label class="form-check-label small text-muted" for="terms">
                            I agree to the <a href="#" class="text-primary text-decoration-none fw-bold">Terms of Service</a> and Privacy Policy.
                        </label>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary shadow-sm">Create Account</button>
                </div>
            </div>

            <div class="text-center mt-4 pt-3 border-top">
                <p class="small text-muted mb-0">Already have an account? <a href="lenderLogin.php" class="fw-bold text-primary text-decoration-none">Login Here</a></p>
            </div>
        </form>
        <?php endif; ?>
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