document.addEventListener('DOMContentLoaded', function () {
    const logoutLink = document.querySelector('a[href="logout.php"]');
    const logoutDialog = document.getElementById('logoutDialog');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    logoutLink.addEventListener('click', function (event) {
        event.preventDefault();
        logoutDialog.classList.add('show');
        setTimeout(() => {
            logoutDialog.querySelector('.dialog').classList.add('show');
        }, 10); // Slight delay to trigger the animation
    });

    confirmLogout.addEventListener('click', function () {
        window.location.href = 'logout.php';
    });

    cancelLogout.addEventListener('click', function () {
        logoutDialog.querySelector('.dialog').classList.remove('show');
        setTimeout(() => {
            logoutDialog.classList.remove('show');
        }, 300); // Match the duration of the CSS transition
    });
});