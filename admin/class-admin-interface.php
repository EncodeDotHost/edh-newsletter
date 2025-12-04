<?php
/**
 * Admin Interface Class
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Admin Interface
 * 
 * Handles admin dashboard, settings, and management interface
 */
class EDH_Newsletter_Admin_Interface {
    
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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_newsletter_test_email', [$this, 'handle_test_email']);
        add_action('wp_ajax_newsletter_trigger_digest', [$this, 'handle_trigger_digest']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu(): void {
        // Main menu page
        add_menu_page(
            __('Newsletter', 'edh-newsletter'),
            __('Newsletter', 'edh-newsletter'),
            'manage_options',
            'edh-newsletter',
            [$this, 'render_dashboard_page'],
            'dashicons-email-alt',
            25
        );
        
        // Dashboard (same as main page)
        add_submenu_page(
            'edh-newsletter',
            __('Dashboard', 'edh-newsletter'),
            __('Dashboard', 'edh-newsletter'),
            'manage_options',
            'edh-newsletter',
            [$this, 'render_dashboard_page']
        );
        
        // Subscribers
        add_submenu_page(
            'edh-newsletter',
            __('Subscribers', 'edh-newsletter'),
            __('Subscribers', 'edh-newsletter'),
            'manage_options',
            'newsletter-subscribers',
            [$this, 'render_subscribers_page']
        );
        
        // Settings
        add_submenu_page(
            'edh-newsletter',
            __('Settings', 'edh-newsletter'),
            __('Settings', 'edh-newsletter'),
            'manage_options',
            'newsletter-settings',
            [$this, 'render_settings_page']
        );
        
        // Templates
        add_submenu_page(
            'edh-newsletter',
            __('Templates', 'edh-newsletter'),
            __('Templates', 'edh-newsletter'),
            'manage_options',
            'newsletter-templates',
            [$this, 'render_templates_page']
        );
        
        // Privacy
        add_submenu_page(
            'edh-newsletter',
            __('Privacy', 'edh-newsletter'),
            __('Privacy', 'edh-newsletter'),
            'manage_options',
            'newsletter-privacy',
            [$this, 'render_privacy_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings(): void {
        // Weekly digest settings
        register_setting('newsletter_weekly_settings', 'newsletter_weekly_send_day', 'absint');
        register_setting('newsletter_weekly_settings', 'newsletter_weekly_send_hour', 'absint');
        
        // Monthly digest settings
        register_setting('newsletter_monthly_settings', 'newsletter_monthly_send_day', 'absint');
        register_setting('newsletter_monthly_settings', 'newsletter_monthly_send_hour', 'absint');
        
        // Email settings
        register_setting('newsletter_email_settings', 'newsletter_from_name', 'sanitize_text_field');
        register_setting('newsletter_email_settings', 'newsletter_from_email', 'sanitize_email');
        
        // Template settings
        register_setting('newsletter_template_settings', 'newsletter_brand_color', 'sanitize_hex_color');
        register_setting('newsletter_template_settings', 'newsletter_logo_url', 'esc_url_raw');
        
        // Privacy settings
        register_setting('newsletter_privacy_settings', 'newsletter_privacy_policy_url', 'esc_url_raw');
        register_setting('newsletter_privacy_settings', 'newsletter_data_retention_days', 'absint');
        register_setting('newsletter_privacy_settings', 'newsletter_require_privacy_consent', 'absint');
        register_setting('newsletter_privacy_settings', 'newsletter_consent_version', 'sanitize_text_field');
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook): void {
        if (strpos($hook, 'edh-newsletter') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        wp_enqueue_script(
            'newsletter-admin',
            EDH_NEWSLETTER_ASSETS_URL . 'js/admin.js',
            ['jquery', 'wp-color-picker'],
            EDH_NEWSLETTER_VERSION,
            true
        );
        
        wp_enqueue_style(
            'newsletter-admin',
            EDH_NEWSLETTER_ASSETS_URL . 'css/admin.css',
            [],
            EDH_NEWSLETTER_VERSION
        );
        
        wp_localize_script('newsletter-admin', 'newsletter_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('newsletter_admin_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this subscriber?', 'edh-newsletter'),
                'test_email_sent' => __('Test email sent successfully!', 'edh-newsletter'),
                'digest_triggered' => __('Digest triggered successfully!', 'edh-newsletter'),
                'error_occurred' => __('An error occurred. Please try again.', 'edh-newsletter'),
            ],
        ]);
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page(): void {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        $scheduler = EDH_Newsletter_Core::get_instance()->get_module('digest_scheduler');
        $email_sender = EDH_Newsletter_Core::get_instance()->get_module('email_sender');
        
        // Get statistics
        $stats = [
            'total_subscribers' => $subscriber_manager ? $subscriber_manager->get_subscriber_count(['status' => 'subscribed']) : 0,
            'weekly_subscribers' => $subscriber_manager ? $subscriber_manager->get_subscriber_count(['status' => 'subscribed', 'frequency' => 'weekly']) : 0,
            'monthly_subscribers' => $subscriber_manager ? $subscriber_manager->get_subscriber_count(['status' => 'subscribed', 'frequency' => 'monthly']) : 0,
            'pending_subscribers' => $subscriber_manager ? $subscriber_manager->get_subscriber_count(['status' => 'pending']) : 0,
        ];
        
        $schedule_status = $scheduler ? $scheduler->get_schedule_status() : [];
        $delivery_stats = $email_sender ? $email_sender->get_delivery_stats() : [];
        
        include EDH_NEWSLETTER_ADMIN_DIR . 'views/dashboard.php';
    }
    
    /**
     * Render subscribers page
     */
    public function render_subscribers_page(): void {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            wp_die(esc_html__('Subscriber manager not available', 'edh-newsletter'));
        }
        
        // Handle actions
        $message = '';
        if (isset($_GET['action']) && isset($_GET['id']) && current_user_can('manage_options')) {
            $id = absint($_GET['id']);
            
            switch ($_GET['action']) {
                case 'delete':
                    if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_subscriber_' . $id)) {
                        $result = $subscriber_manager->delete_subscriber($id);
                        $message = $result['success'] 
                            ? '<div class="notice notice-success"><p>' . __('Subscriber deleted successfully.', 'edh-newsletter') . '</p></div>'
                            : '<div class="notice notice-error"><p>' . __('Error deleting subscriber.', 'edh-newsletter') . '</p></div>';
                    }
                    break;
                    
                case 'unsubscribe':
                    if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'unsubscribe_subscriber_' . $id)) {
                        $result = $subscriber_manager->unsubscribe($id, 'Admin action');
                        $message = $result['success'] 
                            ? '<div class="notice notice-success"><p>' . __('Subscriber unsubscribed successfully.', 'edh-newsletter') . '</p></div>'
                            : '<div class="notice notice-error"><p>' . __('Error unsubscribing subscriber.', 'edh-newsletter') . '</p></div>';
                    }
                    break;
            }
        }
        
        // Get subscribers with pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        $frequency_filter = isset($_GET['frequency']) ? sanitize_key($_GET['frequency']) : 'all';
        
        $args = [
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        if ($status_filter !== 'all') {
            $args['status'] = $status_filter;
        }
        
        if ($frequency_filter !== 'all') {
            $args['frequency'] = $frequency_filter;
        }
        
        $subscribers = $subscriber_manager->get_subscribers($args);
        $total_subscribers = $subscriber_manager->get_subscriber_count($args);
        
        include EDH_NEWSLETTER_ADMIN_DIR . 'views/subscribers.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        $scheduler = EDH_Newsletter_Core::get_instance()->get_module('digest_scheduler');
        $schedule_status = $scheduler ? $scheduler->get_schedule_status() : [];
        $cron_diagnostics = $scheduler ? $scheduler->get_cron_diagnostics() : [];
        
        include EDH_NEWSLETTER_ADMIN_DIR . 'views/settings.php';
    }
    
    /**
     * Render templates page
     */
    public function render_templates_page(): void {
        include EDH_NEWSLETTER_ADMIN_DIR . 'views/templates.php';
    }
    
    /**
     * Render privacy page
     */
    public function render_privacy_page(): void {
        $privacy_manager = EDH_Newsletter_Core::get_instance()->get_module('privacy_manager');
        
        include EDH_NEWSLETTER_ADMIN_DIR . 'views/privacy.php';
    }
    
    /**
     * Handle test email AJAX request
     */
    public function handle_test_email(): void {
        check_ajax_referer('newsletter_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'edh-newsletter'));
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        $frequency = sanitize_key($_POST['frequency'] ?? 'weekly');
        
        if (!is_email($email)) {
            wp_send_json_error(__('Invalid email address', 'edh-newsletter'));
        }
        
        $email_sender = EDH_Newsletter_Core::get_instance()->get_module('email_sender');
        
        if (!$email_sender) {
            wp_send_json_error(__('Email sender not available', 'edh-newsletter'));
        }
        
        $sent = $email_sender->send_test_email($email, $frequency);
        
        if ($sent) {
            wp_send_json_success(__('Test email sent successfully!', 'edh-newsletter'));
        } else {
            wp_send_json_error(__('Failed to send test email', 'edh-newsletter'));
        }
    }
    
    /**
     * Handle trigger digest AJAX request
     */
    public function handle_trigger_digest(): void {
        check_ajax_referer('newsletter_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'edh-newsletter'));
        }
        
        $frequency = sanitize_key($_POST['frequency'] ?? 'weekly');
        $scheduler = EDH_Newsletter_Core::get_instance()->get_module('digest_scheduler');
        
        if (!$scheduler) {
            wp_send_json_error(__('Scheduler not available', 'edh-newsletter'));
        }
        
        $triggered = false;
        
        if ($frequency === 'weekly') {
            $triggered = $scheduler->trigger_weekly_digest();
        } elseif ($frequency === 'monthly') {
            $triggered = $scheduler->trigger_monthly_digest();
        }
        
        if ($triggered) {
            wp_send_json_success(__('Digest triggered successfully!', 'edh-newsletter'));
        } else {
            wp_send_json_error(__('Failed to trigger digest', 'edh-newsletter'));
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices(): void {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, 'edh-newsletter') === false) {
            return;
        }
        
        // Check if cron is working
        $scheduler = EDH_Newsletter_Core::get_instance()->get_module('digest_scheduler');
        
        if ($scheduler && !$scheduler->is_cron_working()) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html__('Newsletter Warning:', 'edh-newsletter') . '</strong> ';
            echo esc_html__('WordPress cron appears to be disabled. Scheduled digests may not be sent automatically.', 'edh-newsletter');
            echo '</p></div>';
        }
        
        // Check if required settings are configured
        $from_email = get_option('newsletter_from_email');
        if (empty($from_email)) {
            $settings_url = admin_url('admin.php?page=newsletter-settings');
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . esc_html__('Newsletter Setup:', 'edh-newsletter') . '</strong> ';
            echo sprintf(
                wp_kses(
                    // translators: %1$s: URL to settings page
                    __('Please configure your email settings in the <a href="%1$s">Settings page</a>.', 'edh-newsletter'),
                    ['a' => ['href' => []]]
                ),
                esc_url($settings_url)
            );
            echo '</p></div>';
        }
    }
    
    /**
     * Get subscriber status counts for filters
     */
    public function get_status_counts(): array {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            return [];
        }
        
        return [
            'all' => $subscriber_manager->get_subscriber_count([]),
            'subscribed' => $subscriber_manager->get_subscriber_count(['status' => 'subscribed']),
            'pending' => $subscriber_manager->get_subscriber_count(['status' => 'pending']),
            'unsubscribed' => $subscriber_manager->get_subscriber_count(['status' => 'unsubscribed']),
            'paused' => $subscriber_manager->get_subscriber_count(['status' => 'paused']),
        ];
    }
    
    /**
     * Get frequency counts for filters
     */
    public function get_frequency_counts(): array {
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            return [];
        }
        
        return [
            'all' => $subscriber_manager->get_subscriber_count(['status' => 'subscribed']),
            'weekly' => $subscriber_manager->get_subscriber_count(['status' => 'subscribed', 'frequency' => 'weekly']),
            'monthly' => $subscriber_manager->get_subscriber_count(['status' => 'subscribed', 'frequency' => 'monthly']),
        ];
    }
    
    /**
     * Render pagination links
     */
    public function render_pagination($total_items, $per_page, $current_page): void {
        $total_pages = ceil($total_items / $per_page);
        
        if ($total_pages <= 1) {
            return;
        }
        
        $page_links = paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo; Previous', 'edh-newsletter'),
            'next_text' => __('Next &raquo;', 'edh-newsletter'),
            'total' => $total_pages,
            'current' => $current_page,
            'type' => 'array',
        ]);
        
        if ($page_links) {
            echo '<div class="tablenav-pages">';
            echo '<span class="displaying-num">' . esc_html(sprintf(
                // translators: %1$s: Number of items (singular: "1 item", plural: "2 items")
                _n('%1$s item', '%1$s items', $total_items, 'edh-newsletter'),
                number_format_i18n($total_items)
            )) . '</span>';
            echo '<span class="pagination-links">' . wp_kses_post(implode('', $page_links)) . '</span>';
            echo '</div>';
        }
    }
}
