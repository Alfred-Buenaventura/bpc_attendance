<?php
require_once 'config.php';
requireLogin(); 

$db = db();
$error = '';
$success = '';
$currentUserId = $_SESSION['user_id'];


$filterSearch = $_GET['search'] ?? ''; 
// MODIFIED: Default filter to show the last 2 days for the demo
$defaultStartDate = date('Y-m-d', strtotime('-2 days'));
$filterStartDate = $_GET['start_date'] ?? $defaultStartDate; 
$filterEndDate = $_GET['end_date'] ?? date('Y-m-d');   
$filterUserId = $_GET['user_id'] ?? ''; // <-- ADDED: Get selected User ID

if (isAdmin()) {
    $pageTitle = 'Attendance Reports';
    $pageSubtitle = 'View and manage all user attendance records';
    
    // --- ADDED: Fetch users for the dropdown ---
    $allUsers = $db->query("SELECT id, faculty_id, first_name, last_name FROM users WHERE status='active' AND role != 'Admin' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
    // --- END ADDED ---
    
    // Admin stats calculation
    $today = date('Y-m-d');
    $entriesTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE date = '$today' AND time_in IS NOT NULL");
    $entriesToday = $entriesTodayResult ? $entriesTodayResult->fetch_assoc()['c'] : 0;

    $exitsTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE date = '$today' AND time_out IS NOT NULL");
    $exitsToday = $exitsTodayResult ? $exitsTodayResult->fetch_assoc()['c'] : 0;

    $presentTodayResult = $db->query("SELECT COUNT(DISTINCT user_id) as c FROM attendance_records WHERE date = '$today' AND time_in IS NOT NULL");
    $presentToday = $presentTodayResult ? $presentTodayResult->fetch_assoc()['c'] : 0;

} else {
    $pageTitle = 'My Attendance';
    $pageSubtitle = 'View your personal attendance history';
    // User stats calculation (default values, will be overridden by demo if needed)
    $today = date('Y-m-d');
    $stmtToday = $db->prepare("SELECT time_in, time_out FROM attendance_records WHERE date = ? AND user_id = ?");
    $stmtToday->bind_param("si", $today, $currentUserId);
    $stmtToday->execute();
    $todayRecord = $stmtToday->get_result()->fetch_assoc();

    $entriesToday = $todayRecord && $todayRecord['time_in'] ? 1 : 0;
    $exitsToday = $todayRecord && $todayRecord['time_out'] ? 1 : 0;

    $presentTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE user_id = $currentUserId AND time_in IS NOT NULL");
    $presentToday = $presentTodayResult ? $presentTodayResult->fetch_assoc()['c'] : 0;
}

/*Query for Table Data*/
$query = "
    SELECT ar.*, u.faculty_id, u.first_name, u.last_name, u.role
    FROM attendance_records ar
    JOIN users u ON ar.user_id = u.id
    WHERE 1=1
";
$params = [];
$types = "";

if (!isAdmin()) {
    /*For Users to see only their own attendance reports*/
    $query .= " AND ar.user_id = ?";
    $params[] = $currentUserId;
    $types .= "i";
} elseif (!empty($filterSearch)) {
    $searchTerm = "%" . $filterSearch . "%";
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.faculty_id LIKE ? OR u.email LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

// --- ADDED: Filter by user ID if admin selected one ---
if (isAdmin() && !empty($filterUserId)) {
    $query .= " AND ar.user_id = ?";
    $params[] = $filterUserId;
    $types .= "i";
}
// --- END ADDED ---

if ($filterStartDate && $filterEndDate) {
    $query .= " AND ar.date BETWEEN ? AND ?";
    $params[] = $filterStartDate;
    $params[] = $filterEndDate;
    $types .= "ss";
} elseif ($filterStartDate) { 
    $query .= " AND ar.date = ?";
    $params[] = $filterStartDate;
    $types .= "s";
}

$query .= " ORDER BY ar.date DESC, ar.time_in ASC"; 

$stmt = $db->prepare($query);
if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $error = "Database query error: " . $db->error;
    $records = [];
}


// ===================================================================
// START: TEMPORARY EXAMPLE DATA
// TO REMOVE: Delete this entire "if (empty($records)) { ... }" block.
// ===================================================================
if (empty($records)) {
    $today_demo = date('Y-m-d');
    $yesterday_demo = date('Y-m-d', strtotime('-1 day'));
    $twoDaysAgo_demo = date('Y-m-d', strtotime('-2 days'));

    $demo_juan = [
        $today_demo => ['date' => $today_demo, 'time_in' => '07:28:00', 'time_out' => '17:01:00', 'status' => 'Present', 'working_hours' => 8.55, 'faculty_id' => 'FAC001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'role' => 'Full Time Teacher'],
        $yesterday_demo => ['date' => $yesterday_demo, 'time_in' => '07:46:00', 'time_out' => '17:05:00', 'status' => 'Late', 'working_hours' => 8.32, 'faculty_id' => 'FAC001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'role' => 'Full Time Teacher'],
        $twoDaysAgo_demo => ['date' => $twoDaysAgo_demo, 'time_in' => null, 'time_out' => null, 'status' => 'Absent', 'working_hours' => 0, 'faculty_id' => 'FAC001', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'role' => 'Full Time Teacher']
    ];

    $demo_admin = [
        $today_demo => ['date' => $today_demo, 'time_in' => '08:01:00', 'time_out' => '17:00:00', 'status' => 'Present', 'working_hours' => 8.00, 'faculty_id' => 'ADMIN001', 'first_name' => 'Admin', 'last_name' => 'User', 'role' => 'Admin'],
        $yesterday_demo => ['date' => $yesterday_demo, 'time_in' => '08:05:00', 'time_out' => '17:01:00', 'status' => 'Present', 'working_hours' => 8.00, 'faculty_id' => 'ADMIN001', 'first_name' => 'Admin', 'last_name' => 'User', 'role' => 'Admin']
    ];
    
    $all_demo_records = [];

    if (isAdmin()) {
        // For Admin view, show both
        $all_demo_records = $demo_juan + $demo_admin;
        // Override admin stats
        $today = date('Y-m-d');
        $entriesToday = (isset($demo_juan[$today]['time_in']) ? 1 : 0) + (isset($demo_admin[$today]['time_in']) ? 1 : 0);
        $exitsToday = (isset($demo_juan[$today]['time_out']) ? 1 : 0) + (isset($demo_admin[$today]['time_out']) ? 1 : 0);
        $presentToday = 2; // 2 users present today

    } elseif (isset($_SESSION['faculty_id']) && $_SESSION['faculty_id'] == 'FAC001') {
        // For Juan's view, show only his
        $all_demo_records = $demo_juan;
        // Override Juan's stats
        $today = date('Y-m-d');
        $entriesToday = isset($demo_juan[$today]['time_in']) ? 1 : 0;
        $exitsToday = isset($demo_juan[$today]['time_out']) ? 1 : 0;
        $presentToday = 2; // 2 days present (present + late)
    }

    // Filter the demo records based on the user's selected dates
    $records = []; // Clear the (empty) real records
    foreach ($all_demo_records as $date => $record) {
        if ($date >= $filterStartDate && $date <= $filterEndDate) {
            // Also filter by selected user if admin
            if (isAdmin() && !empty($filterUserId)) {
                if ($record['faculty_id'] == 'FAC001' && $filterUserId == 1) { // Assuming Juan's ID is 1 for demo
                     $records[] = $record;
                } else if ($record['faculty_id'] == 'ADMIN001' && $filterUserId == $_SESSION['user_id']) { // Assuming Admin's ID
                     $records[] = $record;
                }
                // In a real scenario, you'd match $filterUserId with $record['user_id']
                // This demo is simplified
            } else {
                 $records[] = $record;
            }
        }
    }
    // Sort by date descending
    usort($records, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
// ===================================================================
// END: TEMPORARY EXAMPLE DATA
// ===================================================================


$totalRecords = count($records);

include 'includes/header.php';
?>

<div class="main-body attendance-reports-page"> 
    
    <?php if (isAdmin()): ?>
    <div class="report-header">
        <div></div>
        <button class="btn history-btn">
            <i class="fa-solid fa-clock-rotate-left"></i> History
        </button>
    </div>
    <?php endif; ?>

    <div class="report-stats-grid">
        <div class="report-stat-card">
            <div class="stat-icon-bg bg-emerald-100 text-emerald-600">
                 <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">Today's Entries</span>
                <span class="stat-value"><?= $entriesToday ?></span>
            </div>
        </div>
         <div class="report-stat-card">
            <div class="stat-icon-bg bg-red-100 text-red-600">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">Today's Exits</span>
                <span class="stat-value"><?= $exitsToday ?></span>
            </div>
        </div>
         <div class="report-stat-card">
            <div class="stat-icon-bg bg-blue-100 text-blue-600">
                 <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label"><?= isAdmin() ? 'Users Present' : 'Total Days Present' ?></span>
                <span class="stat-value"><?= $presentToday ?></span>
            </div>
        </div>
         <div class="report-stat-card">
            <div class="stat-icon-bg bg-gray-100 text-gray-600">
                 <i class="fa-solid fa-list-alt"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label"><?= isAdmin() ? 'Total Records' : 'My Records (Filtered)' ?></span>
                <span class="stat-value"><?= $totalRecords ?></span> </div>
        </div>
    </div>

    <?php if (isAdmin()): ?>
    <div class="filter-export-section card">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter and export attendance records for all users</p>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-controls-new">
                <div class="filter-inputs">
                    <div class="form-group filter-item">
                        <label for="searchFilter">Search</label>
                        <div class="search-wrapper">
                            <i class="fa-solid fa-search search-icon-filter"></i>
                            <input type="text" id="searchFilter" name="search" class="form-control search-input-filter" placeholder="Search users..." value="<?= htmlspecialchars($filterSearch) ?>">
                        </div>
                    </div>
                    <div class="form-group filter-item">
                        <label for="dateRangeStartFilter">Date Range</label>
                         <div style="display: flex; gap: 0.5rem;">
                             <input type="date" id="dateRangeStartFilter" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStartDate) ?>">
                             <input type="date" id="dateRangeEndFilter" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEndDate) ?>">
                         </div>
                    </div>
                    
                    <div class="form-group filter-item">
                        <label for="userFilter">Select User</label>
                        <select id="userFilter" name="user_id" class="form-control">
                            <option value="">All Users</option>
                            <?php foreach ($allUsers as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $filterUserId == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['faculty_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    </div>
                
                <div class="filter-actions-new">
                    <button type="submit" class="btn btn-primary apply-filter-btn">
                        <i class="fa-solid fa-check"></i> Apply Filters
                    </button>
                    <a href="#"
                        class="btn btn-primary download-btn"
                        id="printDtrBtn"
                        disabled 
                        title="Please select a user from the dropdown to print DTR">
                        <i class="fa-solid fa-download"></i> Download DTR PDF
                    </a>
                    <a href="export_attendance.php?start_date=<?= $filterStartDate ?>&end_date=<?= $filterEndDate ?>&search=<?= urlencode($filterSearch) ?>"
                        class="btn btn-danger export-csv-btn">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <div class="filter-export-section card">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter your attendance records by date and export</p>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-controls-new">
                <div class="filter-inputs" style="grid-template-columns: 1fr;"> <div class="form-group filter-item">
                        <label for="dateRangeStartFilter">Date Range</label>
                         <div style="display: flex; gap: 0.5rem;">
                             <input type="date" id="dateRangeStartFilter" name="start_date" class="form-control" value="<?= htmlspecialchars($filterStartDate) ?>">
                             <input type="date" id="dateRangeEndFilter" name="end_date" class="form-control" value="<?= htmlspecialchars($filterEndDate) ?>">
                         </div>
                    </div>
                </div>
                
                <div class="filter-actions-new">
                    <button type="submit" class="btn btn-primary apply-filter-btn">
                        <i class="fa-solid fa-check"></i> Apply Filters
                    </button>
                    <a href="print_dtr.php?start_date=<?= $filterStartDate ?>&end_date=<?= $filterEndDate ?>&user_id=<?= $currentUserId ?>"
                        class="btn btn-primary download-btn"
                        id="printDtrBtn">
                        <i class="fa-solid fa-download"></i> Download DTR PDF
                    </a>
                    <a href="export_attendance.php?start_date=<?= $filterStartDate ?>&end_date=<?= $filterEndDate ?>&user_id=<?= $currentUserId ?>"
                        class="btn btn-danger export-csv-btn">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card attendance-table-card">
         <div class="card-body" style="padding: 0;"> <?php if ($error): ?>
                <div class="alert alert-error" style="margin: 1rem;"><?= htmlspecialchars($error) ?></div>
            <?php elseif (empty($records)): ?>
                <p style="text-align: center; color: var(--gray-500); padding: 40px;">No records found matching the selected filters.</p>
            <?php else: ?>
                <table class="attendance-table-new">
                    <thead>
                        <tr>
                            <?php if (isAdmin()): ?>
                                <th>User</th>
                                <th>Department</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <?php if (isAdmin()): ?>
                            <td>
                                <div class="user-cell">
                                    <span class="user-name"><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></span>
                                    <span class="user-id"><?= htmlspecialchars($record['faculty_id']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="department-cell">Administration</span> 
                            </td>
                            <?php endif; ?>
                            
                            <td>
                                <span class="date-cell"><?= date('m/d/Y', strtotime($record['date'])) ?></span>
                            </td>
                            <td>
                                <?php if ($record['time_in']): ?>
                                    <div class="time-cell time-in">
                                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                        <span><?= date('h:i A', strtotime($record['time_in'])) ?></span>
                                        <span class="status-label"><?= htmlspecialchars($record['status']) ?></span>
                                    </div>
                                <?php elseif (isset($record['status']) && $record['status'] == 'Absent'): ?>
                                    <div class="time-cell no-time">
                                        <span class="status-label" style="background-color: var(--red-100); color: var(--red-700);">Absent</span>
                                    </div>
                                <?php else: ?>
                                    <div class="time-cell no-time">-</div>
                                <?php endif; ?>
                            </td>
                             <td>
                                <?php if ($record['time_out']): ?>
                                    <div class="time-cell time-out">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                        <span><?= date('h:i A', strtotime($record['time_out'])) ?></span>
                                    </div>
                                <?php elseif (isset($record['status']) && $record['status'] == 'Absent'): ?>
                                    <div class="time-cell no-time">-</div>
                                <?php else: ?>
                                    <div class="time-cell no-time">-</div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.getElementById('printDtrBtn');
    const searchInput = document.getElementById('searchFilter');

    function checkDtrButtonState() {
        if (!printBtn) return; 

        const startDate = document.getElementById('dateRangeStartFilter').value;
        const endDate = document.getElementById('dateRangeEndFilter').value;
        
        let href = `print_dtr.php?start_date=${startDate}&end_date=${endDate}`;
        
        <?php if (isAdmin()): ?>
            // ==========================================================
            // MODIFIED: This JS will now work correctly
            // ==========================================================
            const searchVal = searchInput ? searchInput.value : '';
            
            // This selector now correctly finds the <select> element
            const userDropdown = document.querySelector('select[name="user_id"]');
            const selectedUserId = userDropdown ? userDropdown.value : '';
            
            href += `&search=${encodeURIComponent(searchVal)}`;
            href += `&user_id=${selectedUserId}`;
            
            if (selectedUserId) {
                printBtn.removeAttribute('disabled');
                printBtn.setAttribute('title', 'Download DTR for selected user');
            } else {
                printBtn.setAttribute('disabled', 'true');
                printBtn.setAttribute('title', 'Please select a user to print DTR');
            }
            // ==========================================================
            // END MODIFICATION
            // ==========================================================
        <?php else: ?>
            // This part for non-admins is unchanged and correct
            href += `&user_id=<?= $currentUserId ?>`;
        <?php endif; ?>
        
        printBtn.setAttribute('href', href);
    }
    
    // Run on page load to set initial button state
    checkDtrButtonState();

    // Add listeners to all filters to update the button href dynamically
    const filterInputs = document.querySelectorAll('.filter-controls-new input, .filter-controls-new select');
    filterInputs.forEach(input => {
        input.addEventListener('change', checkDtrButtonState);
        if (input.type === 'text') {
            input.addEventListener('input', checkDtrButtonState); 
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>