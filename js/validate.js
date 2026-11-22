// Client-side checks for the register and login forms.
// The server checks everything again, so this is only for quick feedback.

document.addEventListener('DOMContentLoaded', function () {

    // ---------- Show / hide password buttons ----------
    var toggles = document.querySelectorAll('.toggle-password');

    toggles.forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.target);
            var icon = button.querySelector('.material-symbols-outlined');
            var showing = input.type === 'text';

            input.type = showing ? 'password' : 'text';
            icon.textContent = showing ? 'visibility' : 'visibility_off';
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

    // ---------- Helpers ----------

    // Show an error message under a field
    function showError(input, message) {
        clearError(input);
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');

        var error = document.createElement('p');
        error.className = 'field-error js-error';
        error.textContent = message;

        // Add the message at the end of the field's group
        var group = input.closest('.form-group');
        group.appendChild(error);
    }

    function clearError(input) {
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');

        var group = input.closest('.form-group');
        var old = group.querySelectorAll('.field-error');
        old.forEach(function (item) {
            item.remove();
        });
    }

    var namePattern  = /^[A-Za-z][A-Za-z' -]{1,49}$/;
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    var phonePattern = /^(\+233|0)[235][0-9]{8}$/;

    // ---------- Register form ----------
    var registerForm = document.getElementById('register-form');

    if (registerForm) {
        registerForm.addEventListener('submit', function (event) {
            var valid = true;

            var fname   = document.getElementById('customer_fname');
            var lname   = document.getElementById('customer_lname');
            var gender  = document.getElementById('customer_gender');
            var email   = document.getElementById('customer_email');
            var phone   = document.getElementById('customer_contact');
            var pass    = document.getElementById('customer_pass');
            var confirm = document.getElementById('confirm_pass');

            [fname, lname, gender, email, phone, pass, confirm].forEach(clearError);

            if (!namePattern.test(fname.value.trim())) {
                showError(fname, 'Enter your first name using letters only.');
                valid = false;
            }
            if (!namePattern.test(lname.value.trim())) {
                showError(lname, 'Enter your surname using letters only.');
                valid = false;
            }
            if (gender.value === '') {
                showError(gender, 'Please select a gender.');
                valid = false;
            }
            if (!emailPattern.test(email.value.trim())) {
                showError(email, 'Enter a valid email address.');
                valid = false;
            }
            if (!phonePattern.test(phone.value.replace(/\s/g, ''))) {
                showError(phone, 'Enter a valid Ghana phone number, for example 024 123 4567.');
                valid = false;
            }
            if (pass.value.length < 8 || !/[A-Za-z]/.test(pass.value) || !/[0-9]/.test(pass.value)) {
                showError(pass, 'Use at least 8 characters with at least one letter and one number.');
                valid = false;
            }
            if (confirm.value !== pass.value || confirm.value === '') {
                showError(confirm, 'The passwords do not match.');
                valid = false;
            }

            if (!valid) {
                event.preventDefault();
                registerForm.querySelector('.is-invalid').focus();
            }
        });
    }

    // ---------- Login form ----------
    var loginForm = document.getElementById('login-form');

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            var valid = true;
            var email = document.getElementById('customer_email');
            var pass  = document.getElementById('customer_pass');

            [email, pass].forEach(clearError);

            if (!emailPattern.test(email.value.trim())) {
                showError(email, 'Enter a valid email address.');
                valid = false;
            }
            if (pass.value === '') {
                showError(pass, 'Enter your password.');
                valid = false;
            }

            if (!valid) {
                event.preventDefault();
                loginForm.querySelector('.is-invalid').focus();
            }
        });
    }
});