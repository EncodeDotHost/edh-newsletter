<?php
/**
 * Core Newsletter Plugin Class
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Main Newsletter Core Class
 * 
 * Handles plugin initialization, dependency injection, and module loading
 */
class EDH_Newsletter_Core {
    
    /**
     * Plugin version
     */
    const VERSION = '2.1.0';
    
    /**
     * Database version
     */
    const DB_VERSION = '2.1';
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin modules
     */
    private $modules = [];
    
    /**
     * Plugin file path
     */
    private $plugin_file;
    
    /**
     * Plugin directory path
     */
    private $plugin_dir;
    
    /**
     * Plugin URL
     */
    private $plugin_url;
    
    /**
     * Get singleton instance
     */
    public static function get_instance($plugin_file = null) {
        if (null === self::$instance) {
            self::$instance = new self($plugin_file);
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_dir = plugin_dir_path($plugin_file);
        $this->plugin_url = plugin_dir_url($plugin_file);
        
        $this->define_constants();
        $this->init_hooks();
        $this->load_modules();
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        define('EDH_NEWSLETTER_VERSION', self::VERSION);
        define('EDH_NEWSLETTER_DB_VERSION', self::DB_VERSION);
        define('EDH_NEWSLETTER_PLUGIN_FILE', $this->plugin_file);
        define('EDH_NEWSLETTER_PLUGIN_DIR', $this->plugin_dir);
        define('EDH_NEWSLETTER_PLUGIN_URL', $this->plugin_url);
        define('EDH_NEWSLETTER_INCLUDES_DIR', $this->plugin_dir . 'includes/');
        define('EDH_NEWSLETTER_ADMIN_DIR', $this->plugin_dir . 'admin/');
        define('EDH_NEWSLETTER_PUBLIC_DIR', $this->plugin_dir . 'public/');
        define('EDH_NEWSLETTER_ASSETS_URL', $this->plugin_url . 'assets/');
        define('EDH_NEWSLETTER_TEMPLATES_DIR', $this->plugin_dir . 'templates/');
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/deactivation hooks are registered in the bootstrap file.
        // This object is constructed during plugins_loaded, so anything hooked
        // here must use a later hook: WP_Hook does not run callbacks added to
        // the priority that is currently executing.
        add_action('init', [$this, 'init_plugin'], 5);
        add_action('init', [$this, 'init_modules'], 10);
    }
    
    /**
     * Load required module files
     */
    private function load_modules() {
        $modules = [
            'subscriber-manager',
            'email-sender',
            'template-manager',
            'privacy-manager',
            'digest-scheduler',
            'spam-guard',
            'blocks',
        ];
        
        foreach ($modules as $module) {
            $file = EDH_NEWSLETTER_INCLUDES_DIR . "class-{$module}.php";
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // Load admin modules
        if (is_admin()) {
            require_once EDH_NEWSLETTER_ADMIN_DIR . 'class-admin-interface.php';
        }
        
        // Frontend forms are loaded in every context: the block editor and REST
        // block previews render them, and their hooks self-gate on request type.
        require_once EDH_NEWSLETTER_PUBLIC_DIR . 'class-frontend-forms.php';
    }
    
    /**
     * Initialize plugin after WordPress is loaded
     */
    public function init_plugin() {
        // Check if we need to run database migrations
        $this->maybe_upgrade_database();
        
        // Load text domain for internationalization
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for non-WordPress.org plugins or custom translation loading
        load_plugin_textdomain('edh-newsletter', false, dirname(plugin_basename($this->plugin_file)) . '/languages/');
    }
    
    /**
     * Initialize modules
     */
    public function init_modules() {
        // Initialize core modules
        $this->modules['subscriber_manager'] = new EDH_Newsletter_Subscriber_Manager();
        $this->modules['email_sender'] = new EDH_Newsletter_Email_Sender();
        $this->modules['template_manager'] = new EDH_Newsletter_Template_Manager();
        $this->modules['privacy_manager'] = new EDH_Newsletter_Privacy_Manager();
        $this->modules['digest_scheduler'] = new EDH_Newsletter_Digest_Scheduler();
        $this->modules['spam_guard'] = new EDH_Newsletter_Spam_Guard();
        $this->modules['frontend_forms'] = new EDH_Newsletter_Frontend_Forms();
        
        // Blocks delegate rendering to frontend_forms, so they come after it
        $this->modules['blocks'] = new EDH_Newsletter_Blocks();
        
        // Initialize admin interface
        if (is_admin()) {
            $this->modules['admin_interface'] = new EDH_Newsletter_Admin_Interface();
        }
        
        // Allow other plugins to hook into our modules
        do_action('edh_newsletter_modules_loaded', $this->modules);
    }
    
    /**
     * Get a specific module
     */
    public function get_module($module_name) {
        return isset($this->modules[$module_name]) ? $this->modules[$module_name] : null;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Run pending migrations first: create_tables() stamps the DB version,
        // which would otherwise skip the v1.x -> v2 migration.
        $this->maybe_upgrade_database();
        
        // Create/update database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron events
        if (class_exists('EDH_Newsletter_Digest_Scheduler')) {
            $scheduler = new EDH_Newsletter_Digest_Scheduler();
            $scheduler->schedule_events();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events, including batch events with arguments and the legacy v1.x hook
        wp_unschedule_hook('edh_newsletter_send_weekly_digest');
        wp_unschedule_hook('edh_newsletter_send_monthly_digest');
        wp_unschedule_hook('edh_newsletter_send_digest_batch');
        wp_unschedule_hook('edh_newsletter_cleanup_expired_data');
        wp_unschedule_hook('wan_send_weekly_digest');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Subscribers table with enhanced fields
        $table_name = $wpdb->prefix . 'newsletter_subscribers';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            digest_frequency varchar(20) DEFAULT 'weekly' NOT NULL,
            token varchar(64) DEFAULT '' NOT NULL,
            privacy_consent_date datetime DEFAULT NULL,
            consent_version varchar(10) DEFAULT '1.0' NOT NULL,
            preferences longtext DEFAULT NULL,
            last_engagement_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY token (token),
            KEY status (status),
            KEY digest_frequency (digest_frequency),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update database version
        update_option('newsletter_db_version', self::DB_VERSION);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        // Weekly digest settings (maintain backward compatibility)
        add_option('newsletter_weekly_send_day', 5); // Friday
        add_option('newsletter_weekly_send_hour', 13); // 1 PM
        
        // Monthly digest settings
        add_option('newsletter_monthly_send_day', 1); // 1st of month
        add_option('newsletter_monthly_send_hour', 10); // 10 AM
        
        // Privacy settings
        add_option('newsletter_privacy_policy_url', '');
        add_option('newsletter_data_retention_days', 365);
        add_option('newsletter_consent_version', '1.0');
        
        // Spam protection
        add_option('newsletter_spam_min_seconds', 3);
        add_option('newsletter_spam_max_per_hour', 10);
        add_option('newsletter_block_disposable_emails', 1);
        
        // Template settings
        add_option('newsletter_brand_color', '#1e73be');
        add_option('newsletter_logo_url', '');
        add_option('newsletter_from_name', get_bloginfo('name'));
        add_option('newsletter_from_email', get_option('admin_email'));
    }
    
    /**
     * Check if database needs upgrading
     */
    private function maybe_upgrade_database() {
        $current_version = get_option('newsletter_db_version', '1.1');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->upgrade_database($current_version);
        }
    }
    
    /**
     * Upgrade database from older versions
     */
    private function upgrade_database($from_version) {
        global $wpdb;
        
        // Migration from version 1.1 to 2.0
        if (version_compare($from_version, '2.0', '<')) {
            // Check if old table exists
            $old_table = $wpdb->prefix . 'wan_subscribers';
            $new_table = $wpdb->prefix . 'newsletter_subscribers';
            
            // Check if old table exists using prepared statement
            // phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-off schema check during upgrade
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $old_table
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery
            if ($table_exists === $old_table) {
                // Migrate data from old table to new table
                $this->migrate_from_legacy_table($old_table, $new_table);
            }
            
            // The v1.x cron event is no longer needed; v2 schedules its own.
            wp_unschedule_hook('wan_send_weekly_digest');
        }
        
        // Apply any schema changes (dbDelta is idempotent) and stamp the version
        $this->create_tables();
    }
    
    /**
     * Migrate data from legacy table structure
     */
    private function migrate_from_legacy_table($old_table, $new_table) {
        global $wpdb;
        
        // First create the new table
        $this->create_tables();
        
        // Migrate existing subscribers (one-off during upgrade; table name is esc_sql()'d)
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $old_table_escaped = esc_sql($old_table);
        $subscribers = $wpdb->get_results("SELECT * FROM `{$old_table_escaped}`");
        
        foreach ($subscribers as $subscriber) {
            $wpdb->insert(
                $new_table,
                [
                    'email' => $subscriber->email,
                    'status' => $subscriber->status,
                    'digest_frequency' => 'weekly', // Default to weekly for existing subscribers
                    'token' => $subscriber->token,
                    'privacy_consent_date' => $subscriber->time, // Use original subscription time as consent date
                    'consent_version' => '1.0',
                    'created_at' => $subscriber->time,
                    'updated_at' => $subscriber->time,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        // Migrate old settings to new format
        $old_settings = [
            'wan_send_day' => 'newsletter_weekly_send_day',
            'wan_send_hour' => 'newsletter_weekly_send_hour',
        ];
        
        foreach ($old_settings as $old_key => $new_key) {
            $value = get_option($old_key);
            if ($value !== false) {
                update_option($new_key, (int) $value);
            }
        }
    }
    
    /**
     * Get plugin directory path
     */
    public function get_plugin_dir() {
        return $this->plugin_dir;
    }
    
    /**
     * Get plugin URL
     */
    public function get_plugin_url() {
        return $this->plugin_url;
    }
}
