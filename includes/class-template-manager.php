<?php
/**
 * Template Manager Class
 *
 * @package Newsletter
 * @since 2.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Template Manager
 *
 * Renders the email templates shipped in the plugin's templates/ directory.
 * A theme can override any template by placing a file with the same name in
 * {theme}/newsletter-templates/. Child themes are checked before parent themes.
 */
class EDH_Newsletter_Template_Manager {
    
    /**
     * Template directory
     */
    private $template_dir;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->template_dir = EDH_NEWSLETTER_TEMPLATES_DIR;
    }
    
    /**
     * Render digest email template
     */
    public function render_digest_template(array $data): string {
        $frequency = ($data['frequency'] ?? 'weekly') === 'monthly' ? 'monthly' : 'weekly';
        
        return $this->render_template($frequency . '-digest', $data);
    }
    
    /**
     * Render confirmation email template
     */
    public function render_confirmation_template(array $data): string {
        return $this->render_template('confirmation', $data);
    }
    
    /**
     * Render welcome email template
     */
    public function render_welcome_template(array $data): string {
        return $this->render_template('welcome', $data);
    }
    
    /**
     * Render a named template with data
     */
    private function render_template(string $template_name, array $data): string {
        $template_file = $this->get_template_file($template_name);
        
        if (!$template_file) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log("Newsletter: template '{$template_name}' not found");
            }
            return '';
        }
        
        $content = $this->include_template($template_file, $data);
        
        // Apply filters for customization
        return apply_filters('edh_newsletter_template_content', $content, $template_file, $data);
    }
    
    /**
     * Include a template file in an isolated scope.
     *
     * Only the keys of $data become local variables; the file path is passed
     * separately so a data key can never redirect the include.
     */
    private function include_template(string $edh_newsletter_template_file, array $edh_newsletter_template_data): string {
        extract($edh_newsletter_template_data, EXTR_SKIP);
        
        ob_start();
        include $edh_newsletter_template_file;
        
        return (string) ob_get_clean();
    }
    
    /**
     * Get template file path: child theme, parent theme, then plugin
     */
    private function get_template_file(string $template_name): ?string {
        $template_name = sanitize_file_name($template_name);
        $relative = 'newsletter-templates/' . $template_name . '.php';
        
        $candidates = [
            trailingslashit(get_stylesheet_directory()) . $relative,
            trailingslashit(get_template_directory()) . $relative,
            $this->template_dir . $template_name . '.php',
        ];
        
        foreach (array_unique($candidates) as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }
        
        return null;
    }
    
    /**
     * Render individual post item
     */
    private function render_post_item(array $post): string {
        $brand_color = esc_attr(get_option('newsletter_brand_color', '#1e73be'));
        $html = '<div style="margin-bottom: 25px; padding: 15px; background: #f9f9f9; border-radius: 5px; border-left: 4px solid ' . $brand_color . ';">';
        
        if (!empty($post['featured_image'])) {
            $html .= '<img src="' . esc_url($post['featured_image']) . '" alt="' . esc_attr($post['title']) . '" style="width: 100%; max-width: 200px; height: auto; float: right; margin-left: 15px; border-radius: 4px;">';
        }
        
        $html .= '<h3 style="margin: 0 0 10px 0; color: #333;">';
        $html .= '<a href="' . esc_url($post['url']) . '" style="color: ' . $brand_color . '; text-decoration: none; font-weight: bold;">';
        $html .= esc_html($post['title']);
        $html .= '</a></h3>';
        
        if (!empty($post['excerpt'])) {
            $html .= '<p style="margin: 10px 0; color: #666; line-height: 1.5;">' . esc_html($post['excerpt']) . '</p>';
        }
        
        $html .= '<div style="font-size: 0.9em; color: #888; margin-top: 10px;">';
        $html .= 'Published: ' . esc_html($post['date']);
        if (!empty($post['author'])) {
            $html .= ' by ' . esc_html($post['author']);
        }
        if (!empty($post['categories'])) {
            $html .= ' in ' . esc_html(implode(', ', $post['categories']));
        }
        $html .= '</div>';
        
        $html .= '<div style="clear: both;"></div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get email footer HTML
     */
    private function get_email_footer(string $unsubscribe_url, string $preferences_url): string {
        $html = '<div class="email-footer">';
        $html .= '<p>You received this email because you subscribed to our newsletter.</p>';
        $html .= '<p>';
        $html .= '<a href="' . esc_url($preferences_url) . '">Manage Preferences</a> | ';
        $html .= '<a href="' . esc_url($unsubscribe_url) . '">Unsubscribe</a>';
        $html .= '</p>';
        $html .= '</div>';
        $html .= '</div></body></html>';
        
        return $html;
    }
    
    /**
     * Get simple footer for confirmation emails
     */
    private function get_simple_footer(): string {
        $html = '<div class="email-footer">';
        $html .= '<p><small>This is an automated message from ' . esc_html(get_bloginfo('name')) . '.</small></p>';
        $html .= '</div>';
        $html .= '</div></body></html>';
        
        return $html;
    }
    
    /**
     * Get email CSS styles
     */
    private function get_email_styles(string $brand_color): string {
        return '<style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
            }
            .email-container {
                width: 100%;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .email-content {
                padding: 0 20px;
            }
            .email-footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                font-size: 0.9em;
                text-align: center;
                color: #666;
            }
            .posts-list {
                margin: 20px 0;
            }
            a {
                color: ' . esc_attr($brand_color) . ';
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            h1, h2, h3 {
                margin-top: 0;
            }
            @media only screen and (max-width: 600px) {
                .email-container {
                    width: 100% !important;
                    padding: 10px !important;
                }
                .email-content {
                    padding: 0 10px !important;
                }
            }
        </style>';
    }
}
