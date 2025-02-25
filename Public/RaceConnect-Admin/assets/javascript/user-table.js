document.addEventListener("DOMContentLoaded", function () {
  fetchUsers();

  // Get elements
  const selectAll = document.querySelector(".select-all");
  const searchInput = document.querySelector(".search-input");
  const filterDropdown = document.querySelector(".filter-dropdown");
  const bulkBan = document.getElementById("bulkBan");
  const bulkUnban = document.getElementById("bulkUnban");
  let usersData = [];

  // Event Listeners
  selectAll.addEventListener("click", toggleSelectAll);
  searchInput.addEventListener("input", filterAndPopulateTable);
  filterDropdown.addEventListener("change", filterAndPopulateTable);
  bulkBan.addEventListener("click", () =>
    performBulkAction("fetch_api.php?action=ban_user", "ban")
  );
  bulkUnban.addEventListener("click", () =>
    performBulkAction("fetch_api.php?action=unban_user", "unban")
  );

  // Fetch user data from the server
  function fetchUsers() {
    fetch("fetch_api.php?action=fetch_users")
      .then((response) => response.json())
      .then((users) => {
        usersData = users;
        populateTable(users);
      })
      .catch((error) => console.error("Error fetching users:", error));
  }

  // Toggle selection of all checkboxes
  function toggleSelectAll() {
    const userChecks = document.querySelectorAll(".user-check");
    userChecks.forEach((checkbox) => (checkbox.checked = selectAll.checked));
    updateSelectedUsers();
  }

  // Filter users based on search input and status filter
  function filterAndPopulateTable() {
    const searchTerm = searchInput.value.toLowerCase();
    const filterValue = filterDropdown.value;

    const filteredUsers = usersData.filter((user) => {
      const matchesSearch = user.username.toLowerCase().includes(searchTerm);
      const matchesFilter =
        filterValue === "all" || user.status.toLowerCase() === filterValue;
      return matchesSearch && matchesFilter;
    });

    populateTable(filteredUsers);
  }

  function populateTable(users) {
    const tbody = document.getElementById("userTableBody");
    let rows = [];

    users.forEach((user) => {
      const username = user.username;
      const date = new Date(user.created_at);
      const status = user.status || "Active";

      // Display suspension days if user is banned
      let suspensionText = "";
      if (status === "Banned" && user.suspension_days !== null) {
        suspensionText = ` (${user.suspension_days} days left)`;
      } else if (status === "Banned") {
        suspensionText = " (Permanent)";
      }

      let action = "";
      if (status === "Active") {
        action = `
            <div class="actions">
              <button class="dropdown-btn" onclick="toggleDropdown(event)">&#8942;</button>
              <div class="dropdown-content">
                <a href="#" class="actions-item" onclick="banUser('${username}')">Ban</a>
              </div>
            </div>
        `;
      } else if (status === "Banned") {
        action = `
            <div class="actions">
              <button class="dropdown-btn" onclick="toggleDropdown(event)">&#8942;</button>
              <div class="dropdown-content">
                  <a href="#" class="actions-item" onclick="unbanUser('${username}')">Unban</a>
              </div>
            </div>
        `;
      }

      rows.push(`
            <tr>
                <td><input type="checkbox" class="user-check" aria-label="${username}" onclick="updateSelectAll()"></td>
                <td>${username}</td>
                <td>${date.toLocaleDateString("en-US", {
                  month: "long",
                  day: "numeric",
                })}, ${date.getFullYear()}</td>
                <td class="status-${status.toLowerCase()}">${status}${suspensionText}</td>
                <td>${action}</td>
            </tr>
        `);
    });

    tbody.innerHTML = rows.join("");
  }

  function performBulkAction(url, actionType) {
    const selectedUsers = getSelectedUsers();

    if (selectedUsers.length === 0) {
      return showAlert(
        "No users selected",
        "Please select at least one user.",
        "warning"
      );
    }

    if (actionType === "ban") {
      // Ask admin for ban duration
      Swal.fire({
        title: "Ban Duration",
        text: `Set the duration of the ban for the selected users`,
        icon: "question",
        input: "select",
        inputOptions: {
          3: "3 Days",
          7: "7 Days",
          30: "30 Days",
          permanent: "Permanent Ban",
        },
        inputPlaceholder: "Choose duration...",
        showCancelButton: true,
        inputValidator: (value) => {
          if (!value) {
            return "You must select a ban duration!";
          }
        },
      }).then((result) => {
        if (result.isConfirmed) {
          const banDuration = result.value;
          processBulkAction(url, actionType, selectedUsers, banDuration);
        }
      });
    } else {
      // No duration required for unbanning
      processBulkAction(url, actionType, selectedUsers, null);
    }
  }

  function processBulkAction(url, actionType, selectedUsers, banDuration) {
    Swal.fire({
      title: `Are you sure?`,
      text: `You are about to ${actionType} ${selectedUsers.length} user(s).`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: `Yes`,
      cancelButtonText: "No",
    }).then((result) => {
      if (result.isConfirmed) {
        const promises = selectedUsers.map((username) =>
          fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `username=${encodeURIComponent(
              username
            )}&ban_duration=${encodeURIComponent(banDuration)}`,
          }).then((response) =>
            response.json().then((data) => ({
              username,
              success: data.success,
              error: data.error,
            }))
          )
        );

        Promise.all(promises)
          .then((results) => {
            const failedUsers = results.filter((res) => !res.success);
            const successfulUsers = results.filter((res) => res.success);
            if (successfulUsers.length > 0) {
              Swal.fire({
                title: "Success!",
                text: `${successfulUsers.length} user(s) ${actionType}ed successfully.`,
                icon: "success",
              });
            }

            if (failedUsers.length > 0) {
              Swal.fire({
                title: "Unbanned!",
                html: `The following users could not be ${actionType}ed: <br><strong>${failedUsers
                  .map((u) => u.username)
                  .join(", ")}</strong>`,
                icon: "success",
              });
            }

            // Uncheck the "Select All" checkbox and all individual checkboxes
            document.querySelector(".select-all").checked = false;
            document.querySelectorAll(".user-check").forEach((checkbox) => {
              checkbox.checked = false;
            });

            fetchUsers(); // Refresh table after actions
          })
          .catch(() => {
            Swal.fire(
              "Error!",
              "An error occurred. Please try again.",
              "error"
            );
          });
      }
    });
  }

  // Ban a single user
  function banUser(username) {
    Swal.fire({
      title: "Ban Duration",
      text: `Select the number of days to ban ${username}:`,
      icon: "question",
      input: "select",
      inputOptions: {
        3: "3 Days",
        7: "7 Days",
        30: "30 Days",
        permanent: "Permanent Ban",
      },
      inputPlaceholder: "Select duration",
      showCancelButton: true,
      confirmButtonText: "Ban User",
      cancelButtonText: "Cancel",
      inputValidator: (value) => {
        if (!value) {
          return "You must select a ban duration!";
        }
      },
    }).then((result) => {
      if (result.isConfirmed) {
        const banDuration = result.value; // Get selected duration

        fetch("fetch_api.php?action=ban_user", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `username=${encodeURIComponent(
            username
          )}&ban_duration=${banDuration}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              Swal.fire(
                "Banned!",
                `${username} has been banned for ${banDuration} days.`,
                "success"
              ).then(() => fetchUsers());
            } else {
              Swal.fire("Error!", `Failed to ban ${username}.`, "error");
            }
          })
          .catch(() => {
            Swal.fire("Error!", "An error occurred.", "error");
          });
      }
    });
  }

  // Unban a single user
  function unbanUser(username) {
    confirmAction("Unban", username, "fetch_api.php?action=unban_user");
  }

  // Confirm and perform ban/unban action
  function confirmAction(action, username, url) {
    Swal.fire({
      title: `Are you sure you want to ${action.toLowerCase()} ${username}?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: `Yes, ${action}`,
      cancelButtonText: "No",
    }).then((result) => {
      if (result.isConfirmed) {
        fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `username=${encodeURIComponent(username)}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              Swal.fire(
                `${action}ed!`,
                `${username} has been ${action.toLowerCase()}ed.`,
                "success"
              ).then(() => fetchUsers());
            } else {
              Swal.fire("Error!", `Failed to ${action.toLowerCase()} ${username}.`, "error");
            }
          })
          .catch(() => {
            Swal.fire("Error!", "An error occurred.", "error");
          });
      }
    });
  }

  // Show a SweetAlert notification
  function showAlert(title, text, icon, callback) {
    Swal.fire({ title, text, icon }).then(() => callback && callback());
  }

  // Toggle dropdown menu
  function toggleDropdown(event) {
    event.stopPropagation();
    const dropdown = event.currentTarget.nextElementSibling;

    document.querySelectorAll(".dropdown-content.show").forEach((content) => {
      if (content !== dropdown) content.classList.remove("show");
    });

    dropdown.classList.toggle("show");
  }

  // Close dropdown if clicking outside
  document.addEventListener("click", () => {
    document
      .querySelectorAll(".dropdown-content.show")
      .forEach((content) => content.classList.remove("show"));
  });

  // Update select all checkbox state
  window.updateSelectAll = function () {
    const allChecked = [...document.querySelectorAll(".user-check")].every(
      (checkbox) => checkbox.checked
    );
    selectAll.checked = allChecked;
    updateSelectedUsers();
  };

  // Get selected users
  function getSelectedUsers() {
    return [...document.querySelectorAll(".user-check:checked")].map(
      (checkbox) => checkbox.getAttribute("aria-label")
    );
  }

  // Log selected users
  function updateSelectedUsers() {
    console.log("Selected users:", getSelectedUsers());
  }

  // Expose functions to global scope
  window.banUser = banUser;
  window.unbanUser = unbanUser;
  window.toggleDropdown = toggleDropdown;
});