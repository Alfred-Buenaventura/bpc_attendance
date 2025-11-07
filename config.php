<?php
// Start or resume a session
session_start();

// --- PHPMailer Imports ---
// These MUST be at the very top, before any HTML or other PHP.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/PHPMailer.php';
require 'vendor/SMTP.php';
require 'vendor/Exception.php';
// --- End PHPMailer Imports ---


// Database connection constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bpc_attendance');

// Set the default timezone for date/time functions
date_default_timezone_set('Asia/Manila');

// Function to get the database connection (uses a static variable to prevent multiple connections)
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }
    return $conn;
}

// Function to clean user input to prevent XSS attacks
function clean($data) {
    return htmlspecialchars(trim($data));
}

// Function to check if a user is currently logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if the logged-in user is an Admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

// Function to require a user to be logged in to access a page
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    
    // Get the name of the script being run
    $current_page = basename($_SERVER['PHP_SELF']);

    // Check for forced password change
    if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === 1) {
        
        // If the flag is set, redirect to change_password.php
        // UNLESS the user is *already* on that page or logging out.
        if ($current_page !== 'change_password.php' && $current_page !== 'logout.php') {
            header('Location: change_password.php?first_login=1');
            exit;
        }
    }
}

// Function to require a user to be an Admin to access a page
function requireAdmin() {
    requireLogin(); // First, ensure they are logged in
    if (!isAdmin()) {
        die('Access denied'); // Stop the script if they are not an admin
    }
}

// Function to hash a password using the default, secure PHP method
function hashPass($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify a submitted password against a saved hash
function verifyPass($password, $hash) {
    return password_verify($password, $hash);
}

// Function to log an activity to the `activity_logs` table
function logActivity($userId, $action, $details = '') {
    $db = db();
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $details, $ip);
    $stmt->execute();
}

// Function to get a single user's details by their ID
function getUser($userId) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * NEW: Function to create an in-app notification for a user.
 */
function createNotification($userId, $message, $type = 'info') {
    $db = db();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $message, $type);
    $stmt->execute();
}


// ===== NEW, FUNCTIONAL sendEmail FUNCTION (for GMAIL) =====
function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);

    // --- !! IMPORTANT !! ---
    // --- Your Gmail SMTP Settings ---
    $smtp_username = 'bpcattendancemonitoringsystem@gmail.com'; // <-- Your credentials
    $smtp_password = 'bmgu yack ddsb xfzp';   // <-- PASTE YOUR 16-DIGIT GMAIL APP PASSWORD
    $smtp_from_name = 'BPC Attendance Monitoring System';
    // ---------------------------------

    try {
        // ===== DEBUGGING DISABLED =====
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
        // $mail->Debugoutput = 'html';
        // ==============================

        //Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_username;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        //Recipients
        $mail->setFrom($smtp_username, $smtp_from_name);
        $mail->addAddress($to); // Add a recipient (name is optional)

        //Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message); // For non-HTML mail clients

        $mail->send();
        return true;
    } catch (Exception $e) {
        
        // ===== DEBUGGING DISABLED =====
        // "Silently" fail but log the error to your server's log
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
        // ===============================
    }
}
// ===== END OF NEW FUNCTION =====



// Function to return a standardized JSON response for API calls
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data) $response['data'] = $data;
    echo json_encode($response);
    exit;
}
?>