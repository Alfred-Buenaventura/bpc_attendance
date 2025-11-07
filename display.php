<?php
// We can include config if we need functions, but for this, it's mostly HTML/JS
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
<body>

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
        
        let hideCardTimer; // Timer to auto-hide the card

        // --- This is the main function ---
        function showScanEvent(data) {
            // 1. Clear any existing timer
            clearTimeout(hideCardTimer);

            // 2. Populate the card with new data
            scanName.textContent = data.name;
            scanStatus.textContent = data.status; // "Time In" or "Time Out"
            scanTime.textContent = data.time;
            scanDate.textContent = data.date;

            // 3. Style the card based on status
            if (data.status.toLowerCase().includes('time in')) {
                scanIcon.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i>';
                scanIcon.className = 'icon-badge time-in';
                scanStatus.className = 'scan-status time-in';
            } else {
                scanIcon.innerHTML = '<i class="fa-solid fa-arrow-right-from-bracket"></i>';
                scanIcon.className = 'icon-badge time-out';
                scanStatus.className = 'scan-status time-out';
            }

            // 4. Show the card
            scanCard.classList.add('show');

            // 5. Set a timer to hide the card after 10 seconds
            hideCardTimer = setTimeout(() => {
                scanCard.classList.remove('show');
            }, 10000); // 10 seconds
        }

        // --- WebSocket Connection ---
        function connectWebSocket() {
            const socket = new WebSocket("ws://127.0.0.1:8080");

            socket.onopen = () => {
                console.log("Display connected to WebSocket service.");
                // You could change the "Ready" text here
                document.getElementById('defaultState').querySelector('p').textContent = "Please scan your fingerprint";
            };

            socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    // Listen for your new "attendance" message type
                    if (data.type === "attendance") {
                        console.log("Attendance event received:", data);
                        showScanEvent(data);
                    }
                    
                    // You can also listen for other events, e.g., errors
                    if (data.status === "error") {
                        console.warn("Scan error:", data.message);
                        // Optionally show a "Scan Failed, Try Again" card
                    }

                } catch (e) {
                    console.error("Error parsing message:", e);
                }
            };

            socket.onerror = () => {
                console.error("WebSocket error. Check if scanner service is running.");
                document.getElementById('defaultState').querySelector('p').textContent = "Scanner service disconnected";
            };

            socket.onclose = () => {
                console.log("WebSocket closed. Reconnecting in 5 seconds...");
                document.getElementById('defaultState').querySelector('p').textContent = "Connection lost. Retrying...";
                // Automatically try to reconnect
                setTimeout(connectWebSocket, 5000);
            };
        }

        // --- Start the connection when the page loads ---
        document.addEventListener('DOMContentLoaded', connectWebSocket);

        // --- FOR TESTING: Click anywhere to simulate a scan ---
        document.body.addEventListener('click', () => {
            console.log("Simulating test scan...");
            const testData = {
                type: "attendance",
                name: "Juan Dela Cruz",
                status: Math.random() > 0.5 ? "Time In" : "Time Out",
                time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                date: new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })
            };
            showScanEvent(testData);
        });

    </script>
</body>
</html>