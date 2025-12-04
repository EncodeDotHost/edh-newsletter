<?php
/**
 * Subscriber Manager Class
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Subscriber Manager
 * 
 * Handles all subscriber CRUD operations and data management
 */
class EDH_Newsletter_Subscriber_Manager {
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Valid subscriber statuses
     */
    const VALID_STATUSES = ['pending', 'subscribed', 'unsubscribed', 'paused'];
    
    /**
     * Valid digest frequencies
     */
    const VALID_FREQUENCIES = ['weekly', 'monthly'];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'newsletter_subscribers';
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('edh_newsletter_cleanup_expired_data', [$this, 'cleanup_expired_subscribers']);
    }
    
    /**
     * Create a new subscriber
     */
    public function create_subscriber(array $data): array {
        global $wpdb;
        
        // Validate required fields
        if (empty($data['email']) || !is_email($data['email'])) {
            return $this->error_response('Invalid email address');
        }
        
        // Sanitize and prepare data
        $subscriber_data = $this->prepare_subscriber_data($data);
        
        // Check if subscriber already exists
        $existing = $this->get_subscriber_by_email($subscriber_data['email']);
        if ($existing && $existing['status'] === 'subscribed') {
            return $this->error_response('Email already subscribed');
        }
        
        // Generate confirmation token
        $subscriber_data['token'] = $this->generate_token($subscriber_data['email']);
        $subscriber_data['privacy_consent_date'] = current_time('mysql');
        $subscriber_data['consent_version'] = get_option('newsletter_consent_version', '1.0');
        
        if ($existing) {
            // Update existing subscriber
            $result = $wpdb->update(
                $this->table_name,
                $subscriber_data,
                ['email' => $subscriber_data['email']],
                $this->get_format_array($subscriber_data),
                ['%s']
            );
            $subscriber_id = $existing['id'];
        } else {
            // Insert new subscriber
            $result = $wpdb->insert(
                $this->table_name,
                $subscriber_data,
                $this->get_format_array($subscriber_data)
            );
            $subscriber_id = $wpdb->insert_id;
        }
        
        if ($result === false) {
            return $this->error_response('Database error: ' . $wpdb->last_error);
        }
        
        $subscriber = $this->get_subscriber($subscriber_id);
        
        // Fire action for other plugins to hook into
        do_action('edh_newsletter_subscriber_created', $subscriber, $data);
        
        return $this->success_response($subscriber);
    }
    
    /**
     * Get subscriber by ID
     */
    public function get_subscriber(int $id): ?array {
        global $wpdb;
        
        $table_name = esc_sql($this->table_name);
        $subscriber = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$table_name}` WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $subscriber ? $this->format_subscriber($subscriber) : null;
    }
    
    /**
     * Get subscriber by email
     */
    public function get_subscriber_by_email(string $email): ?array {
        global $wpdb;
        
        $table_name = esc_sql($this->table_name);
        $subscriber = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$table_name}` WHERE email = %s", $email),
            ARRAY_A
        );
        
        return $subscriber ? $this->format_subscriber($subscriber) : null;
    }
    
    /**
     * Get subscriber by token
     */
    public function get_subscriber_by_token(string $token): ?array {
        global $wpdb;
        
        $table_name = esc_sql($this->table_name);
        $subscriber = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$table_name}` WHERE token = %s", $token),
            ARRAY_A
        );
        
        return $subscriber ? $this->format_subscriber($subscriber) : null;
    }
    
    /**
     * Update subscriber
     */
    public function update_subscriber(int $id, array $data): array {
        global $wpdb;
        
        $subscriber = $this->get_subscriber($id);
        if (!$subscriber) {
            return $this->error_response('Subscriber not found');
        }
        
        // Prepare update data
        $update_data = $this->prepare_subscriber_data($data, false);
        
        if (empty($update_data)) {
            return $this->error_response('No valid data to update');
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $id],
            $this->get_format_array($update_data),
            ['%d']
        );
        
        if ($result === false) {
            return $this->error_response('Database error: ' . $wpdb->last_error);
        }
        
        $updated_subscriber = $this->get_subscriber($id);
        
        // Fire action for other plugins to hook into
        do_action('edh_newsletter_subscriber_updated', $updated_subscriber, $data);
        
        return $this->success_response($updated_subscriber);
    }
    
    /**
     * Confirm subscriber subscription
     */
    public function confirm_subscription(string $token): array {
        $subscriber = $this->get_subscriber_by_token($token);
        
        if (!$subscriber || $subscriber['status'] !== 'pending') {
            return $this->error_response('Invalid or expired confirmation token');
        }
        
        $result = $this->update_subscriber($subscriber['id'], [
            'status' => 'subscribed',
            'token' => '', // Clear token after confirmation
            'last_engagement_date' => current_time('mysql')
        ]);
        
        if ($result['success']) {
            do_action('edh_newsletter_subscription_confirmed', $result['data']);
        }
        
        return $result;
    }
    
    /**
     * Unsubscribe subscriber
     */
    public function unsubscribe(int $id, string $reason = ''): array {
        $subscriber = $this->get_subscriber($id);
        
        if (!$subscriber) {
            return $this->error_response('Subscriber not found');
        }
        
        $preferences = $subscriber['preferences'] ? json_decode($subscriber['preferences'], true) : [];
        $preferences['unsubscribe_reason'] = $reason;
        $preferences['unsubscribed_at'] = current_time('mysql');
        
        $result = $this->update_subscriber($id, [
            'status' => 'unsubscribed',
            'preferences' => json_encode($preferences)
        ]);
        
        if ($result['success']) {
            do_action('edh_newsletter_subscriber_unsubscribed', $result['data'], $reason);
        }
        
        return $result;
    }
    
    /**
     * Pause subscription
     */
    public function pause_subscription(int $id): array {
        return $this->update_subscriber($id, ['status' => 'paused']);
    }
    
    /**
     * Resume subscription
     */
    public function resume_subscription(int $id): array {
        return $this->update_subscriber($id, [
            'status' => 'subscribed',
            'last_engagement_date' => current_time('mysql')
        ]);
    }
    
    /**
     * Delete subscriber (GDPR compliance)
     */
    public function delete_subscriber(int $id): array {
        global $wpdb;
        
        $subscriber = $this->get_subscriber($id);
        if (!$subscriber) {
            return $this->error_response('Subscriber not found');
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            return $this->error_response('Database error: ' . $wpdb->last_error);
        }
        
        do_action('edh_newsletter_subscriber_deleted', $subscriber);
        
        return $this->success_response(['deleted' => true]);
    }
    
    /**
     * Get subscribers by criteria
     */
    public function get_subscribers(array $args = []): array {
        global $wpdb;
        
        $defaults = [
            'status' => 'subscribed',
            'frequency' => null,
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Sanitize and validate arguments
        $args['limit'] = absint($args['limit']);
        $args['offset'] = absint($args['offset']);
        
        // Validate and escape orderby column - whitelist allowed columns
        $allowed_orderby = ['id', 'email', 'status', 'digest_frequency', 'created_at', 'updated_at', 'last_engagement_date'];
        $orderby_column_raw = isset($args['orderby']) ? sanitize_key($args['orderby']) : 'created_at';
        $orderby_column = in_array($orderby_column_raw, $allowed_orderby, true) ? $orderby_column_raw : 'created_at';
        $orderby_column_escaped = esc_sql($orderby_column);
        
        // Validate and escape order direction
        $order_direction_raw = isset($args['order']) ? strtoupper(sanitize_key($args['order'])) : 'DESC';
        $order_direction = ($order_direction_raw === 'ASC') ? 'ASC' : 'DESC';
        $order_direction_escaped = esc_sql($order_direction);
        
        $where_conditions = [];
        $where_values = [];
        $status_map = array_combine(self::VALID_STATUSES, self::VALID_STATUSES);
        
        // Status filter - use map lookup to avoid taint
        if (!empty($args['status'])) {
            $status_list = [];
            if (is_array($args['status'])) {
                foreach ($args['status'] as $status) {
                    if (is_string($status) && isset($status_map[$status])) {
                        $status_list[] = $status_map[$status];
                    }
                }
            } elseif (is_string($args['status']) && isset($status_map[$args['status']])) {
                $status_list[] = $status_map[$args['status']];
            }
            
            if (!empty($status_list)) {
                if (count($status_list) > 1) {
                    $placeholders = implode(',', array_fill(0, count($status_list), '%s'));
                    $where_conditions[] = "status IN ($placeholders)";
                    $where_values = array_merge($where_values, $status_list);
                } else {
                    $where_conditions[] = "status = %s";
                    $where_values[] = $status_list[0];
                }
            }
        }
        
        // Frequency filter - use map lookup to break taint chain
        $frequency_map = [
            'weekly' => 'weekly',
            'monthly' => 'monthly',
        ];
        
        if (isset($args['frequency']) && is_string($args['frequency']) && isset($frequency_map[$args['frequency']])) {
            $where_conditions[] = "digest_frequency = %s";
            // Use trusted value from map, not user input
            $where_values[] = $frequency_map[$args['frequency']];
        }
        
        // Build WHERE clause - all conditions use placeholders, safe to concatenate
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Build ORDER BY clause - use escaped whitelisted column and validated direction
        $order_clause = "ORDER BY {$orderby_column_escaped} {$order_direction_escaped}";
        
        // Build LIMIT clause
        $limit_values = [];
        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = 'LIMIT %d OFFSET %d';
            $limit_values = [$args['limit'], $args['offset']];
        }
        
        // Build query with placeholders - always include at least one placeholder
        $table_name = esc_sql($this->table_name);
        if (empty($where_clause)) {
            // Add a harmless WHERE clause to ensure we always have a placeholder
            $where_clause = 'WHERE 1 = %d';
            $where_values = [1];
        }
        
        // Prepare query with all values and execute
        $all_values = array_merge($where_values, $limit_values);
        $subscribers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table_name}` {$where_clause} {$order_clause} {$limit_clause}",
                $all_values
            ),
            ARRAY_A
        );
        
        return array_map([$this, 'format_subscriber'], $subscribers);
    }
    
    /**
     * Get subscriber count by criteria
     */
    public function get_subscriber_count(array $args = []): int {
        global $wpdb;
        
        $where_conditions = [];
        $where_values = [];
        $status_map = array_combine(self::VALID_STATUSES, self::VALID_STATUSES);
        
        if (!empty($args['status'])) {
            $status_list = [];
            if (is_array($args['status'])) {
                foreach ($args['status'] as $status) {
                    if (is_string($status) && isset($status_map[$status])) {
                        $status_list[] = $status_map[$status];
                    }
                }
            } elseif (is_string($args['status']) && isset($status_map[$args['status']])) {
                $status_list[] = $status_map[$args['status']];
            }
            
            if (!empty($status_list)) {
                if (count($status_list) > 1) {
                    $placeholders = implode(',', array_fill(0, count($status_list), '%s'));
                    $where_conditions[] = "status IN ($placeholders)";
                    $where_values = array_merge($where_values, $status_list);
                } else {
                    $where_conditions[] = "status = %s";
                    $where_values[] = $status_list[0];
                }
            }
        }
        
        // Frequency filter - use map lookup to break taint chain
        $frequency_map = [
            'weekly' => 'weekly',
            'monthly' => 'monthly',
        ];
        
        if (isset($args['frequency']) && is_string($args['frequency']) && isset($frequency_map[$args['frequency']])) {
            $where_conditions[] = "digest_frequency = %s";
            // Use trusted value from map, not user input
            $where_values[] = $frequency_map[$args['frequency']];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        } else {
            // Add a harmless WHERE clause to ensure we always have a placeholder
            $where_clause = 'WHERE 1 = %d';
            $where_values = [1];
        }
        
        // Build and prepare query
        $table_name = esc_sql($this->table_name);
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_name}` {$where_clause}",
                $where_values
            )
        );
    }
    
    /**
     * Update engagement date for subscriber
     */
    public function update_engagement(int $id): void {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            ['last_engagement_date' => current_time('mysql')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }
    
    /**
     * Cleanup expired subscribers based on data retention policy
     */
    public function cleanup_expired_subscribers(): void {
        global $wpdb;
        
        $retention_days = get_option('newsletter_data_retention_days', 365);
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Delete unsubscribed subscribers older than retention period
        $deleted = $wpdb->delete(
            $this->table_name,
            [
                'status' => 'unsubscribed',
                'updated_at' => $cutoff_date
            ],
            ['%s', '%s']
        );
        
        if ($deleted > 0) {
            do_action('edh_newsletter_expired_subscribers_cleaned', $deleted);
        }
    }
    
    /**
     * Export subscriber data (GDPR compliance)
     */
    public function export_subscriber_data(string $email): ?array {
        $subscriber = $this->get_subscriber_by_email($email);
        
        if (!$subscriber) {
            return null;
        }
        
        // Return all subscriber data for export
        return [
            'personal_data' => [
                'email' => $subscriber['email'],
                'subscription_date' => $subscriber['created_at'],
                'status' => $subscriber['status'],
                'digest_frequency' => $subscriber['digest_frequency'],
                'privacy_consent_date' => $subscriber['privacy_consent_date'],
                'consent_version' => $subscriber['consent_version'],
                'last_engagement' => $subscriber['last_engagement_date'],
                'preferences' => $subscriber['preferences']
            ]
        ];
    }
    
    /**
     * Prepare subscriber data for database operations
     */
    private function prepare_subscriber_data(array $data, bool $is_new = true): array {
        $prepared = [];
        
        // Email (required for new subscribers)
        if (isset($data['email'])) {
            $email = sanitize_email($data['email']);
            if (is_email($email)) {
                $prepared['email'] = $email;
            }
        }
        
        // Status
        if (isset($data['status']) && in_array($data['status'], self::VALID_STATUSES)) {
            $prepared['status'] = $data['status'];
        } elseif ($is_new) {
            $prepared['status'] = 'pending';
        }
        
        // Digest frequency
        if (isset($data['digest_frequency']) && in_array($data['digest_frequency'], self::VALID_FREQUENCIES)) {
            $prepared['digest_frequency'] = $data['digest_frequency'];
        } elseif ($is_new) {
            $prepared['digest_frequency'] = 'weekly';
        }
        
        // Preferences (JSON)
        if (isset($data['preferences'])) {
            if (is_array($data['preferences'])) {
                $prepared['preferences'] = json_encode($data['preferences']);
            } elseif (is_string($data['preferences'])) {
                // Validate JSON
                json_decode($data['preferences']);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $prepared['preferences'] = $data['preferences'];
                }
            }
        }
        
        // Token
        if (isset($data['token'])) {
            $prepared['token'] = sanitize_text_field($data['token']);
        }
        
        // Privacy consent date
        if (isset($data['privacy_consent_date'])) {
            $prepared['privacy_consent_date'] = sanitize_text_field($data['privacy_consent_date']);
        }
        
        // Consent version
        if (isset($data['consent_version'])) {
            $prepared['consent_version'] = sanitize_text_field($data['consent_version']);
        }
        
        // Last engagement date
        if (isset($data['last_engagement_date'])) {
            $prepared['last_engagement_date'] = sanitize_text_field($data['last_engagement_date']);
        }
        
        return $prepared;
    }
    
    /**
     * Format subscriber data for output
     */
    private function format_subscriber(array $subscriber): array {
        // Decode JSON preferences
        if (!empty($subscriber['preferences'])) {
            $subscriber['preferences'] = json_decode($subscriber['preferences'], true);
        } else {
            $subscriber['preferences'] = [];
        }
        
        // Convert numeric strings to integers
        $subscriber['id'] = (int) $subscriber['id'];
        
        return $subscriber;
    }
    
    /**
     * Generate secure token for subscriber
     */
    private function generate_token(string $email): string {
        return hash('sha256', $email . time() . wp_generate_uuid4() . AUTH_KEY);
    }
    
    /**
     * Get format array for wpdb operations
     */
    private function get_format_array(array $data): array {
        $formats = [];
        
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'id':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }
        
        return $formats;
    }
    
    /**
     * Create success response
     */
    private function success_response($data = null): array {
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * Create error response
     */
    private function error_response(string $message): array {
        return [
            'success' => false,
            'error' => $message
        ];
    }
}
