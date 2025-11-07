<?php
require_once 'config.php';
requireLogin();

$db = db();
$error = '';

// --- 1. GET PARAMETERS ---
$userId = $_GET['user_id'] ?? 0;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// --- 2. SECURITY CHECK ---
if (!isAdmin()) {
    if ($userId != $_SESSION['user_id']) {
        die('Access Denied. You can only print your own DTR.');
    }
}
if (empty($userId)) {
    die('No user selected.');
}

// --- 3. FETCH USER DATA ---
$userStmt = $db->prepare("SELECT faculty_id, first_name, last_name, middle_name FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    die('User not found.');
}

$middleInitial = !empty($user['middle_name']) ? ' ' . strtoupper(substr($user['middle_name'], 0, 1)) . '.' : '';
$fullName = strtoupper($user['last_name'] . ', ' . $user['first_name'] . $middleInitial);
$facultyId = $user['faculty_id'];

// --- 4. PREPARE DTR DATES ---
try {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
} catch (Exception $e) {
    die('Invalid date range.');
}

$monthNum = $start->format('m');
$monthName = $start->format('F');
$year = $start->format('Y');
$daysInMonth = (int)$start->format('t'); // Total days in the selected month

if ($start->format('Y-m') != $end->format('Y-m')) {
    $monthName = $start->format('F Y') . ' - ' . $end->format('F Y');
}

// --- 5. FETCH REAL-TIME ATTENDANCE DATA ---
$recordsStmt = $db->prepare("SELECT date, time_in, time_out, working_hours FROM attendance_records WHERE user_id = ? AND date BETWEEN ? AND ?");
$recordsStmt->bind_param("iss", $userId, $startDate, $endDate);
$recordsStmt->execute();
$dbRecords = $recordsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process real-time records into a day-keyed array for easy lookup
$realTimeRecords = [];
foreach ($dbRecords as $rec) {
    $dayOfMonth = (int)(new DateTime($rec['date']))->format('j');
    $realTimeRecords[$dayOfMonth] = $rec;
}

// --- 6. FETCH SCHEDULE-BASED DATA ---
$scheduleStmt = $db->prepare("
    SELECT day_of_week, MIN(start_time) AS first_in, MAX(end_time) AS last_out
    FROM class_schedules
    WHERE user_id = ? AND status = 'approved'
    GROUP BY day_of_week
");
$scheduleStmt->bind_param("i", $userId);
$scheduleStmt->execute();
$dbSchedules = $scheduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process scheduled records into a day-of-week-keyed array
$scheduledRecords = [];
foreach ($dbSchedules as $sched) {
    $scheduledRecords[$sched['day_of_week']] = $sched;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR - <?= htmlspecialchars($fullName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dtr-body"> 
    
    <div class="print-controls">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fa-solid fa-print"></i>
            Print DTR
        </button>
        <button class="btn btn-secondary back-link" onclick="history.back()">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </button>
    </div>

    <div class="dtr-container-wrapper">

        <?php for ($i = 0; $i < 2; $i++): // Loop to print two copies on one page ?>
        <div class="dtr-container">
            <div class="dtr-header">
                <h3>CS Form 48</h3>
                <h2>DAILY TIME RECORD</h2>
            </div>

            <table class="info-table">
                <tr>
                    <td class="label">Name</td>
                    <td class="value" style="text-align: center; font-weight: bold; font-size: 1rem;"><?= htmlspecialchars($fullName) ?></td>
                </tr>
                <tr>
                    <td class="label">For the month of</td>
                    <td class="value"><?= htmlspecialchars($monthName . " " . $year) ?></td>
                </tr>
                <tr>
                    <td class="label">Faculty ID</td>
                    <td class="value"><?= htmlspecialchars($facultyId) ?></td>
                </tr>
                <tr>
                    <td class="label">Office Hours (regular days)</td>
                    <td class="value">8:00 AM - 5:00 PM (1hr break)</td>
                </tr>
            </table>

            <table class="attendance-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="day-col">Day</th>
                        <th colspan="2">A.M.</th>
                        <th colspan="2">P.M.</th>
                        <th colspan="2">Hours</th>
                    </tr>
                    <tr>
                        <th class="col-small">Arrival</th>
                        <th class="col-small">Departure</th>
                        <th class="col-small">Arrival</th>
                        <th class="col-small">Departure</th>
                        <th class="col-large">Hours</th>
                        <th class="col-large">Min.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // --- 7. DYNAMICALLY RENDER TABLE ROWS WITH HYBRID LOGIC ---
                    $totalHours = 0;
                    $totalMinutes = 0;
                    
                    for ($day = 1; $day <= 31; $day++):
                        $am_in = '';
                        $am_out = '';
                        $pm_in = '';
                        $pm_out = '';
                        $day_hours = '';
                        $day_minutes = '';
                        
                        $isScheduledDay = false; // Flag for schedule-based logic

                        // Determine which logic to use
                        if (($day >= 1 && $day <= 7) || ($day >= 16 && $day <= 23)) {
                            // --- USE REAL-TIME LOGIC ---
                            if ($day <= $daysInMonth && isset($realTimeRecords[$day])) {
                                $rec = $realTimeRecords[$day];
                                if ($rec['time_in']) {
                                    $time_in_obj = strtotime($rec['time_in']);
                                    if ($time_in_obj < strtotime('12:00:00')) $am_in = date('g:i', $time_in_obj);
                                    else $pm_in = date('g:i', $time_in_obj);
                                }
                                if ($rec['time_out']) {
                                     $time_out_obj = strtotime($rec['time_out']);
                                     if ($time_out_obj > strtotime('13:00:00')) $pm_out = date('g:i', $time_out_obj);
                                     else $am_out = date('g:i', $time_out_obj);
                                }
                                if ($rec['working_hours']) {
                                    $wh = floatval($rec['working_hours']);
                                    $day_hours = floor($wh);
                                    $day_minutes = round(($wh - $day_hours) * 60);
                                    $totalHours += $day_hours;
                                    $totalMinutes += $day_minutes;
                                }
                            }
                        } elseif (($day >= 8 && $day <= 15) || ($day >= 24 && $day <= $daysInMonth)) {
                            // --- USE SCHEDULE-BASED LOGIC ---
                            $currentDate = "$year-$monthNum-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                            $dayOfWeek = date('l', strtotime($currentDate));
                            
                            if (isset($scheduledRecords[$dayOfWeek])) {
                                $isScheduledDay = true;
                                $sched = $scheduledRecords[$dayOfWeek];
                                
                                $start_obj = strtotime($sched['first_in']);
                                $end_obj = strtotime($sched['last_out']);

                                // Same logic as real-time, but using scheduled times
                                if ($start_obj < strtotime('12:00:00')) $am_in = date('g:i', $start_obj);
                                else $pm_in = date('g:i', $start_obj);
                                
                                if ($end_obj > strtotime('13:00:00')) $pm_out = date('g:i', $end_obj);
                                else $am_out = date('g:i', $end_obj);

                                // Calculate scheduled hours (minus 1 hour for lunch if it spans midday)
                                $hours = ($end_obj - $start_obj) / 3600;
                                if ($start_obj < strtotime('12:00:00') && $end_obj > strtotime('13:00:00')) {
                                    $hours -= 1; // Subtract 1-hour lunch break
                                }
                                
                                $wh = max(0, $hours); // Ensure no negative hours
                                $day_hours = floor($wh);
                                $day_minutes = round(($wh - $day_hours) * 60);
                                $totalHours += $day_hours;
                                $totalMinutes += $day_minutes;
                            }
                            // If no schedule for this day, all vars remain blank (e.g., Sunday)
                        }
                        
                        // Grey out days not in the selected month
                        if ($day > $daysInMonth) {
                            $am_in = '<div class="dtr-day-disabled">-</div>';
                            $am_out = '<div class="dtr-day-disabled">-</div>';
                            $pm_in = '<div class="dtr-day-disabled">-</div>';
                            $pm_out = '<div class="dtr-day-disabled">-</div>';
                        }
                    ?>
                    <tr>
                        <td><?= $day ?></td>
                        <td class="time-val <?= $isScheduledDay ? 'dtr-scheduled-time' : '' ?>"><?= $am_in ?></td>
                        <td class="time-val <?= $isScheduledDay ? 'dtr-scheduled-time' : '' ?>"><?= $am_out ?></td>
                        <td class="time-val <?= $isScheduledDay ? 'dtr-scheduled-time' : '' ?>"><?= $pm_in ?></td>
                        <td class="time-val <?= $isScheduledDay ? 'dtr-scheduled-time' : '' ?>"><?= $pm_out ?></td>
                        <td class="<?= $isScheduledDay ? 'dtr-scheduled-time' : '' ?>"><?= $day_hours ?></td>
                        <td class="<?= $isScheduledDay ? 'dtr-scheduled-time' : '' ?>"><?= $day_minutes ?></td>
                    </tr>
                    <?php endfor; ?>

                    <?php
                    // Calculate final total hours and minutes
                    $totalHours += floor($totalMinutes / 60);
                    $totalMinutes = $totalMinutes % 60;
                    ?>
                    <tr class="total-row">
                        <td colspan="5">Total</td>
                        <td><?= $totalHours > 0 ? $totalHours : '' ?></td>
                        <td><?= $totalMinutes > 0 ? $totalMinutes : '' ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="dtr-footer-content">
                I certify on my honor that the above is true and correct record of the hours of work performed, record of which was made daily at the time of arrival and departure from the office.
            </div>

            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">(Signature)</div>
            </div>

            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">(In-charge)</div>
            </div>

        </div>
        <?php endfor; ?>
    </div>

</body>
</html>