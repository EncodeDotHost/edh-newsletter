<?php
/**
 * Default Welcome Email Template
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$edh_newsletter_brand_color = get_option("newsletter_brand_color", "#1e73be");
$edh_newsletter_logo_url = get_option("newsletter_logo_url", "");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome!</title>
    <?php
    // Allow style tag and CSS for email templates
    $edh_newsletter_allowed_html = [
        'style' => [],
    ];
    echo wp_kses($this->get_email_styles($edh_newsletter_brand_color), $edh_newsletter_allowed_html);
    ?>
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
        
        <?php echo wp_kses_post($this->get_email_footer(esc_url($unsubscribe_url), esc_url($manage_preferences_url ?? "#"))); ?>
    </div>
</body>
</html>