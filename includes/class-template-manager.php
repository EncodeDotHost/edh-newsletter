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
 * Handles email template rendering, customization, and management
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
        $this->init_hooks();
        $this->create_default_templates();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'register_template_settings']);
    }
    
    /**
     * Register template customization settings
     */
    public function register_template_settings(): void {
        // Template settings will be registered here
        // This allows for future admin interface integration
    }
    
    /**
     * Render digest email template
     */
    public function render_digest_template(array $data): string {
        $template_name = $data['frequency'] . '-digest';
        $template_file = $this->get_template_file($template_name);
        
        if (!$template_file) {
            return $this->render_fallback_digest_template($data);
        }
        
        return $this->render_template($template_file, $data);
    }
    
    /**
     * Render confirmation email template
     */
    public function render_confirmation_template(array $data): string {
        $template_file = $this->get_template_file('confirmation');
        
        if (!$template_file) {
            return $this->render_fallback_confirmation_template($data);
        }
        
        return $this->render_template($template_file, $data);
    }
    
    /**
     * Render welcome email template
     */
    public function render_welcome_template(array $data): string {
        $template_file = $this->get_template_file('welcome');
        
        if (!$template_file) {
            return $this->render_fallback_welcome_template($data);
        }
        
        return $this->render_template($template_file, $data);
    }
    
    /**
     * Render template with data
     */
    private function render_template(string $template_file, array $data): string {
        // Extract data variables for use in template
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include template file
        include $template_file;
        
        // Get content and clean buffer
        $content = ob_get_clean();
        
        // Apply filters for customization
        return apply_filters('edh_newsletter_template_content', $content, $template_file, $data);
    }
    
    /**
     * Get template file path
     */
    private function get_template_file(string $template_name): ?string {
        $template_file = $this->template_dir . $template_name . '.php';
        
        // Allow theme override
        $theme_template = get_template_directory() . '/newsletter-templates/' . $template_name . '.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        // Check plugin template
        if (file_exists($template_file)) {
            return $template_file;
        }
        
        return null;
    }
    
    /**
     * Create default template files if they don't exist
     */
    private function create_default_templates(): void {
        if (!file_exists($this->template_dir)) {
            wp_mkdir_p($this->template_dir);
        }
        
        $templates = [
            'weekly-digest' => $this->get_default_digest_template(),
            'monthly-digest' => $this->get_default_digest_template(),
            'confirmation' => $this->get_default_confirmation_template(),
            'welcome' => $this->get_default_welcome_template(),
        ];
        
        foreach ($templates as $name => $content) {
            $file_path = $this->template_dir . $name . '.php';
            if (!file_exists($file_path)) {
                file_put_contents($file_path, $content);
            }
        }
    }
    
    /**
     * Render fallback digest template
     */
    private function render_fallback_digest_template(array $data): string {
        $posts = $data['posts'] ?? [];
        $frequency = $data['frequency'] ?? 'weekly';
        $blog_name = $data['blog_name'] ?? get_bloginfo('name');
        $subscriber = $data['subscriber'] ?? [];
        $unsubscribe_url = $data['unsubscribe_url'] ?? '#';
        $is_test = $data['is_test'] ?? false;
        
        $brand_color = get_option('newsletter_brand_color', '#1e73be');
        $logo_url = get_option('newsletter_logo_url', '');
        
        $html = $this->get_email_header($brand_color, $logo_url);
        
        $html .= '<div class="email-content">';
        $html .= '<h1 style="color: ' . esc_attr($brand_color) . '; border-bottom: 2px solid #eee; padding-bottom: 10px;">';
        $html .= esc_html($blog_name) . ' - ' . ucfirst($frequency) . ' Digest';
        $html .= '</h1>';
        
        if ($is_test) {
            $html .= '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">';
            $html .= '<strong>This is a test email</strong> - You are receiving this because you requested a preview.';
            $html .= '</div>';
        }
        
        $html .= '<p>Hello! Here are the articles published over the past ' . ($frequency === 'weekly' ? 'seven days' : 'month') . ':</p>';
        
        if (empty($posts)) {
            $html .= '<p>We didn\'t publish any new articles this past ' . ($frequency === 'weekly' ? 'week' : 'month') . '. Check back next time!</p>';
        } else {
            $html .= '<div class="posts-list">';
            foreach ($posts as $post) {
                $html .= $this->render_post_item($post);
            }
            $html .= '</div>';
        }
        
        $html .= '<p style="margin-top: 30px;">Thank you for subscribing!</p>';
        $html .= '</div>';
        
        $html .= $this->get_email_footer($unsubscribe_url, $data['manage_preferences_url'] ?? '#');
        
        return $html;
    }
    
    /**
     * Render fallback confirmation template
     */
    private function render_fallback_confirmation_template(array $data): string {
        $subscriber = $data['subscriber'] ?? [];
        $confirmation_url = $data['confirmation_url'] ?? '#';
        $blog_name = $data['blog_name'] ?? get_bloginfo('name');
        $privacy_policy_url = $data['privacy_policy_url'] ?? '';
        
        $brand_color = get_option('newsletter_brand_color', '#1e73be');
        $logo_url = get_option('newsletter_logo_url', '');
        
        $html = $this->get_email_header($brand_color, $logo_url);
        
        $html .= '<div class="email-content">';
        $html .= '<h1 style="color: ' . esc_attr($brand_color) . ';">Confirm Your Subscription</h1>';
        $html .= '<p>Hello!</p>';
        $html .= '<p>Thank you for subscribing to our newsletter. Please click the button below to confirm your subscription:</p>';
        
        $html .= '<div style="text-align: center; margin: 30px 0;">';
        $html .= '<a href="' . esc_url($confirmation_url) . '" style="background-color: ' . esc_attr($brand_color) . '; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Confirm Subscription</a>';
        $html .= '</div>';
        
        $html .= '<p>If the button doesn\'t work, you can copy and paste this link into your browser:</p>';
        $html .= '<p><a href="' . esc_url($confirmation_url) . '">' . esc_html($confirmation_url) . '</a></p>';
        
        $html .= '<p><small>If you did not request this subscription, please ignore this email.</small></p>';
        
        if ($privacy_policy_url) {
            $html .= '<p><small>By confirming, you agree to our <a href="' . esc_url($privacy_policy_url) . '">Privacy Policy</a>.</small></p>';
        }
        
        $html .= '</div>';
        $html .= $this->get_simple_footer();
        
        return $html;
    }
    
    /**
     * Render fallback welcome template
     */
    private function render_fallback_welcome_template(array $data): string {
        $subscriber = $data['subscriber'] ?? [];
        $blog_name = $data['blog_name'] ?? get_bloginfo('name');
        $blog_url = $data['blog_url'] ?? home_url();
        $unsubscribe_url = $data['unsubscribe_url'] ?? '#';
        
        $brand_color = get_option('newsletter_brand_color', '#1e73be');
        $logo_url = get_option('newsletter_logo_url', '');
        
        $html = $this->get_email_header($brand_color, $logo_url);
        
        $html .= '<div class="email-content">';
        $html .= '<h1 style="color: ' . esc_attr($brand_color) . ';">Welcome to Our Newsletter!</h1>';
        $html .= '<p>Hello and welcome!</p>';
        $html .= '<p>Thank you for confirming your subscription to the ' . esc_html($blog_name) . ' newsletter.</p>';
        
        $frequency = $subscriber['digest_frequency'] ?? 'weekly';
        $html .= '<p>You will receive our <strong>' . esc_html($frequency) . ' digest</strong> with the latest articles and updates.</p>';
        
        $html .= '<p>In the meantime, feel free to browse our latest content:</p>';
        $html .= '<div style="text-align: center; margin: 20px 0;">';
        $html .= '<a href="' . esc_url($blog_url) . '" style="background-color: ' . esc_attr($brand_color) . '; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;">Visit Our Website</a>';
        $html .= '</div>';
        
        $html .= '<p>Thank you for joining our community!</p>';
        $html .= '</div>';
        
        $html .= $this->get_email_footer($unsubscribe_url, $data['manage_preferences_url'] ?? '#');
        
        return $html;
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
     * Get email header HTML
     */
    private function get_email_header(string $brand_color, string $logo_url = ''): string {
        $html = '<html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<title>Newsletter</title>';
        $html .= $this->get_email_styles($brand_color);
        $html .= '</head><body>';
        $html .= '<div class="email-container">';
        
        if ($logo_url) {
            $html .= '<div style="text-align: center; margin-bottom: 20px;">';
            $html .= '<img src="' . esc_url($logo_url) . '" alt="Logo" style="max-height: 60px; width: auto;">';
            $html .= '</div>';
        }
        
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
    
    /**
     * Get default digest template content
     */
    private function get_default_digest_template(): string {
        return '<?php
/**
 * Default Digest Email Template
 * 
 * Available variables:
 * $posts - Array of post data
 * $frequency - weekly or monthly
 * $blog_name - Site name
 * $subscriber - Subscriber data
 * $unsubscribe_url - Unsubscribe link
 * $manage_preferences_url - Preferences link
 * $is_test - Whether this is a test email
 */

$edh_newsletter_brand_color = get_option("newsletter_brand_color", "#1e73be");
$edh_newsletter_logo_url = get_option("newsletter_logo_url", "");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($blog_name); ?> Newsletter</title>
    <?php echo $this->get_email_styles($edh_newsletter_brand_color); ?>
</head>
<body>
    <div class="email-container">
        <?php if ($edh_newsletter_logo_url): ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="<?php echo esc_url($edh_newsletter_logo_url); ?>" alt="Logo" style="max-height: 60px; width: auto;">
        </div>
        <?php endif; ?>
        
        <div class="email-content">
            <h1 style="color: <?php echo esc_attr($edh_newsletter_brand_color); ?>;">
                <?php echo esc_html($blog_name); ?> - <?php echo ucfirst($frequency); ?> Digest
            </h1>
            
            <?php if ($is_test ?? false): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
                <strong>This is a test email</strong>
            </div>
            <?php endif; ?>
            
            <p>Hello! Here are the articles published over the past <?php echo $frequency === "weekly" ? "seven days" : "month"; ?>:</p>
            
            <?php if (empty($posts)): ?>
                <p>We didn\'t publish any new articles this past <?php echo $frequency === "weekly" ? "week" : "month"; ?>. Check back next time!</p>
            <?php else: ?>
                <div class="posts-list">
                    <?php foreach ($posts as $post): ?>
                        <?php echo $this->render_post_item($post); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <p style="margin-top: 30px;">Thank you for subscribing!</p>
        </div>
        
        <?php echo $this->get_email_footer($unsubscribe_url, $manage_preferences_url ?? "#"); ?>
    </div>
</body>
</html>';
    }
    
    /**
     * Get default confirmation template content
     */
    private function get_default_confirmation_template(): string {
        return '<?php
/**
 * Default Confirmation Email Template
 */
$edh_newsletter_brand_color = get_option("newsletter_brand_color", "#1e73be");
$edh_newsletter_logo_url = get_option("newsletter_logo_url", "");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirm Your Subscription</title>
    <?php echo $this->get_email_styles($edh_newsletter_brand_color); ?>
</head>
<body>
    <div class="email-container">
        <?php if ($edh_newsletter_logo_url): ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="<?php echo esc_url($edh_newsletter_logo_url); ?>" alt="Logo" style="max-height: 60px;">
        </div>
        <?php endif; ?>
        
        <div class="email-content">
            <h1 style="color: <?php echo esc_attr($edh_newsletter_brand_color); ?>;">Confirm Your Subscription</h1>
            <p>Hello!</p>
            <p>Thank you for subscribing to our newsletter. Please click the button below to confirm:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="<?php echo esc_url($confirmation_url); ?>" 
                   style="background-color: <?php echo esc_attr($edh_newsletter_brand_color); ?>; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                   Confirm Subscription
                </a>
            </div>
            
            <p><small>If you did not request this, please ignore this email.</small></p>
        </div>
        
        <?php echo $this->get_simple_footer(); ?>
    </div>
</body>
</html>';
    }
    
    /**
     * Get default welcome template content
     */
    private function get_default_welcome_template(): string {
        return '<?php
/**
 * Default Welcome Email Template
 */
$edh_newsletter_brand_color = get_option("newsletter_brand_color", "#1e73be");
$edh_newsletter_logo_url = get_option("newsletter_logo_url", "");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome!</title>
    <?php echo $this->get_email_styles($edh_newsletter_brand_color); ?>
</head>
<body>
    <div class="email-container">
        <?php if ($edh_newsletter_logo_url): ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="<?php echo esc_url($edh_newsletter_logo_url); ?>" alt="Logo" style="max-height: 60px;">
        </div>
        <?php endif; ?>
        
        <div class="email-content">
            <h1 style="color: <?php echo esc_attr($edh_newsletter_brand_color); ?>;">Welcome!</h1>
            <p>Thank you for confirming your subscription to <?php echo esc_html($blog_name); ?>.</p>
            <p>You will receive our <strong><?php echo esc_html($subscriber["digest_frequency"] ?? "weekly"); ?> digest</strong> with our latest content.</p>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="<?php echo esc_url($blog_url); ?>" 
                   style="background-color: <?php echo esc_attr($edh_newsletter_brand_color); ?>; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;">
                   Visit Our Website
                </a>
            </div>
        </div>
        
        <?php echo $this->get_email_footer($unsubscribe_url, $manage_preferences_url ?? "#"); ?>
    </div>
</body>
</html>';
    }
}
