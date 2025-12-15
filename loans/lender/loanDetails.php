<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['lender_id']) || !isset($_GET['id'])) {
    header("Location: lenderDashboard.php");
    exit;
}

$lender_id = $_SESSION['lender_id'];
$loan_id = $_GET['id'];

// 1. Fetch Loan & Borrower Details
$stmt = $pdo->prepare("
    SELECT l.*, u.name as borrower_name, u.email as borrower_email, u.phone as borrower_phone 
    FROM loans l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.id = ? AND l.lender_id = ?
");
$stmt->execute([$loan_id, $lender_id]);
$loan = $stmt->fetch();

if (!$loan) die("Loan not found or access denied.");

// 2. Fetch Lender Details (To get your email for the CC/Reply-To)
// We assume there is a 'lenders' table or similar. If you store lenders in 'users', change table name.
$stmtLender = $pdo->prepare("SELECT email FROM lenders WHERE id = ?");
$stmtLender->execute([$lender_id]);
$lender_data = $stmtLender->fetch();
$lender_email = $lender_data['email'] ?? 'lender@example.com'; // Fallback if not found

// 3. Fetch Payments
$stmt = $pdo->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC");
$stmt->execute([$loan_id]);
$payments = $stmt->fetchAll();

// 4. Calculations
$total_paid = array_sum(array_column($payments, 'amount_paid'));
$total_due = $loan['amount'] + ($loan['amount'] * ($loan['interest_rate']/100));
$progress = ($total_due > 0) ? ($total_paid/$total_due)*100 : 0;

// 5. PREPARE EMAIL CONTENT (AGREEMENT)
$currency = "PHP"; // Or symbol
$created_date = date('F d, Y', strtotime($loan['created_at']));
$agreement_subject = urlencode("Loan Agreement Copy - Ref #$loan_id");

// Text body for the Agreement Email
$agreement_body = "LOAN AGREEMENT DETAILS\n\n";
$agreement_body .= "Reference ID: #$loan_id\n";
$agreement_body .= "Date Issued: $created_date\n\n";
$agreement_body .= "BETWEEN:\n";
$agreement_body .= "Lender: " . ($_SESSION['lender_name'] ?? 'Lender') . " ($lender_email)\n";
$agreement_body .= "Borrower: " . $loan['borrower_name'] . " (" . $loan['borrower_email'] . ")\n\n";
$agreement_body .= "TERMS:\n";
$agreement_body .= "Principal Amount: " . number_format($loan['amount'], 2) . "\n";
$agreement_body .= "Interest Rate: " . $loan['interest_rate'] . "%\n";
$agreement_body .= "Total Repayment: " . number_format($total_due, 2) . "\n";
$agreement_body .= "Term: " . ($loan['months'] ?? 12) . " Months\n\n";
$agreement_body .= "This email serves as a digital record of the loan details.";

$agreement_body_encoded = urlencode($agreement_body);

// 6. PREPARE GENERAL CONTACT EMAIL
$contact_subject = urlencode("Regarding your Loan #$loan_id");
$contact_body = urlencode("Hi " . $loan['borrower_name'] . ",\n\nI am writing regarding your loan status.\n\nBest regards,\n" . ($_SESSION['lender_name'] ?? 'Lender'));

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<title>Loan #<?= $loan_id ?> | Lender Portal</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { font-family:'Plus Jakarta Sans', sans-serif; background:#f8fafc; margin:0; padding:0; }
.container { max-width:1100px; margin:auto; padding:20px; }
.card-custom { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.05); padding:24px; margin-bottom:24px; }
.info-label { font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
.info-val { font-size:1rem; font-weight:600; color:#111827; }
.progress { height:10px; border-radius:5px; background-color:#e5e7eb; }
.table-sm td, .table-sm th { padding:.25rem .5rem; font-size:.875rem; }
@media(max-width:768px){ .d-md-flex { display:block !important; text-align:center; } }
.payment-table { overflow-x:auto; }
</style>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <a href="lenderLoans.php" class="text-decoration-none text-secondary fw-bold mb-2">
            <i class="fas fa-arrow-left me-2"></i> Back to List
        </a>
        <?php if($loan['status']==='Pending'): ?>
            <button class="btn btn-success mb-2"><i class="fas fa-check me-2"></i> Approve Loan</button>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-custom">
                <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap">
                    <div>
                        <h3 class="fw-bold mb-1">Loan #<?= $loan['id'] ?></h3>
                        <span class="badge bg-secondary"><?= $loan['status'] ?></span>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Total Amount</div>
                        <div class="fs-4 fw-bold text-primary">₱<?= number_format($loan['amount'],2) ?></div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="info-label">Borrower</div>
                        <div class="info-val"><?= htmlspecialchars($loan['borrower_name']) ?></div>
                        <div class="small text-muted">
                            <i class="far fa-envelope me-1"></i><?= htmlspecialchars($loan['borrower_email']) ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="info-label">Phone</div>
                        <div class="info-val"><?= htmlspecialchars($loan['borrower_phone'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="info-label">Interest Rate</div>
                        <div class="info-val text-danger"><?= $loan['interest_rate'] ?>%</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="info-label">Term</div>
                        <div class="info-val"><?= $loan['months'] ?? 12 ?> Months</div>
                    </div>
                </div>

                <hr class="opacity-25">

                <h5 class="fw-bold mb-2">Repayment Progress</h5>
                <div class="d-flex justify-content-between small mb-1">
                    <span>Paid: ₱<?= number_format($total_paid,2) ?></span>
                    <span>Total Due: ₱<?= number_format($total_due,2) ?></span>
                </div>
                <div class="progress mb-4">
                    <div class="progress-bar bg-success" style="width:<?= min(100,$progress) ?>%"></div>
                </div>

                <h5 class="fw-bold mb-2">Payment History</h5>
                <?php if(count($payments)>0): ?>
                    <div class="payment-table">
                        <table class="table table-sm table-borderless">
                            <thead class="text-muted border-bottom">
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($payments as $pay): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($pay['payment_date'])) ?></td>
                                        <td class="text-muted font-monospace small">REF-<?= $pay['id'] ?></td>
                                        <td class="text-end fw-bold text-success">+₱<?= number_format($pay['amount_paid'],2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light text-center text-muted border">No payments recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-custom bg-dark text-white">
                <h5 class="fw-bold mb-3">Lender Actions</h5>
                <p class="small text-white-50">Manage this loan record. Communication uses your default email client.</p>
                
                <div class="d-grid gap-2 mb-3">
                    <a href="mailto:<?= $loan['borrower_email'] ?>?cc=<?= $lender_email ?>&subject=<?= $agreement_subject ?>&body=<?= $agreement_body_encoded ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-file-contract me-2"></i> Email Agreement
                    </a>

                    <a href="mailto:<?= $loan['borrower_email'] ?>?subject=<?= $contact_subject ?>&body=<?= $contact_body ?>" 
                       class="btn btn-outline-light">
                        <i class="fas fa-envelope me-2"></i> Contact Borrower
                    </a>
                </div>
                
                <hr class="border-secondary">
                <div class="small text-white-50">
                    <div><strong>Created:</strong> <?= date('F d, Y', strtotime($loan['created_at'])) ?></div>
                    <div class="mt-1"><strong>Lender Email:</strong> <?= htmlspecialchars($lender_email) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>