document.addEventListener('DOMContentLoaded', function() {
    
    /*toggle loginc for the sidebar*/
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.getElementById('mainContent');
    const dashboardContainer = document.getElementById('dashboardContainer');

    if (sidebarToggle && sidebar && dashboardContainer) {
        /*state of the sidebar, for example if its collapsed or not*/
        const setSidebarState = (isCollapsed) => {
            sidebar.classList.toggle('collapsed', isCollapsed);
            dashboardContainer.classList.toggle('sidebar-collapsed', isCollapsed);
            localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
        };

        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        setSidebarState(isCollapsed);

        sidebarToggle.addEventListener('click', () => {
            const wasCollapsed = sidebar.classList.contains('collapsed');
            setSidebarState(!wasCollapsed);
        });
    }
    /*toggle for the user settings menu*/
    const userSettingsBtn = document.getElementById('userSettingsBtn');
    const settingsMenu = document.getElementById('settings-menu');

    if (userSettingsBtn && settingsMenu) {
        userSettingsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            settingsMenu.classList.toggle('active');
        });
    }
    /*event for settings menu to close if you click outside of the area*/
    document.addEventListener('click', (e) => {
        if (settingsMenu && settingsMenu.classList.contains('active') && 
            !settingsMenu.contains(e.target) && !userSettingsBtn.contains(e.target)) {
            settingsMenu.classList.remove('active');
        }
    });

    window.showLogoutConfirm = function() {
        if (typeof openModal === 'function') {
            openModal('logoutConfirmModal');
        } else {
            console.error('openModal function is not defined.');
        }
    }

    /*date and time display*/
    const liveTimeEl = document.getElementById('live-time');
    const liveDateEl = document.getElementById('live-date');
    function updateTime() {
        if (liveTimeEl && liveDateEl) {
            const now = new Date();
            liveTimeEl.textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            liveDateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }
    }
    updateTime();
    setInterval(updateTime, 1000 * 30);

    /*Connection display in the dashboard, detects if scanner is connected or not*/
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

        /*ZKTeco fingerprint scanner device connection and websocket logic,
        this block detects the connection status to an external bridge app to function.*/
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
                    setTimeout(connectScannerSocket, 5000);
                };

                socket.onerror = (err) => {
                    console.error("Scanner WebSocket error:", err);
                    setScannerStatus(false, "Connection Error");
                    socket.close();
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