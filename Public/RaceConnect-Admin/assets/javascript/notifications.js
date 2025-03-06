let notificationsData = [];

document.addEventListener('DOMContentLoaded', () => {
    // Get DOM elements
    const searchInput = document.getElementById('searchInput');
    const filterDropdown = document.getElementById('filterDropdown');
    const notificationsList = document.getElementById('notificationTableBody');
    const selectAll = document.querySelector('.select-all');
    const bulkArchive = document.getElementById('bulkArchive');
    if (Math.random() < 0.1) {
        checkCleanup();
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', toggleSelectAll);
    }

    if (bulkArchive) {
        bulkArchive.addEventListener('click', () => performBulkAction('archive'));
    }

    function toggleSelectAll() {
        const checkboxes = document.querySelectorAll('.notification-check');
        checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
        updateBulkActionButton();
    }

    function updateBulkActionButton() {
        const checkboxes = document.querySelectorAll('.notification-check');
        const selectedCount = document.querySelectorAll('.notification-check:checked').length;
        
        // Update bulk archive button state
        bulkArchive.disabled = selectedCount === 0;
        
        // Update select all checkbox state
        if (selectAll) {
            // Only check the select all if all checkboxes are checked
            selectAll.checked = selectedCount > 0 && selectedCount === checkboxes.length;
            
            // Add indeterminate state when some but not all are selected
            selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
        }
    }

    function performBulkAction(action) {
        const selectedNotifications = Array.from(document.querySelectorAll('.notification-check:checked'))
            .map(checkbox => checkbox.dataset.id);
    
        if (selectedNotifications.length === 0) {
            return Swal.fire({
                title: 'No Notifications Selected',
                text: 'Please select at least one notification to archive.',
                icon: 'warning'
            });
        }

        const style = document.createElement('style');
    style.textContent = `
        .swal2-input-group {
            position: relative;
        }
        .toggle-password-btn {
            position: absolute;
            right: 30px;
            top: 60%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password-btn:hover {
            opacity: 0.8;
        }
    `;
    document.head.appendChild(style);
    
        Swal.fire({
            title: 'Archive Confirmation',
            text: 'Please enter your password to confirm this action',
            html: `
                <div class="swal2-input-group">
                    <input type="password" id="swal-password" class="swal2-input" placeholder="Enter your password">
                    <button type="button" class="toggle-password-btn" onclick="toggleSwalPassword()">
                        <box-icon name='show' color="#374151"></box-icon>
                    </button>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirm Archive',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C',
            showLoaderOnConfirm: true,
            preConfirm: (password) => {
                if (!password) {
                    Swal.showValidationMessage('Password is required');
                    return false;
                }
                
                return fetch('fetch_api.php?action=bulk_archive_notifications', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        notification_ids: selectedNotifications,
                        password: password
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.error || 'Failed to archive notifications');
                    }
                    return result;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message);
                    throw error;
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Archived!',
                    text: `${selectedNotifications.length} notification(s) have been archived.`,
                    icon: 'success'
                }).then(() => {
                    fetchNotifications();
                    if (selectAll) selectAll.checked = false;
                    updateBulkActionButton();
                });
            }
        });
    }

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
                        <strong>${escapeHtml(notification.post_title || notification.title || 'Untitled')}</strong><br>
                        <span class="report-reason">${escapeHtml(notification.report_reason || notification.content || 'No reason provided')}</span>
                    </div>
                </td>
                <td>${new Date(notification.created_at).toLocaleString()}</td>
                <td>
                    <div class="actions">
                        <button class="view-btn" onclick="handleViewPost(${notification.post_id}, ${notification.marketplace_item_id}, '${notification.type}')" title="View Item">
                            <box-icon type='solid' name='show' color="white"></box-icon>
                        </button>
                        <button class="archive-btn" onclick="handleArchiveNotification(${notification.id})" title="Archive Notification">
                            <box-icon type='solid' name='archive-in' color="white"></box-icon>
                        </button>
                    </div>
                </td>
            `;

            const checkbox = row.querySelector('.notification-check');
            if (checkbox) {
                checkbox.addEventListener('change', updateBulkActionButton);
            }

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

    // Update the handleViewPost function
    window.handleViewPost = function(postId, marketplaceItemId, type) {
        // Determine the correct URL based on the notification type
        let redirectUrl;
        if (type === 'marketplace_report') {
            redirectUrl = `index_marketplace.php?item_id=${marketplaceItemId}&highlight=true`;
        } else if (type === 'post_report') {
            redirectUrl = `index_posts.php?post_id=${postId}&highlight=true`;
        }

        if (redirectUrl) {
            window.location.href = redirectUrl;
        }
    };

    window.handleArchiveNotification = function(notificationId) {
        Swal.fire({
            title: 'Archive Notification',
            text: 'Please enter your password to confirm this action',
            html: `
                <div class="swal2-input-group">
                    <input type="password" id="swal-password" class="swal2-input" placeholder="Enter your password">
                    <button type="button" class="toggle-password-btn" onclick="toggleSwalPassword()">
                        <box-icon name='show' color="#374151"></box-icon>
                    </button>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirm Archive',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#B91C1C',
            showLoaderOnConfirm: true,
            preConfirm: (password) => {
                if (!password) {
                    Swal.showValidationMessage('Password is required');
                    return false;
                }
                
                return fetch('fetch_api.php?action=archive_notification', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        notification_id: notificationId,
                        password: password
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.error || 'Failed to archive notification');
                    }
                    return result;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message);
                    throw error;
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Archived!',
                    text: 'The notification has been archived.',
                    icon: 'success'
                }).then(() => {
                    fetchNotifications();
                    if (Math.random() < 0.1){
                        checkCleanup();
                    }
                });
            }
        });
    };

    window.toggleSwalPassword = function() {
        const passwordInput = document.getElementById('swal-password');
        const toggleBtn = document.querySelector('.toggle-password-btn box-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.setAttribute('name', 'hide');
        } else {
            passwordInput.type = 'password';
            toggleBtn.setAttribute('name', 'show');
        }
    };

    function checkCleanup() {
        fetch('fetch_api.php?action=check_cleanup', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.warn('Cleanup check failed:', data.error);
            }
        })
        .catch(error => console.error('Cleanup check error:', error));
    }

    // Initial fetch
    fetchNotifications();
    
    // Refresh every 30 seconds
    const refreshInterval = setInterval(fetchNotifications, 30000);

    // Clean up on page unload
    window.addEventListener('unload', () => {
        clearInterval(refreshInterval);
    });
});