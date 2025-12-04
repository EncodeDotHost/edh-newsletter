<?php
/**
 * Subscribers Admin View
 *
 * @package Newsletter
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

// Get status and frequency counts for filters
$edh_newsletter_admin_interface = new EDH_Newsletter_Admin_Interface();
$edh_newsletter_status_counts = $edh_newsletter_admin_interface->get_status_counts();
$edh_newsletter_frequency_counts = $edh_newsletter_admin_interface->get_frequency_counts();
?>

<div class="wrap">
    <h1><?php esc_html_e('Newsletter Subscribers', 'edh-newsletter'); ?></h1>
    
    <?php if ($message): ?>
        <?php echo wp_kses_post($message); ?>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="newsletter-filters">
        <form method="get">
            <input type="hidden" name="page" value="newsletter-subscribers">
            
            <div>
                <label for="status-filter"><?php esc_html_e('Status', 'edh-newsletter'); ?></label>
                <select name="status" id="status-filter">
                    <option value="all"><?php
                        // translators: %d: Total number of subscribers
                        printf(esc_html__('All (%d)', 'edh-newsletter'), absint($edh_newsletter_status_counts['all'] ?? 0));
                    ?></option>
                    <option value="subscribed" <?php selected($status_filter, 'subscribed'); ?>>
                        <?php
                        // translators: %d: Number of subscribed subscribers
                        printf(esc_html__('Subscribed (%d)', 'edh-newsletter'), absint($edh_newsletter_status_counts['subscribed'] ?? 0));
                        ?>
                    </option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>
                        <?php
                        // translators: %d: Number of pending subscribers
                        printf(esc_html__('Pending (%d)', 'edh-newsletter'), absint($edh_newsletter_status_counts['pending'] ?? 0));
                        ?>
                    </option>
                    <option value="unsubscribed" <?php selected($status_filter, 'unsubscribed'); ?>>
                        <?php
                        // translators: %d: Number of unsubscribed subscribers
                        printf(esc_html__('Unsubscribed (%d)', 'edh-newsletter'), absint($edh_newsletter_status_counts['unsubscribed'] ?? 0));
                        ?>
                    </option>
                    <option value="paused" <?php selected($status_filter, 'paused'); ?>>
                        <?php
                        // translators: %d: Number of paused subscribers
                        printf(esc_html__('Paused (%d)', 'edh-newsletter'), absint($edh_newsletter_status_counts['paused'] ?? 0));
                        ?>
                    </option>
                </select>
            </div>
            
            <div>
                <label for="frequency-filter"><?php esc_html_e('Frequency', 'edh-newsletter'); ?></label>
                <select name="frequency" id="frequency-filter">
                    <option value="all"><?php
                        // translators: %d: Total number of subscribers
                        printf(esc_html__('All (%d)', 'edh-newsletter'), absint($edh_newsletter_frequency_counts['all'] ?? 0));
                    ?></option>
                    <option value="weekly" <?php selected($frequency_filter, 'weekly'); ?>>
                        <?php
                        // translators: %d: Number of weekly subscribers
                        printf(esc_html__('Weekly (%d)', 'edh-newsletter'), absint($edh_newsletter_frequency_counts['weekly'] ?? 0));
                        ?>
                    </option>
                    <option value="monthly" <?php selected($frequency_filter, 'monthly'); ?>>
                        <?php
                        // translators: %d: Number of monthly subscribers
                        printf(esc_html__('Monthly (%d)', 'edh-newsletter'), absint($edh_newsletter_frequency_counts['monthly'] ?? 0));
                        ?>
                    </option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button"><?php esc_html_e('Filter', 'edh-newsletter'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=newsletter-subscribers')); ?>" class="button"><?php esc_html_e('Reset', 'edh-newsletter'); ?></a>
            </div>
        </form>
    </div>
    
    <!-- Subscribers Table -->
    <form method="post" class="newsletter-bulk-actions">
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value="-1"><?php esc_html_e('Bulk Actions', 'edh-newsletter'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'edh-newsletter'); ?></option>
                    <option value="unsubscribe"><?php esc_html_e('Unsubscribe', 'edh-newsletter'); ?></option>
                    <option value="resubscribe"><?php esc_html_e('Resubscribe', 'edh-newsletter'); ?></option>
                </select>
                <button type="submit" class="button action"><?php esc_html_e('Apply', 'edh-newsletter'); ?></button>
            </div>
            
            <?php $edh_newsletter_admin_interface->render_pagination($total_subscribers, $per_page, $page); ?>
        </div>
        
        <table class="wp-list-table widefat fixed striped newsletter-subscribers-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="newsletter-select-all">
                    </td>
                    <th scope="col" class="manage-column"><?php esc_html_e('ID', 'edh-newsletter'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Email', 'edh-newsletter'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Status', 'edh-newsletter'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Frequency', 'edh-newsletter'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Subscribed Date', 'edh-newsletter'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Last Engagement', 'edh-newsletter'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Actions', 'edh-newsletter'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($subscribers)): ?>
                    <?php foreach ($subscribers as $edh_newsletter_subscriber): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="subscribers[]" value="<?php echo esc_attr($edh_newsletter_subscriber['id']); ?>">
                            </th>
                            <td><?php echo esc_html($edh_newsletter_subscriber['id']); ?></td>
                            <td>
                                <strong><?php echo esc_html($edh_newsletter_subscriber['email']); ?></strong>
                                <?php if (!empty($edh_newsletter_subscriber['preferences'])): ?>
                                    <br><small class="description"><?php esc_html_e('Has preferences', 'edh-newsletter'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="newsletter-status-badge <?php echo esc_attr($edh_newsletter_subscriber['status']); ?>">
                                    <?php echo esc_html(ucfirst($edh_newsletter_subscriber['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ucfirst($edh_newsletter_subscriber['digest_frequency'])); ?></td>
                            <td>
                                <?php 
                                if ($edh_newsletter_subscriber['created_at']) {
                                    echo esc_html(date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'), 
                                        strtotime($edh_newsletter_subscriber['created_at'])
                                    ));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($edh_newsletter_subscriber['last_engagement_date']) {
                                    echo esc_html(date_i18n(
                                        get_option('date_format'), 
                                        strtotime($edh_newsletter_subscriber['last_engagement_date'])
                                    ));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td class="newsletter-subscriber-actions">
                                <?php if ($edh_newsletter_subscriber['status'] === 'subscribed'): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(
                                        add_query_arg([
                                            'page' => 'newsletter-subscribers',
                                            'action' => 'unsubscribe',
                                            'id' => $edh_newsletter_subscriber['id']
                                        ], admin_url('admin.php')),
                                        'unsubscribe_subscriber_' . $edh_newsletter_subscriber['id']
                                    )); ?>" class="button button-small">
                                        <?php esc_html_e('Unsubscribe', 'edh-newsletter'); ?>
                                    </a>
                                <?php elseif ($edh_newsletter_subscriber['status'] === 'unsubscribed'): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(
                                        add_query_arg([
                                            'page' => 'newsletter-subscribers',
                                            'action' => 'resubscribe',
                                            'id' => $edh_newsletter_subscriber['id']
                                        ], admin_url('admin.php')),
                                        'resubscribe_subscriber_' . $edh_newsletter_subscriber['id']
                                    )); ?>" class="button button-small">
                                        <?php esc_html_e('Resubscribe', 'edh-newsletter'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo esc_url(wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'newsletter-subscribers',
                                        'action' => 'delete',
                                        'id' => $edh_newsletter_subscriber['id']
                                    ], admin_url('admin.php')),
                                    'delete_subscriber_' . $edh_newsletter_subscriber['id']
                                )); ?>" 
                                   class="button button-small button-link-delete newsletter-delete-subscriber">
                                    <?php esc_html_e('Delete', 'edh-newsletter'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <p><?php esc_html_e('No subscribers found.', 'edh-newsletter'); ?></p>
                            <?php if ($status_filter !== 'all' || $frequency_filter !== 'all'): ?>
                                <p>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=newsletter-subscribers')); ?>" class="button">
                                        <?php esc_html_e('View All Subscribers', 'edh-newsletter'); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <?php $edh_newsletter_admin_interface->render_pagination($total_subscribers, $per_page, $page); ?>
        </div>
    </form>
    
    <!-- Add New Subscriber -->
    <div class="newsletter-form-section" style="margin-top: 30px;">
        <h3><?php esc_html_e('Add New Subscriber', 'edh-newsletter'); ?></h3>
        <form method="post" class="newsletter-add-subscriber-form">
            <?php wp_nonce_field('newsletter_add_subscriber', 'newsletter_add_subscriber_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="new_subscriber_email"><?php esc_html_e('Email Address', 'edh-newsletter'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="email" id="new_subscriber_email" class="regular-text" required>
                        <p class="description"><?php esc_html_e('Enter the email address for the new subscriber.', 'edh-newsletter'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="new_subscriber_frequency"><?php esc_html_e('Digest Frequency', 'edh-newsletter'); ?></label>
                    </th>
                    <td>
                        <select name="digest_frequency" id="new_subscriber_frequency">
                            <option value="weekly"><?php esc_html_e('Weekly', 'edh-newsletter'); ?></option>
                            <option value="monthly"><?php esc_html_e('Monthly', 'edh-newsletter'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="new_subscriber_status"><?php esc_html_e('Status', 'edh-newsletter'); ?></label>
                    </th>
                    <td>
                        <select name="status" id="new_subscriber_status">
                            <option value="pending"><?php esc_html_e('Pending (requires confirmation)', 'edh-newsletter'); ?></option>
                            <option value="subscribed"><?php esc_html_e('Subscribed (skip confirmation)', 'edh-newsletter'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Choose whether to require email confirmation or subscribe immediately.', 'edh-newsletter'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(esc_html__('Add Subscriber', 'edh-newsletter'), 'primary', 'add_subscriber'); ?>
        </form>
    </div>
    
    <!-- Export Subscribers -->
    <div class="newsletter-form-section">
        <h3><?php esc_html_e('Export Subscribers', 'edh-newsletter'); ?></h3>
        <p><?php esc_html_e('Export subscriber data to CSV format.', 'edh-newsletter'); ?></p>
        
        <form method="post">
            <?php wp_nonce_field('newsletter_export_subscribers', 'newsletter_export_nonce'); ?>
            
            <div style="margin-bottom: 15px;">
                <label>
                    <input type="checkbox" name="export_status[]" value="subscribed" checked>
                    <?php esc_html_e('Subscribed', 'edh-newsletter'); ?>
                </label><br>
                <label>
                    <input type="checkbox" name="export_status[]" value="pending">
                    <?php esc_html_e('Pending', 'edh-newsletter'); ?>
                </label><br>
                <label>
                    <input type="checkbox" name="export_status[]" value="unsubscribed">
                    <?php esc_html_e('Unsubscribed', 'edh-newsletter'); ?>
                </label><br>
                <label>
                    <input type="checkbox" name="export_status[]" value="paused">
                    <?php esc_html_e('Paused', 'edh-newsletter'); ?>
                </label>
            </div>
            
            <button type="submit" name="export_subscribers" class="button button-secondary">
                <?php esc_html_e('Export to CSV', 'edh-newsletter'); ?>
            </button>
        </form>
    </div>
</div>
