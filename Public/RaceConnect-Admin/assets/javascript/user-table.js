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
    document.getElementById('bulkBan').addEventListener('click', function() {
        performBulkAction('ban_user.php', 'ban');
    });
    
    document.getElementById('bulkSuspend').addEventListener('click', function() {
        Swal.fire({
          title: 'Enter Suspension Duration',
          input: 'number',
          inputLabel: 'Number of days to suspend',
          inputAttributes: {
              min: 1,
              step: 1
          },
          showCancelButton: true,
          confirmButtonText: 'Suspend',
          cancelButtonText: 'Cancel'
      }).then((result) => {
          if (result.isConfirmed && result.value > 0) {
              const days = result.value;
              performBulkActionWithDays('suspend_user.php', 'suspend', days);
          }
      });
    });
    
    document.getElementById('bulkUnban').addEventListener('click', function() {
        performBulkAction('unban_user.php', 'unban');
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
          const suspensionDays = user.suspension_days ? `(${user.suspension_days} days)` : '';
      
          // Create a 3-dot button that toggles a dropdown menu
          const action = `
            <div class="actions">
              <button class="dropdown-btn" onclick="toggleDropdown(event)">&#8942;</button>
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
              <td>${date.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })}, ${date.getFullYear()}</td>
              <td class="status-${status.toLowerCase()}">${status} ${suspensionDays}</td>
              <td>${action}</td>
            </tr>
          `);
        });
        tbody.innerHTML = rows.join('');
      }
      
      // Function to toggle the dropdown menu
      function toggleDropdown(event) {
        event.stopPropagation();
        const dropdown = event.currentTarget.nextElementSibling;
        
        // Optionally close other open dropdowns
        document.querySelectorAll('.dropdown-content.show').forEach(content => {
          if (content !== dropdown) {
            content.classList.remove('show');
          }
        });
        
        dropdown.classList.toggle('show');
      }
      
      // Close dropdown if clicking outside
      document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-content.show').forEach(content => {
          content.classList.remove('show');
        });
      });
      

      function performBulkAction(url, actionType) {
        const selectedUsers = Array.from(document.querySelectorAll('.user-check:checked'))
            .map(checkbox => checkbox.getAttribute('aria-label'));
    
        if (selectedUsers.length === 0) {
            Swal.fire({
                title: 'No users selected',
                text: 'Please select at least one user.',
                icon: 'warning',
                confirmButtonText: 'Ok',
                confirmButtonColor: '#B91C1C',
            });
            return;
        }
    
        Swal.fire({
            title: `Are you sure?`,
            text: `You are about to ${actionType} ${selectedUsers.length} user(s).`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: `Yes`,
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.isConfirmed) {
                const promises = selectedUsers.map(username => {
                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `username=${encodeURIComponent(username)}`
                    })
                    .then(response => response.json());
                });
    
                Promise.all(promises).then(results => {
                    const allSuccess = results.every(res => res.success);
    
                    if (allSuccess) {
                        Swal.fire('Success!', `${actionType} completed successfully.`, 'success')
                            .then(() => fetchUsers()); // Refresh the user list
                    } else {
                        Swal.fire('Error!', `Some users could not be ${actionType}ed.`, 'error');
                    }
                }).catch(error => {
                    console.error(`Error during ${actionType}:`, error);
                    Swal.fire('Error!', 'An error occurred. Please try again.', 'error');
                });
            }
        });
    }
    
    
    function banUser(username) {
        Swal.fire({
          title: 'Are you sure?',
          text: `Do you really want to ban: ${username}?`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes',
          cancelButtonText: 'No'
        }).then((result) => {
          if (result.isConfirmed) {
            // Proceed with the ban operation if confirmed
            fetch('ban_user.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: `username=${encodeURIComponent(username)}`
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Banned!', `${username} has been banned.`, 'success')
                .then(() => {
                    fetchUsers();
                });
              } else {
                Swal.fire('Error!', `There was an issue banning ${username}.`, 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error!', 'An error occurred.', 'error');
            });
          }
        });
    }

    function unbanUser(username) {
        Swal.fire({
          title: 'Are you sure?',
          text: `Do you really want to unban: ${username}?`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes',
          cancelButtonText: 'No'
        }).then((result) => {
          if (result.isConfirmed) {
            // Proceed with the ban operation if confirmed
            fetch('unban_user.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: `username=${encodeURIComponent(username)}`
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Unbanned!', `${username} has been unbanned.`, 'success')
                .then(() => {
                    fetchUsers();
                });
              } else {
                Swal.fire('Error!', `There was an issue unbanning ${username}.`, 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error!', 'An error occurred.', 'error');
            });
          }
        });
    }

      function suspendUser(username) {
        Swal.fire({
          title: 'Are you sure?',
          text: `Do you really want to suspend: ${username}?`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes',
          cancelButtonText: 'No'
        }).then((result) => {
          if (result.isConfirmed) {
            // Proceed with the ban operation if confirmed
            fetch('suspend_user.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: `username=${encodeURIComponent(username)}`
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Banned!', `${username} has been suspended.`, 'success')
                .then(() => {
                    fetchUsers();
                });
              } else {
                Swal.fire('Error!', `There was an issue suspending ${username}.`, 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error!', 'An error occurred.', 'error');
            });
          }
        });
    }

    window.updateSelectAll = function() {
        const userChecks = document.querySelectorAll('.user-check');
        const allChecked = Array.from(userChecks).every(checkbox => checkbox.checked);
        selectAll.checked = allChecked;
        updateSelectedUsers();
    }

    window.banUser = banUser;
    window.unbanUser = unbanUser;
    window.suspendUser = suspendUser;
    window.toggleDropdown = toggleDropdown;

    function updateSelectedUsers() {
        const selectedUsers = Array.from(document.querySelectorAll('.user-check:checked')).map(checkbox => checkbox.getAttribute('aria-label'));
        console.log('Selected users:', selectedUsers);
    }
});