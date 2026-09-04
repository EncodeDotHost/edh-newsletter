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
     * Hook suffixes (screen ids) of the plugin's admin pages, filled by add_admin_menu()
     */
    private $page_hooks = [];
    
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
        add_action('wp_ajax_newsletter_run_cleanup', [$this, 'handle_run_cleanup']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
        // CSV export must run before any output
        add_action('admin_init', [$this, 'maybe_export_subscribers']);
    }
    
    /**
     * Whether a hook suffix / screen id belongs to one of this plugin's pages
     */
    private function is_plugin_page(?string $hook): bool {
        return $hook !== null && in_array($hook, $this->page_hooks, true);
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu(): void {
        // Main menu page
        $this->page_hooks[] = add_menu_page(
            __('Newsletter', 'edh-newsletter'),
            __('Newsletter', 'edh-newsletter'),
            'manage_options',
            'edh-newsletter',
            [$this, 'render_dashboard_page'],
            'dashicons-email-alt',
            25
        );
        
        // Dashboard (same as main page)
        $this->page_hooks[] = add_submenu_page(
            'edh-newsletter',
            __('Dashboard', 'edh-newsletter'),
            __('Dashboard', 'edh-newsletter'),
            'manage_options',
            'edh-newsletter',
            [$this, 'render_dashboard_page']
        );
        
        // Subscribers
        $this->page_hooks[] = add_submenu_page(
            'edh-newsletter',
            __('Subscribers', 'edh-newsletter'),
            __('Subscribers', 'edh-newsletter'),
            'manage_options',
            'newsletter-subscribers',
            [$this, 'render_subscribers_page']
        );
        
        // Settings
        $this->page_hooks[] = add_submenu_page(
            'edh-newsletter',
            __('Settings', 'edh-newsletter'),
            __('Settings', 'edh-newsletter'),
            'manage_options',
            'newsletter-settings',
            [$this, 'render_settings_page']
        );
        
        // Templates
        $this->page_hooks[] = add_submenu_page(
            'edh-newsletter',
            __('Templates', 'edh-newsletter'),
            __('Templates', 'edh-newsletter'),
            'manage_options',
            'newsletter-templates',
            [$this, 'render_templates_page']
        );
        
        // Privacy
        $this->page_hooks[] = add_submenu_page(
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
        
        // Spam protection
        register_setting('newsletter_privacy_settings', 'newsletter_suppressed_emails', 'sanitize_textarea_field');
        register_setting('newsletter_privacy_settings', 'newsletter_block_disposable_emails', 'absint');
        register_setting('newsletter_privacy_settings', 'newsletter_spam_min_seconds', 'absint');
        register_setting('newsletter_privacy_settings', 'newsletter_spam_max_per_hour', 'absint');
        register_setting('newsletter_privacy_settings', 'newsletter_turnstile_site_key', 'sanitize_text_field');
        register_setting('newsletter_privacy_settings', 'newsletter_turnstile_secret_key', 'sanitize_text_field');
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook): void {
        if (!$this->is_plugin_page($hook)) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        // Native media picker for the logo field (Settings and Templates pages)
        wp_enqueue_media();
        
        wp_enqueue_script(
            'newsletter-admin',
            EDH_NEWSLETTER_ASSETS_URL . 'js/admin.js',
            ['jquery', 'wp-color-picker', 'media-editor'],
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
                'cleanup_done' => __('Cleanup completed.', 'edh-newsletter'),
                'choose_logo' => __('Choose Logo', 'edh-newsletter'),
                'use_image' => __('Use This Image', 'edh-newsletter'),
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
        
        // Get statistics (one GROUP BY query)
        $counts = $subscriber_manager ? $subscriber_manager->get_status_frequency_counts() : [];
        $stats = [
            'total_subscribers' => array_sum($counts['subscribed'] ?? []),
            'weekly_subscribers' => $counts['subscribed']['weekly'] ?? 0,
            'monthly_subscribers' => $counts['subscribed']['monthly'] ?? 0,
            'pending_subscribers' => array_sum($counts['pending'] ?? []),
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
        $message = $this->handle_subscriber_actions($subscriber_manager);
        
        // Get subscribers with pagination
        $per_page = 20;
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
        $frequency_filter = isset($_GET['frequency']) ? sanitize_key($_GET['frequency']) : 'all';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        
        $args = [
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        // 'all' is passed explicitly so the list and the count use the same filter
        $args['status'] = $status_filter;
        
        if ($frequency_filter !== 'all') {
            $args['frequency'] = $frequency_filter;
        }
        
        $subscribers = $subscriber_manager->get_subscribers($args);
        $total_subscribers = $subscriber_manager->get_subscriber_count($args);
        
        include EDH_NEWSLETTER_ADMIN_DIR . 'views/subscribers.php';
    }
    
    /**
     * Process single, bulk, and add-subscriber actions on the Subscribers page.
     * Returns notice HTML or an empty string.
     */
    private function handle_subscriber_actions(EDH_Newsletter_Subscriber_Manager $subscriber_manager): string {
        if (!current_user_can('manage_options')) {
            return '';
        }
        
        // Single row actions (GET with per-id nonce)
        if (isset($_GET['action'], $_GET['id'])) {
            $id = absint($_GET['id']);
            $action = sanitize_key($_GET['action']);
            
            if (!in_array($action, ['delete', 'unsubscribe', 'resubscribe'], true)) {
                return '';
            }
            
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), "{$action}_subscriber_{$id}")) {
                return $this->notice(__('Security check failed.', 'edh-newsletter'), 'error');
            }
            
            $result = $this->apply_subscriber_action($subscriber_manager, $action, $id);
            
            return $result['success']
                ? $this->notice($this->action_success_message($action, 1), 'success')
                : $this->notice($result['error'] ?? __('The action failed.', 'edh-newsletter'), 'error');
        }
        
        // Bulk actions (POST)
        if (isset($_POST['newsletter_bulk_nonce'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['newsletter_bulk_nonce'])), 'newsletter_bulk_action')) {
                return $this->notice(__('Security check failed.', 'edh-newsletter'), 'error');
            }
            
            $action = sanitize_key($_POST['bulk_action'] ?? '');
            $ids = array_filter(array_map('absint', (array) ($_POST['subscribers'] ?? [])));
            
            if (!in_array($action, ['delete', 'unsubscribe', 'resubscribe'], true) || empty($ids)) {
                return $this->notice(__('Select an action and at least one subscriber.', 'edh-newsletter'), 'error');
            }
            
            $done = 0;
            foreach ($ids as $id) {
                if ($this->apply_subscriber_action($subscriber_manager, $action, $id)['success']) {
                    $done++;
                }
            }
            
            return $this->notice($this->action_success_message($action, $done), $done > 0 ? 'success' : 'error');
        }
        
        // Add subscriber (POST)
        if (isset($_POST['newsletter_add_subscriber_nonce'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['newsletter_add_subscriber_nonce'])), 'newsletter_add_subscriber')) {
                return $this->notice(__('Security check failed.', 'edh-newsletter'), 'error');
            }
            
            return $this->add_subscriber($subscriber_manager);
        }
        
        return '';
    }
    
    /**
     * Apply one action to one subscriber
     */
    private function apply_subscriber_action(EDH_Newsletter_Subscriber_Manager $subscriber_manager, string $action, int $id): array {
        switch ($action) {
            case 'delete':
                return $subscriber_manager->delete_subscriber($id);
            case 'unsubscribe':
                return $subscriber_manager->unsubscribe($id, 'Admin action');
            case 'resubscribe':
                return $subscriber_manager->resubscribe($id);
        }
        
        return ['success' => false, 'error' => __('Unknown action.', 'edh-newsletter')];
    }
    
    /**
     * Success message for a subscriber action
     */
    private function action_success_message(string $action, int $count): string {
        switch ($action) {
            case 'delete':
                // translators: %d: number of subscribers
                return sprintf(_n('%d subscriber deleted.', '%d subscribers deleted.', $count, 'edh-newsletter'), $count);
            case 'unsubscribe':
                // translators: %d: number of subscribers
                return sprintf(_n('%d subscriber unsubscribed.', '%d subscribers unsubscribed.', $count, 'edh-newsletter'), $count);
            case 'resubscribe':
                // translators: %d: number of subscribers
                return sprintf(_n('%d subscriber resubscribed.', '%d subscribers resubscribed.', $count, 'edh-newsletter'), $count);
        }
        
        return '';
    }
    
    /**
     * Handle the "Add New Subscriber" form
     */
    private function add_subscriber(EDH_Newsletter_Subscriber_Manager $subscriber_manager): string {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- newsletter_add_subscriber nonce is verified by handle_subscriber_actions() before this is called
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $frequency = sanitize_key($_POST['digest_frequency'] ?? 'weekly');
        $status = sanitize_key($_POST['status'] ?? 'pending') === 'subscribed' ? 'subscribed' : 'pending';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if (!is_email($email)) {
            return $this->notice(__('Please enter a valid email address.', 'edh-newsletter'), 'error');
        }
        
        $privacy_manager = EDH_Newsletter_Core::get_instance()->get_module('privacy_manager');
        if ($privacy_manager) {
            $privacy_errors = $privacy_manager->validate_email_privacy($email);
            if (!empty($privacy_errors)) {
                return $this->notice(implode(' ', $privacy_errors), 'error');
            }
        }
        
        $result = $subscriber_manager->create_subscriber([
            'email' => $email,
            'digest_frequency' => $frequency,
            'status' => $status,
        ]);
        
        if (!$result['success']) {
            return $this->notice($result['error'], 'error');
        }
        
        if ($status === 'pending') {
            $email_sender = EDH_Newsletter_Core::get_instance()->get_module('email_sender');
            $sent = $email_sender ? $email_sender->send_confirmation_email($result['data']) : false;
            
            return $sent
                ? $this->notice(__('Subscriber added. A confirmation email has been sent.', 'edh-newsletter'), 'success')
                : $this->notice(__('Subscriber added, but the confirmation email could not be sent.', 'edh-newsletter'), 'warning');
        }
        
        return $this->notice(__('Subscriber added and marked as subscribed.', 'edh-newsletter'), 'success');
    }
    
    /**
     * Stream a CSV of subscribers when the export form is submitted (runs on admin_init)
     */
    public function maybe_export_subscribers(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only; the nonce is verified below
        if (!isset($_POST['export_subscribers'], $_POST['newsletter_export_nonce'])) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['newsletter_export_nonce'])), 'newsletter_export_subscribers')) {
            wp_die(esc_html__('Security check failed.', 'edh-newsletter'));
        }
        
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        if (!$subscriber_manager) {
            wp_die(esc_html__('Subscriber manager not available', 'edh-newsletter'));
        }
        
        $statuses = array_map('sanitize_key', (array) wp_unslash($_POST['export_status'] ?? [])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is passed through sanitize_key and then whitelisted
        $statuses = array_values(array_intersect($statuses, EDH_Newsletter_Subscriber_Manager::VALID_STATUSES));
        
        if (empty($statuses)) {
            $statuses = ['subscribed'];
        }
        
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="newsletter-subscribers-' . gmdate('Y-m-d') . '.csv"');
        
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body, not HTML; every cell is quoted by csv_row()
        echo $this->csv_row(['id', 'email', 'status', 'digest_frequency', 'created_at', 'privacy_consent_date', 'consent_version', 'last_engagement_date']);
        
        $after_id = 0;
        do {
            $rows = $subscriber_manager->get_subscribers([
                'status' => $statuses,
                'after_id' => $after_id,
                'orderby' => 'id',
                'order' => 'ASC',
                'limit' => 500,
            ]);
            
            foreach ($rows as $row) {
                $after_id = $row['id'];
                echo $this->csv_row([
                    $row['id'],
                    $row['email'],
                    $row['status'],
                    $row['digest_frequency'],
                    $row['created_at'],
                    $row['privacy_consent_date'],
                    $row['consent_version'],
                    $row['last_engagement_date'],
                ]);
            }
        } while (count($rows) === 500);
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        
        exit;
    }
    
    /**
     * Format one CSV record (RFC 4180 quoting, CRLF line ending)
     */
    private function csv_row(array $fields): string {
        $cells = [];
        foreach ($fields as $value) {
            $cells[] = '"' . str_replace('"', '""', $this->csv_safe($value)) . '"';
        }
        
        return implode(',', $cells) . "\r\n";
    }
    
    /**
     * Neutralise spreadsheet formula injection in a CSV cell
     */
    private function csv_safe($value): string {
        $value = (string) $value;
        
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
    
    /**
     * Build an admin notice
     */
    private function notice(string $message, string $type = 'info'): string {
        return '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
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
        
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
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
     * Handle "Run Cleanup Now" AJAX request
     */
    public function handle_run_cleanup(): void {
        check_ajax_referer('newsletter_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'edh-newsletter'));
        }
        
        $scheduler = EDH_Newsletter_Core::get_instance()->get_module('digest_scheduler');
        
        if (!$scheduler || !$scheduler->trigger_cleanup()) {
            wp_send_json_error(__('Cleanup could not be started', 'edh-newsletter'));
        }
        
        wp_send_json_success(__('Cleanup completed.', 'edh-newsletter'));
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices(): void {
        $screen = get_current_screen();
        
        if (!$screen || !$this->is_plugin_page($screen->id)) {
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
        $counts = $this->get_grouped_counts();
        
        return [
            'all' => array_sum(array_map('array_sum', $counts)),
            'subscribed' => array_sum($counts['subscribed'] ?? []),
            'pending' => array_sum($counts['pending'] ?? []),
            'unsubscribed' => array_sum($counts['unsubscribed'] ?? []),
            'paused' => array_sum($counts['paused'] ?? []),
        ];
    }
    
    /**
     * Get frequency counts (subscribed only) for filters
     */
    public function get_frequency_counts(): array {
        $counts = $this->get_grouped_counts();
        
        return [
            'all' => array_sum($counts['subscribed'] ?? []),
            'weekly' => $counts['subscribed']['weekly'] ?? 0,
            'monthly' => $counts['subscribed']['monthly'] ?? 0,
        ];
    }
    
    /**
     * Grouped counts, fetched once per request
     */
    private function get_grouped_counts(): array {
        static $counts = null;
        
        if ($counts === null) {
            $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
            $counts = $subscriber_manager ? $subscriber_manager->get_status_frequency_counts() : [];
        }
        
        return $counts;
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
