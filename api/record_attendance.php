<?php
require_once '../config.php';

// Get the POSTed data (which is JSON sent from display.php)
$data = json_decode(file_get_contents('php://input'));

if (!$data || !isset($data->user_id)) {
    jsonResponse(false, "Invalid user ID.");
    exit;
}

$userId = (int)$data->user_id;
$db = db();

// Get current date/time in PH time
$today = date('Y-m-d');
$now = date('H:i:s');
$status = "";

// Fetch the user's details
$user = getUser($userId);
if (!$user) {
    jsonResponse(false, "User not found.");
    exit;
}

// Check if an attendance record for today already exists
$stmt = $db->prepare("SELECT id, time_in FROM attendance_records WHERE user_id = ? AND date = ?");
$stmt->bind_param("is", $userId, $today);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record) {
    // RECORD EXISTS: This is a TIME OUT
    // We update the existing record with the time_out
    $stmt = $db->prepare("UPDATE attendance_records SET time_out = ? WHERE id = ?");
    $stmt->bind_param("si", $now, $record['id']);
    $stmt->execute();
    $stmt->close();
    $status = "Time Out";

} else {
    // NO RECORD: This is a TIME IN
    
    // TODO: Add logic here to check against the 'schedules' table
    // to determine if the $status should be "On-time" or "Late".
    // For now, we'll default to "On-time".
    $timeInStatus = "On-time"; 

    $stmt = $db->prepare("INSERT INTO attendance_records (user_id, date, time_in, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $today, $now, $timeInStatus);
    $stmt->execute();
    $stmt->close();
    $status = "Time In";
}

// Prepare the data to send back to display.php
// This matches the format showScanEvent() expects
$displayData = [
    "type"   => "attendance",
    "name"   => $user['first_name'] . ' ' . $user['last_name'],
    "status" => $status,
    "time"   => date('h:i A', strtotime($now)),
    "date"   => date('l, F j, Y') // e.g., "Saturday, November 08, 2025"
];

// Send a successful response with the display data
jsonResponse(true, "Attendance recorded", $displayData);
?>