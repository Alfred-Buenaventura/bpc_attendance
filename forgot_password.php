<?php
// We MUST start the session to read/write the reset_otp
require_once 'config.php';

// If user is logged in, send them to the dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// This variable will control which form to show:
// 1 = Show "Enter Email" form
// 2 = Show "Enter OTP & New Password" form
// 3 = Show "Success" message
$step = 1; // Default to step 1


// --- THIS IS THE FIX for your session bug ---
// Check if a reset is in progress AND if it's still valid
if (isset($_SESSION['reset_user_id']) && isset($_SESSION['reset_otp']) && isset($_SESSION['reset_time'])) {
    
    // Check if OTP is expired (1 hour = 3600 seconds)
    if ((time() - $_SESSION['reset_time']) < 3600) {
        // Not expired: Go to step 2
        $step = 2;
    } else {
        // Expired: Clear the old session data and force step 1
        unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time']);
        $step = 1;
    }
} else {
    // No reset in progress, stay on step 1
    $step = 1;
}
// --- END OF FIX ---


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
                $emailBody = "Your password reset OTP is: <strong>$otp</strong><br><br>This OTP is valid for 1 hour.";

                // Call the email function and CHECK THE RESULT
                if (sendEmail($email, 'Password Reset OTP', $emailBody)) {
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_otp'] = $otp;
                    $_SESSION['reset_time'] = time();
                    
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


    // --- Handle Step 2: Verify OTP & Reset Password ---
    if (isset($_POST['reset_password'])) {
        $otp = strtoupper(clean($_POST['otp']));
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];

        // 1. Check if session is valid
        if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_otp'])) {
            $error = 'Your session has expired. Please request a new OTP.';
            $step = 1; // Send them back to step 1
            unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time']);
        
        // 2. Check if OTP matches
        } else if ($otp !== $_SESSION['reset_otp']) {
            $error = 'The OTP you entered is invalid.';
            $step = 2; // Stay on step 2 to let them retry
        
        // 3. Check if OTP is expired
        } else if ((time() - $_SESSION['reset_time']) >= 3600) { // 1 hour validity
            $error = 'The OTP has expired. Please request a new one.';
            $step = 1; // Send them back to step 1
            unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time']);
        
        // 4. Check if passwords match
        } else if ($newPass !== $confirmPass) {
            $error = 'The new passwords do not match.';
            $step = 2; // Stay on step 2
        
        // 5. Check password length
        } else if (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters long.';
            $step = 2; // Stay on step 2
        
        // 6. All checks passed. Try to update the database.
        } else {
            $db = db();
            $hashedPass = hashPass($newPass);
            $resetUserId = $_SESSION['reset_user_id'];

            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPass, $resetUserId);
            $stmt->execute();

            // 7. Verify the update worked
            if ($stmt->affected_rows === 1) {
                $success = 'Password has been reset successfully. You can now log in with your new password.';
                $step = 3; // Move to final "Success" step
                unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time']);
            } else {
                $error = 'Error: Could not update password. Please try the process again.';
                $step = 1; // Send them back to step 1
                unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time']);
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
            <?php if ($success && $step !== 3): // Show success messages for step 2 only ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            
            <?php // --- STEP 1: SHOW EMAIL FORM --- ?>
            <?php if ($step === 1): ?>
                <p style="text-align: center; color: #555; margin-bottom: 1.5rem;">Enter your email to receive an OTP.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary btn-full-width">Send OTP</button>
                </form>
            <?php endif; ?>


            <?php // --- STEP 2: SHOW OTP & NEW PASSWORD FORM --- ?>
            <?php if ($step === 2): ?>
                <p style="text-align: center; color: #555; margin-bottom: 1.5rem;">Check your email for the OTP.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Enter OTP</label>
                        <input type="text" name="otp" class="form-control" maxlength="6" placeholder="6-character code" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary btn-full-width">Reset Password</button>
                </form>
            <?php endif; ?>
            
            
            <?php // --- STEP 3: SHOW SUCCESS MESSAGE --- ?>
            <?php if ($step === 3): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; text-align: center;">
                    <i class="fa-solid fa-check-circle" style="font-size: 1.5rem; margin-bottom: 0.5rem;"></i><br>
                    <?= htmlspecialchars($success) ?>
                </div>
                <a href="login.php" class="btn btn-primary btn-full-width">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> <span>Back to Login</span>
                </a>
            <?php endif; ?>

            
            <?php if ($step !== 3): // Show "Back to Login" on step 1 and 2 ?>
                <a href="login.php" class="login-new-forgot-link" style="text-align: center; display: block; margin-top: 1.5rem;">
                    Back to Login
                </a>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>