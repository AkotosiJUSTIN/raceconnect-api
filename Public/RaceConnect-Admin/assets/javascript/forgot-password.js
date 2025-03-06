document.addEventListener('DOMContentLoaded', function() {
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const resetPasswordModal = document.getElementById('resetPasswordModal');
    const closeBtns = document.querySelectorAll('.close');
    const forgotPasswordLink = document.querySelector('.forgot-password');
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const modalMessage = document.getElementById('modalMessage');
    const resetModalMessage = document.getElementById('resetModalMessage');
    const toggleButtons = document.querySelectorAll('.toggle-password');
    const verifyOtpButton = document.getElementById('verifyOtpButton');

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
                document.getElementById('passwordSection').style.display = 'none';
                document.getElementById('otp').disabled = false;
                verifyOtpButton.disabled = false;
            }
        });
    });

    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const section = this.closest('#passwordSection');
            if (section) {
                const passwordInputs = section.querySelectorAll('.password-input');
                passwordInputs.forEach(input => {
                    if (input.type === 'password') {
                        input.type = 'text';
                    } else {
                        input.type = 'password';
                    }
                });
                if (passwordInputs.length > 0) {
                    const firstInput = passwordInputs[0];
                    if (firstInput.type === 'text') {
                        this.innerHTML = '<box-icon name="hide" color="#dc2626"></box-icon>';
                    } else {
                        this.innerHTML = '<box-icon name="show" color="#dc2626"></box-icon>';
                    }
                }
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
            document.getElementById('passwordSection').style.display = 'none';
            document.getElementById('otp').disabled = false;
            verifyOtpButton.disabled = false;
        }
    });

    // Handle forgot password form submission
    forgotPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitButton = forgotPasswordForm.querySelector('.submit-button');
        const originalButtonText = submitButton.innerHTML;
        
        // Disable the button and show loading state
        submitButton.disabled = true;
        submitButton.classList.add('loading');
        submitButton.innerHTML = `
            <span class="loading-spinner"></span>
            Sending OTP...
        `;
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
                    resetPasswordForm.reset();
                    document.getElementById('passwordSection').style.display = 'none';
                    document.getElementById('otp').disabled = false;
                    verifyOtpButton.disabled = false;
                    resetModalMessage.innerHTML = '';
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
        })
        .finally(() => {
            submitButton.disabled = false;
            submitButton.classList.remove('loading');
            submitButton.innerHTML = originalButtonText;
        });
    });

    document.getElementById('verifyOtpButton').addEventListener('click', function() {
        const otp = document.getElementById('otp').value;
        const formData = new FormData();
        formData.append('otp', otp);
    
        fetch('verify_otp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide OTP section and show password section
                document.getElementById('otpSection').style.display = 'none';
                document.getElementById('passwordSection').style.display = 'block';
                document.getElementById('otp').disabled = true;
                document.getElementById('verifyOtpButton').disabled = true;
            } else {
                alert(data.message); // e.g., "Invalid or expired OTP"
            }
        });
    });

    // Handle OTP verification
    verifyOtpButton.addEventListener('click', function() {
        const otp = document.getElementById('otp').value;
        const formData = new FormData();
        formData.append('otp', otp);

        fetch('verify_otp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resetModalMessage.innerHTML = `
                    <div class="message success-message">${data.message}</div>
                `;
                document.getElementById('passwordSection').style.display = 'block';
                document.getElementById('otp').disabled = true;
                verifyOtpButton.disabled = true;
            } else {
                resetModalMessage.innerHTML = `
                    <div class="message error-message">${data.message}</div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resetModalMessage.innerHTML = `
                <div class="message error-message">An error occurred</div>
            `;
        });
    });

    // Handle reset password form submission
    resetPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('new_password', document.getElementById('new_password').value);
        formData.append('confirm_password', document.getElementById('confirm_password').value);
    
        fetch('handle_reset_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const resetModalMessage = document.getElementById('resetModalMessage');
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
                <div class="error-message">${data.message}</div>
            `;
                if (data.error) {
                    console.error('Error:', data.error);
                }
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