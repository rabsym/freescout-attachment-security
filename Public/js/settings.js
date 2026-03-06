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
