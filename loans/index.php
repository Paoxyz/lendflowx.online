<?php
session_start();
$role = $_SESSION['role'] ?? '';

if (!empty($_SESSION['user_id'])) {
    if ($role === 'admin') {
        header('Location: admin/verify_users.php');
    } elseif ($role === 'borrower') {
        header('Location: borrower/borrowerDashboard.php');
    } else {
        session_unset();
        header('Location: index.php');
    }
    exit;
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Loan Application & Payment Tracker</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #dc2626;
            --primary-hover: #ef4444;
            --bg-light: #f8fafc;
            --bg-dark: #0f172a;
            --card-light: rgba(255, 255, 255, 0.75);
            --card-dark: rgba(30, 41, 59, 0.65);
            --text-light: #1e293b;
            --text-dark: #e2e8f0;
            --text-muted-light: #64748b;
            --text-muted-dark: #94a3b8;
            --transition: 0.35s ease;
            --radius-xl: 24px;
        }

        * {
            transition: color var(--transition), background var(--transition), transform var(--transition);
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: var(--bg-light);
            color: var(--text-light);
        }

        html[data-bs-theme="dark"] body {
            background: var(--bg-dark);
            color: var(--text-dark);
        }

        /* Enhanced Navbar */
        .navbar {
            backdrop-filter: blur(16px);
            background: var(--card-light);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 14px 0;
        }

        html[data-bs-theme="dark"] .navbar {
            background: var(--card-dark);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .navbar-brand .logo {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: white;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(220,38,38,0.4);
        }

        .navbar-brand:hover .logo {
            transform: rotate(6deg);
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(220,38,38,0.35);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 10px 28px;
            border-radius: 10px;
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            padding: 160px 0 120px;
            overflow: hidden;
        }

        .hero-title {
            font-weight: 800;
            line-height: 1.15;
        }

        .hero-text {
            color: var(--text-muted-light);
        }

        html[data-bs-theme="dark"] .hero-text {
            color: var(--text-muted-dark);
        }

        /* Glass card */
        .card-custom {
            background: var(--card-light);
            border-radius: var(--radius-xl);
            padding: 35px;
            border: 1px solid rgba(255,255,255,0.35);
            backdrop-filter: blur(20px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.07);
        }

        html[data-bs-theme="dark"] .card-custom {
            background: var(--card-dark);
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 12px 32px rgba(0,0,0,0.55);
        }

        /* Floating Shapes */
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.18;
            animation: float 9s ease-in-out infinite;
        }

        .shape1 { width: 320px; height: 320px; background: var(--primary); top: -80px; left: -80px; }
        .shape2 { width: 420px; height: 420px; background: #3b82f6; bottom: 0; right: -100px; opacity: 0.12; }

        @keyframes float {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-25px); }
        }

        /* Steps */
        .step-card {
            text-align: center;
            transition: transform 0.25s ease;
        }
        .step-card:hover {
            transform: translateY(-6px);
        }

        .step-icon {
            width: 80px;
            height: 80px;
            background: rgba(220,38,38,0.12);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 30px;
            color: var(--primary);
        }

        /* FAQ */
        .accordion-item {
            border-radius: 14px !important;
            background: var(--card-light);
            border: none;
        }
        html[data-bs-theme="dark"] .accordion-item {
            background: var(--card-dark);
        }

        /* Footer */
        .footer {
            background: var(--card-light);
            padding: 50px 0;
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        html[data-bs-theme="dark"] .footer {
            background: var(--card-dark);
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        #themeToggle {
            background: transparent;
            border: 0;
            font-size: 23px;
            cursor: pointer;
        }

        /* NAVBAR ALIGNMENT FIX — NO DESIGN CHANGE */
        .navbar-actions-fix {
            flex-wrap: wrap;
            align-items: center;
        }

        @media (max-width: 991px) {
            .navbar-actions-fix {
                width: 100%;
                justify-content: center;
                margin-top: 12px;
            }
        }

        /* RESPONSIVE HERO TEXT */
        .hero-title {
            font-size: 2rem;
        }
        .hero-text {
            font-size: 1rem;
        }
        @media (min-width: 768px) {
            .hero-title {
                font-size: 3.2rem;
            }
            .hero-text {
                font-size: 1.15rem;
            }
        }
        @media (min-width: 1200px) {
            .hero-title {
                font-size: 3.5rem;
            }
            .hero-text {
                font-size: 1.25rem;
            }
        }
    </style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
            <div class="logo">LT</div>
            <div>
                <div class="fw-bold">Loan Tracker</div>
                <small class="text-muted">Application Portal</small>
            </div>
        </a>

        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How it Works</a></li>
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
            </ul>

            <div class="d-flex gap-2 navbar-actions-fix">
                <button id="themeToggle">🌙</button>
                <a href="borrower/borrowerLogin.php" class="btn btn-outline-primary btn-sm">Log In</a>
                <a href="register.php" class="btn btn-primary btn-sm">Register</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero-section">
    <div class="floating-shape shape1"></div>
    <div class="floating-shape shape2"></div>

    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-danger bg-opacity-10 text-danger mb-3 px-3 py-2 rounded-pill">
                    <i class="fas fa-shield-alt me-2"></i>Secure & Admin Verified
                </span>

                <h1 class="hero-title">Simplifying Loan Applications for Everyone.</h1>

                <p class="hero-text mt-3">
                    A seamless platform to register, verify your identity, and track your loan payments.
                    Fast, transparent, and secure.
                </p>

                <div class="d-flex gap-3 mt-4 flex-wrap">
                    <a href="register.php" class="btn btn-primary btn-lg px-5">Start Application</a>
                    <a href="#how-it-works" class="btn btn-outline-primary btn-lg">Learn More</a>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card-custom">
                    <div class="d-flex justify-content-between mb-4">
                        <h5 class="fw-bold">Application Preview</h5>
                        <span class="badge bg-success">Live System</span>
                    </div>

                    <div class="vstack gap-3">

                        <div class="p-3 rounded bg-body-tertiary border d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 p-3 rounded text-primary">
                                <i class="fas fa-id-card fa-lg"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Identity Verification</div>
                                <div class="text-muted small">Upload Gov ID & Selfie</div>
                            </div>
                            <i class="fas fa-check-circle text-success ms-auto"></i>
                        </div>

                        <div class="p-3 rounded bg-body-tertiary border d-flex align-items-center gap-3">
                            <div class="bg-warning bg-opacity-10 p-3 rounded text-warning">
                                <i class="fas fa-file-invoice-dollar fa-lg"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Income Proof</div>
                                <div class="text-muted small">Payslip or Bank Statement</div>
                            </div>
                            <i class="fas fa-circle text-muted ms-auto"></i>
                        </div>

                        <div class="p-3 rounded bg-body-tertiary border d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 p-3 rounded text-info">
                                <i class="fas fa-user-check fa-lg"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Admin Review</div>
                                <div class="text-muted small">Await approval notification</div>
                            </div>
                            <i class="fas fa-lock text-muted ms-auto"></i>
                        </div>

                    </div>

                    <div class="text-center border-top mt-4 pt-3">
                        <small class="text-muted">Join 500+ verified borrowers today</small>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section id="how-it-works" class="py-5">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">How It Works</h2>
        <p class="text-muted mx-auto mb-5" style="max-width:620px;">Get your loan approved in 4 simple steps.</p>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="card-custom step-card p-4 h-100">
                    <div class="step-icon mb-3"><i class="fas fa-user-plus"></i></div>
                    <h5 class="fw-bold">1. Register</h5>
                    <p class="text-muted small">Create your account securely.</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-custom step-card p-4 h-100">
                    <div class="step-icon mb-3"><i class="fas fa-cloud-upload-alt"></i></div>
                    <h5 class="fw-bold">2. Upload Docs</h5>
                    <p class="text-muted small">Gov ID + proof of income.</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-custom step-card p-4 h-100">
                    <div class="step-icon mb-3"><i class="fas fa-glasses"></i></div>
                    <h5 class="fw-bold">3. Verification</h5>
                    <p class="text-muted small">Admins check authenticity.</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card-custom step-card p-4 h-100">
                    <div class="step-icon mb-3"><i class="fas fa-wallet"></i></div>
                    <h5 class="fw-bold">4. Approval</h5>
                    <p class="text-muted small">Apply for loans instantly.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section id="features" class="py-5" style="background: rgba(0,0,0,0.03);">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">Why Choose Us?</h2>
        <p class="text-muted mx-auto mb-5" style="max-width:620px;">A secure, fast and mobile-friendly system.</p>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card-custom p-4 h-100">
                    <i class="fas fa-shield-alt fs-2 text-primary mb-3"></i>
                    <h5 class="fw-bold">Secure Data</h5>
                    <p class="text-muted small">Your documents and personal info are encrypted.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-custom p-4 h-100">
                    <i class="fas fa-tachometer-alt fs-2 text-primary mb-3"></i>
                    <h5 class="fw-bold">Real-Time Tracking</h5>
                    <p class="text-muted small">Track balances, payments, and loan status instantly.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card-custom p-4 h-100">
                    <i class="fas fa-mobile-alt fs-2 text-primary mb-3"></i>
                    <h5 class="fw-bold">Mobile Friendly</h5>
                    <p class="text-muted small">Seamless access across all devices.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-5">
    <div class="container">
        <h2 class="fw-bold text-center mb-4">Frequently Asked Questions</h2>

        <div class="accordion" id="faqAccordion">

            <div class="accordion-item mb-3 shadow-sm">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" data-bs-toggle="collapse" data-bs-target="#faq1">
                        What documents do I need to apply?
                    </button>
                </h2>
                <div id="faq1" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        A valid Gov ID and proof of income such as a payslip or bank statement.
                    </div>
                </div>
            </div>

            <div class="accordion-item mb-3 shadow-sm">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" data-bs-toggle="collapse" data-bs-target="#faq2">
                        How long does verification take?
                    </button>
                </h2>
                <div id="faq2" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        Typically 24–48 hours depending on admin workload.
                    </div>
                </div>
            </div>

            <div class="accordion-item shadow-sm">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" data-bs-toggle="collapse" data-bs-target="#faq3">
                        Can I apply if I am unemployed?
                    </button>
                </h2>
                <div id="faq3" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        You must provide proof of income to qualify for loans.
                    </div>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- FOOTER -->
<footer class="footer">
    <div class="container text-center">
        <h5 class="fw-bold mb-1">Loan Tracker</h5>
        <p class="text-muted small">Secure Institutional Loan Workflows.</p>

        <div class="d-flex justify-content-center gap-3 my-3">
            <a href="#" class="text-muted"><i class="fab fa-facebook"></i></a>
            <a href="#" class="text-muted"><i class="fab fa-twitter"></i></a>
            <a href="#" class="text-muted"><i class="fab fa-linkedin"></i></a>
        </div>

        <small class="text-muted">&copy; <?php echo date('Y'); ?> All Rights Reserved.</small>
    </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const themeBtn = document.getElementById("themeToggle");
    themeBtn.addEventListener("click", () => {
        const html = document.documentElement;
        const current = html.getAttribute("data-bs-theme");
        html.setAttribute("data-bs-theme", current === "light" ? "dark" : "light");
        themeBtn.textContent = current === "light" ? "☀️" : "🌙";
    });
</script>

</body>
</html>
