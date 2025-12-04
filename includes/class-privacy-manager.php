<?php
/**
 * Privacy Manager Class
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Privacy Manager
 * 
 * Handles GDPR compliance, data protection, and privacy features
 */
class EDH_Newsletter_Privacy_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WordPress privacy hooks
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_data_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_data_eraser']);
        
        // Cleanup hooks
        add_action('edh_newsletter_cleanup_expired_data', [$this, 'cleanup_expired_data']);
        
        // Privacy policy content
        add_action('admin_init', [$this, 'add_privacy_policy_content']);
    }
    
    /**
     * Register data exporter for GDPR compliance
     */
    public function register_data_exporter(array $exporters): array {
        $exporters['newsletter-subscriber-data'] = [
            'exporter_friendly_name' => __('Newsletter Subscriber Data', 'edh-newsletter'),
            'callback' => [$this, 'export_subscriber_data'],
        ];
        
        return $exporters;
    }
    
    /**
     * Register data eraser for GDPR compliance
     */
    public function register_data_eraser(array $erasers): array {
        $erasers['newsletter-subscriber-data'] = [
            'eraser_friendly_name' => __('Newsletter Subscriber Data', 'edh-newsletter'),
            'callback' => [$this, 'erase_subscriber_data'],
        ];
        
        return $erasers;
    }
    
    /**
     * Export subscriber data for GDPR requests
     */
    public function export_subscriber_data(string $email_address, int $page = 1): array {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            return [
                'data' => [],
                'done' => true,
            ];
        }
        
        $subscriber = $subscriber_manager->get_subscriber_by_email($email_address);
        
        if (!$subscriber) {
            return [
                'data' => [],
                'done' => true,
            ];
        }
        
        $data_to_export = [];
        
        // Basic subscriber information
        $subscriber_data = [
            [
                'name' => __('Email Address', 'edh-newsletter'),
                'value' => $subscriber['email'],
            ],
            [
                'name' => __('Subscription Status', 'edh-newsletter'),
                'value' => ucfirst($subscriber['status']),
            ],
            [
                'name' => __('Digest Frequency', 'edh-newsletter'),
                'value' => ucfirst($subscriber['digest_frequency']),
            ],
            [
                'name' => __('Subscription Date', 'edh-newsletter'),
                'value' => $subscriber['created_at'],
            ],
            [
                'name' => __('Privacy Consent Date', 'edh-newsletter'),
                'value' => $subscriber['privacy_consent_date'] ?? __('Not recorded', 'edh-newsletter'),
            ],
            [
                'name' => __('Consent Version', 'edh-newsletter'),
                'value' => $subscriber['consent_version'] ?? __('Not recorded', 'edh-newsletter'),
            ],
            [
                'name' => __('Last Engagement', 'edh-newsletter'),
                'value' => $subscriber['last_engagement_date'] ?? __('Never', 'edh-newsletter'),
            ],
        ];
        
        // Add preferences if available
        if (!empty($subscriber['preferences'])) {
            $preferences = is_array($subscriber['preferences']) 
                ? $subscriber['preferences'] 
                : json_decode($subscriber['preferences'], true);
            
            if ($preferences) {
                foreach ($preferences as $key => $value) {
                    $subscriber_data[] = [
                        // translators: %s: Preference key name (e.g., "unsubscribe_reason")
                        'name' => sprintf(__('Preference: %s', 'edh-newsletter'), ucfirst(str_replace('_', ' ', $key))),
                        'value' => is_array($value) ? json_encode($value) : (string) $value,
                    ];
                }
            }
        }
        
        $data_to_export[] = [
            'group_id' => 'newsletter-subscriber',
            'group_label' => __('Newsletter Subscription Data', 'edh-newsletter'),
            'item_id' => 'subscriber-' . $subscriber['id'],
            'data' => $subscriber_data,
        ];
        
        return [
            'data' => $data_to_export,
            'done' => true,
        ];
    }
    
    /**
     * Erase subscriber data for GDPR requests
     */
    public function erase_subscriber_data(string $email_address, int $page = 1): array {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            return [
                'items_removed' => false,
                'items_retained' => false,
                'messages' => [__('Newsletter module not available', 'edh-newsletter')],
                'done' => true,
            ];
        }
        
        $subscriber = $subscriber_manager->get_subscriber_by_email($email_address);
        
        if (!$subscriber) {
            return [
                'items_removed' => false,
                'items_retained' => false,
                'messages' => [__('No newsletter data found for this email address', 'edh-newsletter')],
                'done' => true,
            ];
        }
        
        // Delete the subscriber completely
        $result = $subscriber_manager->delete_subscriber($subscriber['id']);
        
        if ($result['success']) {
            return [
                'items_removed' => true,
                'items_retained' => false,
                'messages' => [__('Newsletter subscription data has been removed', 'edh-newsletter')],
                'done' => true,
            ];
        } else {
            return [
                'items_removed' => false,
                'items_retained' => true,
                'messages' => [__('Failed to remove newsletter data: ', 'edh-newsletter') . $result['error']],
                'done' => true,
            ];
        }
    }
    
    /**
     * Validate privacy consent
     */
    public function validate_consent(array $subscriber_data): array {
        $errors = [];
        
        // Check if privacy consent is required
        $require_consent = get_option('newsletter_require_privacy_consent', true);
        
        if ($require_consent) {
            // Check if consent was provided
            if (empty($subscriber_data['privacy_consent'])) {
                $errors[] = __('Privacy consent is required to subscribe', 'edh-newsletter');
            }
            
            // Check if privacy policy URL is configured
            $privacy_policy_url = get_option('newsletter_privacy_policy_url', '');
            if (empty($privacy_policy_url)) {
                $errors[] = __('Privacy policy URL must be configured', 'edh-newsletter');
            }
        }
        
        return $errors;
    }
    
    /**
     * Record privacy consent
     */
    public function record_consent(int $subscriber_id, array $consent_data = []): bool {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            return false;
        }
        
        $consent_version = get_option('newsletter_consent_version', '1.0');
        
        $update_data = [
            'privacy_consent_date' => current_time('mysql'),
            'consent_version' => $consent_version,
        ];
        
        // Store additional consent metadata
        if (!empty($consent_data)) {
            $subscriber = $subscriber_manager->get_subscriber($subscriber_id);
            $preferences = $subscriber['preferences'] ?? [];
            $preferences['consent_metadata'] = $consent_data;
            $update_data['preferences'] = json_encode($preferences);
        }
        
        $result = $subscriber_manager->update_subscriber($subscriber_id, $update_data);
        
        return $result['success'];
    }
    
    /**
     * Check if consent is still valid
     */
    public function is_consent_valid(array $subscriber): bool {
        $current_version = get_option('newsletter_consent_version', '1.0');
        $subscriber_version = $subscriber['consent_version'] ?? '';
        
        // If versions don't match, consent needs to be renewed
        if ($subscriber_version !== $current_version) {
            return false;
        }
        
        // Check if consent has expired based on retention policy
        $retention_days = get_option('newsletter_consent_retention_days', 0);
        
        if ($retention_days > 0 && !empty($subscriber['privacy_consent_date'])) {
            $consent_date = strtotime($subscriber['privacy_consent_date']);
            $expiry_date = strtotime("+{$retention_days} days", $consent_date);
            
            if (time() > $expiry_date) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get privacy policy content for the plugin
     */
    public function get_privacy_policy_content(): string {
        $content = '<h2>' . __('Newsletter Subscription', 'edh-newsletter') . '</h2>';
        
        $content .= '<p>' . __('When you subscribe to our newsletter, we collect and process the following information:', 'edh-newsletter') . '</p>';
        
        $content .= '<ul>';
        $content .= '<li>' . __('Email address - to send you the newsletter', 'edh-newsletter') . '</li>';
        $content .= '<li>' . __('Subscription preferences - to customize your newsletter experience', 'edh-newsletter') . '</li>';
        $content .= '<li>' . __('Engagement data - to improve our content and delivery', 'edh-newsletter') . '</li>';
        $content .= '</ul>';
        
        $content .= '<h3>' . __('How We Use Your Information', 'edh-newsletter') . '</h3>';
        $content .= '<p>' . __('We use your email address solely to send you our newsletter content. We do not share your email address with third parties without your explicit consent.', 'edh-newsletter') . '</p>';
        
        $content .= '<h3>' . __('Your Rights', 'edh-newsletter') . '</h3>';
        $content .= '<p>' . __('You have the right to:', 'edh-newsletter') . '</p>';
        $content .= '<ul>';
        $content .= '<li>' . __('Unsubscribe at any time using the link in our emails', 'edh-newsletter') . '</li>';
        $content .= '<li>' . __('Request a copy of your data', 'edh-newsletter') . '</li>';
        $content .= '<li>' . __('Request deletion of your data', 'edh-newsletter') . '</li>';
        $content .= '<li>' . __('Update your subscription preferences', 'edh-newsletter') . '</li>';
        $content .= '</ul>';
        
        $retention_days = get_option('newsletter_data_retention_days', 365);
        if ($retention_days > 0) {
            $content .= '<h3>' . __('Data Retention', 'edh-newsletter') . '</h3>';
            $content .= '<p>' . sprintf(
                // translators: %d: Number of days data is retained
                __('We retain your subscription data for %d days after you unsubscribe, after which it is automatically deleted.', 'edh-newsletter'),
                $retention_days
            ) . '</p>';
        }
        
        return $content;
    }
    
    /**
     * Add privacy policy content to WordPress privacy policy guide
     */
    public function add_privacy_policy_content(): void {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        
        wp_add_privacy_policy_content(
            __('Newsletter Plugin', 'edh-newsletter'),
            $this->get_privacy_policy_content()
        );
    }
    
    /**
     * Cleanup expired data based on retention policies
     */
    public function cleanup_expired_data(): void {
        $this->cleanup_expired_subscribers();
        $this->cleanup_expired_tokens();
        $this->cleanup_expired_logs();
    }
    
    /**
     * Cleanup expired subscribers
     */
    private function cleanup_expired_subscribers(): void {
        global $wpdb;
        
        $retention_days = get_option('newsletter_data_retention_days', 365);
        
        if ($retention_days <= 0) {
            return; // No cleanup if retention is disabled
        }
        
        $table_name = esc_sql($wpdb->prefix . 'newsletter_subscribers');
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Delete unsubscribed subscribers older than retention period
        // Note: $wpdb->delete() doesn't support comparison operators, so we use a prepared query
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table_name}` WHERE `status` = %s AND `updated_at` < %s",
            'unsubscribed',
            $cutoff_date
        ));
        
        if ($deleted > 0) {
            do_action('edh_newsletter_expired_subscribers_cleaned', $deleted);
        }
    }
    
    /**
     * Cleanup expired confirmation tokens
     */
    private function cleanup_expired_tokens(): void {
        global $wpdb;
        
        $token_expiry_hours = get_option('newsletter_token_expiry_hours', 24);
        $table_name = esc_sql($wpdb->prefix . 'newsletter_subscribers');
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$token_expiry_hours} hours"));
        
        // Clear tokens from pending subscribers older than expiry time
        // Note: $wpdb->update() doesn't support comparison operators, so we use a prepared query
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE `{$table_name}` SET `token` = %s WHERE `status` = %s AND `created_at` < %s",
            '',
            'pending',
            $cutoff_date
        ));
        
        if ($updated > 0) {
            do_action('edh_newsletter_expired_tokens_cleaned', $updated);
        }
    }
    
    /**
     * Cleanup expired log entries (if logging is implemented)
     */
    private function cleanup_expired_logs(): void {
        // This would cleanup any log tables if implemented
        // For now, just fire an action for extensibility
        do_action('edh_newsletter_cleanup_logs');
    }
    
    /**
     * Generate privacy-compliant unsubscribe reasons
     */
    public function get_unsubscribe_reasons(): array {
        return apply_filters('edh_newsletter_unsubscribe_reasons', [
            'too_frequent' => __('Emails are too frequent', 'edh-newsletter'),
            'not_relevant' => __('Content is not relevant to me', 'edh-newsletter'),
            'never_signed_up' => __('I never signed up for this', 'edh-newsletter'),
            'temporary_break' => __('I want a temporary break', 'edh-newsletter'),
            'privacy_concerns' => __('Privacy concerns', 'edh-newsletter'),
            'other' => __('Other reason', 'edh-newsletter'),
        ]);
    }
    
    /**
     * Anonymize subscriber data (alternative to deletion)
     */
    public function anonymize_subscriber(int $subscriber_id): bool {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            return false;
        }
        
        $anonymized_email = 'anonymized_' . $subscriber_id . '@deleted.local';
        
        $result = $subscriber_manager->update_subscriber($subscriber_id, [
            'email' => $anonymized_email,
            'status' => 'unsubscribed',
            'token' => '',
            'preferences' => json_encode(['anonymized' => true, 'anonymized_at' => current_time('mysql')]),
        ]);
        
        if ($result['success']) {
            do_action('edh_newsletter_subscriber_anonymized', $subscriber_id);
        }
        
        return $result['success'];
    }
    
    /**
     * Check if data processing consent is required
     */
    public function is_consent_required(): bool {
        return (bool) get_option('newsletter_require_privacy_consent', true);
    }
    
    /**
     * Get consent text for forms
     */
    public function get_consent_text(): string {
        $privacy_url = get_option('newsletter_privacy_policy_url', '');
        
        if ($privacy_url) {
            return sprintf(
                // translators: %s: Privacy policy URL
                __('I agree to the processing of my email address for newsletter purposes as described in the <a href="%s" target="_blank">Privacy Policy</a>.', 'edh-newsletter'),
                esc_url($privacy_url)
            );
        }
        
        return __('I agree to receive newsletter emails and understand I can unsubscribe at any time.', 'edh-newsletter');
    }
    
    /**
     * Validate email address for privacy compliance
     */
    public function validate_email_privacy(string $email): array {
        $errors = [];
        
        // Check if email is on any suppression lists
        if ($this->is_email_suppressed($email)) {
            $errors[] = __('This email address has been suppressed and cannot be subscribed.', 'edh-newsletter');
        }
        
        // Check for disposable email domains (optional)
        if (get_option('newsletter_block_disposable_emails', false)) {
            if ($this->is_disposable_email($email)) {
                $errors[] = __('Disposable email addresses are not allowed.', 'edh-newsletter');
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if email is suppressed
     */
    private function is_email_suppressed(string $email): bool {
        // This could integrate with external suppression lists
        // For now, just check a simple option
        $suppressed_emails = get_option('newsletter_suppressed_emails', '');
        $suppressed_list = array_filter(array_map('trim', explode("\n", $suppressed_emails)));
        
        return in_array(strtolower($email), array_map('strtolower', $suppressed_list));
    }
    
    /**
     * Check if email is from a disposable domain
     */
    private function is_disposable_email(string $email): bool {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        
        // Common disposable email domains
        $disposable_domains = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
            'tempmail.org', 'throwaway.email', 'temp-mail.org'
        ];
        
        $disposable_domains = apply_filters('edh_newsletter_disposable_email_domains', $disposable_domains);
        
        return in_array($domain, $disposable_domains);
    }
}
