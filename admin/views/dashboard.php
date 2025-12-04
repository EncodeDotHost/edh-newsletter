<?php
/**
 * Dashboard Admin View
 *
 * @package Newsletter
 * @since 2.0.0
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Newsletter Dashboard', 'edh-newsletter'); ?></h1>
    
    <!-- Statistics Overview -->
    <div class="newsletter-stats">
        <div class="newsletter-stat-box">
            <span class="newsletter-stat-number"><?php echo esc_html($stats['total_subscribers']); ?></span>
            <div class="newsletter-stat-label"><?php esc_html_e('Total Subscribers', 'edh-newsletter'); ?></div>
        </div>
        
        <div class="newsletter-stat-box">
            <span class="newsletter-stat-number"><?php echo esc_html($stats['weekly_subscribers']); ?></span>
            <div class="newsletter-stat-label"><?php esc_html_e('Weekly Subscribers', 'edh-newsletter'); ?></div>
        </div>
        
        <div class="newsletter-stat-box">
            <span class="newsletter-stat-number"><?php echo esc_html($stats['monthly_subscribers']); ?></span>
            <div class="newsletter-stat-label"><?php esc_html_e('Monthly Subscribers', 'edh-newsletter'); ?></div>
        </div>
        
        <div class="newsletter-stat-box">
            <span class="newsletter-stat-number"><?php echo esc_html($stats['pending_subscribers']); ?></span>
            <div class="newsletter-stat-label"><?php esc_html_e('Pending Confirmations', 'edh-newsletter'); ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Quick Actions', 'edh-newsletter'); ?></h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Test Email -->
            <div class="newsletter-test-email">
                <h4><?php esc_html_e('Send Test Email', 'edh-newsletter'); ?></h4>
                <form class="newsletter-test-email-form">
                    <div class="newsletter-test-form">
                        <input type="email" name="test_email" placeholder="<?php esc_attr_e('Enter email address', 'edh-newsletter'); ?>" required>
                        <select name="test_frequency">
                            <option value="weekly"><?php esc_html_e('Weekly Digest', 'edh-newsletter'); ?></option>
                            <option value="monthly"><?php esc_html_e('Monthly Digest', 'edh-newsletter'); ?></option>
                        </select>
                        <button type="submit"><?php esc_html_e('Send Test', 'edh-newsletter'); ?></button>
                    </div>
                </form>
            </div>
            
            <!-- Manual Triggers -->
            <div class="newsletter-test-email">
                <h4><?php esc_html_e('Manual Digest Triggers', 'edh-newsletter'); ?></h4>
                <p><?php esc_html_e('Trigger digest emails manually for testing:', 'edh-newsletter'); ?></p>
                <div style="display: flex; gap: 10px;">
                    <button class="newsletter-trigger-digest button" data-frequency="weekly" data-original-text="<?php esc_attr_e('Trigger Weekly', 'edh-newsletter'); ?>">
                        <?php esc_html_e('Trigger Weekly', 'edh-newsletter'); ?>
                    </button>
                    <button class="newsletter-trigger-digest button" data-frequency="monthly" data-original-text="<?php esc_attr_e('Trigger Monthly', 'edh-newsletter'); ?>">
                        <?php esc_html_e('Trigger Monthly', 'edh-newsletter'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Schedule Status -->
    <?php if (!empty($schedule_status)): ?>
    <div class="newsletter-schedule-status">
        <h3><?php esc_html_e('Schedule Status', 'edh-newsletter'); ?></h3>
        
        <div class="newsletter-schedule-item">
            <div>
                <strong><?php esc_html_e('Weekly Digest', 'edh-newsletter'); ?></strong>
                <?php if ($schedule_status['weekly']['enabled']): ?>
                    <span class="newsletter-schedule-enabled"><?php esc_html_e('Enabled', 'edh-newsletter'); ?></span>
                    <div><?php
                        // translators: %1$s: Formatted date and time of next scheduled run
                        printf(esc_html__('Next run: %1$s', 'edh-newsletter'), esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $schedule_status['weekly']['next_run'])));
                    ?></div>
                <?php else: ?>
                    <span class="newsletter-schedule-disabled"><?php esc_html_e('Not Scheduled', 'edh-newsletter'); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="newsletter-schedule-item">
            <div>
                <strong><?php esc_html_e('Monthly Digest', 'edh-newsletter'); ?></strong>
                <?php if ($schedule_status['monthly']['enabled']): ?>
                    <span class="newsletter-schedule-enabled"><?php esc_html_e('Enabled', 'edh-newsletter'); ?></span>
                    <div><?php
                        // translators: %1$s: Formatted date and time of next scheduled run
                        printf(esc_html__('Next run: %1$s', 'edh-newsletter'), esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $schedule_status['monthly']['next_run'])));
                    ?></div>
                <?php else: ?>
                    <span class="newsletter-schedule-disabled"><?php esc_html_e('Not Scheduled', 'edh-newsletter'); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Activity -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Recent Activity', 'edh-newsletter'); ?></h3>
        
        <?php if (!empty($delivery_stats)): ?>
        <div class="newsletter-stats" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
            <div class="newsletter-stat-box">
                <span class="newsletter-stat-number"><?php echo esc_html($delivery_stats['total_sent'] ?? 0); ?></span>
                <div class="newsletter-stat-label"><?php esc_html_e('Total Emails Sent', 'edh-newsletter'); ?></div>
            </div>
            
            <div class="newsletter-stat-box">
                <span class="newsletter-stat-number"><?php echo esc_html($delivery_stats['failed_deliveries'] ?? 0); ?></span>
                <div class="newsletter-stat-label"><?php esc_html_e('Failed Deliveries', 'edh-newsletter'); ?></div>
            </div>
            
            <div class="newsletter-stat-box">
                <div class="newsletter-stat-label"><?php esc_html_e('Last Digest Sent', 'edh-newsletter'); ?></div>
                <div style="font-size: 0.9em; margin-top: 5px;">
                    <?php 
                    if (!empty($delivery_stats['last_digest_sent'])) {
                        echo esc_html(date_i18n(get_option('date_format'), strtotime($delivery_stats['last_digest_sent'])));
                    } else {
                        esc_html_e('Never', 'edh-newsletter');
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <p><?php esc_html_e('No delivery statistics available yet.', 'edh-newsletter'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Quick Links -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Quick Links', 'edh-newsletter'); ?></h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=newsletter-subscribers')); ?>" class="button button-secondary">
                <?php esc_html_e('Manage Subscribers', 'edh-newsletter'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=newsletter-settings')); ?>" class="button button-secondary">
                <?php esc_html_e('Configure Settings', 'edh-newsletter'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=newsletter-templates')); ?>" class="button button-secondary">
                <?php esc_html_e('Customize Templates', 'edh-newsletter'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=newsletter-privacy')); ?>" class="button button-secondary">
                <?php esc_html_e('Privacy Settings', 'edh-newsletter'); ?>
            </a>
        </div>
    </div>
</div>
