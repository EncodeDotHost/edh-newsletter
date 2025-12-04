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
            
            // Show loading state
            $form.addClass('loading');
            $button.prop('disabled', true);
            
            // Form will submit normally, but we show loading state
            // The loading state will be cleared when the page reloads
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
        
        var $error = $('<div class="newsletter-field-error">' + message + '</div>');
        $field.addClass('newsletter-field-invalid');
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
        $field.removeClass('newsletter-field-invalid');
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
     * Show notification message
     */
    function showNotification(message, type) {
        type = type || 'info';
        
        var $notification = $('<div class="newsletter-notification newsletter-notification-' + type + '">' + 
                            '<span class="newsletter-notification-message">' + message + '</span>' +
                            '<button class="newsletter-notification-close">&times;</button>' +
                            '</div>');
        
        $('body').append($notification);
        
        // Position notification
        $notification.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: 9999,
            padding: '15px 20px',
            borderRadius: '4px',
            maxWidth: '300px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
        });
        
        // Style based on type
        switch (type) {
            case 'success':
                $notification.css({
                    backgroundColor: '#d4edda',
                    border: '1px solid #c3e6cb',
                    color: '#155724'
                });
                break;
            case 'error':
                $notification.css({
                    backgroundColor: '#f8d7da',
                    border: '1px solid #f5c6cb',
                    color: '#721c24'
                });
                break;
            case 'warning':
                $notification.css({
                    backgroundColor: '#fff3cd',
                    border: '1px solid #ffeaa7',
                    color: '#856404'
                });
                break;
            default:
                $notification.css({
                    backgroundColor: '#d1ecf1',
                    border: '1px solid #bee5eb',
                    color: '#0c5460'
                });
        }
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $notification.remove();
            });
        }, 5000);
        
        // Handle close button
        $notification.find('.newsletter-notification-close').on('click', function() {
            $notification.fadeOut(function() {
                $notification.remove();
            });
        });
        
        // Animate in
        $notification.hide().fadeIn();
    }
    
    /**
     * Handle AJAX form submissions (if needed)
     */
    function initAjaxForms() {
        $('.newsletter-ajax-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var formData = new FormData(this);
            
            if (!validateForm($form)) {
                return;
            }
            
            $form.addClass('loading');
            $button.prop('disabled', true);
            
            $.ajax({
                url: $form.attr('action') || window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotification(response.message || 'Success!', 'success');
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            $form[0].reset();
                        }
                    } else {
                        showNotification(response.message || 'An error occurred.', 'error');
                    }
                },
                error: function() {
                    showNotification('A network error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $form.removeClass('loading');
                    $button.prop('disabled', false);
                }
            });
        });
    }
    
    // Initialize AJAX forms if needed
    initAjaxForms();
    
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
        
        // Improve error message accessibility
        $(document).on('DOMNodeInserted', '.newsletter-field-error', function() {
            var $error = $(this);
            var $field = $error.prev('input, select, textarea');
            
            if ($field.length > 0) {
                var errorId = 'newsletter-error-' + Math.random().toString(36).substr(2, 9);
                $error.attr('id', errorId);
                $field.attr('aria-describedby', errorId);
            }
        });
    }
    
    // Initialize accessibility improvements
    initAccessibility();
    
})(jQuery);
