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

// check if we're an admin or a regular user
if (isAdmin()) {
    $pageTitle = 'Attendance Reports';
    $pageSubtitle = 'View and manage all user attendance records';
    
    // fetch users for the dropdown
    $allUsers = $db->query("SELECT id, faculty_id, first_name, last_name FROM users WHERE status='active' AND role != 'Admin' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
    
    // admin stats calculation
    $today = date('Y-m-d');
    $entriesTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE date = '$today' AND time_in IS NOT NULL");
    $entriesToday = $entriesTodayResult ? $entriesTodayResult->fetch_assoc()['c'] : 0;

    $exitsTodayResult = $db->query("SELECT COUNT(*) as c FROM attendance_records WHERE date = '$today' AND time_out IS NOT NULL");
    $exitsToday = $exitsTodayResult ? $exitsTodayResult->fetch_assoc()['c'] : 0;

    $presentTodayResult = $db->query("SELECT COUNT(DISTINCT user_id) as c FROM attendance_records WHERE date = '$today' AND time_in IS NOT NULL");
    $presentToday = $presentTodayResult ? $presentTodayResult->fetch_assoc()['c'] : 0;

} else {
    // or just show user-specific stuff
    $pageTitle = 'My Attendance';
    $pageSubtitle = 'View your personal attendance history';
    // user stats calculation
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

/* * building the main query for table data.
 * we join with the users table to get names.
 */
$query = "
    SELECT ar.*, u.faculty_id, u.first_name, u.last_name, u.role
    FROM attendance_records ar
    JOIN users u ON ar.user_id = u.id
    WHERE 1=1
";
$params = [];
$types = "";

// for users to see only their own attendance reports
if (!isAdmin()) {
    $query .= " AND ar.user_id = ?";
    $params[] = $currentUserId;
    $types .= "i";
} elseif (!empty($filterSearch)) {
    // admin search logic
    $searchTerm = "%" . $filterSearch . "%";
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.faculty_id LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// filter by user id if admin selected one
if (isAdmin() && !empty($filterUserId)) {
    $query .= " AND ar.user_id = ?";
    $params[] = $filterUserId;
    $types .= "i";
}

// handle the date range filters
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

// prepare and execute the database query
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
    <div class="filter-export-section card report-filter-card-admin">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter and export attendance records for all users</p>
        </div>
        <div class="card-body">
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
                    <button type="submit" class="btn btn-primary btn-sm apply-filter-btn">
                        <i class="fa-solid fa-check"></i> Apply Filters
                    </button>
                    <a href="#"
                        class="btn btn-danger btn-sm export-csv-btn"
                        id="exportCsvBtn">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <div class="filter-export-section card report-filter-card-user">
        <div class="card-header">
            <h3><i class="fa-solid fa-filter"></i> Filter & Export</h3>
            <p>Filter your attendance records by date and export</p>
        </div>
        <div class="card-body">
            <p class="user-creation-subtitle" style="margin-top: -1rem; margin-bottom: 1.5rem;">Select a date range to filter your records, then press "Apply". You can download a DTR for the selected range.</p>
            <form method="GET" action="attendance_reports.php" class="filter-controls-new">
                <div class="filter-inputs" style="grid-template-columns: 1fr;"> <div class="form-group filter-item">
                        <label for="dateRangeStartFilter">Date Range</label>
                         <div style="display: flex; gap: 0.5rem;">
                             <input type="date" id="dateRangeStartFilter" name="start_date" class="form-control report-date-input" value="<?= htmlspecialchars($filterStartDate) ?>">
                             <input type="date" id="dateRangeEndFilter" name="end_date" class="form-control report-date-input" value="<?= htmlspecialchars($filterEndDate) ?>">
                         </div>
                    </div>
                </div>
                
                <div class="filter-actions-new">
                    <button type="submit" class="btn btn-primary btn-sm apply-filter-btn">
                        <i class="fa-solid fa-check"></i> Apply Filters
                    </button>
                    <a href="#"
                        class="btn btn-danger btn-sm export-csv-btn"
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
                            <?php else: ?>
                                <th>Name</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // loop through all the attendance records
                        foreach ($records as $record): 
                        ?>
                        
                        <tr 
                            <?php if (isAdmin()): ?>
                                class="clickable-row"
                                onclick="openDtrModal('print_dtr.php?user_id=<?= $record['user_id'] ?>&start_date=<?= htmlspecialchars($filterStartDate) ?>&end_date=<?= htmlspecialchars($filterEndDate) ?>&preview=1', '<?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>')"
                                title="Click to preview DTR for <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>"
                            <?php elseif (!isAdmin()): ?>
                                class="clickable-row"
                                onclick="openDtrModal('print_dtr.php?user_id=<?= $currentUserId ?>&start_date=<?= htmlspecialchars($filterStartDate) ?>&end_date=<?= htmlspecialchars($filterEndDate) ?>&preview=1', '<?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>')"
                                title="Click to preview your DTR"
                            <?php endif; ?>
                        >
                            <?php if (isAdmin()): ?>
                            <td>
                                <div class="user-cell">
                                    <span class="user-name">
                                        <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                                    </span>
                                    <span class="user-id"><?= htmlspecialchars($record['faculty_id']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="department-cell"><?= htmlspecialchars($record['role']) ?></span> 
                            </td>
                            <?php else: ?>
                            <td>
                                <div class="user-cell">
                                    <span class="user-name">
                                        <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                                    </span>
                                    <span class="user-id"><?= htmlspecialchars($record['faculty_id']) ?></span>
                                </div>
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

    <div class="page-hint-card">
        <div class="page-hint-icon">
            <i class="fa-solid fa-lightbulb"></i>
        </div>
        <div class="page-hint-content">
            <?php if (isAdmin()): ?>
                <h4>How to Use This Page</h4>
                <p>
                    You can search for users, filter by date, or select a specific user from the dropdown. Click any user's row in the table to preview their DTR for the selected date range.
                </p>
            <?php else: ?>
                <h4>How to Use This Page</h4>
                <p>
                    Use the date range filters and click "Apply" to see your history. Click any row in the table to preview your DTR for the selected date range.
                </p>
            <?php endif; ?>
        </div>
    </div>
    
</div> <div id="dtrPreviewModal" class="modal modal-dtr-preview">
    <div class="modal-content">
        <div class="modal-header" style="justify-content: space-between; display: flex; width: 100%;">
            <div>
                <h3 id="dtrModalTitle" style="color: var(--emerald-800);"><i class="fa-solid fa-file-invoice"></i> DTR Preview</h3>
                <p id="dtrModalSubtitle" style="color: var(--gray-600); font-size: 0.9rem; margin-top: 4px;"></p>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <button type="button" class="btn btn-primary btn-sm" onclick="printDtrFromModal()">
                    <i class="fa-solid fa-print"></i> Print
                </button>
                <button type="button" class="modal-close" onclick="closeDtrModal()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
        </div>
        <div class="modal-body">
            <iframe id="dtrFrame" src="about:blank" frameborder="0"></iframe>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // const printBtn = document.getElementById('printDtrBtn'); // button is removed
    const exportBtn = document.getElementById('exportCsvBtn');
    
    // helper function to get all the filter values
    function getFilterValues() {
        const startDate = document.getElementById('dateRangeStartFilter').value;
        const endDate = document.getElementById('dateRangeEndFilter').value;
        const searchInput = document.getElementById('searchFilter');
        const searchVal = searchInput ? searchInput.value : '';
        const userDropdown = document.querySelector('select[name="user_id"]');
        const selectedUserId = userDropdown ? userDropdown.value : '';
        
        return { startDate, endDate, searchVal, selectedUserId };
    }

    // this function updates the 'export csv' button's link
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
    
    // run on page load to set the initial button states
    updateExportButtonState();

    // add listeners to all filters to update the buttons dynamically
    const filterInputs = document.querySelectorAll('.filter-controls-new input, .filter-controls-new select');
    filterInputs.forEach(input => {
        input.addEventListener('change', () => {
            updateExportButtonState();
        });
        if (input.type === 'text') {
            input.addEventListener('input', () => { // 'input' is better than 'change' for text fields
                updateExportButtonState();
            }); 
        }
    });
});

/*
 * new functions to open and close the dtr preview modal.
 * these are called by the clickable row.
 */
function openDtrModal(url, userName) {
    const iframe = document.getElementById('dtrFrame');
    const modal = document.getElementById('dtrPreviewModal');
    const subtitle = document.getElementById('dtrModalSubtitle');
    
    if (iframe && modal) {
        if (subtitle && userName) {
            subtitle.textContent = "Previewing for: " + userName;
        }
        iframe.src = url; // load the dtr page into the iframe
        openModal('dtrPreviewModal'); // 'openModal' is globally defined
    }
}

function closeDtrModal() {
    const iframe = document.getElementById('dtrFrame');
    const modal = document.getElementById('dtrPreviewModal');
    const subtitle = document.getElementById('dtrModalSubtitle');
    
    if (iframe && modal) {
        iframe.src = 'about:blank'; // clear the iframe to stop any loading
        if (subtitle) {
            subtitle.textContent = "";
        }
        closeModal('dtrPreviewModal'); // 'closeModal' is globally defined
    }
}

/* new function to trigger print from the modal button */
function printDtrFromModal() {
    const iframe = document.getElementById('dtrFrame');
    if (iframe && iframe.contentWindow) {
        // tell the iframe's window to execute its print function
        iframe.contentWindow.print();
    }
}
</script>

<?php include 'includes/footer.php'; ?>