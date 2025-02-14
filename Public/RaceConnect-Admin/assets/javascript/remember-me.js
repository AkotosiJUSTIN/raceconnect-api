document.addEventListener('DOMContentLoaded', () => {
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');
    const rememberMeCheckbox = document.getElementById('remember-me');
    const loginForm = document.getElementById('loginForm');

    // Autofill if data exists
    if (localStorage.getItem('remember-me') === 'true') {
        emailField.value = localStorage.getItem('savedEmail') || '';
        passwordField.value = localStorage.getItem('savedPassword') || '';
        rememberMeCheckbox.checked = true;
    }

    loginForm.addEventListener('submit', () => {
        if (rememberMeCheckbox.checked) {
            localStorage.setItem('savedEmail', emailField.value);
            localStorage.setItem('savedPassword', passwordField.value);
            localStorage.setItem('remember-me', 'true');
        } else {
            localStorage.removeItem('savedEmail');
            localStorage.removeItem('savedPassword');
            localStorage.removeItem('remember-me');
        }
    });
});