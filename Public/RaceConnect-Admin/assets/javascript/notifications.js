document.addEventListener('DOMContentLoaded', () => {
    fetchNotifications();
    const selectAll = document.querySelector('.select-all');
    const searchInput = document.querySelector('.search-input');
    const filterDropdown = document.querySelector('.filter-dropdown');
    let notificationsData = [];

    // Select All checkbox click handler
    selectAll.addEventListener('click', function() {
        const notificationChecks = document.querySelectorAll('.notification-check');
        if (selectAll.checked) {
            notificationChecks.forEach(checkbox => checkbox.checked = true);
        } else {
            notificationChecks.forEach(checkbox => checkbox.checked = false);
        }
    });

    // Search input event listener
    searchInput.addEventListener('input', function() {
        filterAndPopulateTable();
    });

    // Filter dropdown event listener
    filterDropdown.addEventListener('change', function() {
        filterAndPopulateTable();
    });

    // Fetch notifications from the server every 30 secs
    setInterval(fetchNotifications, 30000);

    function fetchNotifications() {
        fetch('fetch_notifications.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(notifications => {
                console.log('Fetched notifications:', notifications); // Debugging statement
                notificationsData = notifications;
                filterAndPopulateTable();
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }

    function filterAndPopulateTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const filterValue = filterDropdown.value;
    
        const filteredNotifications = notificationsData.filter(notification => {
            // Ensure `notification.notification` exists and is a string
            const notificationText = notification.notification && typeof notification.notification === 'string'
            ? notification.notification.toLowerCase()
            : '';
    
            const matchesSearch =
                (notification.notification && notification.notification.toLowerCase().includes(searchTerm)) ||
                (notification.user_id && notification.user_id.toString().toLowerCase().includes(searchTerm)) ||
                (notification.created_at && notification.created_at.toLowerCase().includes(searchTerm));
    
            // Ensure `notification.status` exists and is a string
            const status = notification.status && typeof notification.status === 'string'
                ? notification.status.toLowerCase()
                : '';
            const matchesFilter = filterValue === 'all' || status === filterValue;
    
            return matchesSearch && matchesFilter;
        });
    
        populateNotifications(filteredNotifications);
    }

    function populateNotifications(notifications) {
        const notificationsList = document.getElementById('notificationTableBody');
        notificationsList.innerHTML = ''; // Clear the current notifications

        notifications.forEach(notification => {
            console.log('Notification data:', notification); // Debugging statement

            const notificationRow = document.createElement('tr');

            notificationRow.innerHTML = `
                <td><input type="checkbox" class="notification-check" aria-label="${notification.id}"></td>
                <td>${notification.user_id}</td>
                <td>${notification.notification}</td>
                <td>${new Date(notification.created_at).toLocaleString()}</td>
                <td>
                    <div class="actions">
                        <button class="actions-item edit-btn" onclick="editNotification('${notification.id}')">
                            <box-icon name='edit' color="white"></box-icon>
                        </button>
                        <button class="actions-item hide-btn" onclick="hideNotification('${notification.id}')">
                            <box-icon type='solid' name='low-vision' color="white"></box-icon>
                        </button>
                        <button class="actions-item delete-btn" onclick="deleteNotification('${notification.id}')">
                            <box-icon type='solid' name='trash' color="white"></box-icon>
                        </button>
                    </div>
                </td>
            `;

            notificationsList.appendChild(notificationRow);
        });
    }

    window.editNotification = function(id) {
        alert(`Edit notification ${id}`);
    }

    window.hideNotification = function(id) {
        alert(`Hide notification ${id}`);
    }

    window.deleteNotification = function(id) {
        alert(`Delete notification ${id}`);
    }
});