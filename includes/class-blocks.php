<?php
/**
 * Blocks Class
 *
 * @package Newsletter
 * @since 2.1.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Newsletter Blocks
 *
 * Registers the block-editor equivalents of the shortcodes. Both blocks are
 * dynamic (server-rendered) and delegate to the Frontend Forms renderers, so
 * a block and its shortcode always produce the same markup.
 */
class EDH_Newsletter_Blocks {
    
    /**
     * Script handle shared by both blocks (referenced from block.json)
     */
    const SCRIPT_HANDLE = 'edh-newsletter-blocks';
    
    /**
     * Editor preview stylesheet handle (referenced from block.json)
     */
    const PREVIEW_STYLE_HANDLE = 'edh-newsletter-block-preview';
    
    /**
     * Constructor
     *
     * Runs during init@10 (from EDH_Newsletter_Core::init_modules), which is a
     * valid time to register blocks. Nothing here is hooked to init itself.
     */
    public function __construct() {
        $this->register_assets();
        $this->register_blocks();
    }
    
    /**
     * Register the shared editor script and preview stylesheet
     */
    private function register_assets(): void {
        wp_register_script(
            self::SCRIPT_HANDLE,
            EDH_NEWSLETTER_ASSETS_URL . 'js/blocks.js',
            ['wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-server-side-render'],
            EDH_NEWSLETTER_VERSION,
            true
        );
        wp_set_script_translations(self::SCRIPT_HANDLE, 'edh-newsletter');
        
        // The public stylesheet doubles as the editor preview style.
        wp_register_style(
            self::PREVIEW_STYLE_HANDLE,
            EDH_NEWSLETTER_ASSETS_URL . 'css/public.css',
            [],
            EDH_NEWSLETTER_VERSION
        );
    }
    
    /**
     * Register both blocks from their block.json files
     */
    private function register_blocks(): void {
        register_block_type(
            EDH_NEWSLETTER_PLUGIN_DIR . 'blocks/signup-form',
            ['render_callback' => [$this, 'render_signup_form']]
        );
        
        register_block_type(
            EDH_NEWSLETTER_PLUGIN_DIR . 'blocks/preferences-form',
            ['render_callback' => [$this, 'render_preferences_form']]
        );
    }
    
    /**
     * Render the signup block by mapping its attributes onto the shortcode
     */
    public function render_signup_form(array $attributes): string {
        $frontend_forms = EDH_Newsletter_Core::get_instance()->get_module('frontend_forms');
        
        if (!$frontend_forms) {
            return '';
        }
        
        $shortcode_atts = [
            'title' => $attributes['title'] ?? '',
            'description' => $attributes['description'] ?? '',
            'button_text' => $attributes['buttonText'] ?? __('Subscribe', 'edh-newsletter'),
            'show_frequency' => !empty($attributes['showFrequency']) ? 'true' : 'false',
            'default_frequency' => ($attributes['defaultFrequency'] ?? 'weekly') === 'monthly' ? 'monthly' : 'weekly',
            'style' => in_array($attributes['style'] ?? 'default', ['default', 'minimal', 'boxed', 'inline'], true)
                ? $attributes['style']
                : 'default',
        ];
        
        return $this->wrap($frontend_forms->render_signup_shortcode($shortcode_atts));
    }
    
    /**
     * Render the preferences block
     */
    public function render_preferences_form(array $attributes): string {
        $frontend_forms = EDH_Newsletter_Core::get_instance()->get_module('frontend_forms');
        
        if (!$frontend_forms) {
            return '';
        }
        
        return $this->wrap($frontend_forms->render_preferences_shortcode([
            'title' => $attributes['title'] ?? '',
        ]));
    }
    
    /**
     * Wrap rendered markup with the block's wrapper attributes (alignment, spacing, custom classes)
     */
    private function wrap(string $content): string {
        if ($content === '') {
            return '';
        }
        
        return '<div ' . get_block_wrapper_attributes() . '>' . $content . '</div>';
    }
}
