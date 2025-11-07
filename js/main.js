document.addEventListener('DOMContentLoaded', function() {
    
    // --- Sidebar Toggle Logic ---
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.getElementById('mainContent');
    const dashboardContainer = document.getElementById('dashboardContainer');

    if (sidebarToggle && sidebar && dashboardContainer) {
        // Function to set sidebar state
        const setSidebarState = (isCollapsed) => {
            sidebar.classList.toggle('collapsed', isCollapsed);
            dashboardContainer.classList.toggle('sidebar-collapsed', isCollapsed);
            localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
        };

        // Check local storage for saved state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        setSidebarState(isCollapsed);

        // Add click event
        sidebarToggle.addEventListener('click', () => {
            const wasCollapsed = sidebar.classList.contains('collapsed');
            setSidebarState(!wasCollapsed);
        });
    }

    // --- User Settings Menu Toggle Logic ---
    const userSettingsBtn = document.getElementById('userSettingsBtn');
    const settingsMenu = document.getElementById('settings-menu');

    if (userSettingsBtn && settingsMenu) {
        userSettingsBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent click from closing menu immediately
            settingsMenu.classList.toggle('active');
        });
    }

    // Close settings menu if clicking outside
    document.addEventListener('click', (e) => {
        if (settingsMenu && settingsMenu.classList.contains('active') && 
            !settingsMenu.contains(e.target) && !userSettingsBtn.contains(e.target)) {
            settingsMenu.classList.remove('active');
        }
    });

    // --- Logout Modal Logic ---
    // Note: The global openModal/closeModal functions are now in header.php
    window.showLogoutConfirm = function() {
        if (typeof openModal === 'function') {
            openModal('logoutConfirmModal');
        } else {
            console.error('openModal function is not defined.');
        }
    }

    // --- Live Time and Date Logic ---
    const liveTimeEl = document.getElementById('live-time');
    const liveDateEl = document.getElementById('live-date');

    function updateTime() {
        if (liveTimeEl && liveDateEl) {
            const now = new Date();
            liveTimeEl.textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            liveDateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }
    }
    updateTime(); // Run once immediately
    setInterval(updateTime, 1000 * 30); // Update every 30 seconds

    // --- Scanner Status WebSocket Logic (for Admin Dashboard) ---
    const scannerStatusWidget = document.getElementById('scanner-status-widget');
    
    if (scannerStatusWidget) {
        const statusText = scannerStatusWidget.querySelector('.scanner-status-text-sub');
        const statusBadge = scannerStatusWidget.querySelector('.scanner-status-badge');
        const statusAction = scannerStatusWidget.querySelector('.scanner-status-action');
        const iconBadge = scannerStatusWidget.querySelector('.scanner-icon-badge');

        const setScannerStatus = (isConnected, message) => {
            if (isConnected) {
                scannerStatusWidget.classList.add('online');
                scannerStatusWidget.classList.remove('offline');
                statusText.textContent = 'Device Connected';
                statusBadge.textContent = 'ONLINE';
                statusAction.textContent = 'Ready to scan';
                iconBadge.style.display = 'none';
            } else {
                scannerStatusWidget.classList.remove('online');
                scannerStatusWidget.classList.add('offline');
                statusText.textContent = message;
                statusBadge.textContent = 'OFFLINE';
                statusAction.textContent = 'Check connection';
                iconBadge.style.display = 'flex';
            }
        };

        function connectScannerSocket() {
            try {
                const socket = new WebSocket("ws://127.0.0.1:8080");

                socket.onopen = () => {
                    console.log("Scanner WebSocket connected.");
                    setScannerStatus(true, "Device Connected");
                };

                socket.onclose = () => {
                    console.log("Scanner WebSocket disconnected.");
                    setScannerStatus(false, "Device Not Detected");
                    // Try to reconnect after 5 seconds
                    setTimeout(connectScannerSocket, 5000);
                };

                socket.onerror = (err) => {
                    console.error("Scanner WebSocket error:", err);
                    setScannerStatus(false, "Connection Error");
                    socket.close(); // Triggers onclose and reconnect
                };

            } catch (err) {
                console.error("Failed to initialize WebSocket:", err);
                setScannerStatus(false, "Service Not Running");
                setTimeout(connectScannerSocket, 5000);
            }
        }
        
        connectScannerSocket();
    }

});

// --- REMOVED ---
// The old openModal() and closeModal() functions were here.
// They are now correctly defined in includes/header.php to prevent conflicts.