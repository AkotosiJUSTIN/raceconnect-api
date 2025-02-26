let notificationsData = [];

document.addEventListener('DOMContentLoaded', () => {
    // Get DOM elements
    const searchInput = document.getElementById('searchInput');
    const filterDropdown = document.getElementById('filterDropdown');
    const notificationsList = document.getElementById('notificationTableBody');

    // Add event listeners
    if (searchInput && filterDropdown) {
        searchInput.addEventListener('input', filterAndPopulateTable);
        filterDropdown.addEventListener('change', filterAndPopulateTable);
    }

    function fetchNotifications() {
        
        fetch('fetch_api.php?action=fetch_notifications', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            return response.text(); // Change to text() first to debug
        })
        .then(text => {
            const result = JSON.parse(text);
            
            if (result.success) {
                notificationsData = result.data || [];
                if (Array.isArray(notificationsData)) {
                    filterAndPopulateTable();
                } else {
                    throw new Error('Invalid notifications data format');
                }
            } else {
                throw new Error(result.error || 'Failed to fetch notifications');
            }
        })
        .catch(error => {
            handleError(error);
        });
    }

    function filterAndPopulateTable() {
        if (!searchInput || !filterDropdown) return;

        const searchTerm = searchInput.value.toLowerCase();
        const filterValue = filterDropdown.value;

        const filteredNotifications = notificationsData.filter(notification => {
            const matchesSearch = 
                (notification.reporter_username || '').toLowerCase().includes(searchTerm) ||
                (notification.post_title || '').toLowerCase().includes(searchTerm) ||
                (notification.report_reason || '').toLowerCase().includes(searchTerm);
            
            const matchesFilter = filterValue === 'all' || 
                                (filterValue === 'unread' && !notification.is_read) ||
                                (filterValue === 'read' && notification.is_read);

            return matchesSearch && matchesFilter;
        });

        populateNotifications(filteredNotifications);
    }

    function populateNotifications(notifications) {
        console.log('Populating notifications:', notifications); // Debug log

        const notificationsList = document.getElementById('notificationTableBody');
        if (!notificationsList) {
            console.error('Notification table body not found');
            return;
        }

        notificationsList.innerHTML = '';

        if (!notifications || notifications.length === 0) {
            notificationsList.innerHTML = `
                <tr>
                    <td colspan="5" class="no-data">No notifications found</td>
                </tr>`;
            return;
        }

        notifications.forEach(notification => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <input type="checkbox" class="notification-check" data-id="${notification.id}">
                </td>
                <td>${escapeHtml(notification.reporter_username || 'Unknown')}</td>
                <td>
                    <div class="notification-content">
                        <strong>${escapeHtml(notification.post_title || 'Untitled Post')}</strong><br>
                        <span class="report-reason">${escapeHtml(notification.report_reason || notification.content || 'No reason provided')}</span>
                    </div>
                </td>
                <td>${new Date(notification.created_at).toLocaleString()}</td>
                <td>
                    <div class="actions">
                        ${notification.post_id ? `
                            <button class="view-btn" onclick="handleViewPost(${notification.post_id})" title="View Post">
                                <box-icon type='solid' name='show' color="white"></box-icon>
                            </button>
                        ` : ''}
                        <button class="archive-btn" onclick="handleArchiveNotification(${notification.id})" title="Archive Notification">
                            <box-icon type='solid' name='archive-in' color="white"></box-icon>
                        </button>
                    </div>
                </td>
            `;
            notificationsList.appendChild(row);
        });
    }

    function handleError(error) {
        console.error('Error:', error);
        if (notificationsList) {
            notificationsList.innerHTML = `
                <tr>
                    <td colspan="5" class="error-message">
                        Failed to load notifications. Please try again later.<br>
                        Error: ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Define global handlers
    window.handleViewPost = function(postId) {
        window.location.href = `index_posts.php?post_id=${postId}`;
    };

    window.handleArchiveNotification = function(notificationId) {
        fetch('fetch_api.php?action=archive_notification', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                fetchNotifications();
            } else {
                throw new Error(result.error || 'Failed to archive notification');
            }
        })
        .catch(error => handleError(error));
    };

    // Initial fetch
    fetchNotifications();
    
    // Refresh every 30 seconds
    const refreshInterval = setInterval(fetchNotifications, 30000);

    // Clean up on page unload
    window.addEventListener('unload', () => {
        clearInterval(refreshInterval);
    });
});