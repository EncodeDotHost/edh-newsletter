/**
 * Newsletter Public JavaScript
 * 
 * @package Newsletter
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initSignupForms();
        initPreferenceForms();
        initFormValidation();
    });
    
    /**
     * Initialize signup forms
     */
    function initSignupForms() {
        $('.newsletter-form').on('submit', function(e) {
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            
            // Basic validation
            if (!validateForm($form)) {
                e.preventDefault();
                return;
            }
            
            // Show loading state. The buttons are disabled on the next tick:
            // the browser builds the form data after this event fires and
            // skips disabled controls, so disabling them now would drop the
            // clicked button's name/value (e.g. action=unsubscribe).
            $form.addClass('loading');
            setTimeout(function() {
                $button.prop('disabled', true);
            }, 0);
        });
    }
    
    /**
     * Initialize preference forms
     */
    function initPreferenceForms() {
        $('.newsletter-preferences-form').on('submit', function(e) {
            var $form = $(this);
            var action = $form.find('input[name="action"]:checked').val() || 
                        $form.find('button[type="submit"][name="action"]').val();
            
            // Confirm destructive actions
            if (action === 'unsubscribe') {
                if (!confirm('Are you sure you want to unsubscribe from our newsletter?')) {
                    e.preventDefault();
                    return;
                }
            }
            
            // Show loading state
            $form.addClass('loading');
        });
    }
    
    /**
     * Initialize form validation
     */
    function initFormValidation() {
        // Real-time email validation
        $('input[type="email"]').on('blur', function() {
            var $input = $(this);
            var email = $input.val();
            
            if (email && !isValidEmail(email)) {
                showFieldError($input, 'Please enter a valid email address.');
            } else {
                clearFieldError($input);
            }
        });
        
        // Privacy consent validation
        $('input[name="privacy_consent"]').on('change', function() {
            var $checkbox = $(this);
            var $form = $checkbox.closest('form');
            var $button = $form.find('button[type="submit"]');
            
            if ($checkbox.length > 0) {
                $button.prop('disabled', !$checkbox.is(':checked'));
            }
        });
        
        // Trigger initial consent check
        $('input[name="privacy_consent"]').trigger('change');
    }
    
    /**
     * Validate form before submission
     */
    function validateForm($form) {
        var isValid = true;
        
        // Validate required fields
        $form.find('input[required], select[required], textarea[required]').each(function() {
            var $field = $(this);
            var value = $field.val().trim();
            
            if (!value) {
                showFieldError($field, 'This field is required.');
                isValid = false;
            } else {
                clearFieldError($field);
            }
        });
        
        // Validate email fields
        $form.find('input[type="email"]').each(function() {
            var $field = $(this);
            var email = $field.val().trim();
            
            if (email && !isValidEmail(email)) {
                showFieldError($field, 'Please enter a valid email address.');
                isValid = false;
            }
        });
        
        // Validate privacy consent if required
        var $consent = $form.find('input[name="privacy_consent"]');
        if ($consent.length > 0 && $consent.prop('required') && !$consent.is(':checked')) {
            showFieldError($consent, 'You must agree to the privacy policy.');
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Show field error
     */
    function showFieldError($field, message) {
        clearFieldError($field);
        
        var errorId = 'newsletter-error-' + Math.random().toString(36).substr(2, 9);
        var $error = $('<div class="newsletter-field-error" role="alert"></div>').attr('id', errorId).text(message);
        $field.addClass('newsletter-field-invalid').attr('aria-describedby', errorId);
        $field.after($error);
        
        // Focus on first error field
        if ($('.newsletter-field-invalid').length === 1) {
            $field.focus();
        }
    }
    
    /**
     * Clear field error
     */
    function clearFieldError($field) {
        $field.removeClass('newsletter-field-invalid').removeAttr('aria-describedby');
        $field.siblings('.newsletter-field-error').remove();
    }
    
    /**
     * Validate email address
     */
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    /**
     * Accessibility improvements
     */
    function initAccessibility() {
        // Add ARIA labels to form fields without labels
        $('input, select, textarea').each(function() {
            var $field = $(this);
            var placeholder = $field.attr('placeholder');
            
            if (placeholder && !$field.attr('aria-label') && !$field.attr('aria-labelledby')) {
                $field.attr('aria-label', placeholder);
            }
        });
        
    }
    
    // Initialize accessibility improvements
    initAccessibility();
    
})(jQuery);
