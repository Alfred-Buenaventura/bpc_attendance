<?php
require_once 'config.php';
requireAdmin();

$db = db();
$userId = $_GET['user_id'] ?? 0;
$user = $db->query("SELECT * FROM users WHERE id='$userId' AND status='active'")->fetch_assoc();

if (!$user) {
    header("Location: complete_registration.php");
    exit;
}

/*handles the submission of the fingerprint*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fingerprint_data'])) {
    $fingerprintData = $_POST['fingerprint_data'];
    $stmt = $db->prepare("UPDATE users SET fingerprint_data=?, fingerprint_registered=1 WHERE id=?");
    $stmt->bind_param("si", $fingerprintData, $userId);
    $stmt->execute();

    logActivity($_SESSION['user_id'], "Fingerprint Registration", "Completed for {$user['first_name']} {$user['last_name']} ({$user['faculty_id']})");
    header("Location: complete_registration.php?success=1");
    exit;
}

$pageTitle = "Fingerprint Registration";

$pageSubtitle = "Fingerprint Registration Process";
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

.scan-step { transition: all 0.3s ease; }
.scan-step.bg-emerald-500 {
    transform: scale(1.1);
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.5);
}
@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.05); opacity: 0.7; }
}
.pulse { animation: pulse 1s infinite ease-in-out; }

/* Styles for disabled button */
.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<div class="main-body flex items-center justify-center">
  <div class="w-full max-w-2xl bg-white p-8 rounded-2xl shadow-lg border border-gray-200">
    <h2 class="text-center text-2xl font-bold text-gray-800 mb-2">Fingerprint Registration</h2>
    <p class="text-center text-gray-600 mb-6">
      Registering Fingerprint for
      <span class="font-semibold text-emerald-700"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span><br>
      Faculty ID: <span class="text-gray-500"><?= htmlspecialchars($user['faculty_id']) ?></span>
    </p>

    <div id="deviceStatusContainer" class="border border-gray-200 bg-gray-50 text-gray-600 py-3 px-4 rounded-lg flex items-center justify-center gap-3 mb-6">
      <i id="deviceStatusIcon" class="fa fa-spinner fa-spin"></i>
      <span id="deviceStatusText" class="font-medium">Connecting to device...</span>
    </div>

    <div class="flex flex-col items-center text-center border border-emerald-100 rounded-xl p-8 mb-6">
      <div class="w-40 h-40 rounded-full border-4 border-emerald-100 flex items-center justify-center mb-4">
        <i class="fa fa-fingerprint fa-4x text-emerald-600" id="fingerIcon"></i>
      </div>
      <p class="text-gray-700 font-medium mb-4" id="scanStatus">Ready to scan fingerprint...</p>
      
      <div class="flex gap-3 mb-6">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <div id="scanStep<?= $i ?>" class="scan-step w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center font-bold text-gray-500"><?= $i ?></div>
        <?php endfor; ?>
      </div>

      <form method="POST" id="fingerprintForm" class="flex items-center gap-3">
        <input type="hidden" name="fingerprint_data" id="fingerprintData">
        <button type="button" id="scanBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-md font-medium shadow flex items-center gap-2" disabled>
          <i class="fa fa-fingerprint"></i> Scan
        </button>
        <a href="complete_registration.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-md font-medium shadow">Cancel</a>
      </form>
    </div>

    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-lg text-sm">
      <strong>Instructions:</strong>
      <ul class="list-disc list-inside mt-2 text-blue-700 space-y-1">
        <li>Ensure your finger is clean and dry.</li>
        <li>Place your finger firmly on the scanner.</li>
        <li>You will need to scan your finger **3 times** to complete the registration.</li>
        <li>Hold still until each scan is complete.</li>
      </ul>
    </div>
  </div>
</div>

<div id="deviceErrorModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header" style="background-color: var(--red-50);">
            <h3 style="color: var(--red-700);"><i class="fa-solid fa-triangle-exclamation"></i> Device Not Detected</h3>
            <button type="button" class="modal-close" onclick="closeModal('deviceErrorModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="color: var(--gray-700); font-size: 1rem;">
                The fingerprint scanner could not be detected. Please ensure:
            </p>
            <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 1rem; color: var(--gray-600);">
                <li>The device is properly connected to the computer via USB.</li>
                <li>The necessary ZKTeco service or software is running on the computer.</li>
                <li>No other application is currently using the device.</li>
            </ul>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="deviceErrorOkBtn">OK</button>
        </div>
    </div>
</div>

<div id="retryConnectionModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header" style="background-color: var(--blue-50);">
            <h3 style="color: var(--blue-700);"><i class="fa-solid fa-plug"></i> Retry Connection?</h3>
            <button type="button" class="modal-close" onclick="closeModal('retryConnectionModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="color: var(--gray-700); font-size: 1rem;">
                The device is still not detected. Please check the connection again.
            </p>
            <p style="color: var(--gray-700); font-size: 1rem; margin-top: 1rem;">
                Would you like to try connecting again?
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='complete_registration.php'">
                <i class="fa-solid fa-arrow-left"></i> Back to List
            </button>
            <button type="button" class="btn btn-primary" id="retryConnectBtn">
                <i class="fa-solid fa-refresh"></i> Retry Connection
            </button>
        </div>
    </div>
</div>

<div id="scanNoticeModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header" style="background-color: var(--yellow-50);">
            <h3 style="color: var(--yellow-700);"><i class="fa-solid fa-circle-info"></i> Important Notice</h3>
            <button type="button" class="modal-close" onclick="closeModal('scanNoticeModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
             <p style="color: var(--gray-700); font-size: 1rem;">
                Before scanning, please ensure:
            </p>
             <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 1rem; color: var(--gray-600);">
                <li>The user's finger is clean and dry.</li>
                <li>The fingerprint scanner surface is clean.</li>
             </ul>
             <p style="color: var(--gray-700); font-size: 1rem; margin-top: 1rem;">
                Press "Proceed" to start the 3-step scan.
            </p>
        </div>
        <div class="modal-footer">
             <button type="button" class="btn btn-secondary" onclick="closeModal('scanNoticeModal')">Cancel Scan</button>
             <button type="button" id="proceedScanBtn" class="btn btn-primary">Proceed</button>
        </div>
    </div>
</div>

<div id="scanFailedModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header" style="background-color: var(--yellow-50);">
            <h3 style="color: var(--yellow-700);"><i class="fa-solid fa-triangle-exclamation"></i> Scan Failed</h3>
            <button type="button" class="modal-close" onclick="closeModal('scanFailedModal')">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="color: var(--gray-700); font-size: 1rem;">
                The scan was unsuccessful. Please try again.
            </p>
            <p id="scanFailedAttempts" style="color: var(--gray-600); font-size: 0.9rem; margin-top: 0.5rem;"></p>
            
            <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 1rem; color: var(--gray-600); font-size: 0.9rem;">
                <li>Make sure the fingerprint scanner surface is clean.</li>
                <li>Ensure the user's finger is clean, dry, and placed firmly.</li>
            </ul>
        </div>
        <div class="modal-footer">
             <button type="button" class="btn btn-secondary" onclick="window.location.href='complete_registration.php'">Cancel</button>
             <button type="button" id="retryScanBtn" class="btn btn-primary">Try Again</button>
        </div>
    </div>
</div>

<div id="maxFailuresModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header" style="background-color: var(--red-50);">
            <h3 style="color: var(--red-700);"><i class="fa-solid fa-times-circle"></i> Maximum Attempts Reached</h3>
        </div>
        <div class="modal-body">
            <p style="color: var(--gray-700); font-size: 1rem;">
                You have failed to scan 5 times in a row.
            </p>
            <p style="color: var(--gray-700); font-size: 1rem; margin-top: 1rem;">
                Please click "OK" to return to the user list and restart the registration process.
            </p>
        </div>
        <div class="modal-footer">
             <button type="button" id="maxFailuresOkBtn" class="btn btn-primary">OK</button>
        </div>
    </div>
</div>


<script>
// Modal helper functions (unchanged)
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
}
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

// --- WebSocket and State Variables ---
let socket;
let isDeviceConnected = false;

// --- UI Element References ---
const deviceStatusContainer = document.getElementById('deviceStatusContainer');
const deviceStatusIcon = document.getElementById('deviceStatusIcon');
const deviceStatusText = document.getElementById('deviceStatusText');
const scanBtn = document.getElementById('scanBtn');
const fingerIcon = document.getElementById('fingerIcon');
const scanStatus = document.getElementById('scanStatus');
const fingerprintForm = document.getElementById('fingerprintForm');

/**
 * Resets the UI to its initial state before a scan starts.
 */
function resetScanUI() {
    for (let i = 1; i <= 3; i++) {
        const stepEl = document.getElementById(`scanStep${i}`);
        if (stepEl) {
            stepEl.classList.remove('bg-emerald-500', 'border-emerald-500', 'text-white');
            stepEl.classList.add('text-gray-500');
        }
    }
    fingerIcon.classList.remove('pulse');
    scanStatus.textContent = "Ready to scan fingerprint...";
    scanBtn.disabled = false; // Re-enable scan button in case of failure
    scanBtn.innerHTML = '<i class="fa fa-fingerprint"></i> Scan';
}

/**
 * Starts the entire 3-step enrollment process.
 * This is called after the user clicks "Proceed" in the notice modal.
 */
function startEnrollment() {
    if (!socket || socket.readyState !== WebSocket.OPEN) {
        scanStatus.textContent = "Device not ready. Please wait or refresh.";
        return;
    }

    // Reset UI for the new attempt
    resetScanUI();
    
    // Update UI for scanning
    scanStatus.textContent = "Place finger for scan 1 of 3...";
    fingerIcon.classList.add('pulse');
    scanBtn.disabled = true; // Disable button during the process
    scanBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Scanning...';

    // Send the one and only command to the C# bridge
    socket.send(JSON.stringify({ command: "enroll_start" }));
}

/**
 * Main WebSocket connection logic
 */
function connectWebSocket() {
    socket = new WebSocket("ws://127.0.0.1:8080");

    socket.onopen = () => {
        isDeviceConnected = true;
        deviceStatusContainer.classList.replace('border-gray-200', 'border-emerald-200');
        deviceStatusContainer.classList.replace('bg-gray-50', 'bg-emerald-50');
        deviceStatusContainer.classList.replace('text-gray-600', 'text-emerald-700');
        deviceStatusIcon.className = 'fa fa-check-circle'; 
        deviceStatusText.textContent = "Device Connected";
        scanBtn.disabled = false; 
        console.log("WebSocket Connected");
    };

    socket.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            console.log("Message from server:", data);

            // C# bridge reports progress on a successful step
            if (data.status === "progress") {
                const step = data.step; // This will be 1, 2, or 3

                // Update the visual step circle
                const stepEl = document.getElementById(`scanStep${step}`);
                if (stepEl) {
                    stepEl.classList.add('bg-emerald-500', 'border-emerald-500', 'text-white');
                    stepEl.classList.remove('text-gray-500');
                }
                
                scanStatus.textContent = data.message; // e.g., "Scan 1 of 3 captured."

                // If not the last step, prompt for the next one
                if (step < 3) {
                    // Give user a moment to see the message, then prompt for next
                    setTimeout(() => {
                        scanStatus.textContent = `Place finger for scan ${step + 1} of 3...`;
                    }, 1000);
                }
            }
            // C# bridge reports success *only* at the very end
            else if (data.status === "success") {
                fingerIcon.classList.remove('pulse');
                scanStatus.textContent = "All scans complete! Saving...";
                
                // Set the hidden form field with the final merged template
                document.getElementById('fingerprintData').value = data.template; 
                
                // Submit the form to the PHP backend
                setTimeout(() => fingerprintForm.submit(), 1500);
            }
            // C# bridge reports an error (e.g., mismatch, timeout)
            else if (data.status === "error") {
                fingerIcon.classList.remove('pulse');
                scanStatus.textContent = `Scan failed: ${data.message || 'Please try again.'}`;
                
                // Show the "Scan Failed" modal
                // We remove the retry count, as the C# bridge aborts the whole process
                document.getElementById('scanFailedAttempts').textContent = "The enrollment process was aborted. Please try again from the beginning.";
                openModal('scanFailedModal');
                
                // Don't re-enable the button here, let the user click "Try Again"
            }
            
        } catch (e) {
            console.error("Error parsing message:", e);
            fingerIcon.classList.remove('pulse');
            scanStatus.textContent = "Received invalid data from device.";
        }
    };

    socket.onerror = () => {
        isDeviceConnected = false;
        deviceStatusContainer.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
        deviceStatusContainer.classList.add('border-red-200', 'bg-red-50', 'text-red-600');
        deviceStatusIcon.className = 'fa fa-times-circle';
        deviceStatusText.textContent = "Device Not Detected";
        scanBtn.disabled = true; 
        openModal('deviceErrorModal'); 
        console.error("WebSocket Error");
    };

    socket.onclose = () => {
        if (isDeviceConnected) { 
            isDeviceConnected = false;
            deviceStatusContainer.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
            deviceStatusContainer.classList.add('border-red-200', 'bg-red-50', 'text-red-600');
            deviceStatusIcon.className = 'fa fa-times-circle';
            deviceStatusText.textContent = "Connection Lost";
            scanBtn.disabled = true;
            console.log("WebSocket Closed");
        }
    };
}

/**
 * Handles the logic for retrying the WebSocket connection.
 */
function retryConnection() {
    closeModal('retryConnectionModal');
    
    deviceStatusContainer.classList.remove('border-red-200', 'bg-red-50', 'text-red-600', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
    deviceStatusContainer.classList.add('border-gray-200', 'bg-gray-50', 'text-gray-600');
    deviceStatusIcon.className = 'fa fa-spinner fa-spin';
    deviceStatusText.textContent = "Connecting to device...";
    scanBtn.disabled = true;

    connectWebSocket(); // Attempt to reconnect
}


// --- Event Listeners ---
document.addEventListener('DOMContentLoaded', () => {
    connectWebSocket(); // Attempt connection on page load

    scanBtn.addEventListener('click', () => {
        if (isDeviceConnected) {
            openModal('scanNoticeModal'); // Show notice first
        } else {
            openModal('deviceErrorModal'); // Show error if disconnected
        }
    });

    // Handle proceeding after notice
    document.getElementById('proceedScanBtn').addEventListener('click', () => {
        closeModal('scanNoticeModal');
        startEnrollment(); // This is the new, single function call
    });
    
    // Handle "Try Again" from scan failure
    document.getElementById('retryScanBtn').addEventListener('click', () => {
        closeModal('scanFailedModal');
        // We don't retry a single step. We just reset the UI
        // so the user can click the main "Scan" button again.
        resetScanUI(); 
    });

    // Handle "OK" from max failures (This modal is now just a regular fail)
    document.getElementById('maxFailuresOkBtn').addEventListener('click', () => {
        window.location.href='complete_registration.php';
    });

    // Handle clicking "OK" on the *initial* error modal
    document.getElementById('deviceErrorOkBtn').addEventListener('click', () => {
        closeModal('deviceErrorModal');
        openModal('retryConnectionModal'); // Open the new retry modal
    });

    // Handle clicking "Retry" on the *new* retry modal
    document.getElementById('retryConnectBtn').addEventListener('click', () => {
        retryConnection(); // Call the retry function
    });
});
</script>

<?php include 'includes/footer.php'; ?>