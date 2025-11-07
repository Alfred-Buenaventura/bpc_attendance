<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

// Security: Only admins can run this script
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Access Denied.']);
    exit;
}

$db = db();
$notifiedCount = 0;

// This is the main notification message
$message = "Reminder: Your account registration is incomplete. Please visit the IT office for fingerprint registration to activate your account.";
$type = 'warning';

try {
    // 1. MODIFIED QUERY: Fetch id, email, and first_name for all pending users
    // This query now matches the logic from complete_registration.php
    $stmt = $db->prepare("
        SELECT id, email, first_name 
        FROM users 
        WHERE status = 'active' AND fingerprint_registered = 0
    ");
    $stmt->execute();
    $pendingUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($pendingUsers)) {
        echo json_encode(['success' => true, 'message' => 'No pending users to notify.']);
        exit;
    }

    // Prepare statements for checking and inserting notifications
    $insertStmt = $db->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
    $checkStmt = $db->prepare("SELECT id FROM notifications WHERE user_id = ? AND message = ? AND is_read = 0");

    foreach ($pendingUsers as $user) {
        $userId = $user['id'];
        $email = $user['email'];
        $firstName = $user['first_name'];

        // Check if an identical, unread notification already exists
        $checkStmt->bind_param("is", $userId, $message);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        if (!$existing) {
            // 1. Create the dashboard notification
            $insertStmt->bind_param("iss", $userId, $message, $type);
            $insertStmt->execute();
            
            if ($insertStmt->affected_rows > 0) {
                $notifiedCount++;

                // 2. --- NEW: Send the email ---
                $emailSubject = "BPC Attendance: Fingerprint Registration Reminder";
                $emailMessage = "Hi " . htmlspecialchars($firstName) . ",<br><br>"
                                . $message 
                                . "<br><br>Thank you,<br>BPC Administration";
                
                // Call the existing sendEmail function from config.php
                sendEmail($email, $emailSubject, $emailMessage);
                // --- END NEW ---
            }
        }
    }

    if ($notifiedCount > 0) {
        // --- MODIFIED: Updated log message ---
        logActivity($_SESSION['user_id'], 'Sent Notifications', "Sent $notifiedCount fingerprint registration reminders via dashboard and email.");
        echo json_encode(['success' => true, 'message' => "Successfully sent $notifiedCount notifications."]);
    } else {
        echo json_encode(['success' => true, 'message' => 'All pending users have already been notified.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>