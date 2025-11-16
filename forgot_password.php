<?php
// We MUST start the session to read/write the reset_otp
require_once 'config.php';

// --- SETTINGS ---
define('OTP_VALIDITY_SECONDS', 300); // 5 minutes
// --- END SETTINGS ---


// If user is logged in, send them to the dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// --- NEW: Handle "Back to Login" link (which also clears session) ---
if (isset($_GET['action']) && $_GET['action'] === 'backtologin') {
    unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time'], $_SESSION['reset_otp_verified']);
    header('Location: login.php');
    exit;
}
// --- END NEW ---

// --- NEW: Invalidate session on any new GET request ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
    // If the user lands on this page for any reason (back button, new link)
    // without it being a special action, destroy any existing reset process.
    unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time'], $_SESSION['reset_otp_verified']);
}
// --- END NEW ---


$error = '';
$success = '';

// This variable will control which form to show:
// 1 = Show "Enter Email" form
// 2 = Show "Enter OTP" form
// 3 = Show "Enter New Password" form
// 4 = Show "Success" message
$step = 1; // Default to step 1


// --- Step-Checking Logic (now simplified) ---
if (isset($_SESSION['reset_user_id']) && isset($_SESSION['reset_otp']) && isset($_SESSION['reset_time'])) {
    
    // Check if OTP is expired
    if ((time() - $_SESSION['reset_time']) >= OTP_VALIDITY_SECONDS) {
        // Expired: Clear the old session data and force step 1
        unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time'], $_SESSION['reset_otp_verified']);
        $step = 1;
        $error = 'Your 5-minute password reset session has expired. Please try again.';
    } else {
        // Session is active. Check which step we are on.
        if (isset($_SESSION['reset_otp_verified']) && $_SESSION['reset_otp_verified'] === true) {
            // Step 2 (OTP) was completed. Show Step 3 (New Password).
            $step = 3;
        } else {
            // Session started, but OTP not verified yet. Show Step 2 (Enter OTP).
            $step = 2;
        }
    }
}
// --- End of Step-Checking Logic ---


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Handle Step 1: Send OTP ---
    if (isset($_POST['send_otp'])) {
        $email = clean($_POST['email']);
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
            $step = 1;
        } else {
            $db = db();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user) {
                $otp = strtoupper(substr(md5(time()), 0, 6));
                $emailBody = "Your password reset OTP is: <strong>$otp</strong><br><br>This OTP is valid for 5 minutes.";

                if (sendEmail($email, 'Password Reset OTP', $emailBody)) {
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_otp'] = $otp;
                    $_SESSION['reset_time'] = time();
                    $_SESSION['reset_otp_verified'] = false; // Explicitly set verification to false
                    
                    $success = "An OTP has been sent to your email address. Please check your inbox.";
                    $step = 2; // Move to the next step
                } else {
                    $error = "The system could not send a reset email. Please contact an administrator.";
                    $step = 1;
                }
            } else {
                $error = 'No account found with that email address.';
                $step = 1;
            }
        }
    }


    // --- Handle Step 2: Verify OTP ---
    if (isset($_POST['verify_otp'])) {
        $otp = strtoupper(clean($_POST['otp']));

        // Re-check session and expiration
        if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_otp']) || (time() - $_SESSION['reset_time']) >= OTP_VALIDITY_SECONDS) {
            $error = 'Your 5-minute session has expired. Please request a new OTP.';
            $step = 1; // Send them back to step 1
            unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time'], $_SESSION['reset_otp_verified']);
        
        // Check if OTP matches
        } else if ($otp !== $_SESSION['reset_otp']) {
            $error = 'The OTP you entered is invalid.';
            $step = 2; // Stay on step 2 to let them retry
        
        // All checks passed.
        } else {
            // Mark OTP as verified and move to the next step
            $_SESSION['reset_otp_verified'] = true;
            $step = 3;
            $success = 'OTP verified successfully. Please set your new password.';
        }
    }

    
    // --- Handle Step 3: Reset Password ---
    if (isset($_POST['reset_password'])) {
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];

        // Final security checks: session, expiration, and OTP verification status
        if (!isset($_SESSION['reset_user_id']) || (time() - $_SESSION['reset_time']) >= OTP_VALIDITY_SECONDS || !isset($_SESSION['reset_otp_verified']) || $_SESSION['reset_otp_verified'] !== true) {
            $error = 'Your session is invalid or has expired. Please start over.';
            $step = 1; // Send them back to step 1
            unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time'], $_SESSION['reset_otp_verified']);
        
        // Check if passwords match
        } else if ($newPass !== $confirmPass) {
            $error = 'The new passwords do not match.';
            $step = 3; // Stay on step 3
        
        // Check password length
        } else if (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters long.';
            $step = 3; // Stay on step 3
        
        // All checks passed. Update the database.
        } else {
            $db = db();
            $hashedPass = hashPass($newPass);
            $resetUserId = $_SESSION['reset_user_id'];

            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPass, $resetUserId);
            $stmt->execute();

            if ($stmt->affected_rows === 1) {
                $success = 'Password has been reset successfully. You can now log in with your new password.';
                $step = 4; // Move to final "Success" step
                // Clean up all session variables
                unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time'], $_SESSION['reset_otp_verified']);
            } else {
                $error = 'Error: Could not update password. Please try the process again.';
                $step = 1; // Send them back to step 1
                unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time'], $_SESSION['reset_otp_verified']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BPC Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=1.2">
</head>
<body class="login-page">
    
    <div class="card login-card-new" style="max-width: 450px;">
        
        <div class="login-new-header">
            <div class="login-logo-container">
                <i class="fa-solid fa-key"></i>
            </div>
            <h2 class="login-title">Reset Password</h2>
        </div>

        <div class="login-new-body">
            
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success && $step !== 4):?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <p style="text-align: center; color: #555; margin-bottom: 1.5rem;">Enter your email to receive an OTP.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary btn-full-width">Send OTP</button>
                </form>
                <a href="forgot_password.php?action=backtologin" class="login-new-forgot-link" style="text-align: center; display: block; margin-top: 1.5rem;">
                    Back
                </a>
            <?php endif; ?>

            <?php if ($step === 2): ?>
                <p style="text-align: center; color: #555; margin-bottom: 1.5rem;">Check your email for the 6-character OTP.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Enter OTP</label>
                        <input type="text" name="otp" class="form-control" maxlength="6" placeholder="6-character code" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-primary btn-full-width">Verify OTP</button>
                </form>
                <a href="forgot_password.php?action=backtologin" class="login-new-forgot-link" style="text-align: center; display: block; margin-top: 1.5rem;">
                    Back
                </a>
            <?php endif; ?>

            <?php if ($step === 3): ?>
                <p style="text-align: center; color: #555; margin-bottom: 1.5rem;">Please enter your new password.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary btn-full-width">Set New Password</button>
                </form>
                <a href="forgot_password.php?action=backtologin" class="login-new-forgot-link" style="text-align: center; display: block; margin-top: 1.5rem;">
                    Back
                </a>
            <?php endif; ?>

            <?php if ($step === 4): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; text-align: center;">
                    <i class="fa-solid fa-check-circle" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i><br>
                    <?= htmlspecialchars($success) ?>
                </div>
                <a href="login.php" class="btn btn-primary btn-full-width">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> <span>Back</span>
                </a>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>