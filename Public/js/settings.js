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
                        field.value = values[key];
                    }
                }
            } catch (e) {
                console.error('Error resetting defaults:', e);
            }
        });
    });
})();
