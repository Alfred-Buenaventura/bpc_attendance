<?php
require_once 'config.php';
requireAdmin();

$db = db();
$activeTab = 'csv';

// --- Read any flash message from the session ---
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? null;
// Unset them so they don't show again on refresh
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
// --- End ---


/*CSV Import*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $activeTab = 'csv';
    if ($_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_message'] = 'Error uploading file. Code: ' . $_FILES['csvFile']['error'];
        $_SESSION['flash_type'] = 'error';
    } else {
        try {
            $file = fopen($_FILES['csvFile']['tmp_name'], 'r');
            if (!$file) {
                throw new Exception("Could not open uploaded file.");
            }

            fgetcsv($file); // Skip header row

            $csvData = [];
            $csvFacultyIds = [];
            while (($data = fgetcsv($file)) !== false) {
                if (count($data) < 8) continue;
                $csvData[] = $data;
                $csvFacultyIds[] = clean($data[0]);
            }
            fclose($file);

            /*Fetch all existing faculty IDs in one query for efficient checking*/
            $existingIdSet = [];
            if (!empty($csvFacultyIds)) {
                $placeholders = implode(',', array_fill(0, count($csvFacultyIds), '?'));
                $types = str_repeat('s', count($csvFacultyIds));
                $stmt = $db->prepare("SELECT faculty_id FROM users WHERE faculty_id IN ($placeholders)");
                $stmt->bind_param($types, ...$csvFacultyIds);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $existingIdSet[$row['faculty_id']] = true;
                }
            }

            $imported = 0;
            $skipped = 0;
            $password = hashPass('DefaultPass123!');
            
            $stmtInsert = $db->prepare("INSERT INTO users (faculty_id, username, password, first_name, last_name, middle_name, email, phone, role, force_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

            foreach ($csvData as $data) {
                $facultyId = clean($data[0]);
                $lastName = clean($data[1]);
                $firstName = clean($data[2]);
                $middleName = clean($data[3]);
                $username = clean($data[4]); // Username from CSV
                $role = clean($data[5]);
                $email = clean($data[6]);
                $phone = $data[7] ?? '';

                /* Skip if role is Admin or faculty_id already exists */
                if (strtolower($role) === 'admin' || isset($existingIdSet[$facultyId])) {
                    $skipped++;
                    continue;
                }

                $stmtInsert->bind_param("sssssssss", $facultyId, $username, $password, $firstName, $lastName, $middleName, $email, $phone, $role);
                $stmtInsert->execute();
                
                if ($stmtInsert->affected_rows > 0) {
                    $newUserId = $db->insert_id;
                    $notifMessage = "Welcome, $firstName! Your account has been created. Please change your password on first login.";
                    createNotification($newUserId, $notifMessage, 'success');
                    
                    // --- SEND EMAIL ---
                    $emailSubject = "Your BPC Attendance Account is Ready";
                    $emailMessage = "Welcome, $firstName!<br><br>Your account for the Fingerprint Attendance System has been created.<br><br>Here are your account details:<br>Username: <strong>$username</strong><br>Password: <strong>DefaultPass123!</strong><br><br>Please log in immediately to change your password.";
                    sendEmail($email, $emailSubject, $emailMessage);
                    // --- END SEND EMAIL ---

                    $imported++;
                }
            }

            logActivity($_SESSION['user_id'], 'CSV Import', "Imported $imported users. Skipped $skipped.");
            
            // --- SET FLASH MESSAGE ---
            $_SESSION['flash_message'] = "Successfully imported $imported user(s). Skipped $skipped duplicate(s)/admin(s).";
            $_SESSION['flash_type'] = 'success';
            // --- END ---

        } catch (Exception $e) {
            $_SESSION['flash_message'] = 'Import failed: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }
    // Redirect to clear POST data and show modal
    header("Location: create_account.php?tab=csv");
    exit;
}

/*Create Single User*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $activeTab = 'create';
    try {
        $facultyId = clean($_POST['faculty_id']);
        $firstName = clean($_POST['first_name']);
        $lastName = clean($_POST['last_name']);
        $middleName = clean($_POST['middle_name']);
        $email = clean($_POST['email']);
        $phone = clean($_POST['phone']);
        $role = clean($_POST['role']);

        if ($role === 'Admin') {
            $_SESSION['flash_message'] = 'Admin accounts must be created from the Admin Management page.';
            $_SESSION['flash_type'] = 'error';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE faculty_id = ?");
            $stmt->bind_param("s", $facultyId);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                $username = strtolower($facultyId); // Use Faculty ID as username
                $password = hashPass('DefaultPass123!');
                $stmt = $db->prepare("INSERT INTO users (faculty_id, username, password, first_name, last_name, middle_name, email, phone, role, force_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssssssss", $facultyId, $username, $password, $firstName, $lastName, $middleName, $email, $phone, $role);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $newUserId = $db->insert_id;
                    logActivity($_SESSION['user_id'], 'User Created', "Created user: $facultyId");
                    
                    // --- SET FLASH MESSAGE ---
                    $_SESSION['flash_message'] = "User Account for $firstName $lastName (ID: $facultyId) successfully created!";
                    $_SESSION['flash_type'] = 'success';
                    // --- END ---

                    $notifMessage = "Welcome, $firstName! Your account has been created. Please change your password on first login.";
                    createNotification($newUserId, $notifMessage, 'success');
                    
                    // --- SEND EMAIL ---
                    $emailSubject = "Your BPC Attendance Account is Ready";
                    $emailMessage = "Welcome, $firstName!<br><br>Your account for the Fingerprint Attendance System has been created.<br><br>Here are your account details:<br>Username: <strong>$username</strong><br>Password: <strong>DefaultPass123!</strong><br><br>Please log in immediately to change your password.";
                    sendEmail($email, $emailSubject, $emailMessage);
                    // --- END SEND EMAIL ---
                    
                }
            } else {
                // --- SET FLASH MESSAGE for duplicate ---
                $_SESSION['flash_message'] = "An account with this Faculty ID ($facultyId) already exists. Duplicate accounts cannot be created.";
                $_SESSION['flash_type'] = 'duplicate'; // Special type
                // --- END ---
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error creating user: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    // Redirect to clear POST data and show modal
    header("Location: create_account.php?tab=create");
    exit;
}

/*Edit User*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $activeTab = 'view';
    try {
        $userId = (int)$_POST['user_id'];
        $firstName = clean($_POST['first_name']);
        $lastName = clean($_POST['last_name']);
        $middleName = clean($_POST['middle_name']);
        $email = clean($_POST['email']);
        $phone = clean($_POST['phone']);
        $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, middle_name=?, email=?, phone=? WHERE id=?");
        $stmt->bind_param("sssssi", $firstName, $lastName, $middleName, $email, $phone, $userId);
        $stmt->execute();
        logActivity($_SESSION['user_id'], 'User Updated', "Updated user ID: $userId");
        $_SESSION['flash_message'] = "User information updated successfully!";
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error updating user: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header("Location: create_account.php?tab=view");
    exit;
}

/*Archive User*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_user'])) {
    $activeTab = 'view';
    try {
        $userId = (int)$_POST['user_id'];
        $stmt = $db->prepare("UPDATE users SET status='archived' WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        logActivity($_SESSION['user_id'], 'User Archived', "Archived user ID: $userId");
        $_SESSION['flash_message'] = 'User archived successfully!';
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error archiving user: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header("Location: create_account.php?tab=view");
    exit;
}

/*Restore User*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_user'])) {
    $activeTab = 'view';
    try {
        $userId = (int)$_POST['user_id'];
        $stmt = $db->prepare("UPDATE users SET status='active' WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        logActivity($_SESSION['user_id'], 'User Restored', "Restored user ID: $userId");
        $_SESSION['flash_message'] = 'User restored successfully!';
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error restoring user: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header("Location: create_account.php?tab=view");
    exit;
}

/*Delete User*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $activeTab = 'view';
    try {
        $userId = (int)$_POST['user_id'];
        $stmt = $db->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        logActivity($_SESSION['user_id'], 'User Deleted', "Permanently deleted user ID: $userId");
        $_SESSION['flash_message'] = 'User permanently deleted!';
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error deleting user: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header("Location: create_account.php?tab=view");
    exit;
}

/* Single query for all active user stats */
$statsQuery = $db->query("
    SELECT
        COUNT(*) as total_active,
        SUM(CASE WHEN role != 'Admin' THEN 1 ELSE 0 END) as non_admin_active,
        SUM(CASE WHEN role = 'Admin' THEN 1 ELSE 0 END) as admin_active
    FROM users
    WHERE status = 'active'
");
$stats = $statsQuery->fetch_assoc();

$totalUsers = $stats['total_active'] ?? 0;
$nonAdminUsers = $stats['non_admin_active'] ?? 0;
$adminUsers = $stats['admin_active'] ?? 0;

$activeUsers = $db->query("SELECT * FROM users WHERE status='active' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$archivedUsers = $db->query("SELECT * FROM users WHERE status='archived' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// --- Set active tab from URL ---
if(isset($_GET['tab'])) {
    $activeTab = $_GET['tab'];
}

$pageTitle = 'Create New Account';
$pageSubtitle = 'Create user accounts individually or import in bulk via CSV';
include 'includes/header.php';

function safe_js_data($data) {
    return htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
}
?>

<main class="main-content" style="position: relative; z-index: 1; padding: 20px;">
<div class="main-body">

<div id="toastContainer" class="toast-container"></div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon emerald">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-details">
            <p>Total Accounts</p>
            <div class="stat-value emerald"><?= $totalUsers ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon emerald">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="stat-details">
            <p>Non-Admin Users</p>
            <div class="stat-value emerald"><?= $nonAdminUsers ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon emerald">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="stat-details">
            <p>Admin Users</p>
            <div class="stat-value emerald"><?= $adminUsers ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="tabs">
        <button class="tab-btn <?= $activeTab === 'csv' ? 'active' : '' ?>" onclick="showTab(event, 'csv')"><i class="fa-solid fa-file-csv"></i> CSV Bulk Import</button>
        <button class="tab-btn <?= $activeTab === 'create' ? 'active' : '' ?>" onclick="showTab(event, 'create')"><i class="fa-solid fa-user-plus"></i> Account Creation</button>
        <button class="tab-btn <?= $activeTab === 'view' ? 'active' : '' ?>" onclick="showTab(event, 'view')"><i class="fa-solid fa-list"></i> View All Accounts</button>
    </div>

    <div id="csvTab" class="tab-content <?= $activeTab === 'csv' ? 'active' : '' ?>">
        <div class="card-body">
            <div class="csv-section-header">
                <i class="fa-solid fa-file-arrow-up"></i>
                <h3>Bulk User Import (CSV)</h3>
            </div>
            <p class="csv-subtitle">Import multiple user accounts from a CSV file</p>

            <div class="download-template-box">
                <div class="download-template-inner">
                    <div class="step-badge">1</div>
                    <div class="download-template-content">
                        <h4>Download the CSV template first <span>to ensure correct format.</span></h4>
                        <button type="button" class="btn btn-primary download-template-link" onclick="confirmDownload()">
                            <i class="fa-solid fa-download"></i>
                            Download Template
                        </button>
                    </div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="csvUploadForm">
                <div style="margin-bottom: 1.5rem;">
                    <label class="csv-upload-label">Upload CSV File</label>
                    <div class="csv-dropzone" id="csvDropzone"
                         onclick="document.getElementById('csvFileInput').click()">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p class="csv-dropzone-text"><strong>Click to choose a CSV file</strong></p>
                        <p id="csvFileStatus" class="csv-file-status">No file chosen...</p>
                    </div>
                    <input type="file" name="csvFile" id="csvFileInput" accept=".csv" style="display: none;" required>
                </div>

                <button type="submit" class="btn btn-primary btn-full-width">
                    <i class="fa-solid fa-upload"></i>
                    Import Users from CSV
                </button>
            </form>

            <div class="csv-requirements">
                <h4>CSV Format Requirements:</h4>
                <ul>
                    <li>Columns (in order): Faculty ID, Last Name, First Name, Middle Name, Username, Role, Email, Phone</li>
                    <li>All users will be created with default password: <strong>DefaultPass123!</strong></li>
                    <li>Users must change password on first login</li>
                    <li>Duplicate Faculty IDs will be skipped</li>
                    <li>Rows with 'Admin' role will be skipped</li>
                </ul>
            </div>
        </div>
    </div>

    <div id="createTab" class="tab-content <?= $activeTab === 'create' ? 'active' : '' ?>">
        <div class="card-body">
            <div class="user-creation-header">
                <i class="fa-solid fa-user-plus"></i>
                <h3>Create New User Account</h3>
            </div>
            <p class="user-creation-subtitle">Create a single user account with default password: <strong>DefaultPass123!</strong></p>

            <form method="POST" style="margin-top: 1.5rem;">
                <div class="user-creation-form-grid">
                    <div class="form-group">
                        <label>Faculty/ID Number <span class="required">*</span></label>
                        <input type="text" name="faculty_id" class="form-control" placeholder="e.g., STAFF001" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g., staff@bulacan.edu.ph" required>
                    </div>
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-control" placeholder="Enter last name" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name (optional)">
                    </div>
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g., 09171234567" required>
                    </div>
                    <div class="form-group form-group-full">
                        <label>Role/Position <span class="required">*</span></label>
                        <select name="role" class="form-control" required>
                            <option value="">Select a role</option>
                            <option value="Full Time Teacher">Full Time Teacher</option>
                            <option value="Part Time Teacher">Part Time Teacher</option>
                            <option value="Registrar">Registrar</option>
                            <option value="Admission">Admission</option>
                            <option value="OPRE">OPRE</option>
                            <option value="Scholarship Office">Scholarship Office</option>
                            <option value="Doctor">Doctor</option>
                            <option value="Nurse">Nurse</option>
                            <option value="Guidance Office">Guidance Office</option>
                            <option value="Library">Library</option>
                            <option value="Finance">Finance</option>
                            <option value="Student Affair">Student Affair</option>
                            <option value="Security Personnel and Facility Operator">Security Personnel and Facility Operator</option>
                            <option value="OVPA">OVPA</option>
                            <option value="MIS">MIS</option>
                        </select>
                    </div>
                </div>

                <div class="password-info-box">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong>Note:</strong> User will be assigned the default password <strong>DefaultPass123!</strong> and will be prompted to change it on first login.
                    </div>
                </div>

                <button type="submit" name="create_user" class="btn btn-primary btn-full-width">
                    <i class="fa-solid fa-user-plus"></i>
                    Create Account
                </button>
            </form>
        </div>
    </div>

    <div id="viewTab" class="tab-content <?= $activeTab === 'view' ? 'active' : '' ?>">
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0;">All Active Accounts</h3>
                <button class="btn btn-secondary" onclick="openArchivedModal()">
                    <i class="fa-solid fa-archive"></i>
                    View Archived Accounts (<?= count($archivedUsers) ?>)
                </button>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Faculty ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activeUsers) > 0): ?>
                        <?php foreach ($activeUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['faculty_id']) ?></td>
                                <td><?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><span class="role-badge"><?= htmlspecialchars($user['role']) ?></span></td>
                                <td>
                                    <?php 
                                    $name = $user['first_name'] . ' ' . $user['last_name'];
                                    ?>
                                    <button class="btn btn-sm" onclick="editUser(<?php
                                        echo safe_js_data($user['id']) . ', ' . 
                                             safe_js_data($user['first_name']) . ', ' . 
                                             safe_js_data($user['last_name']) . ', ' . 
                                             safe_js_data($user['middle_name']) . ', ' . 
                                             safe_js_data($user['email']) . ', ' . 
                                             safe_js_data($user['phone']);
                                    ?>)">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="confirmArchive(<?= (int)$user['id'] ?>, <?= safe_js_data($name) ?>)">
                                        <i class="fa-solid fa-archive"></i> Archive
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 2rem; color: var(--gray-600);">
                                <i class="fa-solid fa-users-slash" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                No active accounts found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div id="editForm" style="display: none; margin-top: 2rem; border-top: 2px solid var(--gray-200); padding-top: 2rem;">
                <h4><i class="fa-solid fa-user-pen"></i> Edit User Information</h4>
                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="form-grid">
                        <div class="form-group"><label>First Name <span class="required">*</span></label><input type="text" name="first_name" id="editFirstName" class="form-control" required></div>
                        <div class="form-group"><label>Last Name <span class="required">*</span></label><input type="text" name="last_name" id="editLastName" class="form-control" required></div>
                        <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" id="editMiddleName" class="form-control"></div>
                        <div class="form-group"><label>Email <span class="required">*</span></label><input type="email" name="email" id="editEmail" class="form-control" required></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="editPhone" class="form-control"></div>
                    </div>
                    <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
                        <button type="submit" name="edit_user" class="btn btn-primary">
                            <i class="fa-solid fa-save"></i> Update User
                        </button>
                        <button type="button" onclick="hideEditForm()" class="btn btn-secondary">
                            <i class="fa-solid fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="archivedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-archive"></i> Archived Accounts</h3>
                <button class="modal-close" onclick="closeArchivedModal()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Faculty ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($archivedUsers) > 0): ?>
                            <?php foreach ($archivedUsers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['faculty_id']) ?></td>
                                    <td><?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone']) ?></td>
                                    <td><span class="role-badge"><?= htmlspecialchars($user['role']) ?></span></td>
                                    <td>
                                        <?php $name = $user['first_name'] . ' ' . $user['last_name']; ?>
                                        <button class="btn btn-sm btn-success" onclick="confirmRestore(<?= (int)$user['id'] ?>, <?= safe_js_data($name) ?>)">
                                            <i class="fa-solid fa-rotate-left"></i> Restore
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= (int)$user['id'] ?>, <?= safe_js_data($name) ?>)">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 2rem; color: var(--gray-600);">
                                    <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                    No archived accounts
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="confirmModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3 id="confirmTitle"><i class="fa-solid fa-circle-exclamation"></i> Confirm Action</h3>
            </div>
            <div class="modal-body">
                <p id="confirmMessage" style="font-size: 1rem; color: var(--gray-700);"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
                <button class="btn btn-primary" id="confirmActionBtn" onclick="executeConfirmedAction()">
                    <i class="fa-solid fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <div id="duplicateUserModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header" style="background-color: var(--yellow-50);">
                <h3 style="color: var(--yellow-700);"><i class="fa-solid fa-triangle-exclamation"></i> Duplicate Account</h3>
            </div>
            <div class="modal-body">
                <p class="fs-large" style="color: var(--gray-700);">
                    An account with this Faculty ID already exists in the system.
                </p>
                <p class="fs-small" style="color: var(--gray-600); margin-top: 1rem;">
                    Please check the "View All Accounts" tab to find the existing user. Duplicate accounts cannot be created.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeModal('duplicateUserModal')">OK</button>
            </div>
        </div>
    </div>
    <div id="doubleConfirmModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header" style="background: var(--red-50);">
                <h3 style="color: var(--red-600);"><i class="fa-solid fa-triangle-exclamation"></i> Final Confirmation</h3>
            </div>
            <div class="modal-body">
                <div style="background: var(--red-50); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid var(--red-600);">
                    <p style="color: var(--red-600); font-weight: 600; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-exclamation-triangle"></i> WARNING: This action cannot be undone!
                    </p>
                    <p style="color: var(--gray-700); font-size: 0.9rem; margin: 0;">
                        All user data will be permanently deleted from the system.
                    </p>
                </div>
                <p id="doubleConfirmMessage" style="font-size: 1rem; color: var(--gray-700);"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDoubleConfirmModal()">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
                <button class="btn btn-danger" onclick="executeDeleteAction()">
                    <i class="fa-solid fa-trash"></i> Yes, Delete Permanently
                </button>
            </div>
        </div>
    </div>
    
    <div id="operationStatusModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header" id="statusModalHeader">
                <h3 id="statusModalTitle"><i class="fa-solid fa-circle-check"></i> Success</h3>
            </div>
            <div class="modal-body">
                <p id="statusModalMessage" style="font-size: 1rem; color: var(--gray-700); line-height: 1.6;">
                    </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeModal('operationStatusModal')">OK</button>
            </div>
        </div>
    </div>
    </div>
</main>

<script>
/* Single place for JS. All DOM bindings happen after DOMContentLoaded. */

let pendingAction = null;
let deleteUserId = null;
let deleteUserName = null;

// Helper function to open/close modals
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}


document.addEventListener('DOMContentLoaded', function() {

    // --- Read flash messages and show modal ---
    const flashMessage = <?= json_encode($flashMessage) ?>;
    const flashType = <?= json_encode($flashType) ?>;

    if (flashMessage) {
        if (flashType === 'duplicate') {
            // Show the specific duplicate modal
            openModal('duplicateUserModal');
        } else {
            // Show the generic status modal
            const modalHeader = document.getElementById('statusModalHeader');
            const modalTitle = document.getElementById('statusModalTitle');
            const modalMessage = document.getElementById('statusModalMessage');

            if (flashType === 'success') {
                modalHeader.style.background = 'var(--emerald-50)';
                modalTitle.innerHTML = '<i class="fa-solid fa-circle-check"></i> Success';
                modalTitle.style.color = 'var(--emerald-800)';
            } else if (flashType === 'error') {
                modalHeader.style.background = 'var(--red-50)';
                modalTitle.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Error';
                modalTitle.style.color = 'var(--red-700)';
            } else {
                // Default/info
                modalHeader.style.background = 'var(--blue-50)';
                modalTitle.innerHTML = '<i class="fa-solid fa-circle-info"></i> Notice';
                modalTitle.style.color = 'var(--blue-700)';
            }
            
            modalMessage.textContent = flashMessage;
            openModal('operationStatusModal');
        }
    }
    // --- END ---


    window.showTab = function(event, tab) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        const el = document.getElementById(tab + 'Tab');
        if (el) el.classList.add('active');
        if (event && event.target) {
            // Check if the click target is the button itself or an icon inside it
            const btn = event.target.closest('.tab-btn');
            if (btn) btn.classList.add('active');
        }
    };

    const csvFileInput = document.getElementById('csvFileInput');
    const csvDropzone = document.getElementById('csvDropzone');
    const csvFileStatus = document.getElementById('csvFileStatus');

    if (csvFileInput && csvDropzone && csvFileStatus) {
        csvFileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                csvFileStatus.textContent = this.files[0].name;
                csvFileStatus.classList.add('has-file');
            } else {
                csvFileStatus.textContent = 'No file chosen...';
                csvFileStatus.classList.remove('has-file');
            }
        });

        csvDropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        csvDropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        csvDropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');

            if (e.dataTransfer.files.length > 0) {
                csvFileInput.files = e.dataTransfer.files;
                csvFileStatus.textContent = e.dataTransfer.files[0].name;
                csvFileStatus.classList.add('has-file');
            }
        });
    }

    <?php if ($activeTab === 'view' && (isset($_POST['restore_user']) || isset($_POST['delete_user']))): ?>
    setTimeout(() => {
        openArchivedModal();
    }, 100);
    <?php endif; ?>

});

/*Edit user functions*/
function editUser(id, firstName, lastName, middleName, email, phone) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editFirstName').value = firstName || '';
    document.getElementById('editLastName').value = lastName || '';
    document.getElementById('editMiddleName').value = middleName || '';
    document.getElementById('editEmail').value = email || '';
    document.getElementById('editPhone').value = phone || '';
    
    const form = document.getElementById('editForm');
    if (form) {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function hideEditForm() {
    const form = document.getElementById('editForm');
    if (form) form.style.display = 'none';
}

function openArchivedModal() {
    openModal('archivedModal');
}

function closeArchivedModal() {
    closeModal('archivedModal');
}

function confirmDownload() {
    document.getElementById('confirmTitle').innerHTML = '<i class="fa-solid fa-download"></i> Download Template';
    document.getElementById('confirmMessage').textContent = 'Download the CSV template file? This template shows the correct format for bulk user import.';
    const btn = document.getElementById('confirmActionBtn');
    btn.className = 'btn btn-primary';
    btn.innerHTML = '<i class="fa-solid fa-download"></i> Download';

    pendingAction = function() {
        window.location.href = 'download_template.php';
    };
    openModal('confirmModal');
}

/*Confirmation for archive, restore and delete actions*/
function confirmArchive(userId, userName) {
    document.getElementById('confirmTitle').innerHTML = '<i class="fa-solid fa-archive"></i> Confirm Archive';
    document.getElementById('confirmMessage').textContent = `Are you sure you want to archive ${userName}? They will no longer have access to the system.`;
    const btn = document.getElementById('confirmActionBtn');
    btn.className = 'btn btn-warning';
    btn.innerHTML = '<i class="fa-solid fa-archive"></i> Archive';

    pendingAction = function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="user_id" value="${userId}"><input type="hidden" name="archive_user" value="1">`;
        document.body.appendChild(form);
        form.submit();
    };
    openModal('confirmModal');
}

function confirmRestore(userId, userName) {
    document.getElementById('confirmTitle').innerHTML = '<i class="fa-solid fa-rotate-left"></i> Confirm Restore';
    document.getElementById('confirmMessage').textContent = `Are you sure you want to restore ${userName}? They will regain access to the system.`;
    const btn = document.getElementById('confirmActionBtn');
    btn.className = 'btn btn-success';
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Restore';

    pendingAction = function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="user_id" value="${userId}"><input type="hidden" name="restore_user" value="1">`;
        document.body.appendChild(form);
        form.submit();
    };
    openModal('confirmModal');
}

function confirmDelete(userId, userName) {
    document.getElementById('confirmTitle').innerHTML = '<i class="fa-solid fa-trash"></i> Confirm Delete';
    document.getElementById('confirmMessage').textContent = `Are you sure you want to permanently delete ${userName}? This action cannot be undone.`;
    const btn = document.getElementById('confirmActionBtn');
    btn.className = 'btn btn-danger';
    btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';

    deleteUserId = userId;
    deleteUserName = userName;

    pendingAction = function() {
        closeConfirmModal();
        setTimeout(() => {
            const doubleMsg = document.getElementById('doubleConfirmMessage');
            if (doubleMsg) doubleMsg.textContent = `Type confirmation: Are you absolutely sure you want to delete ${userName}?`;
            openModal('doubleConfirmModal');
        }, 300);
    };
    openModal('confirmModal');
}

function closeConfirmModal() {
    closeModal('confirmModal');
    pendingAction = null;
}

function closeDoubleConfirmModal() {
    closeModal('doubleConfirmModal');
    deleteUserId = null;
    deleteUserName = null;
}

function executeConfirmedAction() {
    if (pendingAction) pendingAction();
    closeConfirmModal();
}

function executeDeleteAction() {
    if (deleteUserId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="user_id" value="${deleteUserId}"><input type="hidden" name="delete_user" value="1">`;
        document.body.appendChild(form);
        form.submit();
    }
    closeDoubleConfirmModal();
}

window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            closeModal(modal.id);
        }
    });
};
</script>

<?php include 'includes/footer.php'; ?>