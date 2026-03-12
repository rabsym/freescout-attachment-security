/**
 * AttachmentSecurity Settings JavaScript
 * Handles Reset to Defaults functionality
 */
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        var resetBtn = document.getElementById('reset-defaults');
        
        if (!resetBtn) return;
        
        resetBtn.addEventListener('click', function() {
            var confirmMsg = this.getAttribute('data-confirm');
            var defaults = this.getAttribute('data-defaults');
            
            if (!confirm(confirmMsg)) return;
            
            try {
                var values = JSON.parse(defaults);
                
                // Set all fields to default values
                var field;
                for (var key in values) {
                    field = document.getElementById(key);
                    if (field) {
                        // Handle checkboxes
                        if (field.type === 'checkbox') {
                            field.checked = !!values[key];
                        }
                        // Handle select and regular inputs
                        else {
                            field.value = values[key];
                        }
                    }
                }
            } catch (e) {
                console.error('Error resetting defaults:', e);
            }
        });
    });
})();





// Validation for Blocked Extensions field
document.addEventListener('DOMContentLoaded', function() {

    const blockedExtInput = document.getElementById('blocked_extensions');
    const errorSpan = document.getElementById('blocked_extensions_error');
    const form = blockedExtInput ? blockedExtInput.closest('form') : null;

    // Skip if elements don't exist (not in settings page)
    if (!blockedExtInput || !errorSpan || !form) {
        return;
    }

    // Real-time validation
    blockedExtInput.addEventListener('input', function() {
        validateBlockedExtensions(true);
    });

    // Validation on form submit
    form.addEventListener('submit', function(e) {
        if (!validateBlockedExtensions(true)) {
            e.preventDefault();
            blockedExtInput.focus();
            return false;
        }
    });
    
    function validateBlockedExtensions(showError) {
        const value = blockedExtInput.value.trim();
        
        // Empty is valid
        if (value === '') {
            errorSpan.style.display = 'none';
            blockedExtInput.style.borderColor = '';
            return true;
        }
        
        // Regex: only letters, numbers and commas (no spaces or special chars)
        const regex = /^[a-zA-Z0-9]+(,[a-zA-Z0-9]+)*$/;
        
        if (!regex.test(value)) {
            if (showError) {
                errorSpan.style.display = 'block';
                blockedExtInput.style.borderColor = '#d9534f';
            }
            return false;
        } else {
            errorSpan.style.display = 'none';
            blockedExtInput.style.borderColor = '';
            return true;
        }
    }
});


// Email Notification Validation
document.addEventListener('DOMContentLoaded', function() {

    const emailEnabledCheckbox = document.getElementById('email_notifications_enabled');
    const emailInput = document.getElementById('notification_email');
    const emailError = document.getElementById('notification_email_error');
    const emailWarning = document.getElementById('notification_email_warning');
    const form = emailInput ? emailInput.closest('form') : null;

    // Skip if elements don't exist
    if (!emailEnabledCheckbox || !emailInput || !form) {
        return;
    }

    // Check mail driver from data attribute
    const mailDriver = emailEnabledCheckbox.getAttribute('data-mail-driver');
    const isSmtp = (mailDriver === 'smtp');

    // If not SMTP, ensure checkbox stays disabled
    if (!isSmtp) {
        emailEnabledCheckbox.disabled = true;
        emailEnabledCheckbox.checked = false;
        return; // Exit early, no validation needed
    }

    // Update warning visibility when checkbox changes
    emailEnabledCheckbox.addEventListener('change', function() {
        updateEmailWarning();
    });

    // Real-time email validation
    emailInput.addEventListener('input', function() {
        validateEmail(true);
        updateEmailWarning();
    });

    // Validation on form submit
    form.addEventListener('submit', function(e) {
        // Only validate if notifications are enabled
        if (emailEnabledCheckbox.checked) {
            const emailValue = emailInput.value.trim();
            
            // If notifications enabled, email is REQUIRED
            if (emailValue === '') {
                e.preventDefault();
                emailError.textContent = '⚠️ Email address is required when notifications are enabled';
                emailError.style.display = 'block';
                emailInput.style.borderColor = '#d9534f';
                emailInput.focus();
                return false;
            }
            
            // Validate email format
            if (!validateEmail(true)) {
                e.preventDefault();
                emailInput.focus();
                return false;
            }
        }
    });

    function validateEmail(showError) {
        const value = emailInput.value.trim();
        
        // If checking from submit and notifications are enabled, empty is NOT valid
        // But during typing, we don't want to show error immediately on empty
        if (value === '') {
            if (showError) {
                emailError.style.display = 'none';
                emailInput.style.borderColor = '';
            }
            return !emailEnabledCheckbox.checked; // Only valid if notifications disabled
        }
        
        // Basic email regex
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!regex.test(value)) {
            if (showError) {
                emailError.textContent = '⚠️ Invalid email format. Example: security@example.com';
                emailError.style.display = 'block';
                emailInput.style.borderColor = '#d9534f';
            }
            return false;
        } else {
            if (showError) {
                emailError.style.display = 'none';
                emailInput.style.borderColor = '#5cb85c'; // Green border for valid
            }
            return true;
        }
    }

    function updateEmailWarning() {
        // Show warning if notifications enabled but no valid email
        if (emailEnabledCheckbox.checked && emailInput.value.trim() === '') {
            emailWarning.style.display = 'inline';
        } else {
            emailWarning.style.display = 'none';
        }
    }

    // Initial check
    updateEmailWarning();
});
