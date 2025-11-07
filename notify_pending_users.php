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
$message = "Reminder: Your account registration is incomplete. Please visit the IT office for fingerprint registration to activate your account.";
$type = 'warning';

try {
    // 1. Find all users with pending fingerprint registration
    $stmt = $db->prepare("SELECT id FROM users WHERE status = 'pending_fingerprint'");
    $stmt->execute();
    $pendingUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($pendingUsers)) {
        echo json_encode(['success' => true, 'message' => 'No pending users to notify.']);
        exit;
    }

    // 2. Prepare the notification insert statement
    $insertStmt = $db->prepare("
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        message = VALUES(message), 
        type = VALUES(type), 
        is_read = 0, 
        created_at = CURRENT_TIMESTAMP
    ");
    // Note: "ON DUPLICATE KEY" requires a unique index on (user_id, type) or similar.
    // For simplicity, we'll just insert, but a real-world app might prevent spamming.
    // Let's modify the logic to only insert if one doesn't already exist.

    $insertStmt = $db->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");

    $checkStmt = $db->prepare("SELECT id FROM notifications WHERE user_id = ? AND message = ? AND is_read = 0");


    foreach ($pendingUsers as $user) {
        $userId = $user['id'];

        // Check if an identical, unread notification already exists
        $checkStmt->bind_param("is", $userId, $message);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();

        if (!$existing) {
            // No existing unread notification found, so insert a new one
            $insertStmt->bind_param("iss", $userId, $message, $type);
            $insertStmt->execute();
            if ($insertStmt->affected_rows > 0) {
                $notifiedCount++;
            }
        }
    }

    if ($notifiedCount > 0) {
        logActivity($_SESSION['user_id'], 'Sent Notifications', "Sent $notifiedCount fingerprint registration reminders.");
        echo json_encode(['success' => true, 'message' => "Successfully sent $notifiedCount notifications."]);
    } else {
        echo json_encode(['success' => true, 'message' => 'All pending users have already been notified.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>