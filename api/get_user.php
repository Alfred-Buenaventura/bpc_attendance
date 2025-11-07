<?php
require_once '../config.php';
requireAdmin();

if (!isset($_GET['id'])) {
    jsonResponse(false, 'User ID required');
}

$userId = (int)$_GET['id'];
$db = db();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    jsonResponse(false, 'User not found');
}
unset($user['password']);

jsonResponse(true, 'User retrieved', $user);
?>
