document.getElementById('loginForm').addEventListener('submit', function(event) {
    const emailInput = document.getElementById('email').value.trim();
    const roleInput = document.getElementById('role').value;
    const passwordInput = document.getElementById('password').value;
    const errorBanner = document.getElementById('js-error-banner');

    errorBanner.style.display = 'none';
    errorBanner.textContent = '';

    if (roleInput === "") {
        event.preventDefault();
        showJsError("Please choose a role to proceed.");
        return;
    }

    if (!emailInput.endsWith('@cvsu.edu.ph')) {
        event.preventDefault();
        showJsError("Notice: The email used must be an official CvSU account (@cvsu.edu.ph).");
        return;
    }

    if (passwordInput.length < 4) {
        event.preventDefault();
        showJsError("Short Password, it must be more than 4 input.");
        return;
    }
});

function showJsError(message) {
    const errorBanner = document.getElementById('js-error-banner');
    errorBanner.textContent = message;
    errorBanner.style.display = 'block';
}