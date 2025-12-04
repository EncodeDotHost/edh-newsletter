<?php
/**
 * Email Sender Class
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Email Sender
 * 
 * Handles email delivery, tracking, and queue management
 */
class EDH_Newsletter_Email_Sender {
    
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
        add_action('edh_newsletter_send_weekly_digest', [$this, 'send_weekly_digest']);
        add_action('edh_newsletter_send_monthly_digest', [$this, 'send_monthly_digest']);
        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
    }
    
    /**
     * Send weekly digest to all weekly subscribers
     */
    public function send_weekly_digest(): void {
        $this->send_digest('weekly');
    }
    
    /**
     * Send monthly digest to all monthly subscribers
     */
    public function send_monthly_digest(): void {
        $this->send_digest('monthly');
    }
    
    /**
     * Send digest emails based on frequency
     */
    public function send_digest(string $frequency): void {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        $template_manager = EDH_Newsletter_Core::get_instance()->get_module('template_manager');
        
        if (!$subscriber_manager || !$template_manager) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Newsletter: Required modules not available for digest sending');
            }
            return;
        }
        
        // Get subscribers for this frequency
        $subscribers = $subscriber_manager->get_subscribers([
            'status' => 'subscribed',
            'frequency' => $frequency
        ]);
        
        if (empty($subscribers)) {
            return;
        }
        
        // Get posts for the digest period
        $posts = $this->get_digest_posts($frequency);
        
        // Generate email content
        $email_data = [
            'posts' => $posts,
            'frequency' => $frequency,
            'subscriber_count' => count($subscribers),
            'blog_name' => get_bloginfo('name'),
            'blog_url' => home_url(),
        ];
        
        $subject = $this->generate_subject($posts, $frequency);
        
        // Send emails to subscribers
        foreach ($subscribers as $subscriber) {
            $this->send_digest_email($subscriber, $subject, $email_data, $template_manager);
        }
        
        // Log digest sending
        do_action('edh_newsletter_digest_sent', $frequency, count($subscribers), count($posts));
    }
    
    /**
     * Send individual digest email to subscriber
     */
    private function send_digest_email(array $subscriber, string $subject, array $email_data, $template_manager): bool {
        // Add subscriber-specific data
        $email_data['subscriber'] = $subscriber;
        $email_data['unsubscribe_url'] = $this->generate_unsubscribe_url($subscriber);
        $email_data['manage_preferences_url'] = $this->generate_preferences_url($subscriber);
        
        // Generate email content using template manager
        $email_content = $template_manager->render_digest_template($email_data);
        
        // Prepare headers
        $headers = $this->get_email_headers();
        
        // Send email
        $sent = wp_mail($subscriber['email'], $subject, $email_content, $headers);
        
        if ($sent) {
            // Update engagement tracking
            $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
            if ($subscriber_manager) {
                $subscriber_manager->update_engagement($subscriber['id']);
            }
            
            do_action('edh_newsletter_email_sent', $subscriber, $subject);
        } else {
            do_action('edh_newsletter_email_failed', $subscriber, $subject);
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log("Newsletter: Failed to send email to {$subscriber['email']}");
            }
        }
        
        return $sent;
    }
    
    /**
     * Send confirmation email to new subscriber
     */
    public function send_confirmation_email(array $subscriber): bool {
        $template_manager = EDH_Newsletter_Core::get_instance()->get_module('template_manager');
        
        if (!$template_manager) {
            return false;
        }
        
        $confirmation_url = $this->generate_confirmation_url($subscriber['token']);
        
        $email_data = [
            'subscriber' => $subscriber,
            'confirmation_url' => $confirmation_url,
            'blog_name' => get_bloginfo('name'),
            'blog_url' => home_url(),
            'privacy_policy_url' => get_option('newsletter_privacy_policy_url', ''),
        ];
        
        $subject = sprintf(
            // translators: %1$s: Site name
            __('Please confirm your subscription to %1$s', 'edh-newsletter'),
            get_bloginfo('name')
        );
        
        $email_content = $template_manager->render_confirmation_template($email_data);
        $headers = $this->get_email_headers();
        
        $sent = wp_mail($subscriber['email'], $subject, $email_content, $headers);
        
        if ($sent) {
            do_action('edh_newsletter_confirmation_sent', $subscriber);
        } else {
            do_action('edh_newsletter_confirmation_failed', $subscriber);
        }
        
        return $sent;
    }
    
    /**
     * Send welcome email to confirmed subscriber
     */
    public function send_welcome_email(array $subscriber): bool {
        $template_manager = EDH_Newsletter_Core::get_instance()->get_module('template_manager');
        
        if (!$template_manager) {
            return false;
        }
        
        $email_data = [
            'subscriber' => $subscriber,
            'blog_name' => get_bloginfo('name'),
            'blog_url' => home_url(),
            'unsubscribe_url' => $this->generate_unsubscribe_url($subscriber),
            'manage_preferences_url' => $this->generate_preferences_url($subscriber),
        ];
        
        $subject = sprintf(
            // translators: %1$s: Site name
            __('Welcome to %1$s newsletter!', 'edh-newsletter'),
            get_bloginfo('name')
        );
        
        $email_content = $template_manager->render_welcome_template($email_data);
        $headers = $this->get_email_headers();
        
        $sent = wp_mail($subscriber['email'], $subject, $email_content, $headers);
        
        if ($sent) {
            do_action('edh_newsletter_welcome_sent', $subscriber);
        }
        
        return $sent;
    }
    
    /**
     * Get posts for digest based on frequency
     */
    private function get_digest_posts(string $frequency): array {
        $date_range = $this->get_date_range($frequency);
        
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => [
                [
                    'after' => $date_range['start'],
                    'before' => $date_range['end'],
                    'column' => 'post_date_gmt',
                ],
            ],
            'orderby' => 'post_date',
            'order' => 'DESC',
        ];
        
        // Allow filtering of digest post query
        $args = apply_filters('edh_newsletter_digest_post_args', $args, $frequency);
        
        $query = new WP_Query($args);
        $posts = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                $posts[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'url' => get_permalink(),
                    'excerpt' => get_the_excerpt(),
                    'date' => get_the_date(),
                    'author' => get_the_author(),
                    'featured_image' => get_the_post_thumbnail_url(get_the_ID(), 'medium'),
                    'categories' => wp_get_post_categories(get_the_ID(), ['fields' => 'names']),
                ];
            }
            wp_reset_postdata();
        }
        
        return $posts;
    }
    
    /**
     * Get date range for digest based on frequency
     */
    private function get_date_range(string $frequency): array {
        $now = current_time('timestamp', true);
        
        switch ($frequency) {
            case 'weekly':
                $start = gmdate('Y-m-d H:i:s', strtotime('-7 days', $now));
                break;
            case 'monthly':
                $start = gmdate('Y-m-d H:i:s', strtotime('-1 month', $now));
                break;
            default:
                $start = gmdate('Y-m-d H:i:s', strtotime('-7 days', $now));
        }
        
        return [
            'start' => $start,
            'end' => gmdate('Y-m-d H:i:s', $now),
        ];
    }
    
    /**
     * Generate email subject line
     */
    private function generate_subject(array $posts, string $frequency): string {
        $blog_name = get_bloginfo('name');
        $post_count = count($posts);
        
        if ($post_count === 0) {
            return sprintf(
                // translators: %1$s: Digest frequency (e.g., "Weekly" or "Monthly"), %2$s: Site name
                __('Your %1$s digest from %2$s - No new articles this period', 'edh-newsletter'),
                ucfirst($frequency),
                $blog_name
            );
        }
        
        $period = $frequency === 'weekly' ? __('week', 'edh-newsletter') : __('month', 'edh-newsletter');
        
        return sprintf(
            // translators: %1$s: Digest frequency (e.g., "weekly" or "monthly"), %2$d: Number of articles, %3$s: Plural suffix (empty or "s"), %4$s: Site name
            __('Your %1$s digest: %2$d new article%3$s from %4$s', 'edh-newsletter'),
            $frequency,
            $post_count,
            $post_count > 1 ? 's' : '',
            $blog_name
        );
    }
    
    /**
     * Generate confirmation URL
     */
    private function generate_confirmation_url(string $token): string {
        return add_query_arg([
            'newsletter_action' => 'confirm',
            'token' => $token,
        ], home_url('/'));
    }
    
    /**
     * Generate unsubscribe URL
     */
    private function generate_unsubscribe_url(array $subscriber): string {
        $token = $this->generate_action_token($subscriber, 'unsubscribe');
        
        return add_query_arg([
            'newsletter_action' => 'unsubscribe',
            'subscriber_id' => $subscriber['id'],
            'token' => $token,
        ], home_url('/'));
    }
    
    /**
     * Generate preferences management URL
     */
    private function generate_preferences_url(array $subscriber): string {
        $token = $this->generate_action_token($subscriber, 'preferences');
        
        return add_query_arg([
            'newsletter_action' => 'preferences',
            'subscriber_id' => $subscriber['id'],
            'token' => $token,
        ], home_url('/'));
    }
    
    /**
     * Generate secure action token
     */
    private function generate_action_token(array $subscriber, string $action): string {
        return hash('sha256', $subscriber['email'] . $subscriber['id'] . $action . AUTH_KEY);
    }
    
    /**
     * Verify action token
     */
    public function verify_action_token(array $subscriber, string $action, string $token): bool {
        $expected_token = $this->generate_action_token($subscriber, $action);
        return hash_equals($expected_token, $token);
    }
    
    /**
     * Get email headers
     */
    private function get_email_headers(): array {
        $from_name = get_option('newsletter_from_name', get_bloginfo('name'));
        $from_email = get_option('newsletter_from_email', get_option('admin_email'));
        
        return [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
            'Reply-To: ' . $from_email,
        ];
    }
    
    /**
     * Set HTML content type for emails
     */
    public function set_html_content_type(): string {
        return 'text/html';
    }
    
    /**
     * Send test email
     */
    public function send_test_email(string $email, string $frequency = 'weekly'): bool {
        $template_manager = EDH_Newsletter_Core::get_instance()->get_module('template_manager');
        
        if (!$template_manager) {
            return false;
        }
        
        // Get sample posts
        $posts = $this->get_digest_posts($frequency);
        
        // Create test subscriber data
        $test_subscriber = [
            'id' => 0,
            'email' => $email,
            'digest_frequency' => $frequency,
            'status' => 'subscribed',
        ];
        
        $email_data = [
            'posts' => $posts,
            'frequency' => $frequency,
            'subscriber' => $test_subscriber,
            'blog_name' => get_bloginfo('name'),
            'blog_url' => home_url(),
            'unsubscribe_url' => '#',
            'manage_preferences_url' => '#',
            'is_test' => true,
        ];
        
        $subject = '[TEST] ' . $this->generate_subject($posts, $frequency);
        $email_content = $template_manager->render_digest_template($email_data);
        $headers = $this->get_email_headers();
        
        return wp_mail($email, $subject, $email_content, $headers);
    }
    
    /**
     * Get email delivery statistics
     */
    public function get_delivery_stats(): array {
        // This would typically integrate with email service provider APIs
        // For now, return basic stats from WordPress
        
        return [
            'total_sent' => get_option('newsletter_total_emails_sent', 0),
            'last_digest_sent' => get_option('newsletter_last_digest_sent', ''),
            'failed_deliveries' => get_option('newsletter_failed_deliveries', 0),
        ];
    }
    
    /**
     * Update delivery statistics
     */
    public function update_delivery_stats(string $type, bool $success = true): void {
        if ($success) {
            $total = get_option('newsletter_total_emails_sent', 0);
            update_option('newsletter_total_emails_sent', $total + 1);
            
            if (in_array($type, ['weekly', 'monthly'])) {
                update_option('newsletter_last_digest_sent', current_time('mysql'));
            }
        } else {
            $failed = get_option('newsletter_failed_deliveries', 0);
            update_option('newsletter_failed_deliveries', $failed + 1);
        }
    }
}
