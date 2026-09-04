<?php
/**
 * Spam Guard Class
 *
 * @package Newsletter
 * @since 2.1.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Spam Guard
 *
 * Bot and abuse controls for the public forms:
 *  - honeypot field (bots fill it, humans never see it)
 *  - signed render timestamp (rejects submissions faster than a human could fill the form)
 *  - per-address and per-IP throttle (stops the form being used to mail-bomb an inbox)
 *  - optional Cloudflare Turnstile challenge (no visual puzzle; enabled when both keys are set)
 *
 * Usage: call render_fields($form) inside the <form>, then check($form, $email)
 * when processing. A "silent" verdict means the caller should show its normal
 * success message but do nothing, so bots get no signal that they were caught.
 */
class EDH_Newsletter_Spam_Guard {
    
    const HONEYPOT_FIELD = 'newsletter_website_url';
    const TIMESTAMP_FIELD = 'newsletter_ts';
    const SIGNATURE_FIELD = 'newsletter_sig';
    const TURNSTILE_FIELD = 'cf-turnstile-response';
    const TURNSTILE_SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    const TURNSTILE_VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    
    /**
     * Output the hidden anti-spam fields and, when configured, the Turnstile widget
     */
    public function render_fields(string $form): void {
        $timestamp = time();
        
        // Honeypot: hidden by CSS, ignored by assistive tech and the tab order
        echo '<div class="newsletter-hp" aria-hidden="true">';
        echo '<label>' . esc_html__('Leave this field empty', 'edh-newsletter');
        echo '<input type="text" name="' . esc_attr(self::HONEYPOT_FIELD) . '" value="" tabindex="-1" autocomplete="off">';
        echo '</label>';
        echo '</div>';
        
        // Signed render time
        echo '<input type="hidden" name="' . esc_attr(self::TIMESTAMP_FIELD) . '" value="' . esc_attr((string) $timestamp) . '">';
        echo '<input type="hidden" name="' . esc_attr(self::SIGNATURE_FIELD) . '" value="' . esc_attr($this->sign($form, $timestamp)) . '">';
        
        if ($this->is_turnstile_enabled()) {
            wp_enqueue_script('edh-newsletter-turnstile', self::TURNSTILE_SCRIPT, [], null, ['strategy' => 'defer']); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- third-party script, no version
            echo '<div class="newsletter-field newsletter-turnstile">';
            echo '<div class="cf-turnstile" data-sitekey="' . esc_attr((string) get_option('newsletter_turnstile_site_key', '')) . '"></div>';
            echo '</div>';
        }
    }
    
    /**
     * Evaluate a submission.
     *
     * @return array{ok: bool, silent: bool, message: string}
     */
    public function check(string $form, string $email): array {
        // 1. Honeypot: bots fill every field. Pretend success so they learn nothing.
        if ($this->post(self::HONEYPOT_FIELD) !== '') {
            $this->log($form, 'honeypot');
            return $this->verdict(false, true);
        }
        
        // 2. Timing: signed timestamp must verify and be old enough (but not stale)
        $timestamp = (int) $this->post(self::TIMESTAMP_FIELD);
        $signature = $this->post(self::SIGNATURE_FIELD);
        
        if ($timestamp <= 0 || !hash_equals($this->sign($form, $timestamp), $signature)) {
            $this->log($form, 'bad-signature');
            return $this->verdict(false, false, __('The form has expired. Please reload the page and try again.', 'edh-newsletter'));
        }
        
        $age = time() - $timestamp;
        $min_seconds = (int) apply_filters('edh_newsletter_spam_min_seconds', (int) get_option('newsletter_spam_min_seconds', 3), $form);
        
        if ($age < $min_seconds) {
            $this->log($form, 'too-fast');
            return $this->verdict(false, true);
        }
        
        if ($age > 12 * HOUR_IN_SECONDS) {
            return $this->verdict(false, false, __('The form has expired. Please reload the page and try again.', 'edh-newsletter'));
        }
        
        // 3. Throttle: count every attempt so probing is limited too
        $ip_limit = max(1, (int) apply_filters('edh_newsletter_spam_max_per_ip', (int) get_option('newsletter_spam_max_per_hour', 10), $form));
        $email_limit = max(1, (int) apply_filters('edh_newsletter_spam_max_per_email', 3, $form));
        
        $ip_hits = $this->bump('ip_' . md5($this->client_ip()));
        $email_hits = $this->bump('em_' . md5(strtolower($email)));
        
        if ($ip_hits > $ip_limit || $email_hits > $email_limit) {
            $this->log($form, 'throttled');
            return $this->verdict(false, false, __('Too many attempts. Please try again later.', 'edh-newsletter'));
        }
        
        // 4. Turnstile (only when configured)
        if ($this->is_turnstile_enabled() && !$this->verify_turnstile($this->post(self::TURNSTILE_FIELD))) {
            $this->log($form, 'turnstile');
            return $this->verdict(false, false, __('The anti-spam check failed. Please try again.', 'edh-newsletter'));
        }
        
        return $this->verdict(true);
    }
    
    /**
     * Whether Turnstile is configured
     */
    public function is_turnstile_enabled(): bool {
        return get_option('newsletter_turnstile_site_key', '') !== '' && get_option('newsletter_turnstile_secret_key', '') !== '';
    }
    
    /**
     * Verify a Turnstile response token with Cloudflare
     */
    private function verify_turnstile(string $token): bool {
        if ($token === '') {
            return false;
        }
        
        $response = wp_remote_post(self::TURNSTILE_VERIFY, [
            'timeout' => 8,
            'body' => [
                'secret' => (string) get_option('newsletter_turnstile_secret_key', ''),
                'response' => $token,
                'remoteip' => $this->client_ip(),
            ],
        ]);
        
        if (is_wp_error($response)) {
            $this->log('turnstile', 'request-failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        
        return is_array($body) && !empty($body['success']);
    }
    
    /**
     * HMAC over the form name and render time
     */
    private function sign(string $form, int $timestamp): string {
        return hash_hmac('sha256', $form . '|' . $timestamp, wp_salt('nonce'));
    }
    
    /**
     * Increment an hourly counter and return the new value
     */
    private function bump(string $key): int {
        $key = 'edh_nl_rl_' . $key;
        $hits = (int) get_transient($key) + 1;
        set_transient($key, $hits, HOUR_IN_SECONDS);
        
        return $hits;
    }
    
    /**
     * Best-effort client IP. Filter edh_newsletter_client_ip to read a proxy header on trusted setups.
     */
    private function client_ip(): string {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        
        return (string) apply_filters('edh_newsletter_client_ip', $ip);
    }
    
    /**
     * Read a posted string field
     */
    private function post(string $key): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the form nonce before calling check()
        return isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }
    
    private function verdict(bool $ok, bool $silent = false, string $message = ''): array {
        return ['ok' => $ok, 'silent' => $silent, 'message' => $message];
    }
    
    private function log(string $form, string $reason): void {
        do_action('edh_newsletter_spam_blocked', $form, $reason);
        
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log("Newsletter: {$form} submission blocked ({$reason})");
        }
    }
}
