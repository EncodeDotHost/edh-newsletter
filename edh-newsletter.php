<?php
/**
 * Plugin Name: EDH Newsletter - Weekly & Monthly Digest
 * Description: Advanced newsletter plugin with double opt-in, GDPR compliance, customizable templates, and support for weekly and monthly digests.
 * Version: 2.1.0
 * Author: EncodeDotHost
 * License: GPL2
 * Text Domain: edh-newsletter
 * Domain Path: /languages
 * Requires at least: 6.3
 * Tested up to: 7.1
 * Requires PHP: 8.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Main plugin bootstrap file
 * 
 * This file loads the core plugin class and initializes the modular architecture.
 * All legacy functionality has been refactored into separate, organized modules.
 */

// Prevent direct execution
if (!function_exists('add_action')) {
    exit;
}

// Load the core plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-newsletter-core.php';

/**
 * Initialize the plugin
 */
function edh_newsletter_init() {
    return EDH_Newsletter_Core::get_instance(__FILE__);
}

// Initialize plugin
add_action('plugins_loaded', 'edh_newsletter_init', 10);

/**
 * Legacy compatibility functions
 * These maintain backward compatibility with the old plugin version
 */

// Activation hook
function edh_newsletter_activate() {
    $core = EDH_Newsletter_Core::get_instance(__FILE__);
    $core->activate();
}

// Deactivation hook
function edh_newsletter_deactivate() {
    $core = EDH_Newsletter_Core::get_instance(__FILE__);
    $core->deactivate();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'edh_newsletter_activate');
register_deactivation_hook(__FILE__, 'edh_newsletter_deactivate');

// Legacy shortcode support (redirects to new shortcode)
function edh_newsletter_legacy_subscription_form_shortcode($atts) {
    $frontend_forms = EDH_Newsletter_Core::get_instance()->get_module('frontend_forms');
    if ($frontend_forms) {
        return $frontend_forms->render_signup_shortcode($atts);
    }
    return '';
}
add_shortcode('weekly_digest_signup', 'edh_newsletter_legacy_subscription_form_shortcode');

/**
 * Legacy cron handlers
 * These redirect to the new email sender system
 */
function edh_newsletter_legacy_send_weekly_digest_email() {
    $email_sender = EDH_Newsletter_Core::get_instance()->get_module('email_sender');
    if ($email_sender) {
        $email_sender->send_digest('weekly');
    }
}

// Hook legacy cron function to maintain compatibility
add_action('wan_send_weekly_digest', 'edh_newsletter_legacy_send_weekly_digest_email');

/**
 * Helper function to get plugin instance (for developers)
 */
function edh_newsletter() {
    return EDH_Newsletter_Core::get_instance();
}

/**
 * Helper function to get a specific module (for developers)
 */
function edh_newsletter_get_module($module_name) {
    $core = EDH_Newsletter_Core::get_instance();
    return $core->get_module($module_name);
}
