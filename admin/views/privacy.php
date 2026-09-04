<?php
/**
 * Privacy Admin View
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Newsletter Privacy Settings', 'edh-newsletter'); ?></h1>
    
    <!-- Privacy Settings Form -->
    <form method="post" action="options.php">
        <?php settings_fields('newsletter_privacy_settings'); ?>
        
        <div class="newsletter-form-section">
            <h3><?php esc_html_e('GDPR Compliance Settings', 'edh-newsletter'); ?></h3>
            
            <div class="newsletter-field-group">
                <label for="newsletter_require_privacy_consent"><?php esc_html_e('Require Privacy Consent', 'edh-newsletter'); ?></label>
                <div>
                    <label>
                        <input type="checkbox" name="newsletter_require_privacy_consent" id="newsletter_require_privacy_consent" 
                               value="1" <?php checked(get_option('newsletter_require_privacy_consent', 1)); ?>>
                        <?php esc_html_e('Require users to explicitly consent to data processing', 'edh-newsletter'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('When enabled, users must check a consent checkbox before subscribing.', 'edh-newsletter'); ?></p>
                </div>
            </div>
            
            <div class="newsletter-field-group">
                <label for="newsletter_privacy_policy_url"><?php esc_html_e('Privacy Policy URL', 'edh-newsletter'); ?></label>
                <div>
                    <input type="url" name="newsletter_privacy_policy_url" id="newsletter_privacy_policy_url" 
                           value="<?php echo esc_attr(get_option('newsletter_privacy_policy_url', '')); ?>" 
                           class="regular-text">
                    <p class="description"><?php esc_html_e('Link to your privacy policy page. This will be shown in consent forms and emails.', 'edh-newsletter'); ?></p>
                </div>
            </div>
            
            <div class="newsletter-field-group">
                <label for="newsletter_consent_version"><?php esc_html_e('Consent Version', 'edh-newsletter'); ?></label>
                <div>
                    <input type="text" name="newsletter_consent_version" id="newsletter_consent_version" 
                           value="<?php echo esc_attr(get_option('newsletter_consent_version', '1.0')); ?>" 
                           class="small-text">
                    <p class="description"><?php esc_html_e('Update this version when you change your privacy policy to track consent versions.', 'edh-newsletter'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="newsletter-form-section">
            <h3><?php esc_html_e('Data Retention Settings', 'edh-newsletter'); ?></h3>
            
            <div class="newsletter-field-group">
                <label for="newsletter_data_retention_days"><?php esc_html_e('Data Retention Period', 'edh-newsletter'); ?></label>
                <div>
                    <input type="number" name="newsletter_data_retention_days" id="newsletter_data_retention_days" 
                           value="<?php echo esc_attr(get_option('newsletter_data_retention_days', 365)); ?>" 
                           min="0" max="3650" class="small-text">
                    <span><?php esc_html_e('days', 'edh-newsletter'); ?></span>
                    <p class="description">
                        <?php esc_html_e('How long to keep unsubscribed user data before automatic deletion. Set to 0 to disable automatic cleanup.', 'edh-newsletter'); ?>
                        <br>
                        <strong><?php esc_html_e('Recommended: 365 days (1 year)', 'edh-newsletter'); ?></strong>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="newsletter-form-section">
            <h3><?php esc_html_e('Spam Protection', 'edh-newsletter'); ?></h3>
            <p class="description"><?php esc_html_e('A hidden honeypot field and a minimum fill time are always active on the signup and preferences forms. The settings below tune the remaining controls.', 'edh-newsletter'); ?></p>
            
            <div class="newsletter-field-group">
                <label for="newsletter_spam_min_seconds"><?php esc_html_e('Minimum Fill Time', 'edh-newsletter'); ?></label>
                <div>
                    <input type="number" name="newsletter_spam_min_seconds" id="newsletter_spam_min_seconds"
                           value="<?php echo esc_attr((string) get_option('newsletter_spam_min_seconds', 3)); ?>"
                           min="0" max="60" class="small-text">
                    <span><?php esc_html_e('seconds', 'edh-newsletter'); ?></span>
                    <p class="description"><?php esc_html_e('Submissions faster than this after the page loads are treated as bots. 0 disables the check.', 'edh-newsletter'); ?></p>
                </div>
            </div>
            
            <div class="newsletter-field-group">
                <label for="newsletter_spam_max_per_hour"><?php esc_html_e('Submissions per IP per hour', 'edh-newsletter'); ?></label>
                <div>
                    <input type="number" name="newsletter_spam_max_per_hour" id="newsletter_spam_max_per_hour"
                           value="<?php echo esc_attr((string) get_option('newsletter_spam_max_per_hour', 10)); ?>"
                           min="1" max="1000" class="small-text">
                    <p class="description"><?php esc_html_e('Each email address is also limited to 3 submissions per hour, which stops the form being used to flood someone else\'s inbox.', 'edh-newsletter'); ?></p>
                </div>
            </div>
            
            <div class="newsletter-field-group">
                <label for="newsletter_block_disposable_emails"><?php esc_html_e('Disposable Addresses', 'edh-newsletter'); ?></label>
                <div>
                    <label>
                        <input type="checkbox" name="newsletter_block_disposable_emails" id="newsletter_block_disposable_emails"
                               value="1" <?php checked(get_option('newsletter_block_disposable_emails', 1)); ?>>
                        <?php esc_html_e('Reject signups from known disposable email domains', 'edh-newsletter'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Extend the built-in list with the edh_newsletter_disposable_email_domains filter.', 'edh-newsletter'); ?></p>
                </div>
            </div>
            
            <div class="newsletter-field-group">
                <label for="newsletter_suppressed_emails"><?php esc_html_e('Suppression List', 'edh-newsletter'); ?></label>
                <div>
                    <textarea name="newsletter_suppressed_emails" id="newsletter_suppressed_emails" rows="5" class="large-text code"><?php echo esc_textarea((string) get_option('newsletter_suppressed_emails', '')); ?></textarea>
                    <p class="description"><?php esc_html_e('One entry per line. A full address blocks that address; a domain (e.g. example.com) blocks every address at it.', 'edh-newsletter'); ?></p>
                </div>
            </div>
            
            <div class="newsletter-field-group">
                <label for="newsletter_turnstile_site_key"><?php esc_html_e('Cloudflare Turnstile', 'edh-newsletter'); ?></label>
                <div>
                    <input type="text" name="newsletter_turnstile_site_key" id="newsletter_turnstile_site_key"
                           value="<?php echo esc_attr((string) get_option('newsletter_turnstile_site_key', '')); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e('Site key', 'edh-newsletter'); ?>" autocomplete="off">
                    <br>
                    <input type="password" name="newsletter_turnstile_secret_key" id="newsletter_turnstile_secret_key"
                           value="<?php echo esc_attr((string) get_option('newsletter_turnstile_secret_key', '')); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e('Secret key', 'edh-newsletter'); ?>" autocomplete="new-password" style="margin-top: 6px;">
                    <p class="description">
                        <?php esc_html_e('Optional. When both keys are set, a Turnstile challenge is added to the forms and verified on submit. Turnstile is free and shows no puzzle to most visitors.', 'edh-newsletter'); ?>
                        <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get keys', 'edh-newsletter'); ?></a>
                    </p>
                </div>
            </div>
        </div>
        
        <?php submit_button(esc_html__('Save Privacy Settings', 'edh-newsletter')); ?>
    </form>
    
    <!-- Privacy Information -->
    <div class="newsletter-privacy-info">
        <h4><?php esc_html_e('Privacy Compliance Information', 'edh-newsletter'); ?></h4>
        <p><?php esc_html_e('This plugin is designed to help you comply with privacy regulations like GDPR. Here\'s what it does:', 'edh-newsletter'); ?></p>
        
        <ul>
            <li><strong><?php esc_html_e('Double Opt-in:', 'edh-newsletter'); ?></strong> <?php esc_html_e('All subscriptions require email confirmation', 'edh-newsletter'); ?></li>
            <li><strong><?php esc_html_e('Consent Tracking:', 'edh-newsletter'); ?></strong> <?php esc_html_e('Records when and how users gave consent', 'edh-newsletter'); ?></li>
            <li><strong><?php esc_html_e('Data Export:', 'edh-newsletter'); ?></strong> <?php esc_html_e('Integrates with WordPress privacy tools for data export', 'edh-newsletter'); ?></li>
            <li><strong><?php esc_html_e('Data Erasure:', 'edh-newsletter'); ?></strong> <?php esc_html_e('Supports complete data deletion on request', 'edh-newsletter'); ?></li>
            <li><strong><?php esc_html_e('Automatic Cleanup:', 'edh-newsletter'); ?></strong> <?php esc_html_e('Removes old unsubscribed data automatically', 'edh-newsletter'); ?></li>
            <li><strong><?php esc_html_e('Secure Tokens:', 'edh-newsletter'); ?></strong> <?php esc_html_e('Uses cryptographically secure tokens for all actions', 'edh-newsletter'); ?></li>
        </ul>
    </div>
    
    <!-- WordPress Privacy Integration -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('WordPress Privacy Integration', 'edh-newsletter'); ?></h3>
        <p><?php esc_html_e('This plugin integrates with WordPress\'s built-in privacy tools:', 'edh-newsletter'); ?></p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4><?php esc_html_e('Privacy Policy Content', 'edh-newsletter'); ?></h4>
                <p><?php esc_html_e('The plugin automatically adds suggested privacy policy content to WordPress\'s privacy policy guide.', 'edh-newsletter'); ?></p>
                <a href="<?php echo esc_url(admin_url('options-privacy.php')); ?>" class="button button-secondary">
                    <?php esc_html_e('View Privacy Policy Guide', 'edh-newsletter'); ?>
                </a>
            </div>
            
            <div>
                <h4><?php esc_html_e('Personal Data Requests', 'edh-newsletter'); ?></h4>
                <p><?php esc_html_e('Newsletter data is automatically included in WordPress privacy export and erasure requests.', 'edh-newsletter'); ?></p>
                <?php
                $edh_newsletter_export_url = admin_url('tools.php?page=export_personal_data');
                $edh_newsletter_erase_url = admin_url('tools.php?page=erase_personal_data');
                ?>
                <a href="<?php echo esc_url($edh_newsletter_export_url); ?>" class="button button-secondary">
                    <?php esc_html_e('Export Personal Data', 'edh-newsletter'); ?>
                </a>
                <a href="<?php echo esc_url($edh_newsletter_erase_url); ?>" class="button button-secondary">
                    <?php esc_html_e('Erase Personal Data', 'edh-newsletter'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Data Processing Information -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Data Processing Details', 'edh-newsletter'); ?></h3>
        
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Data Type', 'edh-newsletter'); ?></th>
                    <th><?php esc_html_e('Purpose', 'edh-newsletter'); ?></th>
                    <th><?php esc_html_e('Legal Basis', 'edh-newsletter'); ?></th>
                    <th><?php esc_html_e('Retention', 'edh-newsletter'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php esc_html_e('Email Address', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Send newsletter emails', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Consent', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Until unsubscribed + retention period', 'edh-newsletter'); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Subscription Preferences', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Customize newsletter frequency', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Consent', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Until unsubscribed + retention period', 'edh-newsletter'); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Engagement Data', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Improve newsletter content', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Legitimate Interest', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Until unsubscribed + retention period', 'edh-newsletter'); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('IP Address (optional)', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Fraud prevention', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('Legitimate Interest', 'edh-newsletter'); ?></td>
                    <td><?php esc_html_e('30 days', 'edh-newsletter'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Privacy Actions -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Privacy Management Actions', 'edh-newsletter'); ?></h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4><?php esc_html_e('Manual Data Cleanup', 'edh-newsletter'); ?></h4>
                <p><?php esc_html_e('Manually trigger cleanup of expired subscriber data.', 'edh-newsletter'); ?></p>
                <button type="button" class="button button-secondary newsletter-trigger-cleanup" data-action="cleanup">
                    <?php esc_html_e('Run Cleanup Now', 'edh-newsletter'); ?>
                </button>
            </div>
            
            <div>
                <h4><?php esc_html_e('Consent Statistics', 'edh-newsletter'); ?></h4>
                <?php
                $edh_newsletter_subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
                if ($edh_newsletter_subscriber_manager) {
                    $edh_newsletter_total_subscribers = $edh_newsletter_subscriber_manager->get_subscriber_count(['status' => ['subscribed', 'pending', 'paused']]);
                    $edh_newsletter_consented_subscribers = $edh_newsletter_subscriber_manager->get_subscriber_count([
                        'status' => ['subscribed', 'pending', 'paused']
                        // Add filter for consent date not null
                    ]);
                    
                    echo '<p>' . sprintf(
                        // translators: %1$d: Number of subscribers with recorded consent, %2$d: Total number of active subscribers
                        esc_html__('%1$d of %2$d active subscribers have recorded consent.', 'edh-newsletter'),
                        absint($edh_newsletter_consented_subscribers),
                        absint($edh_newsletter_total_subscribers)
                    ) . '</p>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Compliance Checklist -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('GDPR Compliance Checklist', 'edh-newsletter'); ?></h3>
        
        <div style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 4px;">
            <?php
            $edh_newsletter_privacy_policy_url = get_option('newsletter_privacy_policy_url', '');
            $edh_newsletter_require_consent = get_option('newsletter_require_privacy_consent', 1);
            $edh_newsletter_retention_days = get_option('newsletter_data_retention_days', 365);
            ?>
            
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 10px;">
                    <?php if ($edh_newsletter_require_consent): ?>
                        <span style="color: green;">✓</span>
                    <?php else: ?>
                        <span style="color: red;">✗</span>
                    <?php endif; ?>
                    <?php esc_html_e('Privacy consent is required for new subscriptions', 'edh-newsletter'); ?>
                </li>
                
                <li style="margin-bottom: 10px;">
                    <?php if (!empty($edh_newsletter_privacy_policy_url)): ?>
                        <span style="color: green;">✓</span>
                    <?php else: ?>
                        <span style="color: red;">✗</span>
                    <?php endif; ?>
                    <?php esc_html_e('Privacy policy URL is configured', 'edh-newsletter'); ?>
                </li>
                
                <li style="margin-bottom: 10px;">
                    <?php if ($edh_newsletter_retention_days > 0): ?>
                        <span style="color: green;">✓</span>
                    <?php else: ?>
                        <span style="color: orange;">⚠</span>
                    <?php endif; ?>
                    <?php esc_html_e('Data retention period is configured', 'edh-newsletter'); ?>
                </li>
                
                <li style="margin-bottom: 10px;">
                    <span style="color: green;">✓</span>
                    <?php esc_html_e('Double opt-in is enforced for all subscriptions', 'edh-newsletter'); ?>
                </li>
                
                <li style="margin-bottom: 10px;">
                    <span style="color: green;">✓</span>
                    <?php esc_html_e('WordPress privacy tools integration is active', 'edh-newsletter'); ?>
                </li>
                
                <li style="margin-bottom: 10px;">
                    <span style="color: green;">✓</span>
                    <?php esc_html_e('Secure unsubscribe and preference management links', 'edh-newsletter'); ?>
                </li>
            </ul>
            
            <?php if (!$edh_newsletter_require_consent || empty($edh_newsletter_privacy_policy_url)): ?>
                <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                    <strong><?php esc_html_e('Action Required:', 'edh-newsletter'); ?></strong>
                    <?php esc_html_e('Please complete the missing items above to ensure full GDPR compliance.', 'edh-newsletter'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
