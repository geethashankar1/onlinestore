// js/script.js — small site-wide interactions.

document.addEventListener('DOMContentLoaded', function () {

    // Confirm before deleting a product in the admin panel
    document.querySelectorAll('.delete-product-btn').forEach(function (button) {
        button.addEventListener('click', function (event) {
            if (!confirm('Are you sure you want to delete this product?')) {
                event.preventDefault();
            }
        });
    });

    initPasswordToggles();
});

/**
 * Adds a show/hide eye button to every password field on the page.
 *
 * Done in JS rather than in each form's markup so login, register, confirm-
 * password and the checkout CVV all get it without duplicating markup — and so
 * any password field added later picks it up for free.
 */
function initPasswordToggles() {
    var EYE = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
              '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
              '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>' +
              '<line x1="1" y1="1" x2="23" y2="23"/></svg>';

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        // Guard against double-initialising if this ever runs twice.
        if (input.closest('.pw-field')) return;

        var wrap = document.createElement('div');
        wrap.className = 'pw-field';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';                 // must not submit the form
        btn.className = 'pw-toggle';
        btn.innerHTML = EYE;
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('aria-pressed', 'false');
        btn.tabIndex = 0;
        wrap.appendChild(btn);

        btn.addEventListener('click', function () {
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.innerHTML = hidden ? EYE_OFF : EYE;
            btn.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
            btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
            // Keep the caret where the user left it.
            input.focus();
        });
    });
}

// Client-side check that the two password fields match before submitting.
var registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', function (event) {
        var password = document.getElementById('password');
        var confirmPassword = document.getElementById('confirm_password');
        if (password && confirmPassword && password.value !== confirmPassword.value) {
            alert('Passwords do not match!');
            event.preventDefault();
        }
    });
}
