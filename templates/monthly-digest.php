<?php
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

declare(strict_types=1);

defined('ABSPATH') || exit;

$edh_newsletter_brand_color = get_option("newsletter_brand_color", "#1e73be");
$edh_newsletter_logo_url = get_option("newsletter_logo_url", "");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($blog_name); ?> Newsletter</title>
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
            <img src="<?php echo esc_url($edh_newsletter_logo_url); ?>" alt="Logo" style="max-height: 60px; width: auto;">
        </div>
        <?php endif; ?>
        
        <div class="email-content">
            <h1 style="color: <?php echo esc_attr($edh_newsletter_brand_color); ?>;">
                <?php echo esc_html($blog_name); ?> - <?php echo esc_html(ucfirst($frequency)); ?> Digest
            </h1>
            
            <?php if ($is_test ?? false): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
                <strong>This is a test email</strong>
            </div>
            <?php endif; ?>
            
            <p>Hello! Here are the articles published over the past <?php echo $frequency === "weekly" ? "seven days" : "month"; ?>:</p>
            
            <?php if (empty($posts)): ?>
                <p>We didn't publish any new articles this past <?php echo $frequency === "weekly" ? "week" : "month"; ?>. Check back next time!</p>
            <?php else: ?>
                <div class="posts-list">
                    <?php foreach ($posts as $post): ?>
                        <?php echo wp_kses_post($this->render_post_item($post)); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <p style="margin-top: 30px;">Thank you for subscribing!</p>
        </div>
        
        <?php echo wp_kses_post($this->get_email_footer(esc_url($unsubscribe_url), esc_url($manage_preferences_url ?? "#"))); ?>
    </div>
</body>
</html>