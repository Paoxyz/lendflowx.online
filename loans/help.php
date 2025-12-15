<?php
session_start();
$role = $_SESSION['role'] ?? '';
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Help | Loan Tracker</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --primary: #dc2626;
  --primary-hover: #ef4444;
  --bg-light: #f9fafb;
  --bg-dark: #121622;
  --card-light: rgba(255,255,255,0.85);
  --card-dark: rgba(30,40,59,0.85);
  --text-light: #111827;
  --text-dark: #e5e7eb;
  --text-muted-light: #6b7280;
  --text-muted-dark: #9ca3af;
  --transition: 0.4s ease-in-out;
}
body { font-family:Arial,sans-serif; min-height:100vh; background:var(--bg-light); color:var(--text-light); transition:0.4s ease-in-out; }
html[data-bs-theme="dark"] body { background:var(--bg-dark); color:var(--text-dark); }

.navbar { background-color: var(--card-light); transition:0.4s ease-in-out; }
html[data-bs-theme="dark"] .navbar { background-color: var(--card-dark); }

footer { background-color: var(--card-light); text-align:center; color:var(--text-muted-light); padding:20px; transition:0.4s ease-in-out; }
html[data-bs-theme="dark"] footer { background-color: var(--card-dark); color:var(--text-muted-dark); }

#themeToggle { border:none; background:transparent; font-size:22px; cursor:pointer; color:var(--text-muted-light); }
html[data-bs-theme="dark"] #themeToggle { color:var(--text-muted-dark); }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">
      <div class="d-inline-block p-2" style="background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;border-radius:8px;">LT</div>
      Loan Tracker
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="index.php" class="btn btn-link text-muted">Home</a>
      <a href="about.php" class="btn btn-link text-muted">About</a>
      <button id="themeToggle" title="Toggle Dark Mode">🌙</button>
    </div>
  </div>
</nav>

<div class="container py-5">
  <h1>Help & Support</h1>
  <p>Welcome to the Help section. Here you can find answers to common questions about registering, uploading documents, applying for loans, and tracking payments.</p>

  <h4>FAQs:</h4>
  <ul>
    <li><strong>How do I register?</strong> Click "Get Started" on the home page and fill out your information.</li>
    <li><strong>What documents are required?</strong> Government-issued ID is required. Payslip and selfie are optional but recommended.</li>
    <li><strong>How long does verification take?</strong> Admins typically review submissions within 24-48 hours.</li>
    <li><strong>How do I apply for a loan?</strong> Once verified, go to your dashboard and click "Apply for Loan".</li>
    <li><strong>How do I check payments?</strong> Payments can be viewed in your dashboard under "My Loans".</li>
  </ul>
</div>

<footer>
  © <?php echo date('Y'); ?> Loan Tracker — Built for secure institutional loan workflows.
</footer>

<script>
const htmlEl = document.documentElement;
const toggleBtn = document.getElementById('themeToggle');
const savedTheme = localStorage.getItem('theme');
if(savedTheme==='dark'){ htmlEl.setAttribute('data-bs-theme','dark'); toggleBtn.textContent='☀️'; }
toggleBtn.addEventListener('click',()=>{
  const current = htmlEl.getAttribute('data-bs-theme');
  const newTheme = current==='dark'?'light':'dark';
  htmlEl.setAttribute('data-bs-theme',newTheme);
  localStorage.setItem('theme',newTheme);
  toggleBtn.textContent = newTheme==='dark'?'☀️':'🌙';
});
</script>
</body>
</html>
