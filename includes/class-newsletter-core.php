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
    const VERSION = '2.0.0';
    
    /**
     * Database version
     */
    const DB_VERSION = '2.0';
    
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
        register_activation_hook($this->plugin_file, [$this, 'activate']);
        register_deactivation_hook($this->plugin_file, [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_action('init', [$this, 'init_modules']);
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
        
        // Load public modules
        if (!is_admin()) {
            require_once EDH_NEWSLETTER_PUBLIC_DIR . 'class-frontend-forms.php';
        }
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
        
        // Initialize admin interface
        if (is_admin()) {
            $this->modules['admin_interface'] = new EDH_Newsletter_Admin_Interface();
        }
        
        // Initialize frontend forms
        if (!is_admin()) {
            $this->modules['frontend_forms'] = new EDH_Newsletter_Frontend_Forms();
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
        // Clear scheduled events
        wp_clear_scheduled_hook('edh_newsletter_send_weekly_digest');
        wp_clear_scheduled_hook('edh_newsletter_send_monthly_digest');
        wp_clear_scheduled_hook('edh_newsletter_cleanup_expired_data');
        
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
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $old_table
            ));
            if ($table_exists === $old_table) {
                // Migrate data from old table to new table
                $this->migrate_from_legacy_table($old_table, $new_table);
            }
        }
        
        // Update version
        update_option('newsletter_db_version', self::DB_VERSION);
    }
    
    /**
     * Migrate data from legacy table structure
     */
    private function migrate_from_legacy_table($old_table, $new_table) {
        global $wpdb;
        
        // First create the new table
        $this->create_tables();
        
        // Migrate existing subscribers
        // Table name is safe here as it's from our own prefix, but we'll escape it for safety
        $old_table_escaped = esc_sql($old_table);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be parameterized in prepared statements
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
        
        // Migrate old settings to new format
        $old_settings = [
            'wan_send_day' => 'newsletter_weekly_send_day',
            'wan_send_hour' => 'newsletter_weekly_send_hour',
        ];
        
        foreach ($old_settings as $old_key => $new_key) {
            $value = get_option($old_key);
            if ($value !== false) {
                update_option($new_key, $value);
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
