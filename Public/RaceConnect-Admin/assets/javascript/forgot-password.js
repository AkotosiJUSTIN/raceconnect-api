document.addEventListener('DOMContentLoaded', function() {
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const resetPasswordModal = document.getElementById('resetPasswordModal');
    const closeBtns = document.querySelectorAll('.close');
    const forgotPasswordLink = document.querySelector('.forgot-password');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const modalMessage = document.getElementById('modalMessage');
    const resetModalMessage = document.getElementById('resetModalMessage');

    // Show forgot password modal
    forgotPasswordLink.addEventListener('click', function(e) {
        e.preventDefault();
        forgotPasswordModal.style.display = 'block';
    });

    // Close modals when clicking X
    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'forgotPasswordModal') {
                modalMessage.innerHTML = '';
                forgotPasswordForm.reset();
            } else {
                resetModalMessage.innerHTML = '';
                resetPasswordForm.reset();
            }
        });
    });

    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === forgotPasswordModal) {
            forgotPasswordModal.style.display = 'none';
            modalMessage.innerHTML = '';
            forgotPasswordForm.reset();
        } else if (e.target === resetPasswordModal) {
            resetPasswordModal.style.display = 'none';
            resetModalMessage.innerHTML = '';
            resetPasswordForm.reset();
        }
    });

    // Handle forgot password form submission
    forgotPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(forgotPasswordForm);

        fetch('handle_forgot_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modalMessage.innerHTML = `
                    <div class="message success-message">${data.message}</div>
                `;
                setTimeout(() => {
                    forgotPasswordModal.style.display = 'none';
                    resetPasswordModal.style.display = 'block';
                }, 2000);
            } else {
                modalMessage.innerHTML = `
                    <div class="message error-message">${data.message}</div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalMessage.innerHTML = `
                <div class="message error-message">An error occurred. Please try again.</div>
            `;
        });
    });

    // Handle reset password form submission
    resetPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(resetPasswordForm);

        fetch('handle_reset_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resetModalMessage.innerHTML = `
                    <div class="message success-message">${data.message}</div>
                `;
                setTimeout(() => {
                    resetPasswordModal.style.display = 'none';
                    window.location.reload();
                }, 2000);
            } else {
                resetModalMessage.innerHTML = `
                    <div class="message error-message">${data.message}</div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resetModalMessage.innerHTML = `
                <div class="message error-message">An error occurred. Please try again.</div>
            `;
        });
    });
});