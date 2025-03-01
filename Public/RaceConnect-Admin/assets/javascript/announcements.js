document.addEventListener('DOMContentLoaded', () => {
    const announcementForm = document.getElementById('announcementForm');
    const floatingMessageContainer = document.querySelector('.floating-message-container');
    const progressBar = document.querySelector('.progress');

    // Handle form submission
    announcementForm.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(announcementForm);

        fetch('fetch_api.php?action=post_announcement', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || 'Failed to post announcement');
            }
            return result;
        })
        .then(result => {
            if (result.success) {
                // Clear the form
                announcementForm.reset();
                // Show success message
                showFloatingMessage('Announcement posted successfully!', 'success');
            } else {
                showFloatingMessage(result.error || 'Failed to post announcement', 'error');
            }
        })
        .catch(error => {
            console.error('Error posting announcement:', error);
            showFloatingMessage(error.message || 'An error occurred while posting the announcement', 'error');
        });
    });

    // Function to show the floating message
    function showFloatingMessage(message, type = 'success') {
        const container = document.querySelector('.floating-message-container');
        const messageElement = document.getElementById('floatingMessage');
        
        // Set message and style based on type
        messageElement.textContent = message;
        container.className = `floating-message-container ${type}`;
        
        // Reset any existing animations
        container.style.display = 'block';
        container.classList.remove('hide');
        
        // Trigger show animation
        requestAnimationFrame(() => {
            container.classList.add('show');
        });
    
        // Start hide animation after 3 seconds
        setTimeout(() => {
            container.classList.remove('show');
            container.classList.add('hide');
            
            // Remove element after animation completes
            setTimeout(() => {
                container.style.display = 'none';
                container.classList.remove('hide');
            }, 500); // Match the transition duration
        }, 3000);
    }
});