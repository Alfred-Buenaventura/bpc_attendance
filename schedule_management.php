<?php
require_once 'config.php';
requireLogin();

$db = db();
$error = '';
$success = '';
$users = [];
$selectedUserId = null;
$selectedUser = null;
$activeTab = 'manage'; // NEW: Default tab

if (isAdmin()) {
    $users = $db->query("SELECT id, faculty_id, first_name, last_name FROM users WHERE status='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
    $selectedUserId = $_GET['user_id'] ?? ''; 
} else {
    $selectedUserId = $_SESSION['user_id'];
}

/*handles the adding of schedules in the user account*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    
    if (isAdmin()) {
        $error = 'Access Denied. Administrators can manage schedules but not create them.';
    
    } else {
        $userIdToAdd = (int)$_POST['user_id_add'];
        
        if ($userIdToAdd !== $_SESSION['user_id']) {
            $error = 'Access Denied. You can only add schedules for your own account.';
        } else {
            $days = $_POST['day_of_week'] ?? [];
            $subjects = $_POST['subject'] ?? [];
            $startTimes = $_POST['start_time'] ?? [];
            $endTimes = $_POST['end_time'] ?? [];
            $rooms = $_POST['room'] ?? [];
            $addedCount = 0;
            $skippedCount = 0;
            
            // MODIFIED: Added 'status' column, set to 'pending'
            $stmt = $db->prepare("INSERT INTO class_schedules (user_id, day_of_week, subject, start_time, end_time, room, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");

            foreach ($days as $index => $day) {
                $subject = clean($subjects[$index]);
                $startTime = clean($startTimes[$index]);
                $endTime = clean($endTimes[$index]);
                $room = clean($rooms[$index]);
                $day = clean($day);

                if (empty($subject) || empty($startTime) || empty($endTime) || empty($day)) {
                    $skippedCount++;
                    continue; 
                }
                $stmt->bind_param("isssss", $userIdToAdd, $day, $subject, $startTime, $endTime, $room);
                $stmt->execute();
                $addedCount++;
            }

            if ($addedCount > 0) {
                logActivity($_SESSION['user_id'], 'Schedule Submitted', "Submitted $addedCount schedule(s) for approval for user ID: $userIdToAdd");
                $success = "Successfully submitted $addedCount new schedule(s) for approval!";
                if ($skippedCount > 0) {
                    $success .= " (Skipped $skippedCount empty rows)";
                }
            } else {
                $error = 'No schedules were submitted. Please ensure all fields are filled out.';
            }
        }
    }
}

/*editing schedules function*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_schedule'])) {
    $scheduleId = (int)$_POST['schedule_id'];
    $userIdToEdit = (int)$_POST['user_id_edit']; 

    if (!isAdmin() && $userIdToEdit !== $_SESSION['user_id']) {
        $error = 'Access Denied. You can only edit your own schedules.';
    } else {
        $dayOfWeek = clean($_POST['day_of_week']);
        $subject = clean($_POST['subject']);
        $startTime = clean($_POST['start_time']);
        $endTime = clean($_POST['end_time']);
        $room = clean($_POST['room']);
        
        // MODIFIED: Set status to 'pending' on edit
        $stmt = $db->prepare("UPDATE class_schedules SET day_of_week=?, subject=?, start_time=?, end_time=?, room=?, status='pending' WHERE id=? AND user_id=?");
        $stmt->bind_param("sssssii", $dayOfWeek, $subject, $startTime, $endTime, $room, $scheduleId, $userIdToEdit);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'Schedule Updated', "Updated schedule ID: $scheduleId for user ID: $userIdToEdit. Awaiting approval.");
            $success = 'Schedule updated successfully! It has been re-submitted for approval.';
        } else {
            $error = 'Failed to update schedule';
        }
    }
}

/*function for deleting schedules*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $scheduleId = (int)$_POST['schedule_id_delete'];
    $userIdToDelete = (int)$_POST['user_id_delete'];

    if (!isAdmin() && $userIdToDelete !== $_SESSION['user_id']) {
        $error = 'Access Denied. You can only delete your own schedules.';
    } else {
        // MODIFIED: This will delete the schedule regardless of status (pending, approved, etc)
        $stmt = $db->prepare("DELETE FROM class_schedules WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $scheduleId, $userIdToDelete);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'Schedule Deleted', "Deleted schedule ID: $scheduleId for user ID: $userIdToDelete");
            $success = 'Schedule deleted successfully!';
        } else {
            $error = 'Failed to delete schedule.';
        }
    }
}

// NEW: Handle Approve Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_schedule'])) {
    requireAdmin(); // Only admins can do this
    $activeTab = 'pending';
    $scheduleId = (int)$_POST['schedule_id'];
    $userId = (int)$_POST['user_id'];
    $subject = clean($_POST['subject']); // Get subject for notification

    $stmt = $db->prepare("UPDATE class_schedules SET status='approved' WHERE id=?");
    $stmt->bind_param("i", $scheduleId);
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Schedule Approved', "Approved schedule ID: $scheduleId for user ID: $userId");
        createNotification($userId, "Your schedule for '$subject' has been approved.", 'success');
        sendEmail(getUser($userId)['email'], "Schedule Approved", "Your schedule for '$subject' has been approved by the administrator.");
        $success = 'Schedule approved successfully!';
    } else {
        $error = 'Failed to approve schedule.';
    }
}

// NEW: Handle Decline Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decline_schedule'])) {
    requireAdmin(); // Only admins can do this
    $activeTab = 'pending';
    $scheduleId = (int)$_POST['schedule_id'];
    $userId = (int)$_POST['user_id'];
    $subject = clean($_POST['subject']); // Get subject for notification

    $stmt = $db->prepare("UPDATE class_schedules SET status='declined' WHERE id=?");
    $stmt->bind_param("i", $scheduleId);
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'Schedule Declined', "Declined schedule ID: $scheduleId for user ID: $userId");
        createNotification($userId, "Your schedule for '$subject' has been declined.", 'warning');
        sendEmail(getUser($userId)['email'], "Schedule Declined", "Your schedule for '$subject' has been declined by the administrator. Please review and resubmit if necessary.");
        $success = 'Schedule declined successfully.';
    } else {
        $error = 'Failed to decline schedule.';
    }
}


$filterDayOfWeek = $_GET['day_of_week'] ?? '';
$filterStartDate = $_GET['start_date'] ?? ''; 
$filterEndDate = $_GET['end_date'] ?? '';     

$schedules = [];
$pendingSchedules = []; // NEW: Array for pending schedules
$params = [];
$types = "";

if (isAdmin()) {
    // MODIFIED: This query now fetches APPROVED schedules
    $query = "SELECT cs.*, u.first_name, u.last_name, u.faculty_id 
              FROM class_schedules cs 
              JOIN users u ON cs.user_id = u.id";
    $conditions = ["cs.status = 'approved'"]; // MODIFIED: Only show approved

    if ($selectedUserId) {
        $conditions[] = "cs.user_id = ?";
        $params[] = $selectedUserId;
        $types .= "i";
    }
    if ($filterDayOfWeek) {
        $conditions[] = "cs.day_of_week = ?";
        $params[] = $filterDayOfWeek;
        $types .= "s";
    }
    
    $query .= " WHERE " . implode(" AND ", $conditions);
    $query .= " ORDER BY u.last_name, u.first_name, FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time";

    // NEW: Query for PENDING schedules (unfiltered for simplicity)
    $pendingQuery = $db->query("SELECT cs.*, u.first_name, u.last_name, u.faculty_id 
                                FROM class_schedules cs 
                                JOIN users u ON cs.user_id = u.id 
                                WHERE cs.status = 'pending' 
                                ORDER BY cs.created_at ASC");
    if ($pendingQuery) {
        $pendingSchedules = $pendingQuery->fetch_all(MYSQLI_ASSOC);
    }

} else {
    // MODIFIED: User's query
    $query = "SELECT *, null as first_name, null as last_name, null as faculty_id 
              FROM class_schedules 
              WHERE user_id = ? AND status != 'declined'"; // MODIFIED: Show pending and approved
    $params = [$selectedUserId];
    $types = "i";
    
    if ($filterDayOfWeek) {
        $query .= " AND day_of_week = ?";
        $params[] = $filterDayOfWeek;
        $types .= "s";
    }
    $query .= " ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time";
}

$stmt = $db->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $error = "Database query error: " . $db->error;
}

// ... (Rest of the stats calculations remain the same) ...
$weeklyHours = 0;
$totalSchedules = 0;
$totalUsersWithSchedules = 0;

if ($selectedUserId) {
    $stmtUser = $db->prepare("SELECT * FROM users WHERE id=?");
    if ($stmtUser) {
        $stmtUser->bind_param("i", $selectedUserId);
        if ($stmtUser->execute()) {
            $result = $stmtUser->get_result();
            if ($result) {
                $selectedUser = $result->fetch_assoc();
            } else {
                $error .= " Failed to get user results. DB Error: " . $db->error;
            }
        } else {
            $error .= " Failed to execute user query. DB Error: " . $stmtUser->error;
        }
        $stmtUser->close();
    } else {
        $error .= " Failed to prepare user query. DB Error: " . $db->error;
    }

    /*calculates the total hours of the user (ONLY APPROVED)*/
    $stmtHours = $db->prepare("SELECT SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))/3600) as total FROM class_schedules WHERE user_id=? AND status='approved'");
    if ($stmtHours) {
        $stmtHours->bind_param("i", $selectedUserId);
        if ($stmtHours->execute()) { 
            $result = $stmtHours->get_result();
            if ($result) {
                $row = $result->fetch_assoc();
                $weeklyHours = round($row['total'] ?? 0, 1);
            } else {
                $error .= " Failed to get schedule hour results. DB Error: " . $db->error;
                $weeklyHours = 0;
            }
        } else {
            $error .= " Failed to execute schedule hour query. DB Error: " . $stmtHours->error;
            $weeklyHours = 0;
        }
        $stmtHours->close(); 
    } else {
        $error .= " Failed to prepare schedule hour query. DB Error: " . $db->error; 
        $weeklyHours = 0;
    }

} elseif (isAdmin()) {
    // Total APPROVED schedules
    $schedulesResult = $db->query("SELECT COUNT(*) as c FROM class_schedules WHERE status='approved'");
    if ($schedulesResult) {
        $totalSchedules = $schedulesResult->fetch_assoc()['c'];
    } else {
        $totalSchedules = 0;
        $error .= " Failed to get total schedules count. DB Error: " . $db->error;
    }
    
    // Total users with APPROVED schedules
    $usersResult = $db->query("SELECT COUNT(DISTINCT user_id) as c FROM class_schedules WHERE status='approved'");
    if ($usersResult) {
        $totalUsersWithSchedules = $usersResult->fetch_assoc()['c'];
    } else {
        $totalUsersWithSchedules = 0;
        $error .= " Failed to get total users with schedules count. DB Error: " . $db->error;
    }
}
// ... (End of stats calculations) ...

$pageTitle = 'Schedule Management';
$pageSubtitle = isAdmin() ? 'Manage class schedules and working hours' : 'Manage your class schedule';
include 'includes/header.php';
?>

<div class="main-body">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="stats-grid schedule-stats-grid">
        <?php if ($selectedUser):?>
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Viewing Schedule For</p>
                    <div class="stat-value-name">
                        <?= htmlspecialchars($selectedUser['first_name'] . ' ' . $selectedUser['last_name']) ?>
                    </div>
                    <p class="stat-value-subtext"><?= htmlspecialchars($selectedUser['faculty_id']) ?></p>
                </div>
            </div>

            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Total Approved Weekly Hours</p>
                    <div class="stat-value emerald"><?= $weeklyHours ?>h</div>
                </div>
            </div>
            
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Total Classes (Filtered)</p>
                    <div class="stat-value emerald"><?= count($schedules) ?></div>
                </div>
            </div>

        <?php elseif (isAdmin()):?>
             <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Viewing</p>
                    <div class="stat-value-name">All Users</div>
                </div>
            </div>
            
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Total Approved Schedules</p>
                    <div class="stat-value emerald"><?= $totalSchedules ?></div>
                </div>
            </div>
            
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                       <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Users with Approved Schedules</p>
                    <div class="stat-value emerald"><?= $totalUsersWithSchedules ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" id="schedule-card">
        <div class="card-header card-header-flex">
            <div>
                <h3>Manage Schedules</h3>
                <p>Filter schedules to view, edit, or add new ones</p>
            </div>
            
            <div class="card-header-actions">
                <button class="btn btn-secondary" id="toggleManageBtn">
                    <i class="fa-solid fa-pen-to-square"></i> Manage Schedules
                </button>
                
                <?php if (!isAdmin()):?>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fa-solid fa-plus"></i> Add New Schedule(s)
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <div class="tabs" style="padding: 0 1.5rem; background: var(--gray-50);">
            <button class="tab-btn <?= $activeTab === 'manage' ? 'active' : '' ?>" onclick="showScheduleTab(event, 'manage')">
                <i class="fa-solid fa-check-circle"></i> Approved Schedules (<?= count($schedules) ?>)
            </button>
            <button class="tab-btn <?= $activeTab === 'pending' ? 'active' : '' ?>" onclick="showScheduleTab(event, 'pending')" style="position: relative;">
                <i class="fa-solid fa-clock"></i> Pending Approval
                <?php if (count($pendingSchedules) > 0): ?>
                    <span style="position: absolute; top: 0.75rem; right: 0.75rem; background: var(--red-600); color: white; border-radius: 50%; width: 24px; height: 24px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        <?= count($pendingSchedules) ?>
                    </span>
                <?php endif; ?>
            </button>
        </div>
        <?php endif; ?>
        <div id="manageTab" class="tab-content <?= $activeTab === 'manage' ? 'active' : '' ?>">
            <div class="card-body">
                <form method="GET" class="schedule-filter-form">
                    <div class="schedule-filter-grid">
                        
                        <?php if (isAdmin()):?>
                        <div class="form-group">
                            <label>Select User</label>
                            <select name="user_id" class="form-control" onchange="this.form.submit()">
                                <option value="">-- All Users --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $selectedUserId == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['faculty_id']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label>Day of Week</label>
                            <select name="day_of_week" class="form-control" onchange="this.form.submit()">
                                <option value="">All Days</option>
                                <option value="Monday" <?= $filterDayOfWeek == 'Monday' ? 'selected' : '' ?>>Monday</option>
                                <option value="Tuesday" <?= $filterDayOfWeek == 'Tuesday' ? 'selected' : '' ?>>Tuesday</option>
                                <option value="Wednesday" <?= $filterDayOfWeek == 'Wednesday' ? 'selected' : '' ?>>Wednesday</option>
                                <option value="Thursday" <?= $filterDayOfWeek == 'Thursday' ? 'selected' : '' ?>>Thursday</option>
                                <option value="Friday" <?= $filterDayOfWeek == 'Friday' ? 'selected' : '' ?>>Friday</option>
                                <option value="Saturday" <?= $filterDayOfWeek == 'Saturday' ? 'selected' : '' ?>>Saturday</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Start Date (Optional)</label>
                            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStartDate) ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date (Optional)</label>
                            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEndDate) ?>">
                        </div>

                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>

                <?php if (empty($schedules)): ?>
                    <p class="empty-schedule-message">No <?= isAdmin() ? 'approved' : '' ?> schedules found matching the selected filters.</p>
                <?php else: ?>
                    <table id="schedule-table"> <thead>
                            <tr>
                                <?php if (isAdmin() && !$selectedUserId):?>
                                    <th>User</th>
                                <?php endif; ?>
                                <th>Day</th>
                                <th>Subject</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Room</th>
                                <?php if (!isAdmin()): ?>
                                    <th>Status</th> <?php endif; ?>
                                <th class="table-actions">Actions</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                            <?php 
                                $start = new DateTime($schedule['start_time']);
                                $end = new DateTime($schedule['end_time']);
                                $duration = $start->diff($end);
                                $hours = $duration->h + ($duration->i / 60);
                            ?>
                            <tr>
                                <?php if (isAdmin() && !$selectedUserId):?>
                                    <td>
                                        <div class="table-user-name"><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></div>
                                        <div class="table-user-id"><?= htmlspecialchars($schedule['faculty_id']) ?></div>
                                    </td>
                                <?php endif; ?>

                                <td class="table-day-highlight"><?= htmlspecialchars($schedule['day_of_week']) ?></td>
                                <td><?= htmlspecialchars($schedule['subject']) ?></td>
                                <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                                <td><?= number_format($hours, 1) ?>h</td>
                                <td><?= htmlspecialchars($schedule['room'] ?? '-') ?></td>
                                
                                <?php if (!isAdmin()): ?>
                                <td>
                                    <?php if ($schedule['status'] == 'pending'): ?>
                                        <span class="role-badge" style="background: var(--yellow-100); color: var(--yellow-700);">Pending</span>
                                    <?php elseif ($schedule['status'] == 'approved'): ?>
                                        <span class="role-badge">Approved</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                
                                <td class="table-actions"> <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                        <?= $schedule['id'] ?>,
                                        <?= $schedule['user_id'] ?>,
                                        '<?= htmlspecialchars($schedule['day_of_week']) ?>',
                                        '<?= htmlspecialchars(addslashes($schedule['subject'])) ?>',
                                        '<?= htmlspecialchars($schedule['start_time']) ?>',
                                        '<?= htmlspecialchars($schedule['end_time']) ?>',
                                        '<?= htmlspecialchars(addslashes($schedule['room'] ?? '')) ?>'
                                    )">
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $schedule['id'] ?>, <?= $schedule['user_id'] ?>, '<?= htmlspecialchars(addslashes($schedule['subject'])) ?>', '<?= htmlspecialchars($schedule['day_of_week']) ?>')">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <div id="pendingTab" class="tab-content <?= $activeTab === 'pending' ? 'active' : '' ?>">
            <div class="card-body">
                <?php if (empty($pendingSchedules)): ?>
                    <p class="empty-schedule-message">No schedules are currently pending approval.</p>
                <?php else: ?>
                    <table id="pending-schedule-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Day</th>
                                <th>Subject</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingSchedules as $schedule): ?>
                            <tr>
                                <td>
                                    <div class="table-user-name"><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></div>
                                    <div class="table-user-id"><?= htmlspecialchars($schedule['faculty_id']) ?></div>
                                </td>
                                <td class="table-day-highlight"><?= htmlspecialchars($schedule['day_of_week']) ?></td>
                                <td><?= htmlspecialchars($schedule['subject']) ?></td>
                                <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                                <td><?= htmlspecialchars($schedule['room'] ?? '-') ?></td>
                                <td style="text-align: right;">
                                    <form method="POST" style="display: inline-block; margin: 0 4px;">
                                        <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $schedule['user_id'] ?>">
                                        <input type="hidden" name="subject" value="<?= htmlspecialchars($schedule['subject']) ?>">
                                        <button type="submit" name="approve_schedule" class="btn btn-sm btn-success">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline-block; margin: 0 4px;">
                                        <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $schedule['user_id'] ?>">
                                        <input type="hidden" name="subject" value="<?= htmlspecialchars($schedule['subject']) ?>">
                                        <button type="submit" name="decline_schedule" class="btn btn-sm btn-danger">
                                            <i class="fa-solid fa-times"></i> Decline
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        </div>
</div>

<div id="addScheduleModal" class="modal">
    <div class="modal-content modal-lg">
        <form method="POST">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plus"></i> Add New Schedule(s)</h3>
                <button type="button" class="modal-close" onclick="closeModal('addScheduleModal')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="user_id_add" id="addScheduleUserId" value="<?= $selectedUserId ?>"> 
                
                <div id="schedule-entry-list">
                    </div>

                <button type="button" class="btn btn-secondary mt-1" onclick="addScheduleRow()">
                    <i class="fa-solid fa-plus"></i> Add Another Row
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addScheduleModal')">Cancel</button>
                <button type="submit" name="add_schedule" class="btn btn-primary">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

<div id="editScheduleModal" class="modal">
    <div class="modal-content modal-small">
        <form method="POST">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> Edit Schedule</h3>
                <button type="button" class="modal-close" onclick="closeModal('editScheduleModal')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.9rem; color: var(--gray-600); background: var(--yellow-50); border: 1px solid var(--yellow-200); padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem;">
                    Note: Editing a schedule will reset its status to 'Pending' and require re-approval.
                </p>
                <input type="hidden" name="schedule_id" id="editScheduleId">
                <input type="hidden" name="user_id_edit" id="editUserId"> 
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Day of Week</label>
                    <select name="day_of_week" id="editDayOfWeek" class="form-control" required>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value-="Saturday">Saturday</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Subject/Course</label>
                    <input type="text" name="subject" id="editSubject" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label>Start Time</label>
                        <input type="time" name="start_time" id="editStartTime" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label>End Time</label>
                        <input type="time" name="end_time" id="editEndTime" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" name="room" id="editRoom" class="form-control" placeholder="e.g., Room 101">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editScheduleModal')">Cancel</button>
                <button type="submit" name="edit_schedule" class="btn btn-primary">Update & Resubmit</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteScheduleModal" class="modal">
    <div class="modal-content modal-small">
        <form method="POST">
            <div class="modal-header modal-header-danger">
                <h3><i class="fa-solid fa-triangle-exclamation"></i> Confirm Delete</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteScheduleModal')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="schedule_id_delete" id="deleteScheduleId">
                <input type="hidden" name="user_id_delete" id="deleteUserId">
                <p class="fs-large">
                    Are you sure you want to permanently delete the following schedule?
                </p>
                <div class="modal-confirm-detail">
                    <strong id="deleteScheduleSubject"></strong>
                    <span id="deleteScheduleDay"></span>
                </div>
                <p class="fs-small text-danger mt-1">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteScheduleModal')">Cancel</button>
                <button type="submit" name="delete_schedule" class="btn btn-danger">Yes, Delete Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
const scheduleList = document.getElementById('schedule-entry-list');
const addScheduleUserIdField = document.getElementById('addScheduleUserId');

// NEW: Function to switch admin tabs
function showScheduleTab(event, tabName) {
    document.querySelectorAll('#schedule-card .tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('#schedule-card .tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabName + 'Tab').style.display = 'block';
    event.currentTarget.classList.add('active');
}

// NEW: Initialize correct tab display on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isAdmin()): ?>
    document.getElementById('manageTab').style.display = '<?= $activeTab === 'manage' ? 'block' : 'none' ?>';
    document.getElementById('pendingTab').style.display = '<?= $activeTab === 'pending' ? 'block' : 'none' ?>';
    <?php else: ?>
    // Non-admins only have one tab
    document.getElementById('manageTab').style.display = 'block';
    <?php endif; ?>
});
// END NEW


function createScheduleRowHTML() {
    return `
        <div class="schedule-entry-row">
            <div class="form-group form-group-day">
                <label>Day</label>
                <select name="day_of_week[]" class="form-control" required>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>
            </div>
            <div class="form-group form-group-subject">
                <label>Subject</label>
                <input type="text" name="subject[]" class="form-control" placeholder="e.g., IT 101" required>
            </div>
            <div class="form-group form-group-time">
                <label>Start Time</label>
                <input type="time" name="start_time[]" class="form-control" required>
            </div>
            <div class="form-group form-group-time">
                <label>End Time</label>
                <input type="time" name="end_time[]" class="form-control" required>
            </div>
            <div class="form-group form-group-room">
                <label>Room</label>
                <input type="text" name="room[]" class="form-control" placeholder="e.g., Room 101">
            </div>
            <button type="button" class="btn btn-danger" onclick="removeScheduleRow(this)">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;
}

function addScheduleRow() {
    if (scheduleList) {
        scheduleList.insertAdjacentHTML('beforeend', createScheduleRowHTML());
    }
}

function removeScheduleRow(button) {
    button.closest('.schedule-entry-row').remove();
    if (scheduleList && scheduleList.childElementCount === 0) {
        addScheduleRow();
    }
}

function openAddModal() {
    if (scheduleList) {
        scheduleList.innerHTML = '';
        addScheduleRow();
    }
    openModal('addScheduleModal');
}

function openEditModal(id, userId, day, subject, startTime, endTime, room) {
    document.getElementById('editScheduleId').value = id;
    document.getElementById('editUserId').value = userId;
    document.getElementById('editDayOfWeek').value = day;
    document.getElementById('editSubject').value = subject;
    document.getElementById('editStartTime').value = startTime;
    document.getElementById('editEndTime').value = endTime;
    document.getElementById('editRoom').value = room;
    openModal('editScheduleModal');
}

function openDeleteModal(id, userId, subject, day) {
    document.getElementById('deleteScheduleId').value = id;
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteScheduleSubject').textContent = subject;
    document.getElementById('deleteScheduleDay').textContent = day;
    openModal('deleteScheduleModal');
}
/*manage schedule button admin*/
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleManageBtn');
    const scheduleTable = document.getElementById('schedule-table');

    if (toggleBtn && scheduleTable) {
        toggleBtn.addEventListener('click', function() {

            const isManaging = scheduleTable.classList.toggle('managing');

            if (isManaging) {
                toggleBtn.innerHTML = '<i class="fa-solid fa-check"></i> Done Managing';
                toggleBtn.classList.add('btn-success');
                toggleBtn.classList.remove('btn-secondary');
            } else {
                toggleBtn.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Manage Schedules';
                toggleBtn.classList.remove('btn-success');
                toggleBtn.classList.add('btn-secondary');
            }
        });
    }

    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});
</script>

<?php include 'includes/footer.php'; ?>