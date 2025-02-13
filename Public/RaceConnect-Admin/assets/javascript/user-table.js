document.addEventListener('DOMContentLoaded', function() {
    fetchUsers();
    const selectAll = document.querySelector('.select-all');
    const searchInput = document.querySelector('.search-input');
    const filterDropdown = document.querySelector('.filter-dropdown');
    const bulkBan = document.getElementById('bulkBan');
    const bulkSuspend = document.getElementById('bulkSuspend');
    const bulkUnban = document.getElementById('bulkUnban');
    let usersData = [];

    // Select All checkbox click handler
    selectAll.addEventListener('click', function() {
        const userChecks = document.querySelectorAll('.user-check');
        if (selectAll.checked) {
            userChecks.forEach(checkbox => checkbox.checked = true);
        } else {
            userChecks.forEach(checkbox => checkbox.checked = false);
        }
        updateSelectedUsers();
    });

    // Search input event listener
    searchInput.addEventListener('input', function() {
        filterAndPopulateTable();
    });

    // Filter dropdown event listener
    filterDropdown.addEventListener('change', function() {
        filterAndPopulateTable();
    });

    // Bulk action buttons click handlers
    bulkBan.addEventListener('click', function() {
        performBulkAction('ban_user.php');
    });

    bulkSuspend.addEventListener('click', function() {
        performBulkAction('suspend_user.php');
    });

    bulkUnban.addEventListener('click', function() {
        performBulkAction('unban_user.php');
    });

    function fetchUsers() {
        fetch('fetch_users.php')
            .then(response => response.json())
            .then(users => {
                usersData = users;
                populateTable(users);
            })
            .catch(error => console.error('Error fetching users:', error));
    }

    function filterAndPopulateTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const filterValue = filterDropdown.value;
        const filteredUsers = usersData.filter(user => {
            const matchesSearch = user.username.toLowerCase().includes(searchTerm);
            const matchesFilter = filterValue === 'all' || user.status.toLowerCase() === filterValue;
            return matchesSearch && matchesFilter;
        });
        populateTable(filteredUsers);
    }

    function populateTable(users) {
        const tbody = document.getElementById('userTableBody');
        let rows = [];
        users.forEach(user => {
            const username = user.username;
            const date = new Date(user.created_at);
            const status = user.status;
            const action = `
                <div class="actions">
                    <div class="dropdown-content">
                        <a href="#" class="actions-item" onclick="unbanUser('${username}')">Unban</a>
                        <a href="#" class="actions-item" onclick="banUser('${username}')">Ban</a>
                        <a href="#" class="actions-item" onclick="suspendUser('${username}')">Suspend</a>
                    </div>
                </div>
            `;
            
            rows.push(`
                <tr>
                    <td><input type="checkbox" class="user-check" aria-label="${username}" onclick="updateSelectAll()"></td>
                    <td>${username}</td>
                    <td>${date.toLocaleDateString('en-US', {month: 'long', day: 'numeric'})}, ${date.getFullYear()}</td>
                    <td class="status-${status.toLowerCase()}">${status}</td>
                    <td>${action}</td>
                </tr>
            `);
        });
        tbody.innerHTML = rows.join('');
    }

    function performBulkAction(url) {
        const selectedUsers = Array.from(document.querySelectorAll('.user-check:checked')).map(checkbox => checkbox.getAttribute('aria-label'));
        if (selectedUsers.length === 0) {
            alert('No users selected');
            return;
        }

        const promises = selectedUsers.map(username => {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `username=${username}`
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    console.log(`User ${username} updated successfully`);
                } else {
                    console.error(`Failed to update user ${username}`);
                }
            })
            .catch(error => console.error(`Error updating user ${username}:`, error));
        });

        Promise.all(promises).then(() => {
            // Uncheck all checkboxes after performing the bulk action
            selectAll.checked = false;
            document.querySelectorAll('.user-check').forEach(checkbox => checkbox.checked = false);

            fetchUsers();
        });
    }

    window.unbanUser = function(username) {
        fetch('unban_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `username=${username}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(`User ${username} unbanned`);
                fetchUsers();
            } else {
                alert(`Failed to unban user ${username}`);
            }
        })
        .catch(error => console.error('Error unbanning user:', error));
    }

    window.banUser = function(username) {
        fetch('ban_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `username=${username}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(`User ${username} banned`);
                fetchUsers();
            } else {
                alert(`Failed to ban user ${username}`);
            }
        })
        .catch(error => console.error('Error banning user:', error));
    }

    window.suspendUser = function(username) {
        fetch('suspend_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `username=${username}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(`User ${username} suspended`);
                fetchUsers();
            } else {
                alert(`Failed to suspend user ${username}`);
            }
        })
        .catch(error => console.error('Error suspending user:', error));
    }

    window.updateSelectAll = function() {
        const userChecks = document.querySelectorAll('.user-check');
        const allChecked = Array.from(userChecks).every(checkbox => checkbox.checked);
        selectAll.checked = allChecked;
        updateSelectedUsers();
    }

    function updateSelectedUsers() {
        const selectedUsers = Array.from(document.querySelectorAll('.user-check:checked')).map(checkbox => checkbox.getAttribute('aria-label'));
        console.log('Selected users:', selectedUsers);
    }
});