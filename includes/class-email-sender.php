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
     * Cron hook for one batch of a digest run
     */
    const BATCH_HOOK = 'edh_newsletter_send_digest_batch';
    
    /**
     * Placeholder URLs rendered once per run and swapped per recipient.
     * They are valid URLs so esc_url() in templates leaves them intact.
     */
    const UNSUBSCRIBE_PLACEHOLDER = 'https://placeholder.invalid/edh-newsletter/unsubscribe';
    const PREFERENCES_PLACEHOLDER = 'https://placeholder.invalid/edh-newsletter/preferences';
    
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
        add_action(self::BATCH_HOOK, [$this, 'send_digest_batch'], 10, 3);
        // Content type is set per message via get_email_headers(); no site-wide wp_mail_content_type filter.
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
     * Start a digest run: render the email once, then send it in batches.
     *
     * The first batch is sent immediately; further batches are scheduled as
     * single cron events so a large list never has to finish inside one request.
     */
    public function send_digest(string $frequency): void {
        $frequency = $frequency === 'monthly' ? 'monthly' : 'weekly';
        $template_manager = EDH_Newsletter_Core::get_instance()->get_module('template_manager');
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager || !$template_manager) {
            $this->log('Required modules not available for digest sending');
            return;
        }
        
        if ($subscriber_manager->get_subscriber_count(['status' => 'subscribed', 'frequency' => $frequency]) === 0) {
            return;
        }
        
        $posts = $this->get_digest_posts($frequency);
        
        $email_data = [
            'posts' => $posts,
            'frequency' => $frequency,
            'blog_name' => get_bloginfo('name'),
            'blog_url' => home_url(),
        ];
        
        $run = [
            'frequency' => $frequency,
            'subject' => $this->generate_subject($posts, $frequency),
            'email_data' => $email_data,
            'body' => '',
            'sent' => 0,
            'failed' => 0,
            'post_count' => count($posts),
            'started' => time(),
        ];
        
        /**
         * Filter whether the digest body is rendered separately for every recipient.
         * Off by default: the body is rendered once and only the two footer URLs
         * differ per subscriber. Enable if a template override uses $subscriber.
         */
        $per_recipient = (bool) apply_filters('edh_newsletter_render_per_recipient', false, $frequency);
        
        if (!$per_recipient) {
            $run['body'] = $template_manager->render_digest_template(array_merge($email_data, [
                'subscriber' => [],
                'unsubscribe_url' => self::UNSUBSCRIBE_PLACEHOLDER,
                'manage_preferences_url' => self::PREFERENCES_PLACEHOLDER,
            ]));
        }
        
        $run_id = wp_generate_uuid4();
        set_transient($this->run_key($run_id), $run, DAY_IN_SECONDS);
        
        $this->send_digest_batch($frequency, $run_id, 0);
    }
    
    /**
     * Send one batch of a digest run and schedule the next one.
     */
    public function send_digest_batch(string $frequency, string $run_id, int $after_id = 0): void {
        $run = get_transient($this->run_key($run_id));
        
        if (!is_array($run)) {
            $this->log("Digest run {$run_id} state missing; aborting");
            return;
        }
        
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        $template_manager = EDH_Newsletter_Core::get_instance()->get_module('template_manager');
        
        if (!$subscriber_manager || !$template_manager) {
            $this->log('Required modules not available for digest batch');
            return;
        }
        
        $batch_size = max(1, (int) apply_filters('edh_newsletter_digest_batch_size', 100, $frequency));
        
        $subscribers = $subscriber_manager->get_subscribers([
            'status' => 'subscribed',
            'frequency' => $frequency,
            'after_id' => $after_id,
            'orderby' => 'id',
            'order' => 'ASC',
            'limit' => $batch_size,
        ]);
        
        $headers = $this->get_email_headers();
        $sent_ids = [];
        $last_id = $after_id;
        
        foreach ($subscribers as $subscriber) {
            $last_id = $subscriber['id'];
            
            if ($run['body'] !== '') {
                $content = str_replace(
                    [esc_url(self::UNSUBSCRIBE_PLACEHOLDER), esc_url(self::PREFERENCES_PLACEHOLDER), self::UNSUBSCRIBE_PLACEHOLDER, self::PREFERENCES_PLACEHOLDER],
                    [esc_url($this->generate_unsubscribe_url($subscriber)), esc_url($this->generate_preferences_url($subscriber)), $this->generate_unsubscribe_url($subscriber), $this->generate_preferences_url($subscriber)],
                    $run['body']
                );
            } else {
                $content = $template_manager->render_digest_template(array_merge($run['email_data'], [
                    'subscriber' => $subscriber,
                    'unsubscribe_url' => $this->generate_unsubscribe_url($subscriber),
                    'manage_preferences_url' => $this->generate_preferences_url($subscriber),
                ]));
            }
            
            if (wp_mail($subscriber['email'], $run['subject'], $content, $headers)) {
                $run['sent']++;
                $sent_ids[] = $subscriber['id'];
                do_action('edh_newsletter_email_sent', $subscriber, $run['subject']);
            } else {
                $run['failed']++;
                do_action('edh_newsletter_email_failed', $subscriber, $run['subject']);
                $this->log("Failed to send email to {$subscriber['email']}");
            }
        }
        
        $subscriber_manager->update_engagement_bulk($sent_ids);
        $this->update_delivery_stats($frequency, count($sent_ids), count($subscribers) - count($sent_ids));
        
        if (count($subscribers) === $batch_size) {
            set_transient($this->run_key($run_id), $run, DAY_IN_SECONDS);
            wp_schedule_single_event(time(), self::BATCH_HOOK, [$frequency, $run_id, $last_id]);
            return;
        }
        
        delete_transient($this->run_key($run_id));
        do_action('edh_newsletter_digest_sent', $frequency, $run['sent'], $run['post_count'], $run['failed']);
    }
    
    /**
     * Transient key for a digest run
     */
    private function run_key(string $run_id): string {
        return 'edh_newsletter_run_' . sanitize_key($run_id);
    }
    
    /**
     * Log a message when debug logging is enabled
     */
    private function log(string $message): void {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Newsletter: ' . $message);
        }
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
    public function generate_unsubscribe_url(array $subscriber): string {
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
    public function generate_preferences_url(array $subscriber): string {
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
     * Update delivery statistics after a batch
     */
    public function update_delivery_stats(string $type, int $sent, int $failed = 0): void {
        if ($sent > 0) {
            update_option('newsletter_total_emails_sent', (int) get_option('newsletter_total_emails_sent', 0) + $sent, false);
            
            if (in_array($type, ['weekly', 'monthly'], true)) {
                update_option('newsletter_last_digest_sent', current_time('mysql'), false);
            }
        }
        
        if ($failed > 0) {
            update_option('newsletter_failed_deliveries', (int) get_option('newsletter_failed_deliveries', 0) + $failed, false);
        }
    }
}
