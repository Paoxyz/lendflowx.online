<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service | LoanTracker</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #dc2626;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --bg-body: #f8fafc;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: var(--text-main); padding-top: 80px; }
        
        /* Navbar */
        .navbar { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-bottom: 1px solid #e2e8f0; padding: 15px 0; }
        .navbar-brand { font-weight: 800; color: var(--primary); font-size: 1.4rem; }
        
        /* Content */
        .terms-container { max-width: 800px; margin: 0 auto; background: white; padding: 60px; border-radius: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        h1 { font-weight: 800; letter-spacing: -1px; margin-bottom: 10px; }
        h4 { font-weight: 700; margin-top: 30px; margin-bottom: 15px; color: var(--text-main); }
        p, li { color: var(--text-muted); line-height: 1.7; font-size: 0.95rem; }
        ul { padding-left: 20px; }
        .last-updated { font-size: 0.85rem; color: #94a3b8; margin-bottom: 40px; display: block; }
        
        .btn-back { position: fixed; top: 100px; left: 40px; font-weight: 600; color: var(--text-muted); text-decoration: none; transition: 0.2s; }
        .btn-back:hover { color: var(--primary); transform: translateX(-5px); }

        @media (max-width: 992px) {
            .terms-container { padding: 30px; margin: 20px; }
            .btn-back { position: static; display: inline-block; margin-bottom: 20px; margin-left: 20px; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-wallet me-2"></i>LoanTracker</a>
            <a href="index.php" class="btn btn-sm btn-light fw-bold border">Close</a>
        </div>
    </nav>

    <a href="javascript:history.back()" class="btn-back"><i class="fas fa-arrow-left me-2"></i> Back</a>

    <div class="container py-5">
        <div class="terms-container">
            <h1>Terms of Service</h1>
            <span class="last-updated">Last Updated: November 2025</span>

            <hr class="my-4 opacity-10">

            <h4>1. Acceptance of Terms</h4>
            <p>By creating an account or using LoanTracker, you agree to be bound by these Terms of Service. If you do not agree to all the terms and conditions, you must not use our services.</p>

            <h4>2. Role of LoanTracker</h4>
            <p>LoanTracker serves as a platform connecting Borrowers and Lenders. We provide the technology to track loans, schedules, and payments. We are not a bank, and we do not lend our own funds unless explicitly stated.</p>

            <h4>3. User Obligations</h4>
            <ul>
                <li>You must provide accurate and complete registration information.</li>
                <li>You are responsible for maintaining the security of your account credentials.</li>
                <li>You must not use the platform for any illegal activities, including money laundering or fraud.</li>
            </ul>

            <h4>4. For Lenders</h4>
            <p>By registering as a Lender, you acknowledge that:</p>
            <ul>
                <li>Lending involves financial risk, including the potential loss of principal.</li>
                <li>You are responsible for verifying the identity and creditworthiness of borrowers beyond the tools we provide.</li>
                <li>You agree to comply with all applicable local lending laws and tax regulations.</li>
            </ul>

            <h4>5. For Borrowers</h4>
            <p>By registering as a Borrower, you agree to:</p>
            <ul>
                <li>Repay loans according to the agreed schedule.</li>
                <li>Provide truthful information regarding your income and identity.</li>
                <li>Acknowledge that failure to pay may result in legal action by the Lender.</li>
            </ul>

            <h4>6. Fees and Payments</h4>
            <p>LoanTracker may charge a processing fee for loan applications. All fees are disclosed prior to transaction completion. Payments recorded on the system are for tracking purposes; actual fund transfers occur between parties outside the platform (e.g., via Bank Transfer/GCash) unless integrated payment gateways are active.</p>

            <h4>7. Termination</h4>
            <p>We reserve the right to suspend or terminate accounts that violate these terms, engage in fraudulent activity, or harass other users.</p>

            <hr class="my-5 opacity-10">
            
            <p class="text-center small">
                &copy; 2025 LoanTracker. All rights reserved.<br>
                Questions? Contact us at <a href="#" class="text-danger text-decoration-none">legal@loantracker.com</a>
            </p>
        </div>
    </div>

</body>
</html>