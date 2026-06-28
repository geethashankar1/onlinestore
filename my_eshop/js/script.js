// js/script.js
// You can add JavaScript for interactivity, form validation, AJAX calls, etc.

// Example: Confirm before deleting a product in admin panel
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-product-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            if (!confirm('Are you sure you want to delete this product?')) {
                event.preventDefault();
            }
        });
    });
});

// Example: Simple client-side form validation for registration
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', function(event) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        if (password !== confirmPassword) {
            alert('Passwords do not match!');
            event.preventDefault(); // Stop form submission
        }
        // Add more validation as needed (e.g., email format, password strength)
    });
}