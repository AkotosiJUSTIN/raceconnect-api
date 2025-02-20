document.addEventListener('DOMContentLoaded', () => {
    const announcementForm = document.getElementById('announcementForm');
    const announcementContainer = document.getElementById('announcementContainer');
    const floatingMessageContainer = document.querySelector('.floating-message-container');
    const progressBar = document.querySelector('.progress');

    // Fetch and display announcements on page load
    fetchAnnouncements();

    // Handle form submission
    announcementForm.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(announcementForm);

        fetch('post_announcement.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                // Clear the form
                announcementForm.reset();
                // Fetch and display the updated list of announcements
                fetchAnnouncements();
                // Show the floating message
                showFloatingMessage();
            } else {
                alert(result.message);
            }
        })
        .catch(error => console.error('Error posting announcement:', error));
    });

    // Function to fetch and display announcements
    function fetchAnnouncements() {
        fetch('fetch_announcements.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .catch(error => console.error('Error fetching announcements:', error));
    }

    // Function to show the floating message
    function showFloatingMessage() {
        floatingMessageContainer.style.display = 'block';
        progressBar.style.width = '100%';

        let timeLeft = 10; // Total time in seconds
        const interval = setInterval(() => {
            timeLeft--; // Decrease time left by 1 second

            // Update the width of the progress bar
            progressBar.style.width = `${(timeLeft / 10) * 100}%`;

            // If time is up, hide the floating message
            if (timeLeft <= 0) {
                clearInterval(interval); // Stop the interval
                floatingMessageContainer.style.display = 'none'; // Hide the message
            }
        }, 1000); // Run every second
    }
});