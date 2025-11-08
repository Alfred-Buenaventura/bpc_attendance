<?php
require_once 'config.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPC Attendance Display</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/display.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="display-body">

    <div class="display-container">
        
        <div class="default-state" id="defaultState">
            <div class="logo-icon">
                <i class="fa-solid fa-fingerprint"></i>
            </div>
            <h1>Welcome to BPC</h1>
            <p>Please scan your fingerprint</p>
        </div>

        <div class="scan-card" id="scanCard">
            <div class="icon-badge" id="scanIcon">
                </div>
            <div class="user-name" id="scanName">---</div>
            <div class="scan-status" id="scanStatus">---</div>
            <div class="time-date">
                <span id="scanTime">--:-- --</span> | <span id="scanDate">---</span>
            </div>
        </div>

    </div>

    <script>
        const scanCard = document.getElementById('scanCard');
        const scanIcon = document.getElementById('scanIcon');
        const scanName = document.getElementById('scanName');
        const scanStatus = document.getElementById('scanStatus');
        const scanTime = document.getElementById('scanTime');
        const scanDate = document.getElementById('scanDate');
        const defaultStateP = document.getElementById('defaultState').querySelector('p');
        
        let hideCardTimer; // Timer to auto-hide the card

        // --- This is the main function to show the card ---
        function showScanEvent(data) {
            clearTimeout(hideCardTimer);

            scanName.textContent = data.name;
            scanStatus.textContent = data.status;
            scanTime.textContent = data.time;
            scanDate.textContent = data.date;

            // Style the card based on status
            if (data.status.toLowerCase().includes('time in')) {
                scanIcon.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i>';
                scanIcon.className = 'icon-badge time-in';
                scanStatus.className = 'scan-status time-in';
            } else if (data.status.toLowerCase().includes('time out')) {
                scanIcon.innerHTML = '<i class="fa-solid fa-arrow-right-from-bracket"></i>';
                scanIcon.className = 'icon-badge time-out';
                scanStatus.className = 'scan-status time-out';
            } else {
                // Handle error/fail status
                scanIcon.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
                scanIcon.className = 'icon-badge error';
                scanStatus.className = 'scan-status error';
            }

            scanCard.classList.add('show');

            // Set a timer to hide the card
            hideCardTimer = setTimeout(() => {
                scanCard.classList.remove('show');
            }, 7000); // 7 seconds
        }

        // --- NEW: Function to call the backend API ---
        function recordAttendance(userId) {
            console.log("Sending user ID to backend:", userId);
            fetch("api/record_attendance.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // data.data contains the {name, status, time, date} object
                    console.log("Backend response:", data.data);
                    showScanEvent(data.data);
                } else {
                    // Show an error on the card if the API fails
                    console.error("Failed to record attendance:", data.message);
                    showScanEvent({
                        name: "API Error",
                        status: "Contact Admin",
                        time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                        date: new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
                    });
                }
            })
            .catch(err => {
                console.error("AJAX error:", err);
                showScanEvent({
                    name: "Network Error",
                    status: "Check Connection",
                    time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                    date: new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
                });
            });
        }

        // --- WebSocket Connection ---
        function connectWebSocket() {
            const socket = new WebSocket("ws://127.0.0.1:8080/");

            socket.onopen = () => {
                console.log("Display connected. Requesting verification start...");
                defaultStateP.textContent = "Connecting to scanner...";
                // Tell the bridge app to start verification mode
                socket.send(JSON.stringify({ command: "verify_start" }));
            };

            socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    // --- NEW: Listen for verification_success ---
                    if (data.type === "verification_success") {
                        console.log("Verification success received, User ID:", data.user_id);
                        // Send the User ID to our PHP backend to be processed
                        recordAttendance(data.user_id);
                    }
                    // --- NEW: Listen for verification_fail ---
                    else if (data.type === "verification_fail") {
                        console.warn("Verification failed:", data.message);
                        // Show a "Scan Failed" card
                        showScanEvent({
                            name: "Scan Failed",
                            status: "Finger not recognized",
                            time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                            date: new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
                        });
                    }
                    // Listen for status messages from the bridge
                    else if (data.status === "info") {
                        console.log("Bridge info:", data.message);
                        if (data.message.includes("Verification active")) {
                            defaultStateP.textContent = "Please scan your fingerprint";
                        }
                    }
                    // Listen for error messages from the bridge
                    else if (data.status === "error") {
                        console.error("Bridge error:", data.message);
                        defaultStateP.textContent = "Scanner Error: " + data.message;
                    }

                } catch (e) {
                    console.error("Error parsing message:", e);
                }
            };

            socket.onerror = (err) => {
                console.error("WebSocket error. Check if ZKTecoBridge.exe is running.");
                defaultStateP.textContent = "Scanner service disconnected";
            };

            socket.onclose = () => {
                console.log("WebSocket closed. Reconnecting in 5 seconds...");
                defaultStateP.textContent = "Connection lost. Retrying...";
                setTimeout(connectWebSocket, 5000);
            };
        }

        // --- Start the connection when the page loads ---
        document.addEventListener('DOMContentLoaded', connectWebSocket);

        // --- REMOVED: The test click event is no longer needed ---

    </script>
</body>
</html>