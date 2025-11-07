<?php
require_once 'config.php';
requireLogin();

$db = db();
$error = '';
$success = '';
$userId = $_SESSION['id'];


$user = getUserById($id);
if (!$user) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = clean($_POST['first_name']);
    $lastName = clean($_POST['last_name']);
    $middleName = clean($_POST['middle_name']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone']);

    $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, middle_name=?, email=?, phone=? WHERE id=?");
    $stmt->bind_param("sssssi", $firstName, $lastName, $middleName, $email, $phone, $userId);

    if ($stmt->execute()) {
        $_SESSION['full_name'] = $firstName . ' ' . $lastName;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        logActivity($userId, 'Profile Updated', 'User updated their profile information');
        $success = 'Profile information updated successfully!';
        $user = getUserById($id);
    } else {
        $error = 'Failed to update profile information';
    }
}

$pageTitle = 'My Profile';
$pageSubtitle = 'View and edit your account information';
include 'includes/header.php';
?>

<div class="main-body">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">

        <div class="card">
            <div class="card-body" style="text-align: center;">
                 <?php
                    $profilePicturePath = (!empty($user['profile_picture']) && file_exists("uploads/profile_pictures/" . $user['profile_picture']))
                                        ? "uploads/profile_pictures/" . $user['profile_picture']
                                        : 'assets/default_avatar.png';
                 ?>
                 <img src="<?= $profilePicturePath ?>?t=<?= time() ?>" alt="Profile Picture" class="current-profile-pic">

                 <a href="profile_pic.php" class="btn btn-sm btn-secondary" style="margin-top: 1rem; display: inline-block;">
                     <i class="fa-solid fa-camera"></i> Change Picture
                 </a>

                <h3 style="font-size: 20px; font-weight: 700; margin-top: 1rem; margin-bottom: 4px;">
                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                </h3>
                <p style="color: var(--gray-600); margin-bottom: 16px;"><?= htmlspecialchars($user['role']) ?></p>

                <div style="background: var(--gray-50); padding: 16px; border-radius: 12px; text-align: left;">
                    <div style="margin-bottom: 12px;">
                        <p style="font-size: 12px; color: var(--gray-600); margin-bottom: 4px;">Faculty ID</p>
                        <p style="font-weight: 600;"><?= htmlspecialchars($user['faculty_id']) ?></p>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <p style="font-size: 12px; color: var(--gray-600); margin-bottom: 4px;">Username</p>
                        <p style="font-weight: 600;"><?= htmlspecialchars($user['username'] ?? $user['faculty_id']) ?></p>
                    </div>
                    <div>
                        <p style="font-size: 12px; color: var(--gray-600); margin-bottom: 4px;">Member Since</p>
                        <p style="font-weight: 600;"><?= isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : 'N/A' ?></p>
                    </div>
                </div>

                <div style="margin-top: 16px;">
                    <a href="change_password.php" class="btn btn-secondary btn-block">Change Password</a>
                </div>

                <?php if (isset($user['fingerprint_registered'])): ?>
                    <?php if ($user['fingerprint_registered']): ?>
                        <div style="margin-top: 16px; padding: 12px; background: var(--emerald-50); border-radius: 12px;">
                            <i class="fa-solid fa-check-circle" style="color: var(--emerald-600); margin-right: 8px;"></i>
                            <span style="font-size: 12px; font-weight: 600; color: var(--emerald-700);">Fingerprint Registered</span>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 16px; padding: 12px; background: var(--yellow-100); border-radius: 12px;">
                             <i class="fa-solid fa-triangle-exclamation" style="color: #d97706; margin-right: 8px;"></i>
                            <span style="font-size: 12px; font-weight: 600; color: #92400e;">Fingerprint Not Registered</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3>Edit Profile Information</h3>
                        <p>Update your personal information</p>
                    </div>
                    <button type="button" id="editProfileBtn" class="btn btn-primary">
                        <i class="fa-solid fa-pen"></i> Edit Profile
                    </button>
                </div>
                <div class="card-body">
                    <form method="POST" action="profile.php"> <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" id="firstNameInput" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required readonly>
                            </div>

                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" id="lastNameInput" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required readonly>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" id="middleNameInput" class="form-control" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" id="emailInput" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required readonly>
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" id="phoneInput" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" readonly>
                            </div>
                        </div>

                        <div style="background: var(--gray-50); padding: 16px; border-radius: 12px; margin: 24px 0;">
                            <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px;">Read-Only Information</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div>
                                    <p style="font-size: 12px; color: var(--gray-600); margin-bottom: 4px;">Faculty ID</p>
                                    <p style="font-weight: 600;"><?= htmlspecialchars($user['faculty_id']) ?></p>
                                </div>
                                <div>
                                    <p style="font-size: 12px; color: var(--gray-600); margin-bottom: 4px;">Role</p>
                                    <p style="font-weight: 600;"><?= htmlspecialchars($user['role']) ?></p>
                                </div>
                            </div>
                            <?php if (!isAdmin()): ?>
                                <p style="font-size: 12px; color: #d97706; margin-top: 8px;">
                                    ⓘ Contact administrator to change Faculty ID or Role
                                </p>
                            <?php endif; ?>
                        </div>

                        <div id="editModeButtons" style="display: none; display: flex; gap: 12px;">
                            <button type="submit" name="update_profile" id="saveChangesBtn" class="btn btn-primary">Save Changes</button>
                            <button type="button" id="cancelEditBtn" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

             <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                    <p>Your recent account activities</p>
                </div>
                <div class="card-body">
                    <?php
                    $stmtAct = $db->prepare("SELECT * FROM activity_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
                    $stmtAct->bind_param("i", $userId);
                    $stmtAct->execute();
                    $activities = $stmtAct->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>

                    <?php if (empty($activities)): ?>
                        <p style="text-align: center; color: var(--gray-500);">No recent activities</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($activities as $activity): ?>
                                <div style="display: flex; justify-content: space-between; padding: 12px; background: var(--gray-50); border-radius: 8px;">
                                    <div>
                                        <p style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($activity['action']) ?></p>
                                        <p style="font-size: 12px; color: var(--gray-600);"><?= htmlspecialchars($activity['description'] ?? '') ?></p>
                                    </div>
                                    <div style="text-align: right; flex-shrink: 0; margin-left: 1rem;">
                                        <p style="font-size: 12px; color: var(--gray-600);"><?= date('M d, Y', strtotime($activity['created_at'])) ?></p>
                                        <p style="font-size: 10px; color: var(--gray-500);"><?= date('g:i A', strtotime($activity['created_at'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div> </div> </div> </div> <script>
const editProfileBtn = document.getElementById('editProfileBtn');
const editModeButtons = document.getElementById('editModeButtons');
const cancelEditBtn = document.getElementById('cancelEditBtn');
const profileInputs = [
    document.getElementById('firstNameInput'),
    document.getElementById('lastNameInput'),
    document.getElementById('middleNameInput'),
    document.getElementById('emailInput'),
    document.getElementById('phoneInput')
];
let originalValues = {};

if (editProfileBtn && editModeButtons && cancelEditBtn) {
    editProfileBtn.addEventListener('click', () => {
        originalValues = {};
        profileInputs.forEach(input => {
            if (input) {
                input.removeAttribute('readonly');
                originalValues[input.id] = input.value;
            }
        });
        editProfileBtn.style.display = 'none';
        editModeButtons.style.display = 'flex';
    });

    cancelEditBtn.addEventListener('click', () => {
        profileInputs.forEach(input => {
            if (input) {
                input.setAttribute('readonly', true);
                input.value = originalValues[input.id] || input.value;
            }
        });
        editProfileBtn.style.display = 'inline-flex';
        editModeButtons.style.display = 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-error') || alert.classList.contains('alert-success')) {
             setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.3s';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>