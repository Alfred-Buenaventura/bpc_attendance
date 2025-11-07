<?php
require_once 'config.php';
requireAdmin(); // Ensures only logged-in Admins can access this page

$db = db();
$error = '';
$success = '';

// Handle Create Admin form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $facultyId = clean($_POST['faculty_id']);
    $firstName = clean($_POST['first_name']);
    $lastName = clean($_POST['last_name']);
    $middleName = clean($_POST['middle_name']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone']);
    $role = 'Admin'; // Role is hard-coded to Admin

    // Check if the Faculty ID already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE faculty_id = ?");
    $stmt->bind_param("s", $facultyId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        // Faculty ID is unique, proceed to create the admin
        $username = strtolower($facultyId); // Use Faculty ID as the username
        $password = hashPass('DefaultPass123!'); // Default password
        
        // Prepare the insert statement
        $stmt_insert = $db->prepare("INSERT INTO users (faculty_id, username, password, first_name, last_name, middle_name, email, phone, role, force_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt_insert->bind_param("sssssssss", $facultyId, $username, $password, $firstName, $lastName, $middleName, $email, $phone, $role);
        
        // Execute the insert
        if ($stmt_insert->execute()) {
            logActivity($_SESSION['user_id'], 'Admin Created', "Created admin user: $facultyId");
            $success = "Admin account created successfully! User: $firstName $lastName";
            // Optionally clear form fields or redirect
        } else {
            $error = 'Database error: Failed to create account. Please try again.';
        }
    } else {
        // Faculty ID already exists
        $error = 'Faculty ID already exists. Please choose a unique ID.';
    }
}

// Set page variables for the header
$pageTitle = 'Admin Management';
$pageSubtitle = 'Create a new Administrator account';
include 'includes/header.php'; // Include the standard header/sidebar
?>

<div class="main-body">

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <div class="card-body">
            <div class="user-creation-header">
                <i class="fa-solid fa-user-shield" style="color: var(--emerald-600); font-size: 1.5rem;"></i>
                <h3>Create New Admin Account</h3>
            </div>
            <p class="user-creation-subtitle">
                Create a single administrator account. The default password will be <strong>DefaultPass123!</strong>
            </p>
            
            <form method="POST" style="margin-top: 1.5rem;">
                <div class="user-creation-form-grid">
                    <div class="form-group">
                        <label>Admin ID Number <span class="required">*</span></label>
                        <input type="text" name="faculty_id" class="form-control" placeholder="e.g., ADMIN001" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g., admin@bpc.edu.ph" required>
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
                        <label>Role</label>
                        <input type="text" name="role_display" class="form-control" value="Admin" readonly style="background-color: var(--gray-100);">
                    </div>
                </div>

                <div class="password-info-box" style="margin-top: 1.5rem;">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>
                        <strong>Note:</strong> The new admin user will be assigned the default password <strong>DefaultPass123!</strong> and will be required to change it upon their first login.
                    </div>
                </div>

                <button type="submit" name="create_admin" class="btn btn-primary btn-full-width" style="margin-top: 1.5rem;">
                    <i class="fa-solid fa-user-plus"></i>
                    Create Admin Account
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php';?>