<?php
// db.php - PDO connection
$DB_HOST = '127.0.0.1';
$DB_NAME = 'loan_track_db';
$DB_USER = 'root';
$DB_PASS = ''; // your MySQL password if any

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
    // echo "✅ Connected to database successfully!"; // Remove or comment out this line
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
?>
