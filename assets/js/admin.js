/**
 * Newsletter Admin JavaScript
 * 
 * @package Newsletter
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initColorPickers();
        initTestEmailForm();
        initDigestTriggers();
        initSubscriberActions();
        initSettingsTabs();
        initCleanupTrigger();
    });
    
    /**
     * Initialize color pickers
     */
    function initColorPickers() {
        $('.newsletter-color-picker').wpColorPicker();
    }
    
    /**
     * Initialize test email form
     */
    function initTestEmailForm() {
        $('.newsletter-test-email-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $email = $form.find('input[name="test_email"]');
            var $frequency = $form.find('select[name="test_frequency"]');
            
            if (!$email.val() || !isValidEmail($email.val())) {
                alert(newsletter_admin.strings.error_occurred);
                return;
            }
            
            $button.prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: newsletter_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'newsletter_test_email',
                    nonce: newsletter_admin.nonce,
                    email: $email.val(),
                    frequency: $frequency.val() || 'weekly'
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(newsletter_admin.strings.test_email_sent, 'success');
                        $email.val('');
                    } else {
                        showNotice(response.data || newsletter_admin.strings.error_occurred, 'error');
                    }
                },
                error: function() {
                    showNotice(newsletter_admin.strings.error_occurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Send Test Email');
                }
            });
        });
    }
    
    /**
     * Initialize digest trigger buttons
     */
    function initDigestTriggers() {
        $('.newsletter-trigger-digest').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var frequency = $button.data('frequency');
            
            if (!confirm('Are you sure you want to trigger the ' + frequency + ' digest now?')) {
                return;
            }
            
            $button.prop('disabled', true).text('Triggering...');
            
            $.ajax({
                url: newsletter_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'newsletter_trigger_digest',
                    nonce: newsletter_admin.nonce,
                    frequency: frequency
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(newsletter_admin.strings.digest_triggered, 'success');
                    } else {
                        showNotice(response.data || newsletter_admin.strings.error_occurred, 'error');
                    }
                },
                error: function() {
                    showNotice(newsletter_admin.strings.error_occurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text($button.data('original-text') || 'Trigger Digest');
                }
            });
        });
    }
    
    /**
     * Initialize subscriber action confirmations
     */
    function initSubscriberActions() {
        $('.newsletter-delete-subscriber').on('click', function(e) {
            if (!confirm(newsletter_admin.strings.confirm_delete)) {
                e.preventDefault();
            }
        });
        
        $('.newsletter-bulk-actions').on('submit', function(e) {
            var action = $(this).find('select[name="bulk_action"]').val();
            var selected = $(this).find('input[name="subscribers[]"]:checked').length;
            
            if (!action || action === '-1') {
                e.preventDefault();
                alert('Please select an action.');
                return;
            }
            
            if (selected === 0) {
                e.preventDefault();
                alert('Please select at least one subscriber.');
                return;
            }
            
            if (action === 'delete' && !confirm('Are you sure you want to delete the selected subscribers?')) {
                e.preventDefault();
            }
        });
        
        // Select all checkbox
        $('#newsletter-select-all').on('change', function() {
            $('input[name="subscribers[]"]').prop('checked', this.checked);
        });
    }
    
    /**
     * Initialize the privacy page "Run Cleanup Now" button
     */
    function initCleanupTrigger() {
        $('.newsletter-trigger-cleanup').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Running...');
            
            $.ajax({
                url: newsletter_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'newsletter_run_cleanup',
                    nonce: newsletter_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data || newsletter_admin.strings.cleanup_done, 'success');
                    } else {
                        showNotice(response.data || newsletter_admin.strings.error_occurred, 'error');
                    }
                },
                error: function() {
                    showNotice(newsletter_admin.strings.error_occurred, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    }
    
    /**
     * Initialize settings tabs
     */
    function initSettingsTabs() {
        $('.newsletter-settings-tabs a').on('click', function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.attr('href');
            
            // Update active tab
            $('.newsletter-settings-tabs a').removeClass('active');
            $tab.addClass('active');
            
            // Show/hide tab content
            $('.newsletter-tab-content').hide();
            $(target).show();
            
            // Update URL hash
            window.location.hash = target;
        });
        
        // Show active tab on page load
        var hash = window.location.hash;
        if (hash && $(hash).length) {
            $('.newsletter-settings-tabs a[href="' + hash + '"]').click();
        } else {
            $('.newsletter-settings-tabs a:first').click();
        }
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        type = type || 'info';
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
        
        // Handle dismiss button
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut();
        });
    }
    
    /**
     * Validate email address
     */
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    /**
     * Native media picker for the logo field
     */
    function initMediaUploader() {
        if (!wp.media) {
            return;
        }
        
        $('.newsletter-upload-logo').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('input[type="url"]');
            var $preview = $button.siblings('.newsletter-logo-preview');
            
            // A fresh frame per click keeps the field references correct
            var frame = wp.media({
                title: newsletter_admin.strings.choose_logo,
                button: { text: newsletter_admin.strings.use_image },
                multiple: false,
                library: { type: 'image' }
            });
            
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.url;
                
                $input.val(url).trigger('change');
                $preview.empty().append(
                    $('<img>', { src: url, alt: '' }).css({ maxWidth: '200px', maxHeight: '100px' })
                );
            });
            
            frame.open();
        });
        
        $('.newsletter-remove-logo').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.siblings('input[type="url"]').val('').trigger('change');
            $button.siblings('.newsletter-logo-preview').empty();
        });
    }
    
    initMediaUploader();
    
    /**
     * Real-time settings preview
     */
    function initSettingsPreview() {
        var $preview = $('.newsletter-template-preview');
        
        if ($preview.length === 0) {
            return;
        }
        
        // Update preview when settings change
        $('input[name="newsletter_brand_color"]').on('change', function() {
            var color = $(this).val();
            $preview.find('.preview-brand-color').css('color', color);
        });
        
        $('input[name="newsletter_logo_url"]').on('change', function() {
            var logoUrl = $(this).val();
            if (logoUrl) {
                $preview.find('.preview-logo').html('<img src="' + logoUrl + '" style="max-height: 40px;">');
            } else {
                $preview.find('.preview-logo').empty();
            }
        });
    }
    
    // Initialize settings preview
    initSettingsPreview();
    
})(jQuery);
