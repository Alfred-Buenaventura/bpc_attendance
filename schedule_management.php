<?php
require_once 'config.php';
requireLogin();

$db = db();
$error = '';
$success = '';
$users = [];
$selectedUserId = null;
$selectedUser = null;
$activeTab = 'manage'; 

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
                $activeTab = 'pending';
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
            $activeTab = 'pending';
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
        // This will delete the schedule regardless of status (pending, approved, etc)
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

$approvedSchedules = []; // This will hold the flat list for a single user
$groupedApprovedSchedules = []; // This will hold the grouped list for "All Users"
$pendingSchedules = []; 
$params = [];
$types = "";

if (isAdmin()) {
    // --- Admin Query for Approved Schedules ---
    $query = "SELECT cs.*, u.first_name, u.last_name, u.faculty_id 
              FROM class_schedules cs 
              JOIN users u ON cs.user_id = u.id";
    $conditions = ["cs.status = 'approved'"]; 

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

    $stmt = $db->prepare($query);
    if ($stmt) {
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $approvedSchedulesFlat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // --- NEW: Grouping logic for "All Users" view ---
        if (!$selectedUserId) {
            foreach ($approvedSchedulesFlat as $sched) {
                $uid = $sched['user_id'];
                if (!isset($groupedApprovedSchedules[$uid])) {
                    // First time seeing this user, initialize their group
                    $groupedApprovedSchedules[$uid] = [
                        'user_info' => [
                            'first_name' => $sched['first_name'],
                            'last_name' => $sched['last_name'],
                            'faculty_id' => $sched['faculty_id']
                        ],
                        'schedules' => [],
                        'stats' => [
                            'total_hours' => 0,
                            'vacant_hours' => 0,
                            'duty_span' => 0
                        ]
                    ];
                }
                // Add the schedule to this user's list
                $groupedApprovedSchedules[$uid]['schedules'][] = $sched;
            }

            // Now, loop through each user group to calculate their stats
            foreach ($groupedApprovedSchedules as $uid => &$userData) {
                $schedules = $userData['schedules']; // This is already sorted by day, then time
                
                $dailySchedules = [];
                foreach ($schedules as $sched) {
                    $dailySchedules[$sched['day_of_week']][] = $sched;
                }

                $totalWeeklyScheduledHours = 0;
                $totalWeeklyVacantHours = 0;
                $totalWeeklyDutySpan = 0;

                foreach ($dailySchedules as $day => $daySchedules) {
                    if (empty($daySchedules)) continue;

                    $dailyScheduled = 0;
                    $dailyVacant = 0;
                    $firstIn = strtotime($daySchedules[0]['start_time']);
                    $lastOut = strtotime($daySchedules[count($daySchedules) - 1]['end_time']);

                    for ($i = 0; $i < count($daySchedules); $i++) {
                        $schedule = $daySchedules[$i];
                        $startTime = strtotime($schedule['start_time']);
                        $endTime = strtotime($schedule['end_time']);
                        $dailyScheduled += ($endTime - $startTime);

                        if ($i < count($daySchedules) - 1) {
                            $nextStartTime = strtotime($daySchedules[$i + 1]['start_time']);
                            $gap = $nextStartTime - $endTime;
                            if ($gap > 0) {
                                $dailyVacant += $gap;
                            }
                        }
                    }
                    
                    $totalWeeklyScheduledHours += ($dailyScheduled / 3600);
                    $totalWeeklyVacantHours += ($dailyVacant / 3600);
                    $totalWeeklyDutySpan += (($lastOut - $firstIn) / 3600);
                }
                
                $userData['stats']['total_hours'] = $totalWeeklyScheduledHours;
                $userData['stats']['vacant_hours'] = $totalWeeklyVacantHours;
                $userData['stats']['duty_span'] = $totalWeeklyDutySpan;
            }
            unset($userData); // Unset the reference
        } else {
            // If a user *is* selected, just use the flat array
            $approvedSchedules = $approvedSchedulesFlat;
        }
        // --- END NEW GROUPING LOGIC ---

    } else {
        $error = "Database query error: " . $db->error;
    }

    // --- Admin Query for PENDING schedules ---
    $pendingQuery = $db->query("SELECT cs.*, u.first_name, u.last_name, u.faculty_id 
                                FROM class_schedules cs 
                                JOIN users u ON cs.user_id = u.id 
                                WHERE cs.status = 'pending' 
                                ORDER BY cs.created_at ASC");
    if ($pendingQuery) {
        $pendingSchedules = $pendingQuery->fetch_all(MYSQLI_ASSOC);
    }

} else {
    // --- User Query for Approved Schedules ---
    $queryApproved = "SELECT *, null as first_name, null as last_name, null as faculty_id 
              FROM class_schedules 
              WHERE user_id = ? AND status = 'approved'";
    $paramsApproved = [$selectedUserId];
    $typesApproved = "i";
    
    if ($filterDayOfWeek) {
        $queryApproved .= " AND day_of_week = ?";
        $paramsApproved[] = $filterDayOfWeek;
        $typesApproved .= "s";
    }
    $queryApproved .= " ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time";
    
    $stmtApproved = $db->prepare($queryApproved);
    if ($stmtApproved) {
        $stmtApproved->bind_param($typesApproved, ...$paramsApproved);
        $stmtApproved->execute();
        $approvedSchedules = $stmtApproved->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Database query error: " . $db->error;
    }

    // --- User Query for PENDING Schedules ---
    $queryPending = "SELECT * FROM class_schedules WHERE user_id = ? AND status = 'pending'
                     ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), start_time";
    $stmtPending = $db->prepare($queryPending);
    if ($stmtPending) {
        $stmtPending->bind_param("i", $selectedUserId);
        $stmtPending->execute();
        $pendingSchedules = $stmtPending->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Database query error: " . $db->error;
    }
}


// --- ADVANCED STATISTICS CALCULATION ---
$totalWeeklyScheduledHours = 0;
$totalWeeklyVacantHours = 0;
$totalWeeklyDutySpan = 0;

if ($selectedUserId) {
    // We need *all* approved schedules for this user, not just the filtered ones
    $statsStmt = $db->prepare("
        SELECT day_of_week, start_time, end_time 
        FROM class_schedules 
        WHERE user_id = ? AND status = 'approved' 
        ORDER BY day_of_week, start_time
    ");
    $statsStmt->bind_param("i", $selectedUserId);
    $statsStmt->execute();
    $allUserSchedules = $statsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $groupedSchedules = [];
    foreach ($allUserSchedules as $sched) {
        $groupedSchedules[$sched['day_of_week']][] = $sched;
    }

    foreach ($groupedSchedules as $day => $daySchedules) {
        $dailyScheduled = 0;
        $dailyVacant = 0;
        
        if (empty($daySchedules)) continue;

        $firstIn = strtotime($daySchedules[0]['start_time']);
        $lastOut = strtotime($daySchedules[count($daySchedules) - 1]['end_time']);

        for ($i = 0; $i < count($daySchedules); $i++) {
            $schedule = $daySchedules[$i];
            $startTime = strtotime($schedule['start_time']);
            $endTime = strtotime($schedule['end_time']);

            // 1. Calculate scheduled hours
            $dailyScheduled += ($endTime - $startTime);

            // 2. Calculate vacant hours (gap between this class and the next)
            if ($i < count($daySchedules) - 1) {
                $nextStartTime = strtotime($daySchedules[$i + 1]['start_time']);
                $gap = $nextStartTime - $endTime;
                if ($gap > 0) {
                    $dailyVacant += $gap;
                }
            }
        }
        
        $totalWeeklyScheduledHours += ($dailyScheduled / 3600);
        $totalWeeklyVacantHours += ($dailyVacant / 3600);
        $totalWeeklyDutySpan += (($lastOut - $firstIn) / 3600);
    }

    // Fetch user info for the stat card
    $stmtUser = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmtUser->bind_param("i", $selectedUserId);
    $stmtUser->execute();
    $selectedUser = $stmtUser->get_result()->fetch_assoc();

} elseif (isAdmin() && !$selectedUserId) { 
    // Admin general stats
    $schedulesResult = $db->query("SELECT COUNT(*) as c FROM class_schedules WHERE status='approved'");
    $totalSchedules = $schedulesResult ? $schedulesResult->fetch_assoc()['c'] : 0;
    $usersResult = $db->query("SELECT COUNT(DISTINCT user_id) as c FROM class_schedules WHERE status='approved'");
    $totalUsersWithSchedules = $usersResult ? $usersResult->fetch_assoc()['c'] : 0;
}


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
            <!-- CARD 1: User Info -->
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

            <!-- CARD 2: Scheduled Hours -->
            <div class="stat-card stat-card-small">
                <div class="stat-icon emerald">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Total Scheduled Hours</p>
                    <div class="stat-value emerald"><?= number_format($totalWeeklyScheduledHours, 1) ?>h</div>
                    <p class="stat-value-subtext">Total time for classes</p>
                </div>
            </div>
            
            <!-- NEW CARD 3: Vacant Hours -->
            <div class="stat-card stat-card-small">
                <div class="stat-icon" style="color: var(--blue-600); background: var(--blue-100);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                       <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Total Vacant Hours</p>
                    <div class="stat-value" style="color: var(--blue-700);"><?= number_format($totalWeeklyVacantHours, 1) ?>h</div>
                    <p class="stat-value-subtext">Time between classes</p>
                </div>
            </div>
            
            <!-- NEW CARD 4: Duty Span -->
            <div class="stat-card stat-card-small">
                <div class="stat-icon" style="color: var(--indigo-600); background: var(--indigo-100);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                       <path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2m4 0V2m0 4V2m0 4h-4m4 0V2M8 4V2m8 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>
                    </svg>
                </div>
                <div class="stat-details">
                    <p>Total Duty Span</p>
                    <div class="stat-value" style="color: var(--indigo-700);"><?= number_format($totalWeeklyDutySpan, 1) ?>h</div>
                    <p class="stat-value-subtext">First-in to last-out</p>
                </div>
            </div>

        <?php elseif (isAdmin()):?>
             <!-- Admin general cards (for "All Users" view) -->
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
                <!-- MODIFIED: Updated subtitle -->
                <p><?= isAdmin() ? 'Approve pending schedules or manage approved ones' : 'View your approved schedules or pending submissions' ?></p>
            </div>
            
            <div class="card-header-actions">
                <!-- REMOVED: "Manage Schedules" toggle button -->
                
                <?php if (!isAdmin()):?>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fa-solid fa-plus"></i> Add New Schedule(s)
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- NEW: This tab structure now applies to EVERYONE (Admin and User) -->
        <div class="tabs" style="padding: 0 1.5rem; background: var(--gray-50);">
            <button class="tab-btn <?= $activeTab === 'manage' ? 'active' : '' ?>" onclick="showScheduleTab(event, 'manage')">
                <i class="fa-solid fa-check-circle"></i> <?= isAdmin() ? 'Approved Schedules' : 'My Approved Schedules' ?> (<?= count(isAdmin() && !$selectedUserId ? $groupedApprovedSchedules : $approvedSchedules) ?>)
            </button>
            <button class="tab-btn <?= $activeTab === 'pending' ? 'active' : '' ?>" onclick="showScheduleTab(event, 'pending')" style="position: relative;">
                <i class="fa-solid fa-clock"></i> <?= isAdmin() ? 'Pending Approval' : 'My Pending Submissions' ?>
                <?php if (count($pendingSchedules) > 0): ?>
                    <span class="notification-count-badge">
                        <?= count($pendingSchedules) ?>
                    </span>
                <?php endif; ?>
            </button>
        </div>
        
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

                <!-- ====================================================== -->
                <!-- NEW: This entire block renders the correct view       -->
                <!-- ====================================================== -->
                <?php if (isAdmin() && !$selectedUserId): // --- ADMIN "ALL USERS" ACCORDION VIEW --- ?>
                    
                    <?php if (empty($groupedApprovedSchedules)): ?>
                        <p class="empty-schedule-message">No approved schedules found for any user.</p>
                    <?php else: ?>
                        <div class="user-schedule-accordion">
                            <?php foreach ($groupedApprovedSchedules as $userId => $userData): ?>
                                <div class="user-schedule-group">
                                    <button class="user-schedule-header" onclick="toggleScheduleGroup(this)">
                                        <div class="user-schedule-info">
                                            <span class="user-name"><?= htmlspecialchars($userData['user_info']['first_name'] . ' ' . $userData['user_info']['last_name']) ?></span>
                                            <span class="user-id"><?= htmlspecialchars($userData['user_info']['faculty_id']) ?></span>
                                        </div>
                                        <div class="user-schedule-stats">
                                            <span>Sched: <strong><?= number_format($userData['stats']['total_hours'], 1) ?>h</strong></span>
                                            <span>Vacant: <strong><?= number_format($userData['stats']['vacant_hours'], 1) ?>h</strong></span>
                                            <span>Duty: <strong><?= number_format($userData['stats']['duty_span'], 1) ?>h</strong></span>
                                        </div>
                                        <i class="fa-solid fa-chevron-down schedule-group-icon"></i>
                                    </button>
                                    <div class="user-schedule-body">
                                        <!-- Render the standard table inside -->
                                        <table id="schedule-table-user-<?= $userId ?>" class="schedule-table-inner">
                                            <thead>
                                                <tr>
                                                    <th>Day</th>
                                                    <th>Subject</th>
                                                    <th>Time</th>
                                                    <th>Duration</th>
                                                    <th>Room</th>
                                                    <th class="table-actions">Actions</th> 
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $currentDay = '';
                                                $scheduleCount = count($userData['schedules']);
                                                for ($i = 0; $i < $scheduleCount; $i++):
                                                    $schedule = $userData['schedules'][$i];

                                                    // --- DAY GROUP HEADER ---
                                                    if ($schedule['day_of_week'] !== $currentDay) {
                                                        $currentDay = $schedule['day_of_week'];
                                                        echo '<tr class="day-group-header-row"><td colspan="6">' . htmlspecialchars($currentDay) . '</td></tr>';
                                                    }
                                                    // ------------------------

                                                    $start = new DateTime($schedule['start_time']);
                                                    $end = new DateTime($schedule['end_time']);
                                                    $duration = $start->diff($end);
                                                    $hours = $duration->h + ($duration->i / 60);
                                                ?>
                                                <tr>
                                                    <td class="table-day-highlight"><?= date('D', strtotime($schedule['day_of_week'])) ?></td>
                                                    <td><?= htmlspecialchars($schedule['subject']) ?></td>
                                                    <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                                                    <td><?= number_format($hours, 1) ?>h</td>
                                                    <td><?= htmlspecialchars($schedule['room'] ?? '-') ?></td>
                                                    <td class="table-actions">
                                                        <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                                            <?= $schedule['id'] ?>,
                                                            <?= $schedule['user_id'] ?>,
                                                            '<?= htmlspecialchars($schedule['day_of_week']) ?>',
                                                            <?= htmlspecialchars(json_encode($schedule['subject']), ENT_QUOTES, 'UTF-8') ?>,
                                                            '<?= htmlspecialchars($schedule['start_time']) ?>',
                                                            '<?= htmlspecialchars($schedule['end_time']) ?>',
                                                            <?= htmlspecialchars(json_encode($schedule['room'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                        )">
                                                            <i class="fa-solid fa-pen"></i> Edit
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $schedule['id'] ?>, <?= $schedule['user_id'] ?>, <?= htmlspecialchars(json_encode($schedule['subject']), ENT_QUOTES, 'UTF-8') ?>, '<?= htmlspecialchars($schedule['day_of_week']) ?>')">
                                                            <i class="fa-solid fa-trash"></i> Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php
                                                // --- Vacant Row Logic ---
                                                if ($i < $scheduleCount - 1) {
                                                    $nextSchedule = $userData['schedules'][$i + 1];
                                                    if ($nextSchedule['day_of_week'] == $schedule['day_of_week']) {
                                                        $gapStart = strtotime($schedule['end_time']);
                                                        $gapEnd = strtotime($nextSchedule['start_time']);
                                                        $gapInHours = ($gapEnd - $gapStart) / 3600;
                                                        if ($gapInHours > 0) {
                                                            echo '<tr class="vacant-row"><td colspan="6">';
                                                            echo '<i class="fa-solid fa-clock"></i>';
                                                            echo '<strong>Vacant Period:</strong> ' . number_format($gapInHours, 1) . ' hours';
                                                            echo '<span>(' . date('g:i A', $gapStart) . ' - ' . date('g:i A', $gapEnd) . ')</span>';
                                                            echo '</td></tr>';
                                                        }
                                                    }
                                                }
                                                endfor;
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: // --- DEFAULT VIEW (SINGLE USER or NON-ADMIN) --- ?>

                    <?php if (empty($approvedSchedules)): ?>
                        <p class="empty-schedule-message">No approved schedules found matching the selected filters.</p>
                    <?php else: ?>
                        <table id="schedule-table">
                            <thead>
                                <tr>
                                    <?php if (isAdmin() && !$selectedUserId):?>
                                        <th>User</th>
                                    <?php endif; ?>
                                    <th>Day</th>
                                    <th>Subject</th>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Room</th>
                                    <th class="table-actions">Actions</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $currentDay = '';
                                $scheduleCount = count($approvedSchedules);
                                for ($i = 0; $i < $scheduleCount; $i++):
                                    $schedule = $approvedSchedules[$i];

                                    // --- DAY GROUP HEADER ---
                                    if ($schedule['day_of_week'] !== $currentDay) {
                                        $currentDay = $schedule['day_of_week'];
                                        echo '<tr class="day-group-header-row"><td colspan="7">' . htmlspecialchars($currentDay) . '</td></tr>';
                                    }
                                    // ------------------------

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
                                    <td class="table-day-highlight"><?= date('D', strtotime($schedule['day_of_week'])) ?></td>
                                    <td><?= htmlspecialchars($schedule['subject']) ?></td>
                                    <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                                    <td><?= number_format($hours, 1) ?>h</td>
                                    <td><?= htmlspecialchars($schedule['room'] ?? '-') ?></td>
                                    <td class="table-actions">
                                        <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                            <?= $schedule['id'] ?>,
                                            <?= $schedule['user_id'] ?>,
                                            '<?= htmlspecialchars($schedule['day_of_week']) ?>',
                                            <?= htmlspecialchars(json_encode($schedule['subject']), ENT_QUOTES, 'UTF-8') ?>,
                                            '<?= htmlspecialchars($schedule['start_time']) ?>',
                                            '<?= htmlspecialchars($schedule['end_time']) ?>',
                                            <?= htmlspecialchars(json_encode($schedule['room'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        )">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $schedule['id'] ?>, <?= $schedule['user_id'] ?>, <?= htmlspecialchars(json_encode($schedule['subject']), ENT_QUOTES, 'UTF-8') ?>, '<?= htmlspecialchars($schedule['day_of_week']) ?>')">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php
                                // --- Vacant Row Logic ---
                                if ($i < $scheduleCount - 1) {
                                    $nextSchedule = $approvedSchedules[$i + 1];
                                    if ($nextSchedule['day_of_week'] == $schedule['day_of_week']) {
                                        $gapStart = strtotime($schedule['end_time']);
                                        $gapEnd = strtotime($nextSchedule['start_time']);
                                        $gapInHours = ($gapEnd - $gapStart) / 3600;
                                        if ($gapInHours > 0) {
                                            $colSpan = 6;
                                            if (isAdmin() && !$selectedUserId) $colSpan = 7;
                                            echo '<tr class="vacant-row"><td colspan="' . $colSpan . '">';
                                            echo '<i class="fa-solid fa-clock"></i>';
                                            echo '<strong>Vacant Period:</strong> ' . number_format($gapInHours, 1) . ' hours';
                                            echo '<span>(' . date('g:i A', $gapStart) . ' - ' . date('g:i A', $gapEnd) . ')</span>';
                                            echo '</td></tr>';
                                        }
                                    }
                                }
                                endfor; 
                                ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
                <!-- ====================================================== -->
                <!-- END NEW VIEW LOGIC                                   -->
                <!-- ====================================================== -->

            </div>
        </div>

        <div id="pendingTab" class="tab-content <?= $activeTab === 'pending' ? 'active' : '' ?>">
            <div class="card-body">
                <?php if (empty($pendingSchedules)): ?>
                    <p class="empty-schedule-message"><?= isAdmin() ? 'No schedules are currently pending approval.' : 'You have no pending schedule submissions.' ?></p>
                <?php else: ?>
                    <table id="pending-schedule-table">
                        <thead>
                            <tr>
                                <?php if (isAdmin()): ?>
                                    <th>User</th>
                                <?php endif; ?>
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
                                <?php if (isAdmin()): ?>
                                <td>
                                    <div class="table-user-name"><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></div>
                                    <div class="table-user-id"><?= htmlspecialchars($schedule['faculty_id']) ?></div>
                                </td>
                                <?php endif; ?>
                                <td class="table-day-highlight"><?= htmlspecialchars($schedule['day_of_week']) ?></td>
                                <td><?= htmlspecialchars($schedule['subject']) ?></td>
                                <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                                <td><?= htmlspecialchars($schedule['room'] ?? '-') ?></td>
                                <td style="text-align: right;">
                                    <?php if (isAdmin()): ?>
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
                                    <?php else: ?>
                                        <!-- User's pending table has Edit/Delete -->
                                        <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                            <?= $schedule['id'] ?>,
                                            <?= $schedule['user_id'] ?>,
                                            '<?= htmlspecialchars($schedule['day_of_week']) ?>',
                                            <?= htmlspecialchars(json_encode($schedule['subject']), ENT_QUOTES, 'UTF-8') ?>,
                                            '<?= htmlspecialchars($schedule['start_time']) ?>',
                                            '<?= htmlspecialchars($schedule['end_time']) ?>',
                                            <?= htmlspecialchars(json_encode($schedule['room'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        )">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $schedule['id'] ?>, <?= $schedule['user_id'] ?>, <?= htmlspecialchars(json_encode($schedule['subject']), ENT_QUOTES, 'UTF-8') ?>, '<?= htmlspecialchars($schedule['day_of_week']) ?>')">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ======================= -->
<!-- MODALS (All Unchanged)  -->
<!-- ======================= -->
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
                    <!-- Rows will be injected here by JS -->
                </div>

                <button type="button" class="btn btn-secondary" onclick="addScheduleRow()" style="margin-top: 1rem;">
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
                        <option value="Saturday">Saturday</option>
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
                <p class="fs-small text-danger" style="margin-top: 1rem;">
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
// --- Global constants for functions ---
const scheduleList = document.getElementById('schedule-entry-list');
const addScheduleUserIdField = document.getElementById('addScheduleUserId');

// Function to switch tabs
function showScheduleTab(event, tabName) {
    document.querySelectorAll('#schedule-card .tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('#schedule-card .tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabName + 'Tab').style.display = 'block';
    event.currentTarget.classList.add('active');
}

// Initialize correct tab display on page load
document.addEventListener('DOMContentLoaded', function() {
    // This logic works for both Admin and User now
    document.getElementById('manageTab').style.display = '<?= $activeTab === 'manage' ? 'block' : 'none' ?>';
    document.getElementById('pendingTab').style.display = '<?= $activeTab === 'pending' ? 'block' : 'none' ?>';
});

// --- NEW: Function to toggle accordion ---
function toggleScheduleGroup(button) {
    const group = button.closest('.user-schedule-group');
    const body = group.querySelector('.user-schedule-body');
    const icon = button.querySelector('.schedule-group-icon');

    if (body.style.maxHeight) {
        // It's open, close it
        body.style.maxHeight = null;
        button.classList.remove('active');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    } else {
        // It's closed, open it
        // We add a bit of padding just in case
        body.style.maxHeight = (body.scrollHeight + 20) + "px";
        button.classList.add('active');
        icon.classList.add('fa-chevron-up');
        icon.classList.remove('fa-chevron-down');
    }
}
// --- END NEW ---


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
        scheduleList.innerHTML = ''; // Clear any existing rows
        addScheduleRow(); // Add one new row by default
    }
    openModal('addScheduleModal'); // 'openModal' is globally defined in header.php
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

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
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