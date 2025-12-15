<?php
 
 * Send a notification to a Borrower OR a Lender
 * * @param PDO $pdo       Database connection
 * @param string $msg    The message text
 * @param string $target 'borrower' or 'lender'
 * @param int $id        The ID of the user or lender
 */
function sendNotification($pdo, $msg, $target, $id) {
    if ($target === 'borrower') {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'borrower', NOW())");
        $stmt->execute([$id, $msg]);
    } 
    elseif ($target === 'lender') {
        $stmt = $pdo->prepare("INSERT INTO notifications (lender_id, message, type, created_at) VALUES (?, ?, 'lender', NOW())");
        $stmt->execute([$id, $msg]);
    }
}

?>