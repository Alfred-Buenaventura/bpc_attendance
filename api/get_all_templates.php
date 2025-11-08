<?php
require_once '../config.php';

$db = db();

// --- CORRECTION ---
// Now selects 'id' and 'fingerprint_data' to match your database
$sql = "SELECT id, fingerprint_data FROM users WHERE fingerprint_data IS NOT NULL AND status = 'active'";
$result = $db->query($sql);

if ($result) {
    // We need to rename the keys to what the C# app expects
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = [
            'id' => $row['id'], // C# app expects 'id'
            'fingerprint_template' => $row['fingerprint_data'] // C# app expects 'fingerprint_template'
        ];
    }
    jsonResponse(true, "Templates fetched", $templates);
} else {
    // Send the REAL MySQL error message
    jsonResponse(false, "Database query failed: " . $db->error);
}
?>