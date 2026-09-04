<?php
/**
 * Frontend Forms Class
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Frontend Forms
 * 
 * Handles public subscription forms and user-facing functionality
 */
class EDH_Newsletter_Frontend_Forms {
    
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
        // This object is constructed on init@10, so the dispatcher must use a later hook.
        add_action('template_redirect', [$this, 'handle_public_actions']);
        add_shortcode('newsletter_signup', [$this, 'render_signup_shortcode']);
        add_shortcode('newsletter_preferences', [$this, 'render_preferences_shortcode']);
    }
    
    /**
     * Handle public actions (confirmation, unsubscribe, etc.)
     */
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- these GET links carry their own credential: a random confirmation token or a signed action token, verified before any change is made.
    public function handle_public_actions(): void {
        if (!isset($_GET['newsletter_action'])) {
            return;
        }
        
        $action = sanitize_key($_GET['newsletter_action']);
        
        switch ($action) {
            case 'confirm':
                $this->handle_confirmation();
                break;
            case 'unsubscribe':
                $this->handle_unsubscribe();
                break;
            case 'preferences':
                $this->handle_preferences();
                break;
        }
    }
    
    /**
     * Handle subscription confirmation
     */
    private function handle_confirmation(): void {
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
        
        if ($token === '') {
            $this->show_message(__('Invalid confirmation link.', 'edh-newsletter'), 'error');
            return;
        }
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            $this->show_message(__('Service temporarily unavailable.', 'edh-newsletter'), 'error');
            return;
        }
        
        $result = $subscriber_manager->confirm_subscription($token);
        
        if ($result['success']) {
            // Send welcome email
            $email_sender = EDH_Newsletter_Core::get_instance()->get_module('email_sender');
            if ($email_sender) {
                $email_sender->send_welcome_email($result['data']);
            }
            
            $this->show_message(
                sprintf(
                    // translators: %1$s: Site name
                    __('Thank you! Your subscription to %1$s has been confirmed.', 'edh-newsletter'),
                    get_bloginfo('name')
                ),
                'success'
            );
        } else {
            $this->show_message(
                __('Confirmation failed. The link may be invalid or expired.', 'edh-newsletter'),
                'error'
            );
        }
    }
    
    /**
     * Handle unsubscribe request
     */
    private function handle_unsubscribe(): void {
        if (!isset($_GET['subscriber_id']) || !isset($_GET['token'])) {
            $this->show_message(__('Invalid unsubscribe link.', 'edh-newsletter'), 'error');
            return;
        }
        
        $subscriber = $this->resolve_action_link('unsubscribe');
        
        if (!$subscriber) {
            $this->show_message(__('Invalid unsubscribe link.', 'edh-newsletter'), 'error');
            return;
        }
        
        // Show unsubscribe form
        $this->enqueue_public_scripts();
        $this->render_unsubscribe_form($subscriber);
    }
    
    /**
     * Handle preferences management
     */
    private function handle_preferences(): void {
        if (!isset($_GET['subscriber_id']) || !isset($_GET['token'])) {
            $this->show_message(__('Invalid preferences link.', 'edh-newsletter'), 'error');
            return;
        }
        
        $subscriber = $this->resolve_action_link('preferences');
        
        if (!$subscriber) {
            $this->show_message(__('Invalid preferences link.', 'edh-newsletter'), 'error');
            return;
        }
        
        // Handle preferences form submission
        if ($_POST && isset($_POST['newsletter_preferences_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process_preferences_form()
            $this->process_preferences_form($subscriber);
        } else {
            // Show preferences form
            $this->enqueue_public_scripts();
            $this->render_preferences_form($subscriber);
        }
    }
    
    /**
     * Load the subscriber referenced by a signed action link, or null if the link is invalid
     */
    private function resolve_action_link(string $action): ?array {
        $subscriber_id = absint($_GET['subscriber_id'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
        
        if ($subscriber_id === 0 || $token === '') {
            return null;
        }
        
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        $email_sender = EDH_Newsletter_Core::get_instance()->get_module('email_sender');
        
        if (!$subscriber_manager || !$email_sender) {
            $this->show_message(__('Service temporarily unavailable.', 'edh-newsletter'), 'error');
            return null;
        }
        
        $subscriber = $subscriber_manager->get_subscriber($subscriber_id);
        
        if (!$subscriber || !$email_sender->verify_action_token($subscriber, $action, $token)) {
            return null;
        }
        
        return $subscriber;
    }
    
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    
    /**
     * Render signup shortcode
     */
    public function render_signup_shortcode($atts): string {
        $this->enqueue_public_scripts();
        
        $atts = shortcode_atts([
            'title' => __('Subscribe to Our Newsletter', 'edh-newsletter'),
            'description' => __('Get the latest updates delivered to your inbox.', 'edh-newsletter'),
            'button_text' => __('Subscribe', 'edh-newsletter'),
            'show_frequency' => 'true',
            'default_frequency' => 'weekly',
            'style' => 'default',
        ], $atts);
        
        // Handle form submission
        $message = '';
        if ($_POST && isset($_POST['newsletter_signup_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process_signup_form()
            $message = $this->process_signup_form();
        }
        
        ob_start();
        $this->render_signup_form($atts, $message);
        return ob_get_clean();
    }
    
    /**
     * Render preferences shortcode
     */
    public function render_preferences_shortcode($atts): string {
        $this->enqueue_public_scripts();
        
        $atts = shortcode_atts([
            'title' => __('Manage Your Newsletter Preferences', 'edh-newsletter'),
        ], $atts);
        
        ob_start();
        echo '<div class="newsletter-preferences-lookup">';
        echo '<h3>' . esc_html($atts['title']) . '</h3>';
        echo '<p>' . esc_html__('Enter your email address to manage your newsletter preferences:', 'edh-newsletter') . '</p>';
        
        // Simple email lookup form
        echo '<form method="post" class="newsletter-form">';
        wp_nonce_field('newsletter_preferences_lookup', 'newsletter_preferences_lookup_nonce');
        echo '<input type="email" name="email" placeholder="' . esc_attr__('Your email address', 'edh-newsletter') . '" required>';
        $this->render_spam_guard_fields('lookup');
        echo '<button type="submit" name="lookup_preferences">' . esc_html__('Manage Preferences', 'edh-newsletter') . '</button>';
        echo '</form>';
        
        // Handle lookup
        if ($_POST && isset($_POST['lookup_preferences'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in handle_preferences_lookup()
            $this->handle_preferences_lookup();
        }
        
        echo '</div>';
        return ob_get_clean();
    }
    
    /**
     * Process signup form submission
     */
    private function process_signup_form(): string {
        if (!wp_verify_nonce($this->post_field('newsletter_signup_nonce'), 'newsletter_signup')) {
            return $this->get_message(__('Security check failed. Please try again.', 'edh-newsletter'), 'error');
        }
        
        $email = sanitize_email($this->post_field('email'));
        $frequency = sanitize_key($_POST['frequency'] ?? 'weekly');
        $privacy_consent = !empty($_POST['privacy_consent']);
        
        if (!is_email($email)) {
            return $this->get_message(__('Please enter a valid email address.', 'edh-newsletter'), 'error');
        }
        
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        $privacy_manager = EDH_Newsletter_Core::get_instance()->get_module('privacy_manager');
        $email_sender = EDH_Newsletter_Core::get_instance()->get_module('email_sender');
        
        if (!$subscriber_manager || !$privacy_manager || !$email_sender) {
            return $this->get_message(__('Service temporarily unavailable. Please try again later.', 'edh-newsletter'), 'error');
        }
        
        // Validate privacy consent
        if ($privacy_manager->is_consent_required() && !$privacy_consent) {
            return $this->get_message(__('You must agree to the privacy policy to subscribe.', 'edh-newsletter'), 'error');
        }
        
        // Validate email for privacy compliance
        $privacy_errors = $privacy_manager->validate_email_privacy($email);
        if (!empty($privacy_errors)) {
            return $this->get_message(implode(' ', $privacy_errors), 'error');
        }
        
        // Bot and abuse checks
        $spam_guard = EDH_Newsletter_Core::get_instance()->get_module('spam_guard');
        $verdict = $spam_guard ? $spam_guard->check('signup', $email) : ['ok' => true, 'silent' => false, 'message' => ''];
        if (!$verdict['ok']) {
            // A silent verdict shows the normal success text so bots get no feedback
            return $verdict['silent']
                ? $this->get_message(__('Thank you! Please check your email and click the confirmation link to complete your subscription.', 'edh-newsletter'), 'success')
                : $this->get_message($verdict['message'], 'error');
        }
        
        // Create subscriber
        $subscriber_data = [
            'email' => $email,
            'digest_frequency' => $frequency,
            'status' => 'pending',
            'privacy_consent' => $privacy_consent,
        ];
        
        $result = $subscriber_manager->create_subscriber($subscriber_data);
        
        if (!$result['success']) {
            return $this->get_message($result['error'], 'error');
        }
        
        // Record privacy consent
        if ($privacy_consent) {
            $privacy_manager->record_consent($result['data']['id'], [
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'timestamp' => current_time('mysql'),
            ]);
        }
        
        // Send confirmation email
        $email_sent = $email_sender->send_confirmation_email($result['data']);
        
        if ($email_sent) {
            return $this->get_message(
                __('Thank you! Please check your email and click the confirmation link to complete your subscription.', 'edh-newsletter'),
                'success'
            );
        } else {
            return $this->get_message(
                __('Subscription created but confirmation email could not be sent. Please contact us for assistance.', 'edh-newsletter'),
                'warning'
            );
        }
    }
    
    /**
     * Process preferences form submission
     */
    private function process_preferences_form(array $subscriber): void {
        if (!wp_verify_nonce($this->post_field('newsletter_preferences_nonce'), 'newsletter_preferences_' . $subscriber['id'])) {
            $this->show_message(__('Security check failed. Please try again.', 'edh-newsletter'), 'error');
            return;
        }
        
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            $this->show_message(__('Service temporarily unavailable.', 'edh-newsletter'), 'error');
            return;
        }
        
        $new_frequency = sanitize_key($_POST['digest_frequency'] ?? $subscriber['digest_frequency']);
        $action = sanitize_key($_POST['action'] ?? '');
        
        // Only confirmed (active or paused) subscriptions can be managed here.
        // Pending rows must confirm by email; unsubscribed rows must sign up again.
        if (!in_array($subscriber['status'], ['subscribed', 'paused'], true)) {
            $this->show_message(__('This subscription is not active. Please sign up again to receive the newsletter.', 'edh-newsletter'), 'error');
            return;
        }
        
        if (($action === 'pause' && $subscriber['status'] !== 'subscribed') || ($action === 'resume' && $subscriber['status'] !== 'paused')) {
            $this->show_message(__('That action is not available for your subscription.', 'edh-newsletter'), 'error');
            return;
        }
        
        switch ($action) {
            case 'update':
                $result = $subscriber_manager->update_subscriber($subscriber['id'], [
                    'digest_frequency' => $new_frequency,
                ]);
                
                if ($result['success']) {
                    $this->show_message(__('Your preferences have been updated successfully.', 'edh-newsletter'), 'success');
                } else {
                    $this->show_message(__('Failed to update preferences. Please try again.', 'edh-newsletter'), 'error');
                }
                break;
                
            case 'pause':
                $result = $subscriber_manager->pause_subscription($subscriber['id']);
                
                if ($result['success']) {
                    $this->show_message(__('Your subscription has been paused. You can resume it anytime.', 'edh-newsletter'), 'success');
                } else {
                    $this->show_message(__('Failed to pause subscription. Please try again.', 'edh-newsletter'), 'error');
                }
                break;
                
            case 'resume':
                $result = $subscriber_manager->resume_subscription($subscriber['id']);
                
                if ($result['success']) {
                    $this->show_message(__('Your subscription has been resumed.', 'edh-newsletter'), 'success');
                } else {
                    $this->show_message(__('Failed to resume subscription. Please try again.', 'edh-newsletter'), 'error');
                }
                break;
                
            default:
                $this->show_message(__('No action was selected.', 'edh-newsletter'), 'error');
        }
    }
    
    /**
     * Handle preferences lookup
     */
    private function handle_preferences_lookup(): void {
        if (!wp_verify_nonce($this->post_field('newsletter_preferences_lookup_nonce'), 'newsletter_preferences_lookup')) {
            echo wp_kses_post($this->get_message(esc_html__('Security check failed.', 'edh-newsletter'), 'error'));
            return;
        }
        
        $email = sanitize_email($this->post_field('email'));
        
        if (!is_email($email)) {
            echo wp_kses_post($this->get_message(esc_html__('Please enter a valid email address.', 'edh-newsletter'), 'error'));
            return;
        }
        
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        $email_sender = EDH_Newsletter_Core::get_instance()->get_module('email_sender');
        
        if (!$subscriber_manager || !$email_sender) {
            echo wp_kses_post($this->get_message(esc_html__('Service temporarily unavailable.', 'edh-newsletter'), 'error'));
            return;
        }
        
        $spam_guard = EDH_Newsletter_Core::get_instance()->get_module('spam_guard');
        $verdict = $spam_guard ? $spam_guard->check('lookup', $email) : ['ok' => true, 'silent' => false, 'message' => ''];
        if (!$verdict['ok'] && !$verdict['silent']) {
            echo wp_kses_post($this->get_message($verdict['message'], 'error'));
            return;
        }
        
        // A silent verdict falls through to the neutral message below without sending anything
        $subscriber = $verdict['ok'] ? $subscriber_manager->get_subscriber_by_email($email) : null;
        
        // Only confirmed subscriptions get a management link. The response is the
        // same either way so the form cannot be used to test whether an address is on the list.
        if ($subscriber && in_array($subscriber['status'], ['subscribed', 'paused'], true)) {
            $preferences_url = $email_sender->generate_preferences_url($subscriber);
            
            $subject = sprintf(
                // translators: %1$s: Site name
                __('Manage your %1$s newsletter preferences', 'edh-newsletter'),
                get_bloginfo('name')
            );
            $message = sprintf(
                // translators: %1$s: Preferences management URL
                __('Click here to manage your newsletter preferences: %1$s', 'edh-newsletter'),
                $preferences_url
            );
            
            wp_mail($email, $subject, $message);
        }
        
        echo wp_kses_post($this->get_message(
            esc_html__('If that address has an active subscription, a preferences management link has been sent to it.', 'edh-newsletter'),
            'success'
        ));
    }
    
    /**
     * Render signup form
     */
    private function render_signup_form(array $atts, string $message = ''): void {
        $privacy_manager = EDH_Newsletter_Core::get_instance()->get_module('privacy_manager');
        $show_frequency = $atts['show_frequency'] === 'true';
        $require_consent = $privacy_manager && $privacy_manager->is_consent_required();
        
        echo '<div class="newsletter-signup-form newsletter-style-' . esc_attr($atts['style']) . '">';
        
        if (!empty($atts['title'])) {
            echo '<h3>' . esc_html($atts['title']) . '</h3>';
        }
        
        if (!empty($atts['description'])) {
            echo '<p>' . esc_html($atts['description']) . '</p>';
        }
        
        if ($message) {
            echo wp_kses_post($message);
        }
        
        echo '<form method="post" class="newsletter-form">';
        wp_nonce_field('newsletter_signup', 'newsletter_signup_nonce');
        
        echo '<div class="newsletter-field">';
        echo '<input type="email" name="email" placeholder="' . esc_attr__('Your email address', 'edh-newsletter') . '" required>';
        echo '</div>';
        
        if ($show_frequency) {
            echo '<div class="newsletter-field">';
            echo '<label>' . esc_html__('How often would you like to receive our newsletter?', 'edh-newsletter') . '</label>';
            echo '<select name="frequency">';
            echo '<option value="weekly"' . selected($atts['default_frequency'], 'weekly', false) . '>' . esc_html__('Weekly', 'edh-newsletter') . '</option>';
            echo '<option value="monthly"' . selected($atts['default_frequency'], 'monthly', false) . '>' . esc_html__('Monthly', 'edh-newsletter') . '</option>';
            echo '</select>';
            echo '</div>';
        } else {
            echo '<input type="hidden" name="frequency" value="' . esc_attr($atts['default_frequency']) . '">';
        }
        
        if ($require_consent) {
            echo '<div class="newsletter-field newsletter-consent">';
            echo '<label>';
            echo '<input type="checkbox" name="privacy_consent" required>';
            echo ' ' . wp_kses_post($privacy_manager->get_consent_text());
            echo '</label>';
            echo '</div>';
        }
        
        $this->render_spam_guard_fields('signup');
        
        echo '<div class="newsletter-field">';
        echo '<button type="submit" name="newsletter_signup">' . esc_html($atts['button_text']) . '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Render unsubscribe form
     */
    private function render_unsubscribe_form(array $subscriber): void {
        $privacy_manager = EDH_Newsletter_Core::get_instance()->get_module('privacy_manager');
        
        if ($_POST && isset($_POST['newsletter_unsubscribe_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process_unsubscribe_form()
            $this->process_unsubscribe_form($subscriber);
            return;
        }
        
        echo '<div class="newsletter-unsubscribe-form">';
        echo '<h2>' . esc_html__('Unsubscribe from Newsletter', 'edh-newsletter') . '</h2>';
        echo '<p>' . sprintf(
            // translators: %1$s: Subscriber email address
            esc_html__('We\'re sorry to see you go, %1$s. Please let us know why you\'re unsubscribing:', 'edh-newsletter'),
            esc_html($subscriber['email'])
        ) . '</p>';
        
        echo '<form method="post" class="newsletter-form">';
        wp_nonce_field('newsletter_unsubscribe_' . $subscriber['id'], 'newsletter_unsubscribe_nonce');
        
        $reasons = $privacy_manager ? $privacy_manager->get_unsubscribe_reasons() : [];
        
        if (!empty($reasons)) {
            echo '<div class="newsletter-field">';
            echo '<label>' . esc_html__('Reason for unsubscribing:', 'edh-newsletter') . '</label>';
            foreach ($reasons as $key => $label) {
                echo '<label class="newsletter-radio">';
                echo '<input type="radio" name="reason" value="' . esc_attr($key) . '">';
                echo ' ' . esc_html($label);
                echo '</label>';
            }
            echo '</div>';
        }
        
        echo '<div class="newsletter-field">';
        echo '<label>' . esc_html__('Additional comments (optional):', 'edh-newsletter') . '</label>';
        echo '<textarea name="comments" rows="3"></textarea>';
        echo '</div>';
        
        echo '<div class="newsletter-field newsletter-actions">';
        echo '<button type="submit" name="action" value="unsubscribe" class="newsletter-button-danger">' . esc_html__('Unsubscribe', 'edh-newsletter') . '</button>';
        echo '<button type="submit" name="action" value="pause" class="newsletter-button-secondary">' . esc_html__('Pause Instead', 'edh-newsletter') . '</button>';
        echo '<a href="' . esc_url(home_url()) . '" class="newsletter-button-link">' . esc_html__('Cancel', 'edh-newsletter') . '</a>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Render preferences form
     */
    private function render_preferences_form(array $subscriber): void {
        echo '<div class="newsletter-preferences-form">';
        echo '<h2>' . esc_html__('Manage Newsletter Preferences', 'edh-newsletter') . '</h2>';
        echo '<p>' . sprintf(
            // translators: %1$s: Subscriber email address
            esc_html__('Update your newsletter preferences for %1$s:', 'edh-newsletter'),
            esc_html($subscriber['email'])
        ) . '</p>';
        
        echo '<form method="post" class="newsletter-form">';
        wp_nonce_field('newsletter_preferences_' . $subscriber['id'], 'newsletter_preferences_nonce');
        
        echo '<div class="newsletter-field">';
        echo '<label>' . esc_html__('Digest Frequency:', 'edh-newsletter') . '</label>';
        echo '<select name="digest_frequency">';
        echo '<option value="weekly"' . selected($subscriber['digest_frequency'], 'weekly', false) . '>' . esc_html__('Weekly', 'edh-newsletter') . '</option>';
        echo '<option value="monthly"' . selected($subscriber['digest_frequency'], 'monthly', false) . '>' . esc_html__('Monthly', 'edh-newsletter') . '</option>';
        echo '</select>';
        echo '</div>';
        
        echo '<div class="newsletter-field">';
        echo '<p><strong>' . esc_html__('Current Status:', 'edh-newsletter') . '</strong> ' . esc_html(ucfirst($subscriber['status'])) . '</p>';
        echo '</div>';
        
        echo '<div class="newsletter-field newsletter-actions">';
        echo '<button type="submit" name="action" value="update">' . esc_html__('Update Preferences', 'edh-newsletter') . '</button>';
        
        if ($subscriber['status'] === 'subscribed') {
            echo '<button type="submit" name="action" value="pause" class="newsletter-button-secondary">' . esc_html__('Pause Subscription', 'edh-newsletter') . '</button>';
        } elseif ($subscriber['status'] === 'paused') {
            echo '<button type="submit" name="action" value="resume" class="newsletter-button-secondary">' . esc_html__('Resume Subscription', 'edh-newsletter') . '</button>';
        }
        
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Process unsubscribe form
     */
    private function process_unsubscribe_form(array $subscriber): void {
        if (!wp_verify_nonce($this->post_field('newsletter_unsubscribe_nonce'), 'newsletter_unsubscribe_' . $subscriber['id'])) {
            $this->show_message(__('Security check failed.', 'edh-newsletter'), 'error');
            return;
        }
        
        $action = sanitize_key($_POST['action'] ?? '');
        $reason = sanitize_key($_POST['reason'] ?? '');
        $comments = sanitize_textarea_field($this->post_field('comments'));
        
        $subscriber_manager = EDH_Newsletter_Core::get_instance()->get_module('subscriber_manager');
        
        if (!$subscriber_manager) {
            $this->show_message(__('Service temporarily unavailable.', 'edh-newsletter'), 'error');
            return;
        }
        
        if ($action === 'unsubscribe') {
            $full_reason = $reason;
            if ($comments) {
                $full_reason .= ': ' . $comments;
            }
            
            $result = $subscriber_manager->unsubscribe($subscriber['id'], $full_reason);
            
            if ($result['success']) {
                $this->show_message(
                    __('You have been successfully unsubscribed. We\'re sorry to see you go!', 'edh-newsletter'),
                    'success'
                );
            } else {
                $this->show_message(__('Failed to unsubscribe. Please try again.', 'edh-newsletter'), 'error');
            }
        } elseif ($action === 'pause') {
            if ($subscriber['status'] !== 'subscribed') {
                $this->show_message(__('Only an active subscription can be paused.', 'edh-newsletter'), 'error');
                return;
            }
            
            $result = $subscriber_manager->pause_subscription($subscriber['id']);
            
            if ($result['success']) {
                $this->show_message(
                    __('Your subscription has been paused. You can resume it anytime using the preferences link in any of our emails.', 'edh-newsletter'),
                    'success'
                );
            } else {
                $this->show_message(__('Failed to pause subscription. Please try again.', 'edh-newsletter'), 'error');
            }
        } else {
            $this->show_message(__('No action was selected.', 'edh-newsletter'), 'error');
        }
    }
    
    /**
     * Enqueue public scripts and styles.
     *
     * Called only when a form is actually rendered (shortcode or action page),
     * so pages without a newsletter form do not load jQuery or these assets.
     */
    public function enqueue_public_scripts(): void {
        wp_enqueue_style(
            'newsletter-public',
            EDH_NEWSLETTER_ASSETS_URL . 'css/public.css',
            [],
            EDH_NEWSLETTER_VERSION
        );
        
        wp_enqueue_script(
            'newsletter-public',
            EDH_NEWSLETTER_ASSETS_URL . 'js/public.js',
            ['jquery'],
            EDH_NEWSLETTER_VERSION,
            true
        );
    }
    
    /**
     * Output the honeypot, signed timestamp and optional Turnstile widget
     */
    private function render_spam_guard_fields(string $form): void {
        $spam_guard = EDH_Newsletter_Core::get_instance()->get_module('spam_guard');
        if ($spam_guard) {
            $spam_guard->render_fields($form);
        }
    }
    
    /**
     * Read a posted field, unslashed, as a plain string ('' when absent).
     * Callers apply the sanitizer appropriate to the field.
     */
    private function post_field(string $key): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce is checked by the caller; the value is sanitized by the caller
        return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    }
    
    /**
     * Show message and exit
     */
    private function show_message(string $message, string $type = 'info'): void {
        $return_link = '<p><a href="' . esc_url(home_url()) . '">' . esc_html__('Return to site', 'edh-newsletter') . '</a></p>';
        wp_die(
            wp_kses_post($this->get_message($message, $type) . $return_link),
            esc_html__('Newsletter', 'edh-newsletter')
        );
    }
    
    /**
     * Get formatted message HTML
     */
    private function get_message(string $message, string $type = 'info'): string {
        $class = 'newsletter-message newsletter-message-' . $type;
        return '<div class="' . $class . '"><p>' . esc_html($message) . '</p></div>';
    }
}
