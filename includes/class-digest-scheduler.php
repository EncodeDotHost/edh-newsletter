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
 * Schedules the weekly and monthly digest sends and the daily cleanup task.
 * Digest sends are single events computed in the site timezone and re-armed
 * after each run, so the configured day and hour hold across DST changes and
 * months of different lengths.
 */
class EDH_Newsletter_Digest_Scheduler {

    /**
     * Cron hook names
     */
    const WEEKLY_HOOK = 'edh_newsletter_send_weekly_digest';
    const MONTHLY_HOOK = 'edh_newsletter_send_monthly_digest';
    const CLEANUP_HOOK = 'edh_newsletter_cleanup_expired_data';

    /**
     * Options that affect the schedule, mapped to the frequency they belong to
     */
    const SCHEDULE_OPTIONS = [
        'newsletter_weekly_send_day' => 'weekly',
        'newsletter_weekly_send_hour' => 'weekly',
        'newsletter_monthly_send_day' => 'monthly',
        'newsletter_monthly_send_hour' => 'monthly',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->ensure_scheduled();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Reschedule when a schedule option is created or changed.
        add_action('added_option', [$this, 'handle_option_change'], 10, 1);
        add_action('updated_option', [$this, 'handle_option_change'], 10, 1);

        // Re-arm the single events after each send (runs after Email Sender at priority 10).
        add_action(self::WEEKLY_HOOK, [$this, 'schedule_weekly_digest'], 20);
        add_action(self::MONTHLY_HOOK, [$this, 'schedule_monthly_digest'], 20);
    }

    /**
     * Make sure every event exists. Safe to call on every request.
     */
    public function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::WEEKLY_HOOK)) {
            $this->schedule_weekly_digest();
        }

        if (!wp_next_scheduled(self::MONTHLY_HOOK)) {
            $this->schedule_monthly_digest();
        }

        $this->schedule_cleanup_task();
    }

    /**
     * React to a schedule option being added or updated
     */
    public function handle_option_change(string $option): void {
        if (!isset(self::SCHEDULE_OPTIONS[$option])) {
            return;
        }

        if (self::SCHEDULE_OPTIONS[$option] === 'weekly') {
            $this->schedule_weekly_digest();
        } else {
            $this->schedule_monthly_digest();
        }
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
     * Schedule the next weekly digest
     */
    public function schedule_weekly_digest(): void {
        wp_unschedule_hook(self::WEEKLY_HOOK);

        $next_run = $this->calculate_next_weekly_run();
        wp_schedule_single_event($next_run, self::WEEKLY_HOOK);

        do_action('edh_newsletter_weekly_digest_scheduled', $next_run);
    }

    /**
     * Schedule the next monthly digest
     */
    public function schedule_monthly_digest(): void {
        wp_unschedule_hook(self::MONTHLY_HOOK);

        $next_run = $this->calculate_next_monthly_run();
        wp_schedule_single_event($next_run, self::MONTHLY_HOOK);

        do_action('edh_newsletter_monthly_digest_scheduled', $next_run);
    }

    /**
     * Schedule the daily cleanup task at 02:00 site time
     */
    public function schedule_cleanup_task(): void {
        if (wp_next_scheduled(self::CLEANUP_HOOK)) {
            return;
        }

        $next_cleanup = (new DateTimeImmutable('tomorrow 02:00', wp_timezone()))->getTimestamp();
        wp_schedule_event($next_cleanup, 'daily', self::CLEANUP_HOOK);
    }

    /**
     * Compute the next weekly run from the stored options
     */
    private function calculate_next_weekly_run(): int {
        $day = (int) get_option('newsletter_weekly_send_day', 5);
        $hour = (int) get_option('newsletter_weekly_send_hour', 13);

        return $this->calculate_weekly_run_time($day, $hour);
    }

    /**
     * Compute the next monthly run from the stored options
     */
    private function calculate_next_monthly_run(): int {
        $day = (int) get_option('newsletter_monthly_send_day', 1);
        $hour = (int) get_option('newsletter_monthly_send_hour', 10);

        return $this->calculate_monthly_run_time($day, $hour);
    }

    /**
     * Next occurrence of $target_day (0 = Sunday) at $target_hour, site timezone
     */
    private function calculate_weekly_run_time(int $target_day, int $target_hour, ?DateTimeImmutable $now = null): int {
        $target_day = max(0, min(6, $target_day));
        $target_hour = max(0, min(23, $target_hour));

        $now = $now ?? new DateTimeImmutable('now', wp_timezone());
        $days_ahead = ($target_day - (int) $now->format('w') + 7) % 7;
        $target = $now->modify("+{$days_ahead} days")->setTime($target_hour, 0, 0);

        if ($target <= $now) {
            $target = $target->modify('+7 days');
        }

        return $target->getTimestamp();
    }

    /**
     * Next occurrence of day-of-month $target_day at $target_hour, site timezone.
     * A day beyond the end of a month is clamped to that month's last day.
     */
    private function calculate_monthly_run_time(int $target_day, int $target_hour, ?DateTimeImmutable $now = null): int {
        $target_day = max(1, min(31, $target_day));
        $target_hour = max(0, min(23, $target_hour));

        $now = $now ?? new DateTimeImmutable('now', wp_timezone());
        $target = $this->clamp_to_month($now, $target_day, $target_hour);

        if ($target <= $now) {
            $target = $this->clamp_to_month($now->modify('first day of next month'), $target_day, $target_hour);
        }

        return $target->getTimestamp();
    }

    /**
     * Build a datetime in the month of $month for $day (clamped) at $hour
     */
    private function clamp_to_month(DateTimeImmutable $month, int $day, int $hour): DateTimeImmutable {
        $last_day = (int) $month->format('t');

        return $month
            ->setDate((int) $month->format('Y'), (int) $month->format('n'), min($day, $last_day))
            ->setTime($hour, 0, 0);
    }

    /**
     * Clear all scheduled events
     */
    public function clear_all_schedules(): void {
        wp_unschedule_hook(self::WEEKLY_HOOK);
        wp_unschedule_hook(self::MONTHLY_HOOK);
        wp_unschedule_hook(self::CLEANUP_HOOK);
    }

    /**
     * Format a timestamp for display in the site timezone
     */
    public function format_next_run_time($timestamp): string {
        if (!$timestamp) {
            return __('Not scheduled', 'edh-newsletter');
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $timestamp);
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
                'next_run_formatted' => $this->format_next_run_time($weekly_scheduled),
                'day' => (int) get_option('newsletter_weekly_send_day', 5),
                'hour' => (int) get_option('newsletter_weekly_send_hour', 13),
            ],
            'monthly' => [
                'enabled' => (bool) $monthly_scheduled,
                'next_run' => $monthly_scheduled,
                'next_run_formatted' => $this->format_next_run_time($monthly_scheduled),
                'day' => (int) get_option('newsletter_monthly_send_day', 1),
                'hour' => (int) get_option('newsletter_monthly_send_hour', 10),
            ],
            'cleanup' => [
                'enabled' => (bool) $cleanup_scheduled,
                'next_run' => $cleanup_scheduled,
                'next_run_formatted' => $this->format_next_run_time($cleanup_scheduled),
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

        do_action('edh_newsletter_send_weekly_digest');

        return true;
    }

    /**
     * Manually trigger monthly digest (for testing)
     */
    public function trigger_monthly_digest(): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        do_action('edh_newsletter_send_monthly_digest');

        return true;
    }

    /**
     * Manually trigger cleanup
     */
    public function trigger_cleanup(): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        do_action('edh_newsletter_cleanup_expired_data');

        return true;
    }

    /**
     * Check if cron is working properly
     */
    public function is_cron_working(): bool {
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            return false;
        }

        return !empty(_get_cron_array());
    }

    /**
     * Get cron diagnostic information
     */
    public function get_cron_diagnostics(): array {
        $diagnostics = [
            'wp_cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'scheduled_events' => [],
            'timezone' => wp_timezone_string(),
            'current_time' => current_time('mysql'),
            'gmt_offset' => get_option('gmt_offset'),
        ];

        foreach ([self::WEEKLY_HOOK, self::MONTHLY_HOOK, self::CLEANUP_HOOK] as $hook) {
            $timestamp = wp_next_scheduled($hook);
            $diagnostics['scheduled_events'][$hook] = [
                'scheduled' => (bool) $timestamp,
                'next_run' => $timestamp,
                'next_run_formatted' => $this->format_next_run_time($timestamp),
            ];
        }

        return $diagnostics;
    }
}
