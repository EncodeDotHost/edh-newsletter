<?php
/**
 * Settings Admin View
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php esc_html_e('Newsletter Settings', 'edh-newsletter'); ?></h1>
    
    <!-- Settings Tabs -->
    <div class="newsletter-settings-tabs">
        <a href="#weekly-settings" class="active"><?php esc_html_e('Weekly Digest', 'edh-newsletter'); ?></a>
        <a href="#monthly-settings"><?php esc_html_e('Monthly Digest', 'edh-newsletter'); ?></a>
        <a href="#email-settings"><?php esc_html_e('Email Settings', 'edh-newsletter'); ?></a>
        <a href="#template-settings"><?php esc_html_e('Template Settings', 'edh-newsletter'); ?></a>
    </div>
    
    <!-- Weekly Digest Settings -->
    <div id="weekly-settings" class="newsletter-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('newsletter_weekly_settings'); ?>
            
            <div class="newsletter-form-section">
                <h3><?php esc_html_e('Weekly Digest Schedule', 'edh-newsletter'); ?></h3>
                
                <div class="newsletter-field-group">
                    <label for="newsletter_weekly_send_day"><?php esc_html_e('Sending Day', 'edh-newsletter'); ?></label>
                    <div>
                        <select name="newsletter_weekly_send_day" id="newsletter_weekly_send_day">
                            <?php
                            $edh_newsletter_days = [
                                0 => __('Sunday', 'edh-newsletter'),
                                1 => __('Monday', 'edh-newsletter'),
                                2 => __('Tuesday', 'edh-newsletter'),
                                3 => __('Wednesday', 'edh-newsletter'),
                                4 => __('Thursday', 'edh-newsletter'),
                                5 => __('Friday', 'edh-newsletter'),
                                6 => __('Saturday', 'edh-newsletter'),
                            ];
                            $edh_newsletter_current_day = get_option('newsletter_weekly_send_day', 5);
                            foreach ($edh_newsletter_days as $edh_newsletter_value => $edh_newsletter_label) {
                                printf(
                                    '<option value="%d"%s>%s</option>',
                                    absint($edh_newsletter_value),
                                    selected($edh_newsletter_current_day, $edh_newsletter_value, false),
                                    esc_html($edh_newsletter_label)
                                );
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the day of the week to send the weekly digest.', 'edh-newsletter'); ?></p>
                    </div>
                </div>
                
                <div class="newsletter-field-group">
                    <label for="newsletter_weekly_send_hour"><?php esc_html_e('Sending Time', 'edh-newsletter'); ?></label>
                    <div>
                        <select name="newsletter_weekly_send_hour" id="newsletter_weekly_send_hour">
                            <?php
                            $edh_newsletter_current_hour = get_option('newsletter_weekly_send_hour', 13);
                            for ($edh_newsletter_i = 0; $edh_newsletter_i < 24; $edh_newsletter_i++) {
                                $edh_newsletter_display_time = gmdate('g:i A', strtotime("{$edh_newsletter_i}:00"));
                                printf(
                                    '<option value="%d"%s>%s (%02d:00)</option>',
                                    absint($edh_newsletter_i),
                                    selected($edh_newsletter_current_hour, $edh_newsletter_i, false),
                                    esc_html($edh_newsletter_display_time),
                                    absint($edh_newsletter_i)
                                );
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the hour to send the weekly digest (in your site\'s timezone).', 'edh-newsletter'); ?></p>
                    </div>
                </div>
                
                <?php submit_button(esc_html__('Save Weekly Settings', 'edh-newsletter')); ?>
            </div>
        </form>
        
        <?php if (!empty($schedule_status['weekly'])): ?>
        <div class="newsletter-form-section">
            <h3><?php esc_html_e('Current Schedule Status', 'edh-newsletter'); ?></h3>
            <p>
                <?php if ($schedule_status['weekly']['enabled']): ?>
                    <span class="newsletter-schedule-enabled"><?php esc_html_e('Weekly digest is scheduled', 'edh-newsletter'); ?></span><br>
                    <?php
                    // translators: %1$s: Formatted date and time of next scheduled run
                    printf(esc_html__('Next run: %1$s', 'edh-newsletter'), esc_html($schedule_status['weekly']['next_run_formatted']));
                    ?>
                <?php else: ?>
                    <span class="newsletter-schedule-disabled"><?php esc_html_e('Weekly digest is not scheduled', 'edh-newsletter'); ?></span><br>
                    <?php esc_html_e('Please save your settings to activate the schedule.', 'edh-newsletter'); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Monthly Digest Settings -->
    <div id="monthly-settings" class="newsletter-tab-content" style="display: none;">
        <form method="post" action="options.php">
            <?php settings_fields('newsletter_monthly_settings'); ?>
            
            <div class="newsletter-form-section">
                <h3><?php esc_html_e('Monthly Digest Schedule', 'edh-newsletter'); ?></h3>
                
                <div class="newsletter-field-group">
                    <label for="newsletter_monthly_send_day"><?php esc_html_e('Day of Month', 'edh-newsletter'); ?></label>
                    <div>
                        <select name="newsletter_monthly_send_day" id="newsletter_monthly_send_day">
                            <?php
                            $edh_newsletter_current_day = get_option('newsletter_monthly_send_day', 1);
                            for ($edh_newsletter_i = 1; $edh_newsletter_i <= 31; $edh_newsletter_i++) {
                                printf(
                                    '<option value="%d"%s>%d</option>',
                                    absint($edh_newsletter_i),
                                    selected($edh_newsletter_current_day, $edh_newsletter_i, false),
                                    absint($edh_newsletter_i)
                                );
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the day of the month to send the monthly digest. If the day doesn\'t exist in a month (e.g., 31st in February), it will use the last day of that month.', 'edh-newsletter'); ?></p>
                    </div>
                </div>
                
                <div class="newsletter-field-group">
                    <label for="newsletter_monthly_send_hour"><?php esc_html_e('Sending Time', 'edh-newsletter'); ?></label>
                    <div>
                        <select name="newsletter_monthly_send_hour" id="newsletter_monthly_send_hour">
                            <?php
                            $edh_newsletter_current_hour = get_option('newsletter_monthly_send_hour', 10);
                            for ($edh_newsletter_i = 0; $edh_newsletter_i < 24; $edh_newsletter_i++) {
                                $edh_newsletter_display_time = gmdate('g:i A', strtotime("{$edh_newsletter_i}:00"));
                                printf(
                                    '<option value="%d"%s>%s (%02d:00)</option>',
                                    absint($edh_newsletter_i),
                                    selected($edh_newsletter_current_hour, $edh_newsletter_i, false),
                                    esc_html($edh_newsletter_display_time),
                                    absint($edh_newsletter_i)
                                );
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select the hour to send the monthly digest (in your site\'s timezone).', 'edh-newsletter'); ?></p>
                    </div>
                </div>
                
                <?php submit_button(esc_html__('Save Monthly Settings', 'edh-newsletter')); ?>
            </div>
        </form>
        
        <?php if (!empty($schedule_status['monthly'])): ?>
        <div class="newsletter-form-section">
            <h3><?php esc_html_e('Current Schedule Status', 'edh-newsletter'); ?></h3>
            <p>
                <?php if ($schedule_status['monthly']['enabled']): ?>
                    <span class="newsletter-schedule-enabled"><?php esc_html_e('Monthly digest is scheduled', 'edh-newsletter'); ?></span><br>
                    <?php
                    // translators: %1$s: Formatted date and time of next scheduled run
                    printf(esc_html__('Next run: %1$s', 'edh-newsletter'), esc_html($schedule_status['monthly']['next_run_formatted']));
                    ?>
                <?php else: ?>
                    <span class="newsletter-schedule-disabled"><?php esc_html_e('Monthly digest is not scheduled', 'edh-newsletter'); ?></span><br>
                    <?php esc_html_e('Please save your settings to activate the schedule.', 'edh-newsletter'); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Email Settings -->
    <div id="email-settings" class="newsletter-tab-content" style="display: none;">
        <form method="post" action="options.php">
            <?php settings_fields('newsletter_email_settings'); ?>
            
            <div class="newsletter-form-section">
                <h3><?php esc_html_e('Email Configuration', 'edh-newsletter'); ?></h3>
                
                <div class="newsletter-field-group">
                    <label for="newsletter_from_name"><?php esc_html_e('From Name', 'edh-newsletter'); ?></label>
                    <div>
                        <input type="text" name="newsletter_from_name" id="newsletter_from_name" 
                               value="<?php echo esc_attr(get_option('newsletter_from_name', get_bloginfo('name'))); ?>" 
                               class="regular-text">
                        <p class="description"><?php esc_html_e('The name that appears in the "From" field of newsletter emails.', 'edh-newsletter'); ?></p>
                    </div>
                </div>
                
                <div class="newsletter-field-group">
                    <label for="newsletter_from_email"><?php esc_html_e('From Email', 'edh-newsletter'); ?></label>
                    <div>
                        <input type="email" name="newsletter_from_email" id="newsletter_from_email" 
                               value="<?php echo esc_attr(get_option('newsletter_from_email', get_option('admin_email'))); ?>" 
                               class="regular-text" required>
                        <p class="description"><?php esc_html_e('The email address that newsletter emails are sent from.', 'edh-newsletter'); ?></p>
                    </div>
                </div>
                
                <?php submit_button(esc_html__('Save Email Settings', 'edh-newsletter')); ?>
            </div>
        </form>
    </div>
    
    <!-- Template Settings -->
    <div id="template-settings" class="newsletter-tab-content" style="display: none;">
        <form method="post" action="options.php">
            <?php settings_fields('newsletter_template_settings'); ?>
            
            <div class="newsletter-form-section">
                <h3><?php esc_html_e('Template Customization', 'edh-newsletter'); ?></h3>
                
                <div class="newsletter-field-group">
                    <label for="newsletter_brand_color"><?php esc_html_e('Brand Color', 'edh-newsletter'); ?></label>
                    <div>
                        <input type="text" name="newsletter_brand_color" id="newsletter_brand_color" 
                               value="<?php echo esc_attr(get_option('newsletter_brand_color', '#1e73be')); ?>" 
                               class="newsletter-color-picker">
                        <p class="description"><?php esc_html_e('The primary color used in newsletter templates.', 'edh-newsletter'); ?></p>
                    </div>
                </div>
                
                <div class="newsletter-field-group">
                    <label for="newsletter_logo_url"><?php esc_html_e('Logo URL', 'edh-newsletter'); ?></label>
                    <div>
                        <input type="url" name="newsletter_logo_url" id="newsletter_logo_url" 
                               value="<?php echo esc_attr(get_option('newsletter_logo_url', '')); ?>" 
                               class="regular-text">
                        <button type="button" class="button newsletter-upload-logo"><?php esc_html_e('Upload Logo', 'edh-newsletter'); ?></button>
                        <button type="button" class="button newsletter-remove-logo"><?php esc_html_e('Remove', 'edh-newsletter'); ?></button>
                        <p class="description"><?php esc_html_e('Logo to display in newsletter emails (optional).', 'edh-newsletter'); ?></p>
                        <div class="newsletter-logo-preview">
                            <?php if (get_option('newsletter_logo_url')): ?>
                                <img src="<?php echo esc_url(get_option('newsletter_logo_url')); ?>" style="max-width: 200px; max-height: 100px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php submit_button(esc_html__('Save Template Settings', 'edh-newsletter')); ?>
            </div>
        </form>
    </div>
    
    <!-- Cron Diagnostics -->
    <?php if (!empty($cron_diagnostics)): ?>
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('System Diagnostics', 'edh-newsletter'); ?></h3>
        
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('WordPress Cron', 'edh-newsletter'); ?></strong></td>
                    <td>
                        <?php if ($cron_diagnostics['wp_cron_enabled']): ?>
                            <span style="color: green;"><?php esc_html_e('Enabled', 'edh-newsletter'); ?></span>
                        <?php else: ?>
                            <span style="color: red;"><?php esc_html_e('Disabled', 'edh-newsletter'); ?></span>
                            <p class="description"><?php esc_html_e('WordPress cron is disabled. Scheduled emails may not be sent automatically.', 'edh-newsletter'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Timezone', 'edh-newsletter'); ?></strong></td>
                    <td><?php echo esc_html($cron_diagnostics['timezone']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Current Time', 'edh-newsletter'); ?></strong></td>
                    <td><?php echo esc_html($cron_diagnostics['current_time']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
