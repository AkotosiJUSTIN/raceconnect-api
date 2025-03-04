document.addEventListener('DOMContentLoaded', () => {
    const announcementForm = document.getElementById('announcementForm');
    const imageInput = document.getElementById('announcementImage');
    const previewContainer = document.querySelector('.preview-container');
    const removeImageBtn = document.getElementById('removeImage');
    const submitBtn = announcementForm.querySelector('.submit-btn');
    const btnText = submitBtn.querySelector('.btn-text');
    const loadingSpinner = submitBtn.querySelector('.loading-spinner');
    const floatingMessageContainer = document.querySelector('.floating-message-container');
    const progressBar = document.querySelector('.progress');
    const contentTextarea = document.getElementById('announcementContent');
    const charCount = document.getElementById('charCount');
    const charCounter = charCount.parentElement;
    const maxLength = contentTextarea.getAttribute('maxlength');

    // Handle form submission
    announcementForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Show loading state
        btnText.textContent = 'Posting...';
        loadingSpinner.style.display = 'block';
        submitBtn.disabled = true;
        progressBar.style.width = '0%';

        const formData = new FormData(this);

        try {
            const response = await fetch('fetch_api.php?action=post_announcement', {
                method: 'POST',
                body: formData
            });

            progressBar.style.width = '50%';
            const result = await response.json();

            if (result.success) {
                showFloatingMessage('Announcement posted successfully!', 'success');
                announcementForm.reset();
                previewContainer.style.display = 'none';
            } else {
                progressBar.style.width = '100%';
                showFloatingMessage(result.error || 'Failed to post announcement', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            progressBar.style.width = '100%';
            showFloatingMessage('An error occurred while posting the announcement', 'error');
        } finally {
            // Reset button state
            btnText.textContent = 'Post Announcement';
            loadingSpinner.style.display = 'none';
            submitBtn.disabled = false;
        }
    });

    // Update the image input handler
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const fileName = document.getElementById('fileName');
        const imagePreview = document.getElementById('imagePreview');
        
        if (file) {
            // Validate file size (e.g., 5MB limit)
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            if (file.size > maxSize) {
                showFloatingMessage('Image size should be less than 5MB', 'error');
                this.value = '';
                return;
            }

            // Update file name display
            fileName.textContent = file.name;
            previewContainer.style.display = 'block';
            
            // Create preview for image
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    document.getElementById('removeImage').style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        } else {
            resetImagePreview();
        }
    });

    // Function to reset image preview
    function resetImagePreview() {
        imageInput.value = '';
        previewContainer.style.display = 'none';
        document.getElementById('fileName').textContent = 'No file selected';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('imagePreview').src = '';
        document.getElementById('removeImage').style.display = 'none';
    }

    // Update the remove image handler
    removeImageBtn.addEventListener('click', (e) => {
        e.preventDefault();
        resetImagePreview();
    });

    function updateCharacterCount() {
        const currentLength = contentTextarea.value.length;
        charCount.textContent = currentLength;

        // Update counter color based on remaining characters
        charCounter.classList.remove('near-limit', 'at-limit');
        if (currentLength >= maxLength * 0.9) { // 90% of limit
            charCounter.classList.add('at-limit');
        } else if (currentLength >= maxLength * 0.8) { // 80% of limit
            charCounter.classList.add('near-limit');
        }
    }

    contentTextarea.addEventListener('input', updateCharacterCount);
    updateCharacterCount();

    // Function to show the floating message
    function showFloatingMessage(message, type = 'success') {
        // Clear any existing timeouts
        if (window.messageTimeout) {
            clearTimeout(window.messageTimeout);
        }
        
        floatingMessageContainer.style.display = 'block';
        floatingMessageContainer.className = `floating-message-container ${type}`;
        const messageElement = document.getElementById('floatingMessage');
        messageElement.textContent = message;
        
        // Reset and start progress bar animation
        progressBar.style.width = '0%';
        requestAnimationFrame(() => {
            progressBar.style.width = '100%';
            floatingMessageContainer.classList.add('show');
        });

        // Hide message after animation completes
        window.messageTimeout = setTimeout(() => {
            floatingMessageContainer.classList.remove('show');
            floatingMessageContainer.classList.add('hide');
            
            setTimeout(() => {
                floatingMessageContainer.style.display = 'none';
                floatingMessageContainer.classList.remove('hide');
                progressBar.style.width = '0%';
            }, 500);
        }, 3000);
    }
});