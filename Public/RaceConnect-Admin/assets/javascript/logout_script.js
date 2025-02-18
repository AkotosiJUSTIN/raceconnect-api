document.addEventListener('DOMContentLoaded', function () {
    const logoutBtn = document.querySelector('.logout-btn');  // Select the logout button
    const logoutDialog = document.getElementById('logoutDialog');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    logoutBtn.addEventListener('click', function (event) {
        event.preventDefault();
        logoutDialog.classList.add('show');
        setTimeout(() => {
            logoutDialog.querySelector('.dialog').classList.add('show');
        }, 10);
    });

    confirmLogout.addEventListener('click', function () {
        window.location.href = 'logout.php';
    });

    cancelLogout.addEventListener('click', function () {
        logoutDialog.querySelector('.dialog').classList.remove('show');
        setTimeout(() => {
            logoutDialog.classList.remove('show');
        }, 300);
    });
});