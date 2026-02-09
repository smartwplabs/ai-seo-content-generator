<?php
/**
 * AI SEO Content Generator - Standalone SEO Mode
 * 
 * Provides basic SEO functionality when no SEO plugin is detected.
 * Outputs meta tags, Open Graph, and Twitter Cards.
 * 
 * @package AI_SEO_Content_Generator
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if standalone SEO mode should be active
 * Active when no major SEO plugin is detected
 */
function ai_seo_standalone_is_active() {
    // Check if any major SEO plugin is active
    $seo_plugins = [
        'wordpress-seo/wp-seo.php',           // Yoast SEO
        'wordpress-seo-premium/wp-seo-premium.php', // Yoast Premium
        'seo-by-rank-math/rank-math.php',     // Rank Math
        'all-in-one-seo-pack/all_in_one_seo_pack.php', // AIOSEO
        'wp-seopress/seopress.php',           // SEOPress
    ];
    
    foreach ($seo_plugins as $plugin) {
        if (is_plugin_active($plugin)) {
            return false;
        }
    }
    
    // Also check by class/function existence
    if (defined('WPSEO_VERSION') || // Yoast
        defined('RANK_MATH_VERSION') || // Rank Math
        defined('AIOSEO_VERSION') || // AIOSEO
        defined('SEOPRESS_VERSION')) { // SEOPress
        return false;
    }
    
    return true;
}

/**
 * Initialize standalone SEO if no SEO plugin detected
 */
function ai_seo_standalone_init() {
    if (!ai_seo_standalone_is_active()) {
        return;
    }
    
    // Output meta tags
    add_action('wp_head', 'ai_seo_standalone_output_meta', 1);
    
    // Filter document title
    add_filter('pre_get_document_title', 'ai_seo_standalone_document_title', 20);
    add_filter('document_title_parts', 'ai_seo_standalone_title_parts', 20);
}
add_action('init', 'ai_seo_standalone_init');

/**
 * Output meta tags for products
 */
function ai_seo_standalone_output_meta() {
    if (!is_singular('product')) {
        return;
    }
    
    global $post;
    $post_id = $post->ID;
    
    // Get our stored meta data
    $meta_title = get_post_meta($post_id, '_ai_seo_meta_title', true);
    $meta_desc = get_post_meta($post_id, '_ai_seo_meta_description', true);
    $focus_keyword = get_post_meta($post_id, '_ai_seo_focus_keyword', true);
    
    // Get product data for Open Graph
    $product = wc_get_product($post_id);
    $image_id = $product ? $product->get_image_id() : get_post_thumbnail_id($post_id);
    $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
    $price = $product ? $product->get_price() : '';
    $currency = get_woocommerce_currency();
    
    echo "\n<!-- AI SEO Standalone Mode by Smart WP Labs -->\n";
    
    // Meta description
    if (!empty($meta_desc)) {
        echo '<meta name="description" content="' . esc_attr($meta_desc) . '">' . "\n";
    }
    
    // Canonical URL
    echo '<link rel="canonical" href="' . esc_url(get_permalink($post_id)) . '">' . "\n";
    
    // Robots
    echo '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">' . "\n";
    
    // Open Graph
    echo '<meta property="og:type" content="product">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($meta_title ?: get_the_title($post_id)) . '">' . "\n";
    if (!empty($meta_desc)) {
        echo '<meta property="og:description" content="' . esc_attr($meta_desc) . '">' . "\n";
    }
    echo '<meta property="og:url" content="' . esc_url(get_permalink($post_id)) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    if (!empty($image_url)) {
        echo '<meta property="og:image" content="' . esc_url($image_url) . '">' . "\n";
    }
    
    // Product specific Open Graph
    if ($product) {
        if (!empty($price)) {
            echo '<meta property="product:price:amount" content="' . esc_attr($price) . '">' . "\n";
            echo '<meta property="product:price:currency" content="' . esc_attr($currency) . '">' . "\n";
        }
        echo '<meta property="product:availability" content="' . ($product->is_in_stock() ? 'in stock' : 'out of stock') . '">' . "\n";
    }
    
    // Twitter Cards
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($meta_title ?: get_the_title($post_id)) . '">' . "\n";
    if (!empty($meta_desc)) {
        echo '<meta name="twitter:description" content="' . esc_attr($meta_desc) . '">' . "\n";
    }
    if (!empty($image_url)) {
        echo '<meta name="twitter:image" content="' . esc_url($image_url) . '">' . "\n";
    }
    
    echo "<!-- /AI SEO Standalone Mode -->\n\n";
}

/**
 * Filter document title for products
 */
function ai_seo_standalone_document_title($title) {
    if (!is_singular('product')) {
        return $title;
    }
    
    global $post;
    $meta_title = get_post_meta($post->ID, '_ai_seo_meta_title', true);
    
    if (!empty($meta_title)) {
        return $meta_title;
    }
    
    return $title;
}

/**
 * Filter document title parts
 */
function ai_seo_standalone_title_parts($title_parts) {
    if (!is_singular('product')) {
        return $title_parts;
    }
    
    global $post;
    $meta_title = get_post_meta($post->ID, '_ai_seo_meta_title', true);
    
    if (!empty($meta_title)) {
        $title_parts['title'] = $meta_title;
    }
    
    return $title_parts;
}

/**
 * Save standalone SEO fields when generating content
 * This hooks into the existing generation process
 */
function ai_seo_standalone_save_fields($post_id, $field, $content) {
    if (!ai_seo_standalone_is_active()) {
        return;
    }
    
    // These fields are saved by standalone mode
    $standalone_fields = ['title', 'meta_description', 'focus_keyword'];
    
    if (!in_array($field, $standalone_fields)) {
        return;
    }
    
    // Map field names to our meta keys
    $meta_keys = [
        'title' => '_ai_seo_meta_title',
        'meta_description' => '_ai_seo_meta_description',
        'focus_keyword' => '_ai_seo_focus_keyword'
    ];
    
    if (isset($meta_keys[$field])) {
        update_post_meta($post_id, $meta_keys[$field], $content);
    }
}
add_action('ai_seo_content_generated', 'ai_seo_standalone_save_fields', 5, 3);

/**
 * Add standalone indicator to dashboard
 */
function ai_seo_standalone_admin_notice() {
    if (!ai_seo_standalone_is_active()) {
        return;
    }
    
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'toplevel_page_ai-seo-content-generator') {
        return;
    }
    
    echo '<div class="notice notice-info" style="border-left-color: #00a0d2;">';
    echo '<p><strong>🔧 Standalone SEO Mode Active</strong> — No SEO plugin detected. AI SEO Content Generator will output meta tags directly.</p>';
    echo '</div>';
}
add_action('admin_notices', 'ai_seo_standalone_admin_notice');

/**
 * Get SEO provider - returns standalone mode info if no plugin detected
 */
function ai_seo_standalone_provider_info() {
    if (!ai_seo_standalone_is_active()) {
        return null;
    }
    
    return [
        'name' => 'Standalone Mode',
        'active' => true,
        'fields' => [
            'title' => '_ai_seo_meta_title',
            'meta_description' => '_ai_seo_meta_description',
            'focus_keyword' => '_ai_seo_focus_keyword'
        ]
    ];
}
