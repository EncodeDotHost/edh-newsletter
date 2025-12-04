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
            var action = $(this).find('select[name="action"]').val();
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
     * Initialize media uploader for logo
     */
    function initMediaUploader() {
        var mediaUploader;
        
        $('.newsletter-upload-logo').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('input[type="url"]');
            var $preview = $button.siblings('.newsletter-logo-preview');
            
            // If the media uploader already exists, reopen it
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            // Create the media uploader
            mediaUploader = wp.media({
                title: 'Choose Logo',
                button: {
                    text: 'Use This Image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // When an image is selected
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $input.val(attachment.url);
                $preview.html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 100px;">');
            });
            
            // Open the uploader
            mediaUploader.open();
        });
        
        // Remove logo
        $('.newsletter-remove-logo').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('input[type="url"]');
            var $preview = $button.siblings('.newsletter-logo-preview');
            
            $input.val('');
            $preview.empty();
        });
    }
    
    // Initialize media uploader if wp.media is available
    if (typeof wp !== 'undefined' && wp.media) {
        initMediaUploader();
    }
    
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
