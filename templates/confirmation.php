<?php
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
        
        <?php echo wp_kses_post($this->get_simple_footer()); ?>
    </div>
</body>
</html>