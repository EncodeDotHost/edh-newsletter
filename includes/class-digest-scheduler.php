<?php
/**
 * Digest Scheduler Class
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Digest Scheduler
 * 
 * Handles cron scheduling for weekly and monthly digests
 */
class EDH_Newsletter_Digest_Scheduler {
    
    /**
     * Cron hook names
     */
    const WEEKLY_HOOK = 'edh_newsletter_send_weekly_digest';
    const MONTHLY_HOOK = 'edh_newsletter_send_monthly_digest';
    const CLEANUP_HOOK = 'edh_newsletter_cleanup_expired_data';
    
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
        // Register custom cron intervals
        add_filter('cron_schedules', [$this, 'add_custom_cron_intervals']);
        
        // Hook into settings updates to reschedule
        add_action('update_option_newsletter_weekly_send_day', [$this, 'reschedule_weekly_digest']);
        add_action('update_option_newsletter_weekly_send_hour', [$this, 'reschedule_weekly_digest']);
        add_action('update_option_newsletter_monthly_send_day', [$this, 'reschedule_monthly_digest']);
        add_action('update_option_newsletter_monthly_send_hour', [$this, 'reschedule_monthly_digest']);
        
        // Schedule cleanup task
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CLEANUP_HOOK);
        }
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_custom_cron_intervals(array $schedules): array {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Weekly', 'edh-newsletter'),
        ];
        
        $schedules['monthly'] = [
            'interval' => MONTH_IN_SECONDS,
            'display' => __('Monthly', 'edh-newsletter'),
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule all digest events
     */
    public function schedule_events(): void {
        $this->schedule_weekly_digest();
        $this->schedule_monthly_digest();
        $this->schedule_cleanup_task();
    }
    
    /**
     * Schedule weekly digest
     */
    public function schedule_weekly_digest(): void {
        // Clear existing schedule
        $this->unschedule_event(self::WEEKLY_HOOK);
        
        $next_run = $this->calculate_next_weekly_run();
        
        if ($next_run) {
            wp_schedule_event($next_run, 'weekly', self::WEEKLY_HOOK);
            do_action('edh_newsletter_weekly_digest_scheduled', $next_run);
        }
    }
    
    /**
     * Schedule monthly digest
     */
    public function schedule_monthly_digest(): void {
        // Clear existing schedule
        $this->unschedule_event(self::MONTHLY_HOOK);
        
        $next_run = $this->calculate_next_monthly_run();
        
        if ($next_run) {
            wp_schedule_event($next_run, 'monthly', self::MONTHLY_HOOK);
            do_action('edh_newsletter_monthly_digest_scheduled', $next_run);
        }
    }
    
    /**
     * Schedule cleanup task
     */
    public function schedule_cleanup_task(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            // Schedule daily cleanup at 2 AM
            $next_cleanup = strtotime('tomorrow 2:00 AM');
            wp_schedule_event($next_cleanup, 'daily', self::CLEANUP_HOOK);
        }
    }
    
    /**
     * Reschedule weekly digest
     */
    public function reschedule_weekly_digest(): void {
        $this->schedule_weekly_digest();
    }
    
    /**
     * Reschedule monthly digest
     */
    public function reschedule_monthly_digest(): void {
        $this->schedule_monthly_digest();
    }
    
    /**
     * Calculate next weekly digest run time
     */
    private function calculate_next_weekly_run(): ?int {
        $target_day = get_option('newsletter_weekly_send_day', 5); // Friday
        $target_hour = get_option('newsletter_weekly_send_hour', 13); // 1 PM
        
        return $this->calculate_next_run_time($target_day, $target_hour, 'weekly');
    }
    
    /**
     * Calculate next monthly digest run time
     */
    private function calculate_next_monthly_run(): ?int {
        $target_day = get_option('newsletter_monthly_send_day', 1); // 1st of month
        $target_hour = get_option('newsletter_monthly_send_hour', 10); // 10 AM
        
        return $this->calculate_next_run_time($target_day, $target_hour, 'monthly');
    }
    
    /**
     * Calculate next run time based on frequency
     */
    private function calculate_next_run_time(int $target_day, int $target_hour, string $frequency): ?int {
        // Get current time in site timezone
        $now = current_time('timestamp', false);
        $today = gmdate('Y-m-d', $now);
        $current_hour = (int) gmdate('G', $now);
        
        if ($frequency === 'weekly') {
            return $this->calculate_weekly_run_time($target_day, $target_hour, $now);
        } elseif ($frequency === 'monthly') {
            return $this->calculate_monthly_run_time($target_day, $target_hour, $now);
        }
        
        return null;
    }
    
    /**
     * Calculate weekly run time
     */
    private function calculate_weekly_run_time(int $target_day, int $target_hour, int $now): int {
        $current_day = (int) gmdate('w', $now); // 0 (Sunday) to 6 (Saturday)
        $current_hour = (int) gmdate('G', $now);
        
        // Calculate days until target day
        $days_until_target = ($target_day - $current_day + 7) % 7;
        
        // If it's the target day but past the target hour, schedule for next week
        if ($days_until_target === 0 && $current_hour >= $target_hour) {
            $days_until_target = 7;
        }
        
        // Calculate the target timestamp
        $target_date = gmdate('Y-m-d', strtotime("+{$days_until_target} days", $now));
        $target_timestamp = strtotime("{$target_date} {$target_hour}:00:00");
        
        return $target_timestamp;
    }
    
    /**
     * Calculate monthly run time
     */
    private function calculate_monthly_run_time(int $target_day, int $target_hour, int $now): int {
        $current_day = (int) gmdate('j', $now); // Day of month (1-31)
        $current_hour = (int) gmdate('G', $now);
        $current_month = gmdate('Y-m', $now);
        
        // If target day hasn't passed this month, schedule for this month
        if ($target_day > $current_day || ($target_day === $current_day && $current_hour < $target_hour)) {
            $target_date = "{$current_month}-" . str_pad((string) $target_day, 2, '0', STR_PAD_LEFT);
        } else {
            // Schedule for next month
            $next_month = gmdate('Y-m', strtotime('+1 month', $now));
            $target_date = "{$next_month}-" . str_pad((string) $target_day, 2, '0', STR_PAD_LEFT);
        }
        
        // Handle months with fewer days (e.g., February 30th -> February 28th/29th)
        $target_timestamp = strtotime("{$target_date} {$target_hour}:00:00");
        
        // If the date doesn't exist (e.g., Feb 30), use the last day of the month
        if ($target_timestamp === false) {
            $year_month = explode('-', $target_date)[0] . '-' . explode('-', $target_date)[1];
            $last_day = gmdate('t', strtotime($year_month . '-01'));
            $target_date = "{$year_month}-{$last_day}";
            $target_timestamp = strtotime("{$target_date} {$target_hour}:00:00");
        }
        
        return $target_timestamp;
    }
    
    /**
     * Unschedule a specific event
     */
    private function unschedule_event(string $hook): void {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }
    
    /**
     * Clear all scheduled events
     */
    public function clear_all_schedules(): void {
        $this->unschedule_event(self::WEEKLY_HOOK);
        $this->unschedule_event(self::MONTHLY_HOOK);
        $this->unschedule_event(self::CLEANUP_HOOK);
    }
    
    /**
     * Get next scheduled digest times
     */
    public function get_next_digest_times(): array {
        return [
            'weekly' => [
                'timestamp' => wp_next_scheduled(self::WEEKLY_HOOK),
                'formatted' => $this->format_next_run_time(wp_next_scheduled(self::WEEKLY_HOOK)),
            ],
            'monthly' => [
                'timestamp' => wp_next_scheduled(self::MONTHLY_HOOK),
                'formatted' => $this->format_next_run_time(wp_next_scheduled(self::MONTHLY_HOOK)),
            ],
            'cleanup' => [
                'timestamp' => wp_next_scheduled(self::CLEANUP_HOOK),
                'formatted' => $this->format_next_run_time(wp_next_scheduled(self::CLEANUP_HOOK)),
            ],
        ];
    }
    
    /**
     * Format next run time for display
     */
    private function format_next_run_time($timestamp): string {
        if (!$timestamp) {
            return __('Not scheduled', 'edh-newsletter');
        }
        
        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS)
        );
    }
    
    /**
     * Get schedule status information
     */
    public function get_schedule_status(): array {
        $weekly_scheduled = wp_next_scheduled(self::WEEKLY_HOOK);
        $monthly_scheduled = wp_next_scheduled(self::MONTHLY_HOOK);
        $cleanup_scheduled = wp_next_scheduled(self::CLEANUP_HOOK);
        
        return [
            'weekly' => [
                'enabled' => (bool) $weekly_scheduled,
                'next_run' => $weekly_scheduled,
                'day' => get_option('newsletter_weekly_send_day', 5),
                'hour' => get_option('newsletter_weekly_send_hour', 13),
            ],
            'monthly' => [
                'enabled' => (bool) $monthly_scheduled,
                'next_run' => $monthly_scheduled,
                'day' => get_option('newsletter_monthly_send_day', 1),
                'hour' => get_option('newsletter_monthly_send_hour', 10),
            ],
            'cleanup' => [
                'enabled' => (bool) $cleanup_scheduled,
                'next_run' => $cleanup_scheduled,
            ],
        ];
    }
    
    /**
     * Manually trigger weekly digest (for testing)
     */
    public function trigger_weekly_digest(): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        do_action(self::WEEKLY_HOOK);
        
        return true;
    }
    
    /**
     * Manually trigger monthly digest (for testing)
     */
    public function trigger_monthly_digest(): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        do_action(self::MONTHLY_HOOK);
        
        return true;
    }
    
    /**
     * Manually trigger cleanup (for testing)
     */
    public function trigger_cleanup(): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        do_action(self::CLEANUP_HOOK);
        
        return true;
    }
    
    /**
     * Check if cron is working properly
     */
    public function is_cron_working(): bool {
        // Check if WP-Cron is disabled
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            return false;
        }
        
        // Check if there are any scheduled events
        $cron_array = _get_cron_array();
        
        return !empty($cron_array);
    }
    
    /**
     * Get cron diagnostic information
     */
    public function get_cron_diagnostics(): array {
        $diagnostics = [
            'wp_cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'scheduled_events' => [],
            'timezone' => get_option('timezone_string') ?: 'UTC' . get_option('gmt_offset'),
            'current_time' => current_time('mysql'),
            'gmt_offset' => get_option('gmt_offset'),
        ];
        
        // Get our scheduled events
        $our_hooks = [self::WEEKLY_HOOK, self::MONTHLY_HOOK, self::CLEANUP_HOOK];
        
        foreach ($our_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            $diagnostics['scheduled_events'][$hook] = [
                'scheduled' => (bool) $timestamp,
                'next_run' => $timestamp,
                'next_run_formatted' => $timestamp ? $this->format_next_run_time($timestamp) : 'Not scheduled',
            ];
        }
        
        return $diagnostics;
    }
    
    /**
     * Validate schedule settings
     */
    public function validate_schedule_settings(array $settings): array {
        $errors = [];
        
        // Validate weekly settings
        if (isset($settings['weekly_send_day'])) {
            $day = (int) $settings['weekly_send_day'];
            if ($day < 0 || $day > 6) {
                $errors[] = __('Weekly send day must be between 0 (Sunday) and 6 (Saturday)', 'edh-newsletter');
            }
        }
        
        if (isset($settings['weekly_send_hour'])) {
            $hour = (int) $settings['weekly_send_hour'];
            if ($hour < 0 || $hour > 23) {
                $errors[] = __('Weekly send hour must be between 0 and 23', 'edh-newsletter');
            }
        }
        
        // Validate monthly settings
        if (isset($settings['monthly_send_day'])) {
            $day = (int) $settings['monthly_send_day'];
            if ($day < 1 || $day > 31) {
                $errors[] = __('Monthly send day must be between 1 and 31', 'edh-newsletter');
            }
        }
        
        if (isset($settings['monthly_send_hour'])) {
            $hour = (int) $settings['monthly_send_hour'];
            if ($hour < 0 || $hour > 23) {
                $errors[] = __('Monthly send hour must be between 0 and 23', 'edh-newsletter');
            }
        }
        
        return $errors;
    }
    
    /**
     * Get human-readable schedule description
     */
    public function get_schedule_description(string $frequency): string {
        if ($frequency === 'weekly') {
            $day = get_option('newsletter_weekly_send_day', 5);
            $hour = get_option('newsletter_weekly_send_hour', 13);
            
            $days = [
                0 => __('Sunday', 'edh-newsletter'),
                1 => __('Monday', 'edh-newsletter'),
                2 => __('Tuesday', 'edh-newsletter'),
                3 => __('Wednesday', 'edh-newsletter'),
                4 => __('Thursday', 'edh-newsletter'),
                5 => __('Friday', 'edh-newsletter'),
                6 => __('Saturday', 'edh-newsletter'),
            ];
            
            $time = gmdate('g:i A', strtotime("{$hour}:00"));
            
            return sprintf(
                // translators: %1$s: Day of the week (e.g., "Friday"), %2$s: Time (e.g., "1:00 PM")
                __('Every %1$s at %2$s', 'edh-newsletter'),
                $days[$day],
                $time
            );
        } elseif ($frequency === 'monthly') {
            $day = get_option('newsletter_monthly_send_day', 1);
            $hour = get_option('newsletter_monthly_send_hour', 10);
            
            $time = gmdate('g:i A', strtotime("{$hour}:00"));
            
            return sprintf(
                // translators: %1$s: Ordinal day of month (e.g., "1st", "2nd"), %2$s: Time (e.g., "10:00 AM")
                __('On the %1$s of each month at %2$s', 'edh-newsletter'),
                $this->get_ordinal_number($day),
                $time
            );
        }
        
        return __('Not configured', 'edh-newsletter');
    }
    
    /**
     * Get ordinal number (1st, 2nd, 3rd, etc.)
     */
    private function get_ordinal_number(int $number): string {
        $suffix = 'th';
        
        if ($number % 100 < 11 || $number % 100 > 13) {
            switch ($number % 10) {
                case 1:
                    $suffix = 'st';
                    break;
                case 2:
                    $suffix = 'nd';
                    break;
                case 3:
                    $suffix = 'rd';
                    break;
            }
        }
        
        return $number . $suffix;
    }
}
