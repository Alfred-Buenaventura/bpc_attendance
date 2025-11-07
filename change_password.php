<?php
require_once 'config.php';
requireLogin();

$db = db();
$error = '';
$success = '';

// ===== THIS IS THE KEY CHANGE =====
// We determine the UI mode based on the SESSION, not the URL.
// This is more secure and reliable.
$firstLogin = (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === 1);
// ================================

/*Handles the change password and tokens*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'];
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];
    
    /*Pulls the current user*/
    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!verifyPass($currentPass, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New passwords do not match';
    } elseif (strlen($newPass) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        $hashedPass = hashPass($newPass);
        $stmt = $db->prepare("UPDATE users SET password=?, force_password_change=0 WHERE id=?");
        $stmt->bind_param("si", $hashedPass, $_SESSION['user_id']);
        $stmt->execute();
        
        // Update the session flag to clear the requirement
        $_SESSION['force_password_change'] = 0;
        
        logActivity($_SESSION['user_id'], 'Password Changed', 'User changed their password');
        $success = 'Password changed successfully!';
        
        if ($firstLogin) {
            // Redirect to index, which will now show the full dashboard
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Change Password';
$pageSubtitle = $firstLogin ? 'Please change your default password' : 'Update your account password';

// --- Conditionally load header ---
if ($firstLogin) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - BPC Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=1.2">
    <style>
        /* Add password toggle styles for this page */
        .password-input { position: relative; display: flex; }
        .password-input .form-control { padding-right: 3.5rem; }
        .password-input .toggle-password {
            position: absolute; right: 0; top: 0; height: 100%; width: 3.5rem;
            background: none; border: none; cursor: pointer;
            color: var(--gray-400); font-size: 1.1rem;
        }
    </style>
</head>
<body class="login-page">
<?php
} else {
    // Load the full dashboard header
    include 'includes/header.php';
    // Add styles for the password toggle in the main layout
    echo "<style>
        .password-input { position: relative; display: flex; }
        .password-input .form-control { padding-right: 3.5rem; }
        .password-input .toggle-password {
            position: absolute; right: 0; top: 0; height: 100%; width: 3.5rem;
            background: none; border: none; cursor: pointer;
            color: var(--gray-400); font-size: 1.1rem;
        }
    </style>";
}
// --- END ---
?>

<?php
// --- Show centered card for first login, or standard page for others ---
if ($firstLogin):
?>
    <div class="card login-card-new" style="max-width: 500px;">
        <div class="login-new-header">
            <div class="login-logo-container"><i class="fa-solid fa-key"></i></div>
            <h2 class="login-title">Change Password</h2>
            <p class="login-subtitle">For security, you must change your password.</p>
        </div>
        <div class="login-new-body" style="text-align: left;">
            
            <?php if ($error): ?><div class="alert alert-error" style="margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success" style="margin-bottom: 1rem;"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <div class="password-input">
                        <input type="password" name="current_password" id="currentPassField" class="form-control" required>
                        <button type="button" class="toggle-password" onclick="toggleVisibility('currentPassField', 'currentPassEye')">
                            <i id="currentPassEye" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-input">
                        <input type="password" name="new_password" id="newPassField" class="form-control" minlength="8" required>
                    </div>
                    <p style="font-size: 12px; color: var(--gray-600); margin-top: 4px;">
                        Must be at least 8 characters long
                    </p>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="password-input">
                        <input type="password" name="confirm_password" id="confirmPassField" class="form-control" minlength="8" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: -8px; margin-bottom: 24px;">
                    <label class="checkbox" style="font-size: 14px; color: var(--gray-700);">
                        <input type="checkbox" id="toggleNewPasswords">
                        <span>Show new password</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-full-width">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <div id="firstLoginModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header" style="background-color: var(--blue-50);">
                <h3 style="color: var(--blue-700);"><i class="fa-solid fa-shield-halved"></i> Security Update Required</h3>
            </div>
            <div class="modal-body">
                <p class="fs-large" style="color: var(--gray-700);">
                    Looks like this is your first login.
                </p>
                <p class="fs-large" style="color: var(--gray-700); margin-top: 1rem;">
                    For security reasons, please change the default password to a more secure password of your choosing.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeModal('firstLoginModal')">OK, I understand</button>
            </div>
        </div>
    </div>
    <?php
else:
// This is the original layout for a logged-in user
?>
<div class="main-body">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h3>Change Your Password</h3>
            <p>Enter your current password and choose a new one</p>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <div class="password-input">
                        <input type="password" name="current_password" id="currentPassField" class="form-control" required>
                        <button type="button" class="toggle-password" onclick="toggleVisibility('currentPassField', 'currentPassEye')">
                            <i id="currentPassEye" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-input">
                        <input type="password" name="new_password" id="newPassField" class="form-control" minlength="8" required>
                        <button type="button" class="toggle-password" onclick="toggleVisibility('newPassField', 'newPassEye')">
                            <i id="newPassEye" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <p style="font-size: 12px; color: var(--gray-600); margin-top: 4px;">
                        Must be at least 8 characters long
                    </p>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="password-input">
                        <input type="password" name="confirm_password" id="confirmPassField" class="form-control" minlength="8" required>
                        <button type="button" class="toggle-password" onclick="toggleVisibility('confirmPassField', 'confirmPassEye')">
                            <i id="confirmPassEye" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="max-width: 600px; margin: 24px auto 0;">
        <div class="card-body">
            <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">Password Requirements:</h4>
            <ul style="list-style: none; padding: 0; color: var(--gray-600); font-size: 14px;">
                <li style="margin-bottom: 8px;">✓ At least 8 characters long</li>
                <li style="margin-bottom: 8px;">✓ Mix of letters and numbers recommended</li>
                <li style="margin-bottom: 8px;">✓ Avoid using common words or personal information</li>
                <li>✓ Don't reuse passwords from other accounts</li>
            </ul>
        </div>
    </div>
</div>
<?php
endif;
// --- END ---
?>


<script>
    function toggleVisibility(fieldId, iconId) {
        // ... (existing function, leave it as is) ...
    }

    // Modal functions
    function openModal(modalId) {
        // ... (existing function, leave it as is) ...
    }
    function closeModal(modalId) {
        // ... (existing function, leave it as is) ...
    }

    // Run this script only for the first login scenario
    <?php if ($firstLogin): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Automatically open the modal on page load
        openModal('firstLoginModal');

        // --- NEW: Add logic for the checkbox ---
        const toggleCheckbox = document.getElementById('toggleNewPasswords');
        const newPassField = document.getElementById('newPassField');
        const confirmPassField = document.getElementById('confirmPassField');

        if (toggleCheckbox && newPassField && confirmPassField) {
            toggleCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    newPassField.type = 'text';
                    confirmPassField.type = 'text';
                } else {
                    newPassField.type = 'password';
                    confirmPassField.type = 'password';
                }
            });
        }
        // --- END NEW ---
    });
    <?php endif; ?>
    // --- END ---
</script>