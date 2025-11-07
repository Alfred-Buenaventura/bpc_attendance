<?php
// Include the main configuration file which has helper functions and database connection
require_once 'config.php';

// ===== THIS IS THE CORRECTED LOGIC =====
// Check if the user is already logged in
if (isLoggedIn()) {
    // User is logged in. Check if they MUST change their password.
    if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === 1) {
        // Yes, they must change. Redirect them to the change password page.
        header('Location: change_password.php?first_login=1');
    } else {
        // No, they are a normal logged-in user. Send them to the dashboard.
        header('Location: index.php');
    }
    exit; // Stop the script from running further
}
// ===== END OF CORRECTED LOGIC =====
// If the user is NOT logged in, the script continues and shows the login form below.


// Initialize variables to store error or success messages to show the user
$error = '';
$success = '';

// This variable controls whether to show the "Enter OTP" part of the "Forgot Password" modal
$showOtpForm = false; 

// Check if the page was loaded via a POST request (meaning a form was submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Handle User Login ---
    if (isset($_POST['login'])) {
        $username = clean($_POST['username']); 
        $password = $_POST['password'];

        if (!empty($username) && !empty($password)) {
            $db = db(); 
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR faculty_id = ?) AND status = 'active'");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && verifyPass($password, $user['password'])) {
                
                // Create the user's session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['faculty_id'] = $user['faculty_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                // Store the force_password_change flag in the session
                $_SESSION['force_password_change'] = (int)$user['force_password_change'];

                logActivity($user['id'], 'Login', 'User logged in successfully.');

                // Redirect based on password change requirement
                if ($_SESSION['force_password_change']) {
                    header('Location: change_password.php?first_login=1');
                } else {
                    header('Location: index.php');
                }
                exit; 
            } else {
                $error = 'Invalid username or password. Please try again.';
            }
        } else {
            $error = 'Please enter both username and password.';
        }
    }

    // --- Handle Forgot Password (Step 1: Send OTP) ---
    if (isset($_POST['send_otp'])) {
        $email = clean($_POST['email']);

        if (!empty($email)) {
            $db = db();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user) {
                $otp = strtoupper(substr(md5(time()), 0, 6));
                
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_time'] = time(); // Used to check if the OTP has expired

                // sendEmail($email, 'Password Reset OTP', "Your password reset OTP is: $otp.");

                $success = "An OTP has been sent to your email: $otp"; 
                $showOtpForm = true; // Tell the page to show the OTP entry form
            } else {
                $error = 'No account found with that email address.';
                $showOtpForm = false;
            }
        }
    }

    // --- Handle Password Reset (Step 2: Verify OTP) ---
    if (isset($_POST['reset_password'])) {
        $otp = strtoupper(clean($_POST['otp']));
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];
        $showOtpForm = true; 

        if (isset($_SESSION['reset_otp']) && $otp === $_SESSION['reset_otp']) {
            if ((time() - $_SESSION['reset_time']) < 3600) { // 1 hour validity
                if ($newPass === $confirmPass) {
                    if (strlen($newPass) >= 8) {
                        $db = db();
                        $hashedPass = hashPass($newPass); 
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->bind_param("si", $hashedPass, $_SESSION['reset_user_id']);
                        $stmt->execute();

                        unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time']);
                        $success = 'Password has been reset successfully. You can now login.';
                        $showOtpForm = false; 
                    } else {
                        $error = 'Password must be at least 8 characters long.';
                    }
                } else {
                    $error = 'The new passwords do not match.';
                }
            } else {
                $error = 'The OTP has expired. Please request a new one.';
            }
        } else {
            $error = 'The OTP you entered is invalid.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BPC Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=1.2"> </head>
<body class="login-page">
    
    <div class="card login-card-new">
        
        <div class="login-new-header">
            <div class="login-logo-container">
                <i class="fa-solid fa-fingerprint"></i>
            </div>
            <h2 class="login-title">BPC Attendance System</h2>
            <p class="login-subtitle">Fingerprint-based Attendance & Gate Entry Monitoring</p>
        </div>

        <div class="login-new-body">
            
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label> <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-input">
                        <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                            <i id="eyeIcon" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options-new">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" name="login" class="btn btn-primary btn-full-width">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> <span>Sign In</span> </button>
            </form>

            <a href="#" onclick="showForgotModal()" class="login-new-forgot-link">Forgot your password?</a>
        </div>
    </div>
    
    <div id="forgotModal" class="modal">
        <div class="modal-content modal-small">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fa-solid fa-key"></i> Reset Password</h3>
                    <button type="button" class="modal-close" onclick="closeForgotModal()">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div id="emailStep">
                        <p>Enter your email to receive an OTP</p>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>

                    <div id="otpStep" style="display: none;">
                        <p>Check your email for the OTP.</p>
                        <div class="form-group">
                            <label>Enter OTP</label>
                            <input type="text" name="otp" class="form-control" maxlength="6" placeholder="6-character code">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeForgotModal()">Cancel</button>
                    <button type="submit" name="send_otp" id="sendOtpBtn" class="btn btn-primary">Send OTP</button>
                    <button type="submit" name="reset_password" id="resetPassBtn" class="btn btn-primary" style="display: none;">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passField = document.getElementById('passwordField');
            const eyeIcon = document.getElementById('eyeIcon');
            const isPassword = passField.type === 'password';
            
            passField.type = isPassword ? 'text' : 'password';
            eyeIcon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        }

        const modal = document.getElementById('forgotModal');
        const emailStep = document.getElementById('emailStep');
        const otpStep = document.getElementById('otpStep');
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const resetPassBtn = document.getElementById('resetPassBtn');

        function showForgotModal() {
            if (modal) modal.style.display = 'flex';
        }

        function closeForgotModal() {
            if (modal) modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target === modal) {
                closeForgotModal();
            }
        }

        <?php if ($showOtpForm): ?>
            emailStep.style.display = 'none';
            otpStep.style.display = 'block';
            sendOtpBtn.style.display = 'none';
            resetPassBtn.style.display = 'inline-flex';
            showForgotModal();
        <?php endif; ?>
    </script>
</body>
</html>