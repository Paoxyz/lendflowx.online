<?php
session_start();
require_once '../db.php';

// Access Control
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'borrower') {
    header('Location: borrowerLogin.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['user_name'] ?? 'Borrower';

// ---------------------------------------------------------
// --- DEFENSE CONFIGURATION (The "Fake" Database) ---
// ---------------------------------------------------------
// Map specific Lender IDs to their "Real" payment details.
// If a lender ID is not listed here, it falls back to their phone number.
$lender_configs = [
    // Lender ID => Details
    1 => [
        'gcash' => '0917-111-2222',
        'maya' => '0918-333-4444',
        'bank_name' => 'BDO Unibank',
        'bank_acc' => '0019-1234-5678'
    ],
    2 => [
        'gcash' => '0995-888-7777',
        'maya' => '0922-555-6666',
        'bank_name' => 'UnionBank',
        'bank_acc' => '1094-5555-1111'
    ],
    // Add more lenders here if needed for your demo
];

// 1. FETCH LOAN & LENDER INFO
$stmt = $pdo->prepare("
    SELECT l.*, 
           u.id AS lender_user_id,
           u.name AS lender_name,
           u.phone AS lender_phone,
           u.email AS lender_email,
           COALESCE(SUM(s.total_due - IFNULL(s.paid_amount,0)),0) AS remaining_balance
    FROM loans l
    JOIN users u ON l.lender_id = u.id
    LEFT JOIN loan_schedule s ON l.id = s.loan_id
    WHERE l.user_id = ? AND l.status='Approved'
    GROUP BY l.id
    HAVING remaining_balance > 0.01
    ORDER BY l.created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$loan = $stmt->fetch();

// 2. CONSTRUCT DYNAMIC PAYMENT METHODS
$payment_methods = [];
$js_bank_details = [];

if ($loan) {
    $lid = $loan['lender_user_id'];
    $name = htmlspecialchars($loan['lender_name']);
    $phone = htmlspecialchars($loan['lender_phone']); // Fallback

    // Check if we have "Custom" details for this lender in our PHP config
    $custom = $lender_configs[$lid] ?? null;

    // --- GCASH ---
    $payment_methods[] = 'GCash';
    $js_bank_details['GCash'] = [
        'account_name' => $name,
        'account_number' => $custom['gcash'] ?? $phone, // Use Config or Fallback
        'instruction' => 'Send via GCash Express Send. Screenshot required.',
        'icon' => 'fa-mobile-alt',
        'color' => 'primary'
    ];

    // --- MAYA ---
    $payment_methods[] = 'PayMaya';
    $js_bank_details['PayMaya'] = [
        'account_name' => $name,
        'account_number' => $custom['maya'] ?? $phone, // Use Config or Fallback
        'instruction' => 'Send via Maya. Save the Reference ID.',
        'icon' => 'fa-wallet',
        'color' => 'success'
    ];

    // --- BANK TRANSFER ---
    $payment_methods[] = 'Bank Transfer';
    $bankName = $custom['bank_name'] ?? 'Bank Transfer';
    $bankNum = $custom['bank_acc'] ?? 'Contact Lender';
    
    $js_bank_details['Bank Transfer'] = [
        'account_name' => $name . " ($bankName)",
        'account_number' => $bankNum,
        'instruction' => $custom ? "Deposit to the account above." : "Please contact $name at $phone for bank details.",
        'icon' => 'fa-university',
        'color' => 'info'
    ];

    // --- CASH ---
    $payment_methods[] = 'Cash';
    $js_bank_details['Cash'] = [
        'account_name' => $name,
        'account_number' => 'Meet-up / Cash',
        'instruction' => 'Pay cash directly. Lender must verify manually.',
        'icon' => 'fa-money-bill-wave',
        'color' => 'warning'
    ];
}

// ------------------------------------------
//  HANDLE PAYMENT
// ------------------------------------------
$payment_error = '';
$payment_success = false;
$receipt_data = []; 
$remaining_balance = $loan['remaining_balance'] ?? 0;
$default_payment = $remaining_balance; 
$next_installment = null;

if ($loan) {
    $stmt = $pdo->prepare("SELECT * FROM loan_schedule WHERE loan_id=? AND status != 'Paid' ORDER BY due_date ASC LIMIT 1");
    $stmt->execute([$loan['id']]);
    $next_installment = $stmt->fetch();
    if ($next_installment) {
        $bill_amount = $next_installment['total_due'] - $next_installment['paid_amount'];
        $default_payment = min($bill_amount, $remaining_balance);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loan) {
    $amount_paid = floatval($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $method = $_POST['payment_method'] ?? 'Cash'; 
    $proof_path = null;

    if ($amount_paid <= 0) {
        $payment_error = "Enter a valid positive amount.";
    } elseif ($amount_paid > $remaining_balance + 1) { 
        $payment_error = "Amount cannot exceed remaining balance.";
    } else {
        // File Upload
        if ($method !== 'Cash') {
            if (isset($_FILES['receipt_proof']) && $_FILES['receipt_proof']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                $filename = $_FILES['receipt_proof']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $target_dir = "../uploads/receipts/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                    $new_filename = "PAY-" . time() . "-" . $loan['id'] . "." . $ext;
                    if (move_uploaded_file($_FILES['receipt_proof']['tmp_name'], $target_dir . $new_filename)) {
                        $proof_path = $new_filename;
                    } else {
                        $payment_error = "Failed to upload receipt.";
                    }
                } else {
                    $payment_error = "Invalid file type. JPG, PNG, PDF only.";
                }
            } else {
                $payment_error = "Please upload proof of payment.";
            }
        }
    }

    if (empty($payment_error)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO payments (loan_id, amount_paid, payment_date, payment_method, proof_of_payment, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$loan['id'], $amount_paid, $payment_date, $method, $proof_path]);
            
            $receipt_id = $pdo->lastInsertId();
            $receipt_data = [
                'id' => $receipt_id, 'date' => $payment_date, 'amount' => $amount_paid,
                'loan_ref' => $loan['loan_id'], 'borrower' => $username, 'method' => $method
            ];

            // AUTO-CREDIT (Waterfall)
            $money_to_distribute = $amount_paid;
            $schedStmt = $pdo->prepare("SELECT * FROM loan_schedule WHERE loan_id = ? AND status != 'Paid' ORDER BY due_date ASC");
            $schedStmt->execute([$loan['id']]);
            $schedules = $schedStmt->fetchAll();

            foreach ($schedules as $sched) {
                if ($money_to_distribute <= 0) break;
                $current_due = $sched['total_due'] - $sched['paid_amount'];
                if ($money_to_distribute >= $current_due) {
                    $pdo->prepare("UPDATE loan_schedule SET paid_amount = total_due, status = 'Paid' WHERE id = ?")->execute([$sched['id']]);
                    $money_to_distribute -= $current_due;
                } else {
                    $new_paid = $sched['paid_amount'] + $money_to_distribute;
                    $pdo->prepare("UPDATE loan_schedule SET paid_amount = ?, status = 'Partially Paid' WHERE id = ?")->execute([$new_paid, $sched['id']]);
                    $money_to_distribute = 0;
                }
            }

            $checkStmt = $pdo->prepare("SELECT SUM(total_due - paid_amount) as balance FROM loan_schedule WHERE loan_id = ?");
            $checkStmt->execute([$loan['id']]);
            $new_balance = $checkStmt->fetchColumn();

            if ($new_balance <= 0) {
                $pdo->prepare("UPDATE loans SET status = 'Completed' WHERE id = ?")->execute([$loan['id']]);
                $remaining_balance = 0; $loan = null; 
            } else {
                $remaining_balance = $new_balance;
            }

            $pdo->commit();
            $payment_success = true; 

        } catch (Exception $e) {
            $pdo->rollBack();
            $payment_error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch history
$payments = [];
if ($loan) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE loan_id=? ORDER BY payment_date DESC");
    $stmt->execute([$loan['id']]);
    $payments = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Pay Loan | LoanTracker</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #dc2626; --primary-soft: rgba(220, 38, 38, 0.08);
        --bg-body: #f8fafc; --bg-card: #ffffff; --bg-sidebar: #ffffff;
        --text-main: #0f172a; --text-muted: #64748b; --border: #e2e8f0;
        --sidebar-w: 260px; --shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    [data-bs-theme="dark"] {
        --bg-body: #0f172a; --bg-card: #1e293b; --bg-sidebar: #1e293b;
        --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155;
    }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-body); color: var(--text-main); padding-top: 70px; }

    /* Sidebar Mobile */
    .sidebar { width: var(--sidebar-w); background: var(--bg-sidebar); border-right: 1px solid var(--border); position: fixed; top: 70px; bottom: 0; left: 0; z-index: 1040; padding: 24px; display: flex; flex-direction: column; transition: transform 0.3s ease; }
    .main-content { flex: 1; margin-left: var(--sidebar-w); padding: 30px; width: 100%; transition: all 0.3s ease; }
    .sidebar-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1050; display: none; }
    .sidebar-overlay.show { display: block; }
    @media (max-width: 991.98px) {
        .sidebar { transform: translateX(-100%); top: 0; z-index: 1060; width: 280px; }
        .sidebar.active { transform: translateX(0); }
        .main-content { margin-left: 0; padding: 15px; } 
    }

    /* Navbar */
    .navbar { background: var(--bg-card); border-bottom: 1px solid var(--border); height: 70px; z-index: 1030; }
    .navbar-brand { font-weight: 800; color: var(--primary); font-size: 1.3rem; }

    /* UI Components */
    .card-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 20px; box-shadow: var(--shadow); margin-bottom: 20px; }
    .form-control, .form-select { padding: 12px 16px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-size: 16px; }
    .bank-info-box { background-color: var(--bg-body); border: 1px dashed var(--border); border-radius: 12px; padding: 20px; position: relative; overflow: hidden; }
    .bank-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
    
    .balance-card { background: linear-gradient(135deg, var(--bg-body), var(--bg-card)); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 25px; }
    .display-balance { font-size: 2rem; font-weight: 800; color: var(--text-main); }
    .nav-link { padding: 12px 20px; color: var(--text-muted); font-weight: 600; border-radius: 12px; margin-bottom: 4px; display: flex; align-items: center; gap: 12px; transition: 0.2s; }
    .nav-link:hover, .nav-link.active { background: var(--primary-soft); color: var(--primary); }
    
    #receiptTicket { display: none; }
    @media print { body * { visibility: hidden; } .sidebar, .navbar, .no-print { display: none !important; } #receiptTicket, #receiptTicket * { visibility: visible; } #receiptTicket { position: absolute; top: 0; left: 0; width: 100%; display: block; padding: 20px; font-family: monospace; border: 2px dashed #000; background: white; color: black; } }

    /* Copy Button */
    .btn-copy { position: absolute; top: 15px; right: 15px; font-size: 0.8rem; padding: 4px 10px; border-radius: 20px; background: rgba(0,0,0,0.05); color: var(--text-muted); border: none; cursor: pointer; transition: 0.2s; }
    .btn-copy:hover { background: var(--primary); color: white; }
    .btn-copy i { margin-right: 4px; }
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid px-3"> 
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link text-primary p-0 d-lg-none" id="sidebarToggle"><i class="fas fa-bars fs-2"></i></button>
        <a class="navbar-brand" href="#"><i class="fas fa-wallet me-2"></i>LoanTracker</a>
    </div>
    <div class="d-flex align-items-center gap-3">
      <button id="themeToggle" class="btn btn-link text-muted p-1 fs-5 border-0"><i class="fas fa-moon"></i></button>
      <div class="dropdown">
        <button class="btn btn-light rounded-pill py-1 px-2 fw-bold d-flex align-items-center gap-2 border shadow-sm" type="button" data-bs-toggle="dropdown">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 14px;">
                <?= strtoupper(substr($username, 0, 1)) ?>
            </div>
            <span class="d-none d-md-block small"><?= htmlspecialchars(explode(' ', $username)[0]) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
            <li><a class="dropdown-item small text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<aside class="sidebar" id="sidebar">
    <div class="d-flex justify-content-between align-items-center mb-4 d-lg-none">
        <div class="fw-bold text-primary fs-4">Menu</div>
        <button class="btn btn-light btn-sm" id="closeSidebar"><i class="fas fa-times"></i></button>
    </div>
    <div class="sidebar-brand d-none d-lg-flex mb-4 px-2 d-flex align-items-center gap-2 text-primary fw-bold">
        <i class="fas fa-wallet fa-lg"></i> <span style="font-size: 1.2rem;">LoanTracker</span>
    </div>
    <nav class="nav flex-column flex-grow-1">
        <a href="borrowerDashboard.php" class="nav-link"><i class="fas fa-th-large w-5"></i> Dashboard</a>
        <a href="apply_loans.php" class="nav-link"><i class="fas fa-plus-circle w-5"></i> Apply Loan</a>
        <a href="#" class="nav-link active"><i class="fas fa-money-bill-wave w-5"></i> Pay Loan</a>
    </nav>
    <div class="mt-auto">
        <button class="nav-link text-danger w-100 text-start border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i class="fas fa-sign-out-alt w-5"></i> Sign Out
        </button>
    </div>
</aside>

<main class="main-content">
    <div id="receiptTicket">
        <div class="text-center mb-3">
            <h4 class="fw-bold">OFFICIAL RECEIPT</h4>
            <small>LoanTracker Systems Inc.</small><br>
            <small><?= date('F d, Y h:i A') ?></small>
        </div>
        <div class="border-top border-bottom py-2 my-2">
            <div>Ref: #<?= $receipt_data['id'] ?? '---' ?></div>
            <div>Paid By: <?= $receipt_data['borrower'] ?? $username ?></div>
            <div>Method: <?= htmlspecialchars($receipt_data['method'] ?? 'Cash') ?></div>
        </div>
        <div class="text-end fw-bold fs-5 mt-2">
            PAID: ₱<?= number_format($receipt_data['amount'] ?? 0, 2) ?>
        </div>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-7 col-md-10 col-12">
            <?php if($payment_success): ?>
                <div class="card-box text-center py-5">
                    <div class="mb-4 text-success opacity-75"><i class="fas fa-check-circle fa-5x"></i></div>
                    <h2 class="fw-bold mb-2">Success!</h2>
                    <p class="text-muted mb-4">Paid <span class="text-dark fw-bold">₱<?= number_format($receipt_data['amount'], 2) ?></span></p>
                    <div class="d-grid gap-2 col-md-8 mx-auto">
                        <button onclick="window.print()" class="btn btn-primary py-3 fw-bold">Download Receipt</button>
                        <a href="borrowerDashboard.php" class="btn btn-light border fw-bold py-3">Back to Dashboard</a>
                    </div>
                </div>
            <?php elseif($loan): ?>
                <div class="card-box">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                        <h6 class="fw-bold m-0 text-uppercase text-muted text-xs">Paying to: <?= htmlspecialchars($loan['lender_name']) ?></h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill">#<?= htmlspecialchars($loan['loan_id']) ?></span>
                    </div>

                    <div class="balance-card d-flex flex-row justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block text-xs fw-bold text-uppercase mb-1">Balance</small>
                            <span class="display-balance">₱<?= number_format($remaining_balance, 2) ?></span>
                        </div>
                    </div>

                    <?php if($next_installment): ?>
                        <div class="alert alert-warning border-0 d-flex align-items-center gap-3 mb-4 p-3 rounded-3 shadow-sm">
                            <div class="bg-white bg-opacity-50 p-2 rounded"><i class="fas fa-clock text-warning"></i></div>
                            <div>
                                <div class="text-xs fw-bold opacity-75 text-uppercase">Due Next</div>
                                <div class="fw-bold text-dark">
                                    ₱<?= number_format(max(0,$next_installment['total_due'] - $next_installment['paid_amount']),2) ?>
                                    <small class="text-muted d-block d-sm-inline ms-sm-1">on <?= date('M d', strtotime($next_installment['due_date'])) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($payment_error): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-4 text-sm"><i class="fas fa-exclamation-circle me-2"></i> <?= $payment_error ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">AMOUNT TO PAY</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 fw-bold">₱</span>
                                <input type="number" step="0.01" name="amount" class="form-control border-start-0 ps-0 fw-bold fs-5" required max="<?= $remaining_balance ?>" value="<?= $default_payment ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">PAYMENT METHOD</label>
                            <select name="payment_method" id="paymentMethod" class="form-select" required>
                                <?php foreach($payment_methods as $pm): ?>
                                    <option value="<?= $pm ?>"><?= $pm ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="bankInfoContainer" class="mb-3" style="display:none;">
                            <div class="bank-info-box">
                                <button type="button" class="btn-copy" onclick="copyNumber()"><i class="fas fa-copy"></i> Copy</button>
                                
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div id="bankIcon" class="bank-icon text-white bg-primary"><i class="fas fa-mobile-alt"></i></div>
                                    <div>
                                        <div class="text-xs fw-bold text-muted text-uppercase">Send To:</div>
                                        <div id="bankName" class="fw-bold text-dark">Lender Name</div>
                                    </div>
                                </div>
                                <div class="bg-white p-2 rounded border text-center mb-3">
                                    <div class="text-xs text-muted mb-1">ACCOUNT NO.</div>
                                    <div id="bankNumber" class="fs-4 fw-bold text-dark letter-spacing-1">---</div>
                                </div>
                                <div class="alert alert-light border text-xs text-muted mb-3" id="bankInstruction">Instruction...</div>
                                <label class="form-label text-muted fw-bold small">ATTACH PROOF</label>
                                <input type="file" name="receipt_proof" id="receiptInput" class="form-control" accept="image/*,application/pdf">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold small">DATE</label>
                            <input type="date" name="payment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm">
                            Confirm Payment <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="card-box text-center py-5">
                    <div class="mb-3 opacity-25 text-muted"><i class="fas fa-check-circle fa-4x"></i></div>
                    <h5>All Settled!</h5>
                    <p class="text-muted small">No active payments due.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if($payments): ?>
        <div class="col-lg-5 col-md-10 col-12 no-print">
             <div class="card-box h-100">
                <h6 class="fw-bold mb-4 pb-2 border-bottom">History</h6>
                <div class="vstack gap-3">
                    <?php foreach($payments as $p): ?>
                    <div class="d-flex justify-content-between align-items-center pb-3 border-bottom">
                        <div class="d-flex gap-3 align-items-center">
                            <div class="bg-light rounded-circle p-2 text-muted" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-receipt"></i></div>
                            <div style="line-height: 1.2;">
                                <div class="fw-bold text-sm text-dark">₱<?= number_format($p['amount_paid'], 2) ?></div>
                                <small class="text-muted text-xs"><?= date('M d', strtotime($p['payment_date'])) ?> • <?= htmlspecialchars($p['payment_method']) ?></small>
                            </div>
                        </div>
                        <div>
                            <?php if(!empty($p['proof_of_payment'])): ?>
                                <a href="../uploads/receipts/<?= htmlspecialchars($p['proof_of_payment']) ?>" target="_blank" class="btn btn-sm btn-light border py-0 px-2 text-xs"><i class="fas fa-eye"></i></a>
                            <?php endif; ?>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 text-xs">Paid</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
             </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-body text-center p-4">
        <div class="mb-3 text-danger"><i class="fas fa-sign-out-alt fa-3x"></i></div>
        <p class="mb-4 fw-medium">Sign out?</p>
        <div class="d-flex gap-2 justify-content-center">
            <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">No</button>
            <a href="../logout.php" class="btn btn-danger px-4 fw-bold">Yes</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Payment UI Logic
    const bankDetails = <?= json_encode($js_bank_details) ?>;
    const methodSelect = document.getElementById('paymentMethod');
    const infoContainer = document.getElementById('bankInfoContainer');
    const bankName = document.getElementById('bankName');
    const bankNumber = document.getElementById('bankNumber');
    const bankInstruction = document.getElementById('bankInstruction');
    const bankIcon = document.getElementById('bankIcon');
    const receiptInput = document.getElementById('receiptInput');

    function updatePaymentUI() {
        if(!methodSelect) return;
        const method = methodSelect.value;
        const data = bankDetails[method];

        if (method === 'Cash') {
            infoContainer.style.display = 'none';
            if(receiptInput) receiptInput.removeAttribute('required');
        } else {
            infoContainer.style.display = 'block';
            if(receiptInput) receiptInput.setAttribute('required', 'required');
            bankName.textContent = data.account_name;
            bankNumber.textContent = data.account_number;
            bankInstruction.textContent = data.instruction;
            bankIcon.className = `bank-icon text-white bg-${data.color}`;
            bankIcon.innerHTML = `<i class="fas ${data.icon}"></i>`;
        }
    }

    function copyNumber() {
        const num = document.getElementById('bankNumber').textContent;
        navigator.clipboard.writeText(num).then(() => {
            alert("Number copied!");
        });
    }

    if(methodSelect) {
        methodSelect.addEventListener('change', updatePaymentUI);
        updatePaymentUI();
    }

    // Sidebar & Theme
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('closeSidebar');
    function toggleSidebar() { sidebar.classList.toggle('active'); overlay.classList.toggle('show'); }
    if(toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
    if(closeBtn) closeBtn.addEventListener('click', toggleSidebar);
    if(overlay) overlay.addEventListener('click', toggleSidebar);

    const html = document.documentElement;
    const themeBtn = document.getElementById('themeToggle');
    if(localStorage.getItem('theme')==='dark'){ html.setAttribute('data-bs-theme', 'dark'); }
    if(themeBtn) {
        themeBtn.addEventListener('click', () => {
            const mode = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', mode);
            localStorage.setItem('theme', mode);
        });
    }
</script>
</body>
</html>