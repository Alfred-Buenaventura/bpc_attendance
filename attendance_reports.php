<?php
require_once 'config.php';
requireLogin(); 

$db = db();
$error = '';
$success = '';
$currentUserId = $_SESSION['user_id'];


$filterSearch = $_GET['search'] ?? ''; 
// MODIFIED: Default filter to show the last 7 days for a better default view
$defaultStartDate = date('Y-m-d', strtotime('-7 days'));
$filterStartDate = $_GET['start_date'] ?? $defaultStartDate; 
$filterEndDate = $_GET['end_date'] ?? date('Y-m-d');   
$filterUserId = $_GET['user_id'] ?? ''; // Get selected User ID

if (isAdmin()) {
    $pageTitle = 'Attendance Reports';
    $pageSubtitle = 'View and manage all user attendance records';
    
    // Fetch users for the dropdown
    $allUsers = $db->query("SELECT id, faculty_id, first_name, last_name FROM users WHERE status='active' AND role != 'Admin' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
    
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
    // User stats calculation
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
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.faculty_id LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Filter by user ID if admin selected one
if (isAdmin() && !empty($filterUserId)) {
    $query .= " AND ar.user_id = ?";
    $params[] = $filterUserId;
    $types .= "i";
}

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
// REMOVED: All temporary demo data has been removed.
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
    <!-- NEW: Added 'report-filter-card-admin' class -->
    <div class="filter-export-section card report-filter-card-admin">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter and export attendance records for all users</p>
        </div>
        <div class="card-body">
            <!-- The form action is changed to attendance_reports.php -->
            <form method="GET" action="attendance_reports.php" class="filter-controls-new">
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
                             <!-- NEW: Added 'report-date-input' class -->
                             <input type="date" id="dateRangeStartFilter" name="start_date" class="form-control report-date-input" value="<?= htmlspecialchars($filterStartDate) ?>">
                             <input type="date" id="dateRangeEndFilter" name="end_date" class="form-control report-date-input" value="<?= htmlspecialchars($filterEndDate) ?>">
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
                    <!-- NEW: Updated href to be dynamic via JS -->
                    <a href="#"
                        class="btn btn-primary download-btn"
                        id="printDtrBtn"
                        disabled 
                        title="Please select a user from the dropdown to print DTR">
                        <i class="fa-solid fa-download"></i> Download DTR PDF
                    </a>
                    <!-- NEW: Updated href to be dynamic via JS -->
                    <a href="#"
                        class="btn btn-danger export-csv-btn"
                        id="exportCsvBtn">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <!-- NEW: Added 'report-filter-card-user' class -->
    <div class="filter-export-section card report-filter-card-user">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter your attendance records by date and export</p>
        </div>
        <div class="card-body">
            <!-- The form action is changed to attendance_reports.php -->
            <form method="GET" action="attendance_reports.php" class="filter-controls-new">
                <div class="filter-inputs" style="grid-template-columns: 1fr;"> <div class="form-group filter-item">
                        <label for="dateRangeStartFilter">Date Range</label>
                         <div style="display: flex; gap: 0.5rem;">
                             <!-- NEW: Added 'report-date-input' class -->
                             <input type="date" id="dateRangeStartFilter" name="start_date" class="form-control report-date-input" value="<?= htmlspecialchars($filterStartDate) ?>">
                             <input type="date" id="dateRangeEndFilter" name="end_date" class="form-control report-date-input" value="<?= htmlspecialchars($filterEndDate) ?>">
                         </div>
                    </div>
                </div>
                
                <div class="filter-actions-new">
                    <button type="submit" class="btn btn-primary apply-filter-btn">
                        <i class="fa-solid fa-check"></i> Apply Filters
                    </button>
                    <!-- NEW: Updated href to be dynamic via JS -->
                    <a href="#"
                        class="btn btn-primary download-btn"
                        id="printDtrBtn">
                        <i class="fa-solid fa-download"></i> Download DTR PDF
                    </a>
                    <!-- NEW: Updated href to be dynamic via JS -->
                    <a href="#"
                        class="btn btn-danger export-csv-btn"
                        id="exportCsvBtn">
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
                <p style="text-align: center; color: var(--gray-500); padding: 40px; font-size: 1.1rem;">No records found matching the selected filters.</p>
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
                                <!-- NEW: Role is now dynamic -->
                                <span class="department-cell"><?= htmlspecialchars($record['role']) ?></span> 
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
                                        <!-- NEW: Added status-late class -->
                                        <span class="status-label <?= ($record['status'] == 'Late') ? 'status-late' : '' ?>">
                                            <?= htmlspecialchars($record['status']) ?>
                                        </span>
                                    </div>
                                <?php elseif (isset($record['status']) && $record['status'] == 'Absent'): ?>
                                    <div class="time-cell no-time">
                                        <span class="status-label status-absent">Absent</span>
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
    const exportBtn = document.getElementById('exportCsvBtn');
    
    // Helper function to get filter values
    function getFilterValues() {
        const startDate = document.getElementById('dateRangeStartFilter').value;
        const endDate = document.getElementById('dateRangeEndFilter').value;
        const searchInput = document.getElementById('searchFilter');
        const searchVal = searchInput ? searchInput.value : '';
        const userDropdown = document.querySelector('select[name="user_id"]');
        const selectedUserId = userDropdown ? userDropdown.value : '';
        
        return { startDate, endDate, searchVal, selectedUserId };
    }

    function checkDtrButtonState() {
        if (!printBtn) return; 

        const filters = getFilterValues();
        let href = `print_dtr.php?start_date=${filters.startDate}&end_date=${filters.endDate}`;
        
        <?php if (isAdmin()): ?>
            href += `&search=${encodeURIComponent(filters.searchVal)}`;
            href += `&user_id=${filters.selectedUserId}`;
            
            if (filters.selectedUserId) {
                printBtn.removeAttribute('disabled');
                printBtn.setAttribute('title', 'Download DTR for selected user');
            } else {
                printBtn.setAttribute('disabled', 'true');
                printBtn.setAttribute('title', 'Please select a user to print DTR');
            }
        <?php else: ?>
            // For non-admins, it's always enabled for themselves
            href += `&user_id=<?= $currentUserId ?>`;
            printBtn.removeAttribute('disabled');
        <?php endif; ?>
        
        printBtn.setAttribute('href', href);
    }

    // NEW: Function to update the Export CSV button
    function updateExportButtonState() {
        if (!exportBtn) return;
        
        const filters = getFilterValues();
        let href = `export_attendance.php?start_date=${filters.startDate}&end_date=${filters.endDate}`;

        <?php if (isAdmin()): ?>
            href += `&search=${encodeURIComponent(filters.searchVal)}`;
            href += `&user_id=${filters.selectedUserId}`;
        <?php else: ?>
            href += `&user_id=<?= $currentUserId ?>`;
        <?php endif; ?>
        
        exportBtn.setAttribute('href', href);
    }
    
    // Run on page load to set initial button states
    checkDtrButtonState();
    updateExportButtonState();

    // Add listeners to all filters to update the buttons dynamically
    const filterInputs = document.querySelectorAll('.filter-controls-new input, .filter-controls-new select');
    filterInputs.forEach(input => {
        input.addEventListener('change', () => {
            checkDtrButtonState();
            updateExportButtonState();
        });
        if (input.type === 'text') {
            input.addEventListener('input', () => {
                checkDtrButtonState();
                updateExportButtonState();
            }); 
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>