<?php
require_once 'config.php';
requireAdmin();

$db = db();

$totalResult = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active'");
$totalUsers = $totalResult ? $totalResult->fetch_assoc()['c'] : 0;

$registeredResult = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND fingerprint_registered=1");
$registeredUsers = $registeredResult ? $registeredResult->fetch_assoc()['c'] : 0;

$pendingResult = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND fingerprint_registered=0");
$pendingCount = $pendingResult ? $pendingResult->fetch_assoc()['c'] : 0;

// Fetch pending users
$pendingUsers = $db->query("SELECT * FROM users WHERE status='active' AND fingerprint_registered=0 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$registeredUserList = $db->query("SELECT * FROM users WHERE status='active' AND fingerprint_registered=1 ORDER BY first_name ASC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "Complete Registration";
$pageSubtitle = "Manage user fingerprint registration status.";
include 'includes/header.php';
?>

<div class="main-body registration-page"> 
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="search-bar-container">
        <i class="fa-solid fa-search search-icon"></i>
        <input type="text" id="userSearchInput" class="search-input" placeholder="Search by name, faculty ID, or email...">
    </div>

    <div class="registration-stats-grid">
        <div class="reg-stat-card total-users">
            <div class="reg-stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="reg-stat-details">
                <p>Total Users</p>
                <span class="reg-stat-value"><?= $totalUsers ?></span>
            </div>
        </div>
        <div class="reg-stat-card registered">
            <div class="reg-stat-icon">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="reg-stat-details">
                <p>Registered</p>
                <span class="reg-stat-value"><?= $registeredUsers ?></span>
            </div>
        </div>
        <div class="reg-stat-card pending">
            <div class="reg-stat-icon">
                <i class="fa-solid fa-user-clock"></i>
            </div>
            <div class="reg-stat-details">
                <p>Pending</p>
                <span class="reg-stat-value"><?= $pendingCount ?></span>
            </div>
        </div>
    </div>

    <div class="pending-registrations-section">
        
        <div class="card-header-flex" style="margin-bottom: 1.5rem; align-items: center;">
            <h3 class="section-title" style="margin: 0;">Pending Registrations (<?= $pendingCount ?>)</h3>
            <button class="btn btn-primary" onclick="openModal('notifyModal')" <?= empty($pendingUsers) ? 'disabled' : '' ?>>
                <i class="fa-solid fa-bell"></i> Notify All Pending
            </button>
        </div>

        <?php if (empty($pendingUsers)): ?>
            <div class="empty-state-card">
                 <i class="fa-solid fa-check-circle empty-icon"></i>
                <p class="empty-text-title">No Pending Registrations</p>
                <p class="empty-text-subtitle">All active users have completed fingerprint registration.</p>
                <a href="create_account.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fa-fa-user-plus"></i> Create New Account
                </a>
            </div>
        <?php else: ?>
            <div class="user-cards-container">
                <?php foreach ($pendingUsers as $u): ?>
                    <div class="user-card" data-search-term="<?= strtolower(htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' ' . $u['faculty_id'] . ' ' . $u['email'])) ?>">
                        <div class="user-card-header">
                            <span class="user-card-status pending">Pending</span>
                            <span class="user-card-role"><?= htmlspecialchars(str_replace(' ', '_', strtoupper($u['role']))) ?></span>
                        </div>
                        <div class="user-card-details">
                            <p class="user-card-name"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></p>
                            <p class="user-card-info"><?= htmlspecialchars($u['faculty_id']) ?></p>
                            <p class="user-card-info"><?= htmlspecialchars($u['email']) ?></p>
                        </div>
                        <a href="fingerprint_registration.php?user_id=<?= $u['id'] ?>" class="user-card-register-btn">
                            <i class="fa fa-fingerprint"></i>
                            Register Fingerprint
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="registered-users-section" style="margin-top: 2.5rem;">
       
        <h3 class="section-title" style="margin-bottom: 1.5rem;">
            Registered Users (<?= $registeredUsers ?>)
        </h3>


        <?php if (empty($registeredUserList)): ?>
            <div class="empty-state-card">
                <i class="fa-solid fa-user-slash empty-icon"></i>
                <p class="empty-text-title">No Registered Users</p>
                <p class="empty-text-subtitle">No users have completed fingerprint registration yet.</p>
            </div>
        <?php else: ?>
            <div class="user-cards-container">
                <?php foreach ($registeredUserList as $u): ?>
                    <div class="user-card" data-search-term="<?= strtolower(htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' ' . $u['faculty_id'] . ' ' . $u['email'])) ?>">
                        <div class="user-card-header">
                            <span class="user-card-status registered">Registered</span>
                            <span class="user-card-role"><?= htmlspecialchars(str_replace(' ', '_', strtoupper($u['role']))) ?></span>
                        </div>
                        <div class="user-card-details">
                            <p class="user-card-name"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></p>
                            <p class="user-card-info"><?= htmlspecialchars($u['faculty_id']) ?></p>
                            <p class="user-card-info"><?= htmlspecialchars($u['email']) ?></p>
                        </div>
                        <div class="user-card-registered-status">
                            <i class="fa-solid fa-check-circle"></i>
                            Registered
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    </div>

<div id="notifyModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h3><i class="fa-solid fa-bell"></i> Notify Pending Users</h3>
            <button type="button" class="modal-close" onclick="closeModal('notifyModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="fs-large">
                Are you sure you want to send a dashboard notification to all 
                <strong><?= $pendingCount ?></strong> pending user(s)?
            </p>
            <p class="fs-small" style="color: var(--gray-600); margin-top: 1rem;">
                They will receive a pop-up reminder on their dashboard to complete their fingerprint registration.
            </p>
            <div id="notify-status-message" style="margin-top: 1rem;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('notifyModal')">Cancel</button>
            <button type="button" id="confirmNotifyBtn" class="btn btn-primary" onclick="sendNotifications()">
                <i class="fa-solid fa-paper-plane"></i> Yes, Notify All
            </button>
        </div>
    </div>
</div>


<script>

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
}
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearchInput');
    const userCards = document.querySelectorAll('.user-card'); // This selector gets ALL cards

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

            userCards.forEach(card => {
                const cardSearchTerm = card.getAttribute('data-search-term');
                if (cardSearchTerm && cardSearchTerm.includes(searchTerm)) {
                    card.style.display = ''; // Show card
                } else {
                    card.style.display = 'none'; // Hide card
                }
            });
        });
    }
});

// NEW SCRIPT for notification modal (Unchanged)
function sendNotifications() {
    const notifyBtn = document.getElementById('confirmNotifyBtn');
    const statusMessage = document.getElementById('notify-status-message');

    notifyBtn.disabled = true;
    notifyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
    statusMessage.innerHTML = '';
    statusMessage.className = '';

    fetch('notify_pending_users.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusMessage.textContent = data.message;
            statusMessage.className = 'alert alert-success';
            notifyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Done';
            
            setTimeout(() => {
                closeModal('notifyModal');
                notifyBtn.disabled = false;
                notifyBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Yes, Notify All';
            }, 2000);
        } else {
            statusMessage.textContent = 'Error: ' + data.message;
            statusMessage.className = 'alert alert-error';
            notifyBtn.disabled = false;
            notifyBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Yes, Notify All';
        }
    })
    .catch(error => {
        statusMessage.textContent = 'A network error occurred. Please try again.';
        statusMessage.className = 'alert alert-error';
        notifyBtn.disabled = false;
        notifyBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Yes, Notify All';
    });
}
</script>

<?php include 'includes/footer.php'; ?>