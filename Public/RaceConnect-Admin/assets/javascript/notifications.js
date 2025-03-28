let notificationsData = [];

document.addEventListener('DOMContentLoaded', () => {
    // Get DOM elements
    const searchInput = document.getElementById('searchInput');
    const readStatusFilter = document.getElementById('readStatusFilter');
    const notificationTypeFilter = document.getElementById('notificationTypeFilter');
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
    
        Swal.fire({
            title: 'Archive Notifications',
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
            preConfirm: async () => {
                try {
                    const password = document.getElementById('swal-password').value;
                    if (!password) {
                        Swal.showValidationMessage('Password is required');
                        return false;
                    }
                    
                    const response = await fetch('fetch_api.php?action=bulk_archive_notifications', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            notification_ids: selectedNotifications,
                            password: password
                        })
                    });

                    const result = await response.json();
                    if (!result.success) {
                        Swal.showValidationMessage(result.error || 'Failed to archive notifications');
                        return false;
                    }
                    return result;
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                    return false;
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Success',
                    text: `${selectedNotifications.length} notification(s) have been archived`,
                    icon: 'success',
                    timer: 1500
                }).then(() => {
                    fetchNotifications();
                    if (selectAll) selectAll.checked = false;
                    updateBulkActionButton();
                    if (Math.random() < 0.1) {
                        checkCleanup();
                    }
                });
            }
        });
    }

    // Add event listeners
    if (searchInput && readStatusFilter && notificationTypeFilter) {
        searchInput.addEventListener('input', filterAndPopulateTable);
        readStatusFilter.addEventListener('change', filterAndPopulateTable);
        notificationTypeFilter.addEventListener('change', filterAndPopulateTable);
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
        if (!searchInput || !readStatusFilter || !notificationTypeFilter) return;

        const searchTerm = searchInput.value.toLowerCase();
        const readStatus = readStatusFilter.value;
        const notificationType = notificationTypeFilter.value;

        const filteredNotifications = notificationsData.filter(notification => {
            // Check if the notification matches the search term
            const matchesSearch = 
                (notification.reporter_username || '').toLowerCase().includes(searchTerm) ||
                (notification.post_title || '').toLowerCase().includes(searchTerm) ||
                (notification.report_reason || '').toLowerCase().includes(searchTerm);
        
            // Check if the notification matches the read status filter
            const isRead = notification.is_read === 1 || notification.is_read === true;
            const matchesReadStatus = readStatus === 'all' || 
                                    (readStatus === 'unread' && !isRead) ||
                                    (readStatus === 'read' && isRead);

            // Check if the notification matches the type filter
            const isAppeal = notification.type === 'appeal_submission';
            const matchesType = notificationType === 'all' || 
                              (notificationType === 'appeal' && isAppeal) ||
                              (notificationType === 'report' && !isAppeal);

            return matchesSearch && matchesReadStatus && matchesType;
        });

        populateNotifications(filteredNotifications);
    }

    function populateNotifications(notifications) {
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
            row.classList.add(notification.is_read === 1 ? 'read' : 'unread');
            
            row.innerHTML = `
            <td>
                <input type="checkbox" class="notification-check" data-id="${notification.id}">
            </td>
            <td>${escapeHtml(notification.reporter_username || 'Unknown')}</td>
            <td>
                <div class="notification-content ${notification.is_read === 1 ? 'read' : 'unread'}">
                    <strong>${escapeHtml(notification.post_title || notification.title || 'Pending Appeal for Review')}</strong><br>
                    <span class="report-reason">${escapeHtml(notification.report_reason || notification.content || 'No reason provided')}</span>
                </div>
            </td>
            <td>${new Date(notification.created_at).toLocaleString()}</td>
            <td>
                <div class="actions">
                    <button class="view-btn" 
                        onclick="handleViewPost(
                            ${notification.post_id || 'null'}, 
                            ${notification.marketplace_item_id || 'null'}, 
                            '${notification.type}', 
                            ${notification.id}
                        )" 
                        data-type="${notification.type}"
                        data-appeal-id="${notification.appeal_id || ''}"
                        title="View Item">
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

    window.handleViewPost = async function(postId, marketplaceItemId, type, notificationId) {
        try {
            console.log("Handling notification:", { type, postId, marketplaceItemId, notificationId });
    
            // Mark notification as read
            const response = await fetch('fetch_api.php?action=mark_notification_read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            });
    
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
    
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Failed to update notification');
            }
    
            // Get notification data
            const notification = notificationsData.find(n => n.id === notificationId);
            
            // Handle redirect based on notification type and data
            let redirectUrl;
            if (type === 'appeal_submission') {
                if (postId && postId !== 'null') {
                    redirectUrl = `index_posts.php?post_id=${postId}&highlight=true&appeal=true`;
                } else if (marketplaceItemId && marketplaceItemId !== 'null') {
                    redirectUrl = `index_marketplace.php?item_id=${marketplaceItemId}&highlight=true&appeal=true`;
                } else if (notification && notification.reporter_username) {
                    redirectUrl = `index_users.php?username=${notification.reporter_username}&appeal=true`;
                }
            } else if (type === 'marketplace_report' && marketplaceItemId && marketplaceItemId !== 'null') {
                redirectUrl = `index_marketplace.php?item_id=${marketplaceItemId}&highlight=true`;
            } else if (type === 'post_report' && postId && postId !== 'null') {
                redirectUrl = `index_posts.php?post_id=${postId}&highlight=true`;
            }
    
            // Update local data after determining redirect
            if (notification) {
                notification.is_read = 1;
                filterAndPopulateTable();
            }
    
            if (redirectUrl) {
                console.log("Redirecting to:", redirectUrl);
                window.location.href = redirectUrl;
            } else {
                console.warn('No valid redirect URL could be determined', { 
                    notification, 
                    type, 
                    postId, 
                    marketplaceItemId,
                    notificationData: notificationsData 
                });
                Swal.fire({
                    title: 'Error',
                    text: 'Could not determine where to redirect for this notification',
                    icon: 'warning'
                });
            }
    
        } catch (error) {
            console.error("Error details:", error);
            Swal.fire({
                title: 'Error',
                text: `Failed to update notification: ${error.message}`,
                icon: 'error'
            });
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
            preConfirm: async () => {
                try {
                    const password = document.getElementById('swal-password').value;
                    if (!password) {
                        Swal.showValidationMessage('Password is required');
                        return false;
                    }
                    
                    const response = await fetch('fetch_api.php?action=archive_notification', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            notification_id: notificationId,
                            password: password
                        })
                    });

                    const result = await response.json();
                    if (!result.success) {
                        Swal.showValidationMessage(result.error || 'Failed to archive notification');
                        return false;
                    }
                    return result;
                } catch (error) {
                    Swal.showValidationMessage(error.message);
                    return false;
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Success',
                    text: 'Notification has been archived',
                    icon: 'success',
                    timer: 1500
                }).then(() => {
                    fetchNotifications();
                    if (Math.random() < 0.1) {
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