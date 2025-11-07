<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <i class="fa-solid fa-fingerprint"></i>
            </div>
            <div class="sidebar-title">
                <h1>BPC Attendance</h1>
                <p><?= isAdmin() ? 'Admin Panel' : 'Staff Dashboard' ?></p>
            </div>
        </div>
        <button class="btn sidebar-toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
        </button>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8h5z"/></svg>
            <span class="nav-text">Home</span>
        </a>
        
        <?php if (isAdmin()):?>
        <a href="create_account.php" class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            <span class="nav-text">Create Account</span>
        </a>
        <a href="complete_registration.php" class="nav-item">
             <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 7c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-10c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm6.1 4.95c.71.71 1.17 1.66 1.17 2.72 0 1.06-.46 2.01-1.17 2.72-.71.71-1.66 1.17-2.72 1.17-1.06 0-2.01-.46-2.72-1.17-.71-.71-1.17-1.66-1.17-2.72 0-1.06.46-2.01 1.17-2.72.71-.71 1.66-1.17 2.72-1.17 1.06 0 2.01.46 2.72 1.17zM7.9 16.95c.71.71 1.66 1.17 2.72 1.17 1.06 0 2.01-.46 2.72-1.17.71-.71 1.17-1.66 1.17-2.72 0-1.06-.46 2.01-1.17-2.72-.71-.71-1.66-1.17-2.72-1.17-1.06 0-2.01.46-2.72 1.17-.71.71-1.17 1.66-1.17 2.72 0 1.06.46 2.01 1.17 2.72z"/></svg>
            <span class="nav-text">Complete Registration</span>
        </a>
        <?php endif;?>
        
        <a href="attendance_reports.php" class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
            <span class="nav-text">Attendance Reports</span>
        </a>
        <a href="schedule_management.php" class="nav-item">
            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
            <span class="nav-text">Schedule Management</span>
        </a>
    </nav>

    <div class="sidebar-footer">

        <div id="settings-menu">
            <?php if (isAdmin()):?>
            <a href="create_admin.php" class="settings-menu-item">
                <i class="fa-solid fa-user-shield"></i>
                <span>Create Admin</span>
            </a>
            <?php endif; ?>
            <a href="profile.php" class="settings-menu-item">
                <i class="fa-solid fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="about.php" class="settings-menu-item">
                <i class="fa-solid fa-circle-info"></i>
                <span>About Us</span>
            </a>
            <a href="contact.php" class="settings-menu-item">
                <i class="fa-solid fa-envelope"></i>
                <span>Contact Support</span>
            </a>
        </div>
        <div class="user-info">
            <div class="user-info-inner">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['first_name'] ?? 'A', 0, 1) . substr($_SESSION['last_name'] ?? 'D', 0, 1)) ?>
                </div>
                <div class="user-details">
                    <p>Logged in as</p>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                    <div class="user-id">ID: <?= htmlspecialchars($_SESSION['faculty_id'] ?? 'N/A') ?></div>
                </div>

                <button class="btn user-settings-btn" id="userSettingsBtn" title="Settings">
                    <i class="fa-solid fa-gear"></i>
                </button>
            </div>
        </div>

        <button class="btn logout-btn" onclick="showLogoutConfirm()">
             <svg class="logout-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
            <span class="logout-text">Log out</span>
        </button>
    </div>

    <div id="logoutConfirmModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3><i class="fa-solid fa-arrow-right-from-bracket"></i> Confirm Logout</h3>
                <button type="button" class="modal-close" onclick="closeModal('logoutConfirmModal')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size: 1rem; color: var(--gray-700);">Are you sure you want to log out?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('logoutConfirmModal')">
                    Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="window.location.href='logout.php'">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Log Out
                </button>
            </div>
        </div>
    </div>
    </aside>