<?php
/**
 * Templates Admin View
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Newsletter Templates', 'edh-newsletter'); ?></h1>
    
    <!-- Template Customization -->
    <form method="post" action="options.php">
        <?php settings_fields('newsletter_template_settings'); ?>
        
        <div class="newsletter-form-section">
            <h3><?php esc_html_e('Template Customization', 'edh-newsletter'); ?></h3>
            
            <div class="newsletter-field-group">
                <label for="newsletter_brand_color"><?php esc_html_e('Brand Color', 'edh-newsletter'); ?></label>
                <div>
                    <input type="text" name="newsletter_brand_color" id="newsletter_brand_color" 
                           value="<?php echo esc_attr(get_option('newsletter_brand_color', '#1e73be')); ?>" 
                           class="newsletter-color-picker">
                    <p class="description"><?php esc_html_e('The primary color used in newsletter templates for headers, buttons, and links.', 'edh-newsletter'); ?></p>
                </div>
            </div>
            
            <div class="newsletter-field-group">
                <label for="newsletter_logo_url"><?php esc_html_e('Logo URL', 'edh-newsletter'); ?></label>
                <div>
                    <input type="url" name="newsletter_logo_url" id="newsletter_logo_url" 
                           value="<?php echo esc_attr(get_option('newsletter_logo_url', '')); ?>" 
                           class="regular-text">
                    <button type="button" class="button newsletter-upload-logo"><?php esc_html_e('Upload Logo', 'edh-newsletter'); ?></button>
                    <button type="button" class="button newsletter-remove-logo"><?php esc_html_e('Remove', 'edh-newsletter'); ?></button>
                    <p class="description"><?php esc_html_e('Logo to display at the top of newsletter emails (optional). Recommended size: 200x60px or smaller.', 'edh-newsletter'); ?></p>
                    <div class="newsletter-logo-preview">
                        <?php if (get_option('newsletter_logo_url')): ?>
                            <img src="<?php echo esc_url(get_option('newsletter_logo_url')); ?>" style="max-width: 200px; max-height: 100px; margin-top: 10px; border: 1px solid #ddd; padding: 10px;">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php submit_button(esc_html__('Save Template Settings', 'edh-newsletter')); ?>
        </div>
    </form>
    
    <!-- Template Preview -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Template Preview', 'edh-newsletter'); ?></h3>
        <p><?php esc_html_e('Preview how your newsletter emails will look with the current settings.', 'edh-newsletter'); ?></p>
        
        <div class="newsletter-template-preview" style="border: 1px solid #ddd; padding: 20px; background: #f9f9f9; max-width: 600px;">
            <div class="preview-logo" style="text-align: center; margin-bottom: 20px;">
                <?php if (get_option('newsletter_logo_url')): ?>
                    <img src="<?php echo esc_url(get_option('newsletter_logo_url')); ?>" style="max-height: 60px;">
                <?php else: ?>
                    <div style="color: #999; font-style: italic;"><?php esc_html_e('[Your Logo Here]', 'edh-newsletter'); ?></div>
                <?php endif; ?>
            </div>
            
            <h1 class="preview-brand-color" style="color: <?php echo esc_attr(get_option('newsletter_brand_color', '#1e73be')); ?>; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <?php echo esc_html(get_bloginfo('name')); ?> - Weekly Digest
            </h1>
            
            <p><?php esc_html_e('Hello! Here are the articles published over the past seven days:', 'edh-newsletter'); ?></p>
            
            <div style="margin-bottom: 25px; padding: 15px; background: #fff; border-radius: 5px; border-left: 4px solid <?php echo esc_attr(get_option('newsletter_brand_color', '#1e73be')); ?>;">
                <h3 style="margin: 0 0 10px 0; color: #333;">
                    <a href="#" style="color: <?php echo esc_attr(get_option('newsletter_brand_color', '#1e73be')); ?>; text-decoration: none; font-weight: bold;">
                        <?php esc_html_e('Sample Article Title', 'edh-newsletter'); ?>
                    </a>
                </h3>
                <p style="margin: 10px 0; color: #666; line-height: 1.5;">
                    <?php esc_html_e('This is a sample excerpt from an article that would appear in your newsletter digest...', 'edh-newsletter'); ?>
                </p>
                <div style="font-size: 0.9em; color: #888;">
                    <?php esc_html_e('Published: December 1, 2023 by Author Name', 'edh-newsletter'); ?>
                </div>
            </div>
            
            <p style="margin-top: 30px;"><?php esc_html_e('Thank you for subscribing!', 'edh-newsletter'); ?></p>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 0.9em; text-align: center; color: #666;">
                <p><?php esc_html_e('You received this email because you subscribed to our newsletter.', 'edh-newsletter'); ?></p>
                <p>
                    <a href="#" style="color: <?php echo esc_attr(get_option('newsletter_brand_color', '#1e73be')); ?>;"><?php esc_html_e('Manage Preferences', 'edh-newsletter'); ?></a> | 
                    <a href="#" style="color: <?php echo esc_attr(get_option('newsletter_brand_color', '#1e73be')); ?>;"><?php esc_html_e('Unsubscribe', 'edh-newsletter'); ?></a>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Test Email -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Send Test Email', 'edh-newsletter'); ?></h3>
        <p><?php esc_html_e('Send a test email to see how your template looks in an actual email client.', 'edh-newsletter'); ?></p>
        
        <form class="newsletter-test-email-form" method="post" action="#">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_email"><?php esc_html_e('Email Address', 'edh-newsletter'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="test_email" id="test_email" class="regular-text" required>
                        <p class="description"><?php esc_html_e('Enter the email address where you want to send the test.', 'edh-newsletter'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_frequency"><?php esc_html_e('Template Type', 'edh-newsletter'); ?></label>
                    </th>
                    <td>
                        <select name="test_frequency" id="test_frequency">
                            <option value="weekly"><?php esc_html_e('Weekly Digest', 'edh-newsletter'); ?></option>
                            <option value="monthly"><?php esc_html_e('Monthly Digest', 'edh-newsletter'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <button type="submit" class="button button-primary"><?php esc_html_e('Send Test Email', 'edh-newsletter'); ?></button>
        </form>
    </div>
    
    <!-- Template Files Information -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Template Files', 'edh-newsletter'); ?></h3>
        <p><?php esc_html_e('Advanced users can customize templates by creating template files in their theme.', 'edh-newsletter'); ?></p>
        
        <div style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 4px;">
            <h4 style="margin-top: 0; color: #0073aa;"><?php esc_html_e('Theme Template Override', 'edh-newsletter'); ?></h4>
            <p><?php esc_html_e('To customize newsletter templates, create a folder called "newsletter-templates" in your active theme directory and copy the template files from the plugin.', 'edh-newsletter'); ?></p>
            
            <p><strong><?php esc_html_e('Available Templates:', 'edh-newsletter'); ?></strong></p>
            <ul>
                <li><code>weekly-digest.php</code> - <?php esc_html_e('Weekly digest email template', 'edh-newsletter'); ?></li>
                <li><code>monthly-digest.php</code> - <?php esc_html_e('Monthly digest email template', 'edh-newsletter'); ?></li>
                <li><code>confirmation.php</code> - <?php esc_html_e('Subscription confirmation email', 'edh-newsletter'); ?></li>
                <li><code>welcome.php</code> - <?php esc_html_e('Welcome email after confirmation', 'edh-newsletter'); ?></li>
            </ul>
            
            <p><strong><?php esc_html_e('Template Location:', 'edh-newsletter'); ?></strong></p>
            <code><?php echo esc_html(get_template_directory() . '/newsletter-templates/'); ?></code>
            
            <p><strong><?php esc_html_e('Plugin Templates Location:', 'edh-newsletter'); ?></strong></p>
            <code><?php echo esc_html(EDH_NEWSLETTER_TEMPLATES_DIR); ?></code>
        </div>
    </div>
    
    <!-- Template Variables -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Template Variables', 'edh-newsletter'); ?></h3>
        <p><?php esc_html_e('When customizing templates, you can use these variables:', 'edh-newsletter'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4><?php esc_html_e('Digest Templates', 'edh-newsletter'); ?></h4>
                <ul style="font-family: monospace; font-size: 0.9em;">
                    <li><code>$posts</code> - <?php esc_html_e('Array of post data', 'edh-newsletter'); ?></li>
                    <li><code>$frequency</code> - <?php esc_html_e('weekly or monthly', 'edh-newsletter'); ?></li>
                    <li><code>$blog_name</code> - <?php esc_html_e('Site name', 'edh-newsletter'); ?></li>
                    <li><code>$subscriber</code> - <?php esc_html_e('Subscriber data', 'edh-newsletter'); ?></li>
                    <li><code>$unsubscribe_url</code> - <?php esc_html_e('Unsubscribe link', 'edh-newsletter'); ?></li>
                    <li><code>$manage_preferences_url</code> - <?php esc_html_e('Preferences link', 'edh-newsletter'); ?></li>
                </ul>
            </div>
            
            <div>
                <h4><?php esc_html_e('Confirmation Templates', 'edh-newsletter'); ?></h4>
                <ul style="font-family: monospace; font-size: 0.9em;">
                    <li><code>$subscriber</code> - <?php esc_html_e('Subscriber data', 'edh-newsletter'); ?></li>
                    <li><code>$confirmation_url</code> - <?php esc_html_e('Confirmation link', 'edh-newsletter'); ?></li>
                    <li><code>$blog_name</code> - <?php esc_html_e('Site name', 'edh-newsletter'); ?></li>
                    <li><code>$blog_url</code> - <?php esc_html_e('Site URL', 'edh-newsletter'); ?></li>
                    <li><code>$privacy_policy_url</code> - <?php esc_html_e('Privacy policy URL', 'edh-newsletter'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Shortcodes -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Newsletter Shortcodes', 'edh-newsletter'); ?></h3>
        <p><?php esc_html_e('Use these shortcodes to add newsletter signup forms to your posts and pages:', 'edh-newsletter'); ?></p>
        
        <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
            <h4><?php esc_html_e('Basic Signup Form', 'edh-newsletter'); ?></h4>
            <code>[newsletter_signup]</code>
            
            <h4 style="margin-top: 20px;"><?php esc_html_e('Customized Signup Form', 'edh-newsletter'); ?></h4>
            <code>[newsletter_signup title="Subscribe Now" description="Get our latest updates" button_text="Join Us" default_frequency="monthly" style="boxed"]</code>
            
            <h4 style="margin-top: 20px;"><?php esc_html_e('Preferences Management', 'edh-newsletter'); ?></h4>
            <code>[newsletter_preferences]</code>
            
            <h4 style="margin-top: 20px;"><?php esc_html_e('Legacy Shortcode (still supported)', 'edh-newsletter'); ?></h4>
            <code>[weekly_digest_signup]</code>
        </div>
        
        <h4><?php esc_html_e('Shortcode Parameters', 'edh-newsletter'); ?></h4>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Parameter', 'edh-newsletter'); ?></th>
                    <th><?php esc_html_e('Description', 'edh-newsletter'); ?></th>
                    <th><?php esc_html_e('Default', 'edh-newsletter'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>title</code></td>
                    <td><?php esc_html_e('Form title', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Subscribe to Our Newsletter', 'edh-newsletter'); ?></td>
                </tr>
                <tr>
                    <td><code>description</code></td>
                    <td><?php esc_html_e('Form description text', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Get the latest updates...', 'edh-newsletter'); ?></td>
                </tr>
                <tr>
                    <td><code>button_text</code></td>
                    <td><?php esc_html_e('Submit button text', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Subscribe', 'edh-newsletter'); ?></td>
                </tr>
                <tr>
                    <td><code>show_frequency</code></td>
                    <td><?php esc_html_e('Show frequency selection (true/false)', 'edh-newsletter'); ?></td>
                    <td>true</td>
                </tr>
                <tr>
                    <td><code>default_frequency</code></td>
                    <td><?php esc_html_e('Default frequency (weekly/monthly)', 'edh-newsletter'); ?></td>
                    <td>weekly</td>
                </tr>
                <tr>
                    <td><code>style</code></td>
                    <td><?php esc_html_e('Form style (default/minimal/boxed/inline)', 'edh-newsletter'); ?></td>
                    <td>default</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
