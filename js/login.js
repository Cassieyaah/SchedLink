document.addEventListener("DOMContentLoaded", function() {
    const loginForm = document.getElementById("loginForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const togglePassword = document.getElementById("togglePassword");

    if (togglePassword) {
        togglePassword.addEventListener("change", function() {
            if (this.checked) {
                passwordInput.type = "text";
            } else {
                passwordInput.type = "password";
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener("submit", function(event) {
            const emailValue = emailInput.value.trim().toLowerCase();

            if (!emailValue.endsWith("@cvsu.edu.ph")) {
                event.preventDefault(); 
                alert("Error: Please use your official CvSU institutional email (@cvsu.edu.ph).");
                emailInput.focus();
                return false;
            }

            if (passwordInput.value.trim() === "") {
                event.preventDefault();
                alert("Error: Password cannot be empty.");
                passwordInput.focus();
                return false;
            }
        });
    }
});

