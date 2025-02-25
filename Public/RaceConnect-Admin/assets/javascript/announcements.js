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

        fetch('fetch_api.php?action=post_announcement', {
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
        fetch('fetch_api.php?action=fetch_announcements')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Update the announcement container with fetched data
                announcementContainer.innerHTML = data.map(announcement => `
                    <div class="announcement">
                        <h3>${announcement.title}</h3>
                        <p>${announcement.content}</p>
                        <small>${announcement.created_at}</small>
                    </div>
                `).join('');
            })
            .catch(error => console.error('Error fetching announcements:', error));
    }

    // Function to show the floating message
    function showFloatingMessage() {
        floatingMessageContainer.classList.add('show');
        setTimeout(() => {
            floatingMessageContainer.classList.remove('show');
        }, 3000);
    }
});