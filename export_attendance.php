<?php
require_once 'config.php';
requireLogin();

$db = db();

// --- 1. GET ALL FILTERS ---
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userId = $_GET['user_id'] ?? '';
$search = $_GET['search'] ?? ''; // <-- This was missing

// --- 2. PREPARE QUERY ---
$query = "
    SELECT ar.date, u.faculty_id, u.first_name, u.last_name, u.role,
           ar.time_in, ar.time_out, ar.working_hours, ar.status, ar.remarks
    FROM attendance_records ar
    JOIN users u ON ar.user_id = u.id
    WHERE ar.date BETWEEN ? AND ?
";

$params = [$startDate, $endDate];
$types = "ss";

// --- 3. APPLY FILTERS (with correct logic) ---
if (!isAdmin()) {
    // Non-admins can only export their own data
    $userId = $_SESSION['user_id'];
    $query .= " AND ar.user_id = ?";
    $params[] = $userId;
    $types .= "i";
} elseif ($userId) {
    // If a specific user is selected, that takes priority
    $query .= " AND ar.user_id = ?";
    $params[] = $userId;
    $types .= "i";
} elseif (!empty($search)) {
    // --- NEW: Apply search filter if no specific user is selected ---
    $searchTerm = "%" . $search . "%";
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.faculty_id LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}
// If no user or search, it just exports all users in the date range (for Admin)

$query .= " ORDER BY ar.date DESC, u.first_name";

$stmt = $db->prepare($query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- 4. GENERATE CSV ---
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Header Row
fputcsv($output, [
    'Date',
    'Faculty ID',
    'First Name',
    'Last Name',
    'Role',
    'Time In',
    'Time Out',
    'Working Hours',
    'Status',
    'Remarks'
]);

// Data Rows
foreach ($records as $record) {
    fputcsv($output, [
        date('m/d/Y', strtotime($record['date'])),
        $record['faculty_id'],
        $record['first_name'],
        $record['last_name'],
        $record['role'],
        $record['time_in'] ?? '-',
        $record['time_out'] ?? '-',
        $record['working_hours'] ?? '-',
        $record['status'],
        $record['remarks'] ?? ''
    ]);
}

fclose($output);
exit;
?>