<?php

$fullName = "DELA CRUZ, JUAN P.";
$monthName = "November";
$year = "2025";
$daysInMonth = 30; 

$records = [
    3 => [
        'date' => '2025-11-03',
        'time_in' => '07:58:00',
        'time_out' => '17:02:00',
        'working_hours' => 8.07
    ],
    4 => [
        'date' => '2025-11-04',
        'time_in' => '08:05:00',
        'time_out' => '17:01:00',
        'working_hours' => 7.93
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR - <?= htmlspecialchars($fullName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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

        <?php for ($i = 0; $i < 2; $i++): ?>
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
                    <td class="label">Office Hours (regular days)</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">Arrival & Departure</td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">Saturdays</td>
                    <td class_name="value"></td>
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
                    $totalHours = 0;
                    $totalMinutes = 0;
                    for ($day = 1; $day <= 31; $day++):
                        $am_in = '';
                        $am_out = '';
                        $pm_in = '';
                        $pm_out = '';
                        $day_hours = '';
                        $day_minutes = '';

                        if ($day <= $daysInMonth && isset($records[$day])) {
                            $rec = $records[$day];

                            
                            if ($rec['time_in']) {
                                $time_in_obj = strtotime($rec['time_in']);
                                if ($time_in_obj < strtotime('12:00:00')) {
                                    $am_in = date('g:i', $time_in_obj);
                                } else {
                                    $pm_in = date('g:i', $time_in_obj);
                                }
                            }
                            if ($rec['time_out']) {
                                 $time_out_obj = strtotime($rec['time_out']);
                                 if ($time_out_obj > strtotime('12:00:00')) {
                                     $pm_out = date('g:i', $time_out_obj);
                                 } else {
                                     $am_out = date('g:i', $time_out_obj);
                                 }
                            }
                        
                            if ($rec['working_hours']) {
                                $wh = floatval($rec['working_hours']);
                                $day_hours = floor($wh);
                                $day_minutes = round(($wh - $day_hours) * 60);

                                $totalHours += $day_hours;
                                $totalMinutes += $day_minutes;
                            }
                        } elseif ($day > $daysInMonth) {
                            
                            $am_in = '<div style="background: #eee; height: 100%; width: 100%;">-</div>';
                            $am_out = '<div style="background: #eee; height: 100%; width: 100%;">-</div>';
                            $pm_in = '<div style="background: #eee; height: 100%; width: 100%;">-</div>';
                            $pm_out = '<div style="background: #eee; height: 100%; width: 100%;">-</div>';
                        }
                    ?>
                    <tr>
                        <td><?= $day ?></td>
                        <td class="time-val"><?= $am_in ?></td>
                        <td class="time-val"><?= $am_out ?></td>
                        <td class="time-val"><?= $pm_in ?></td>
                        <td class="time-val"><?= $pm_out ?></td>
                        <td><?= $day_hours ?></td>
                        <td><?= $day_minutes ?></td>
                    </tr>
                    <?php endfor; ?>

                    <?php
                    
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