document.addEventListener('DOMContentLoaded', () => {
    const announcementForm = document.getElementById('announcementForm');
    const announcementContainer = document.getElementById('announcementContainer');

    // Mock data for existing announcements
    let announcements = [
        {
            id: 1,
            title: "System Maintenance",
            content: "The system will undergo maintenance on October 15th.",
            created_at: "2023-10-10T10:00:00Z"
        },
        {
            id: 2,
            title: "New Feature Release",
            content: "We have released a new feature for notifications!",
            created_at: "2023-10-08T14:30:00Z"
        }
    ];

    // Populate existing announcements
    populateAnnouncements(announcements);

    // Handle form submission
    announcementForm.addEventListener('submit', (event) => {
        event.preventDefault();

        // Get form values
        const title = document.getElementById('announcementTitle').value.trim();
        const content = document.getElementById('announcementContent').value.trim();

        if (!title || !content) {
            alert('Please fill in all fields.');
            return;
        }

        // Create a new announcement object
        const newAnnouncement = {
            id: Date.now(), // Use timestamp as a unique ID
            title: title,
            content: content,
            created_at: new Date().toISOString()
        };

        // Add the new announcement to the list
        announcements.unshift(newAnnouncement);

        // Clear the form
        announcementForm.reset();

        // Re-render the announcements
        populateAnnouncements(announcements);
    });

    // Populate the announcements container
    function populateAnnouncements(announcements) {
        announcementContainer.innerHTML = ''; // Clear existing content

        if (announcements.length === 0) {
            announcementContainer.innerHTML = '<p>No announcements available.</p>';
            return;
        }

        announcements.forEach(announcement => {
            const date = new Date(announcement.created_at);
            const formattedDate = `${date.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })}, ${date.getFullYear()}`;

            const announcementCard = document.createElement('div');
            announcementCard.className = 'announcement-card';
            announcementCard.innerHTML = `
                <div class="announcement-header">
                    <h3 class="announcement-title">${announcement.title}</h3>
                    <span class="announcement-date">${formattedDate}</span>
                </div>
                <div class="announcement-content">
                    <p>${announcement.content}</p>
                </div>
            `;
            announcementContainer.appendChild(announcementCard);
        });
    }
});