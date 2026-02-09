<?php
/**
 * AI SEO Search Optimization - Schema & Tab Output
 * 
 * v2.1.0 - Complete rebuild based on AI search research:
 * - Outputs only standard schema.org (FAQPage, HowTo, Product)
 * - Displays content in WooCommerce tabs (visible to AI crawlers)
 * - Removed all custom meta tags (AI ignores them)
 * 
 * @package AI_SEO_Content_Generator
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output AI Search schema in wp_head
 * Only outputs standard schema.org - no custom meta tags
 */
function ai_seo_search_output_schema() {
    if (!ai_seo_search_is_licensed()) {
        return;
    }
    
    if (!is_singular('product')) {
        return;
    }
    
    global $post;
    $post_id = $post->ID;
    
    $output = '';
    
    // FAQ Schema (FAQPage - standard schema.org)
    $faq = get_post_meta($post_id, '_ai_seo_faq_schema', true);
    if (!empty($faq) && is_array($faq)) {
        $output .= ai_seo_search_generate_faq_schema($faq);
    }
    
    // HowTo Schema for Care Instructions (standard schema.org)
    $care = get_post_meta($post_id, '_ai_seo_care_instructions', true);
    if (!empty($care) && is_array($care)) {
        $output .= ai_seo_search_generate_howto_schema($post, $care);
    }
    
    if (!empty($output)) {
        echo "\n<!-- AI SEO Search Optimization by Smart WP Labs -->\n";
        echo $output;
        echo "<!-- /AI SEO Search Optimization -->\n\n";
    }
}
add_action('wp_head', 'ai_seo_search_output_schema', 5);

/**
 * Generate FAQ Page Schema
 */
function ai_seo_search_generate_faq_schema($faqs) {
    if (empty($faqs)) {
        return '';
    }
    
    $faq_items = [];
    foreach ($faqs as $faq) {
        if (!empty($faq['question']) && !empty($faq['answer'])) {
            $faq_items[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                ]
            ];
        }
    }
    
    if (empty($faq_items)) {
        return '';
    }
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faq_items
    ];
    
    return '<script type="application/ld+json">' . "\n" . 
           wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . 
           "\n</script>\n";
}

/**
 * Generate HowTo Schema
 */
function ai_seo_search_generate_howto_schema($post, $steps) {
    if (empty($steps)) {
        return '';
    }
    
    $howto_steps = [];
    $position = 1;
    foreach ($steps as $step) {
        $howto_steps[] = [
            '@type' => 'HowToStep',
            'position' => $position,
            'text' => $step
        ];
        $position++;
    }
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'How to Care for ' . $post->post_title,
        'description' => 'Care and maintenance instructions for ' . $post->post_title,
        'step' => $howto_steps
    ];
    
    return '<script type="application/ld+json">' . "\n" . 
           wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . 
           "\n</script>\n";
}

/**
 * Enhance WooCommerce product schema
 */
function ai_seo_search_enhance_product_schema($markup, $product) {
    if (!ai_seo_search_is_licensed()) {
        return $markup;
    }
    
    $post_id = $product->get_id();
    
    // Product highlights as additionalProperty
    $highlights = get_post_meta($post_id, '_ai_seo_product_highlights', true);
    if (!empty($highlights) && is_array($highlights)) {
        if (!isset($markup['additionalProperty'])) {
            $markup['additionalProperty'] = [];
        }
        
        foreach ($highlights as $index => $highlight) {
            $markup['additionalProperty'][] = [
                '@type' => 'PropertyValue',
                'name' => 'Feature ' . ($index + 1),
                'value' => $highlight
            ];
        }
    }
    
    // Alt names as alternateName (standard schema.org)
    $alt_names = get_post_meta($post_id, '_ai_seo_alt_names', true);
    if (!empty($alt_names) && is_array($alt_names)) {
        $markup['alternateName'] = $alt_names;
    }
    
    return $markup;
}
add_filter('woocommerce_structured_data_product', 'ai_seo_search_enhance_product_schema', 10, 2);

/**
 * =============================================================================
 * WOOCOMMERCE TAB DISPLAY
 * Makes AI Search content visible to crawlers and users
 * =============================================================================
 */

/**
 * Add AI Search tabs to WooCommerce product page
 */
function ai_seo_search_add_product_tabs($tabs) {
    if (!ai_seo_search_is_licensed()) {
        return $tabs;
    }
    
    global $post;
    if (!$post) {
        return $tabs;
    }
    
    $display_settings = get_option('ai_seo_search_display', [
        'display_mode' => 'combined',
        'show_faq' => 1,
        'show_care' => 1,
        'show_highlights' => 1,
        'show_pros_cons' => 1
    ]);
    
    // Check if we have any content to display
    $has_faq = !empty(get_post_meta($post->ID, '_ai_seo_faq_schema', true));
    $has_care = !empty(get_post_meta($post->ID, '_ai_seo_care_instructions', true));
    $has_highlights = !empty(get_post_meta($post->ID, '_ai_seo_product_highlights', true));
    $has_pros_cons = !empty(get_post_meta($post->ID, '_ai_seo_pros_cons', true));
    
    $display_mode = $display_settings['display_mode'] ?? 'combined';
    
    // Don't display mode (none or shortcode - user handles placement)
    if ($display_mode === 'none' || $display_mode === 'shortcode') {
        return $tabs;
    }
    
    // Append to description mode
    if ($display_mode === 'append') {
        add_filter('woocommerce_product_description_heading', '__return_empty_string');
        add_filter('the_content', 'ai_seo_search_append_to_description', 20);
        return $tabs;
    }
    
    // Additional Information tab mode
    if ($display_mode === 'additional_info') {
        // Modify the existing additional_information tab callback
        if (isset($tabs['additional_information'])) {
            $tabs['additional_information']['callback'] = 'ai_seo_search_render_additional_info_tab';
        }
        return $tabs;
    }
    
    // Combined tab mode
    if ($display_mode === 'combined') {
        $has_any = ($has_faq && !empty($display_settings['show_faq'])) ||
                   ($has_care && !empty($display_settings['show_care'])) ||
                   ($has_highlights && !empty($display_settings['show_highlights'])) ||
                   ($has_pros_cons && !empty($display_settings['show_pros_cons']));
        
        if ($has_any) {
            $tabs['ai_product_info'] = [
                'title' => __('Product Info', 'ai-seo-content-generator'),
                'priority' => 25,
                'callback' => 'ai_seo_search_render_combined_tab'
            ];
        }
        return $tabs;
    }
    
    // Separate tabs mode
    if ($display_mode === 'separate') {
        if ($has_faq && !empty($display_settings['show_faq'])) {
            $tabs['ai_faq'] = [
                'title' => __('FAQ', 'ai-seo-content-generator'),
                'priority' => 25,
                'callback' => 'ai_seo_search_render_faq_tab'
            ];
        }
        
        if ($has_care && !empty($display_settings['show_care'])) {
            $tabs['ai_care'] = [
                'title' => __('Care Instructions', 'ai-seo-content-generator'),
                'priority' => 26,
                'callback' => 'ai_seo_search_render_care_tab'
            ];
        }
        
        if (($has_highlights || $has_pros_cons) && 
            (!empty($display_settings['show_highlights']) || !empty($display_settings['show_pros_cons']))) {
            $tabs['ai_details'] = [
                'title' => __('Details', 'ai-seo-content-generator'),
                'priority' => 27,
                'callback' => 'ai_seo_search_render_details_tab'
            ];
        }
    }
    
    return $tabs;
}
add_filter('woocommerce_product_tabs', 'ai_seo_search_add_product_tabs', 50);

/**
 * Render Additional Information tab with AI content appended
 */
function ai_seo_search_render_additional_info_tab() {
    global $product;
    
    // Render original WooCommerce additional information (attributes table)
    wc_get_template('single-product/product-attributes.php', [
        'product_attributes' => array_filter($product->get_attributes(), 'wc_attributes_array_filter_visible')
    ]);
    
    // Now render the AI content below
    global $post;
    $display_settings = get_option('ai_seo_search_display', []);
    
    echo '<div class="ai-seo-product-info" style="margin-top: 20px;">';
    
    // FAQ Section
    if (!empty($display_settings['show_faq'])) {
        $faq = get_post_meta($post->ID, '_ai_seo_faq_schema', true);
        if (!empty($faq) && is_array($faq)) {
            ai_seo_search_render_faq_content($faq);
        }
    }
    
    // Care Instructions Section
    if (!empty($display_settings['show_care'])) {
        $care = get_post_meta($post->ID, '_ai_seo_care_instructions', true);
        if (!empty($care) && is_array($care)) {
            ai_seo_search_render_care_content($care, $post->post_title);
        }
    }
    
    // Product Highlights Section
    if (!empty($display_settings['show_highlights'])) {
        $highlights = get_post_meta($post->ID, '_ai_seo_product_highlights', true);
        if (!empty($highlights) && is_array($highlights)) {
            ai_seo_search_render_highlights_content($highlights);
        }
    }
    
    // Pros & Cons Section
    if (!empty($display_settings['show_pros_cons'])) {
        $pros_cons = get_post_meta($post->ID, '_ai_seo_pros_cons', true);
        if (!empty($pros_cons) && is_array($pros_cons)) {
            ai_seo_search_render_pros_cons_content($pros_cons);
        }
    }
    
    echo '</div>';
}

/**
 * Render combined tab (all content in one tab)
 */
function ai_seo_search_render_combined_tab() {
    global $post;
    $display_settings = get_option('ai_seo_search_display', []);
    
    echo '<div class="ai-seo-product-info">';
    
    // FAQ Section
    if (!empty($display_settings['show_faq'])) {
        $faq = get_post_meta($post->ID, '_ai_seo_faq_schema', true);
        if (!empty($faq) && is_array($faq)) {
            ai_seo_search_render_faq_content($faq);
        }
    }
    
    // Care Instructions Section
    if (!empty($display_settings['show_care'])) {
        $care = get_post_meta($post->ID, '_ai_seo_care_instructions', true);
        if (!empty($care) && is_array($care)) {
            ai_seo_search_render_care_content($care, $post->post_title);
        }
    }
    
    // Product Highlights Section
    if (!empty($display_settings['show_highlights'])) {
        $highlights = get_post_meta($post->ID, '_ai_seo_product_highlights', true);
        if (!empty($highlights) && is_array($highlights)) {
            ai_seo_search_render_highlights_content($highlights);
        }
    }
    
    // Pros & Cons Section
    if (!empty($display_settings['show_pros_cons'])) {
        $pros_cons = get_post_meta($post->ID, '_ai_seo_pros_cons', true);
        if (!empty($pros_cons) && is_array($pros_cons)) {
            ai_seo_search_render_pros_cons_content($pros_cons);
        }
    }
    
    echo '</div>';
}

/**
 * Render FAQ tab (separate mode)
 */
function ai_seo_search_render_faq_tab() {
    global $post;
    $faq = get_post_meta($post->ID, '_ai_seo_faq_schema', true);
    if (!empty($faq) && is_array($faq)) {
        echo '<div class="ai-seo-product-info">';
        ai_seo_search_render_faq_content($faq);
        echo '</div>';
    }
}

/**
 * Render Care Instructions tab (separate mode)
 */
function ai_seo_search_render_care_tab() {
    global $post;
    $care = get_post_meta($post->ID, '_ai_seo_care_instructions', true);
    if (!empty($care) && is_array($care)) {
        echo '<div class="ai-seo-product-info">';
        ai_seo_search_render_care_content($care, $post->post_title);
        echo '</div>';
    }
}

/**
 * Render Details tab (highlights + pros/cons in separate mode)
 */
function ai_seo_search_render_details_tab() {
    global $post;
    $display_settings = get_option('ai_seo_search_display', []);
    
    echo '<div class="ai-seo-product-info">';
    
    if (!empty($display_settings['show_highlights'])) {
        $highlights = get_post_meta($post->ID, '_ai_seo_product_highlights', true);
        if (!empty($highlights) && is_array($highlights)) {
            ai_seo_search_render_highlights_content($highlights);
        }
    }
    
    if (!empty($display_settings['show_pros_cons'])) {
        $pros_cons = get_post_meta($post->ID, '_ai_seo_pros_cons', true);
        if (!empty($pros_cons) && is_array($pros_cons)) {
            ai_seo_search_render_pros_cons_content($pros_cons);
        }
    }
    
    echo '</div>';
}

/**
 * Append content to description (append mode)
 */
function ai_seo_search_append_to_description($content) {
    if (!is_singular('product')) {
        return $content;
    }
    
    global $post;
    $display_settings = get_option('ai_seo_search_display', []);
    
    ob_start();
    echo '<div class="ai-seo-product-info ai-seo-appended">';
    
    if (!empty($display_settings['show_faq'])) {
        $faq = get_post_meta($post->ID, '_ai_seo_faq_schema', true);
        if (!empty($faq) && is_array($faq)) {
            ai_seo_search_render_faq_content($faq);
        }
    }
    
    if (!empty($display_settings['show_care'])) {
        $care = get_post_meta($post->ID, '_ai_seo_care_instructions', true);
        if (!empty($care) && is_array($care)) {
            ai_seo_search_render_care_content($care, $post->post_title);
        }
    }
    
    if (!empty($display_settings['show_highlights'])) {
        $highlights = get_post_meta($post->ID, '_ai_seo_product_highlights', true);
        if (!empty($highlights) && is_array($highlights)) {
            ai_seo_search_render_highlights_content($highlights);
        }
    }
    
    if (!empty($display_settings['show_pros_cons'])) {
        $pros_cons = get_post_meta($post->ID, '_ai_seo_pros_cons', true);
        if (!empty($pros_cons) && is_array($pros_cons)) {
            ai_seo_search_render_pros_cons_content($pros_cons);
        }
    }
    
    echo '</div>';
    $appended = ob_get_clean();
    
    return $content . $appended;
}

/**
 * =============================================================================
 * CONTENT RENDERING FUNCTIONS
 * Optimized HTML structure for AI snippet extraction
 * =============================================================================
 */

/**
 * Render FAQ content with proper H2/H3 structure
 */
function ai_seo_search_render_faq_content($faq) {
    if (empty($faq)) return;
    
    echo '<div class="ai-seo-faq-section">';
    echo '<h2>Frequently Asked Questions</h2>';
    
    foreach ($faq as $item) {
        if (!empty($item['question']) && !empty($item['answer'])) {
            echo '<div class="ai-seo-faq-item">';
            echo '<h3>' . esc_html($item['question']) . '</h3>';
            echo '<p>' . esc_html($item['answer']) . '</p>';
            echo '</div>';
        }
    }
    
    echo '</div>';
}

/**
 * Render Care Instructions with proper structure
 */
function ai_seo_search_render_care_content($care, $product_title) {
    if (empty($care)) return;
    
    echo '<div class="ai-seo-care-section">';
    echo '<h2>How to Care for Your ' . esc_html($product_title) . '</h2>';
    echo '<ol>';
    
    foreach ($care as $step) {
        echo '<li>' . esc_html($step) . '</li>';
    }
    
    echo '</ol>';
    echo '</div>';
}

/**
 * Render Product Highlights
 */
function ai_seo_search_render_highlights_content($highlights) {
    if (empty($highlights)) return;
    
    echo '<div class="ai-seo-highlights-section">';
    echo '<h2>Key Features</h2>';
    echo '<ul>';
    
    foreach ($highlights as $highlight) {
        echo '<li>' . esc_html($highlight) . '</li>';
    }
    
    echo '</ul>';
    echo '</div>';
}

/**
 * Render Pros & Cons
 */
function ai_seo_search_render_pros_cons_content($pros_cons) {
    if (empty($pros_cons)) return;
    
    echo '<div class="ai-seo-pros-cons-section">';
    echo '<h2>Pros and Cons</h2>';
    
    if (!empty($pros_cons['pros'])) {
        echo '<div class="ai-seo-pros">';
        echo '<h3>Pros</h3>';
        echo '<ul>';
        foreach ($pros_cons['pros'] as $pro) {
            echo '<li>' . esc_html($pro) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    if (!empty($pros_cons['cons'])) {
        echo '<div class="ai-seo-cons">';
        echo '<h3>Cons</h3>';
        echo '<ul>';
        foreach ($pros_cons['cons'] as $con) {
            echo '<li>' . esc_html($con) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * Add minimal CSS for tab content
 */
function ai_seo_search_tab_styles() {
    if (!is_singular('product')) {
        return;
    }
    ?>
    <style>
    .ai-seo-product-info h2 { margin-top: 1.5em; margin-bottom: 0.75em; }
    .ai-seo-product-info h2:first-child { margin-top: 0; }
    .ai-seo-product-info h3 { margin-top: 1em; margin-bottom: 0.5em; }
    .ai-seo-faq-item { margin-bottom: 1.5em; }
    .ai-seo-faq-item p { margin-top: 0.5em; }
    .ai-seo-pros-cons-section { margin-top: 1.5em; }
    .ai-seo-pros { margin-bottom: 1.5em; }
    .ai-seo-pros h3 { color: #28a745; }
    .ai-seo-cons h3 { color: #dc3545; }
    .ai-seo-pros ul, 
    .ai-seo-cons ul,
    .ai-seo-highlights-section ul { 
        list-style: disc !important; 
        margin-left: 1.5em !important; 
        columns: 1 !important;
        -webkit-columns: 1 !important;
        -moz-columns: 1 !important;
        display: block !important;
    }
    .ai-seo-pros li, 
    .ai-seo-cons li,
    .ai-seo-highlights-section li { 
        margin-bottom: 0.75em; 
        display: list-item !important;
        width: 100% !important;
        break-inside: avoid !important;
    }
    .ai-seo-appended { margin-top: 2em; padding-top: 2em; border-top: 1px solid #eee; }
    </style>
    <?php
}
add_action('wp_head', 'ai_seo_search_tab_styles', 99);

/**
 * =============================================================================
 * SHORTCODE FOR FLEXIBLE PLACEMENT
 * [ai_seo_product_info] - Display AI Search content anywhere
 * =============================================================================
 */

/**
 * Shortcode: [ai_seo_product_info]
 * 
 * Attributes:
 * - show: comma-separated list of sections to show (default: all)
 *   Options: summary, faq, care, highlights, pros_cons
 * - product_id: specific product ID (default: current product)
 * 
 * Examples:
 * [ai_seo_product_info] - Show all sections
 * [ai_seo_product_info show="faq,pros_cons"] - Show only FAQ and Pros/Cons
 * [ai_seo_product_info product_id="123"] - Show for specific product
 */
function ai_seo_search_shortcode($atts) {
    if (!ai_seo_search_is_licensed()) {
        return '';
    }
    
    $atts = shortcode_atts([
        'show' => 'all',
        'product_id' => 0
    ], $atts, 'ai_seo_product_info');
    
    // Get product ID
    $product_id = intval($atts['product_id']);
    if (!$product_id) {
        global $post;
        if ($post && $post->post_type === 'product') {
            $product_id = $post->ID;
        }
    }
    
    if (!$product_id) {
        return '<!-- ai_seo_product_info: No product found -->';
    }
    
    // Determine which sections to show
    $show_all = ($atts['show'] === 'all');
    $sections = $show_all ? ['summary', 'faq', 'care', 'highlights', 'pros_cons'] : array_map('trim', explode(',', $atts['show']));
    
    ob_start();
    echo '<div class="ai-seo-product-info ai-seo-shortcode">';
    
    // Product Summary
    if (in_array('summary', $sections)) {
        $summary = get_post_meta($product_id, '_ai_seo_product_summary', true);
        if (!empty($summary)) {
            echo '<div class="ai-seo-summary-section">';
            echo '<p>' . esc_html($summary) . '</p>';
            echo '</div>';
        }
    }
    
    // FAQ
    if (in_array('faq', $sections)) {
        $faq = get_post_meta($product_id, '_ai_seo_faq_schema', true);
        if (!empty($faq) && is_array($faq)) {
            ai_seo_search_render_faq_content($faq);
        }
    }
    
    // Care Instructions
    if (in_array('care', $sections)) {
        $care = get_post_meta($product_id, '_ai_seo_care_instructions', true);
        if (!empty($care) && is_array($care)) {
            $product_title = get_the_title($product_id);
            ai_seo_search_render_care_content($care, $product_title);
        }
    }
    
    // Product Highlights
    if (in_array('highlights', $sections)) {
        $highlights = get_post_meta($product_id, '_ai_seo_product_highlights', true);
        if (!empty($highlights) && is_array($highlights)) {
            ai_seo_search_render_highlights_content($highlights);
        }
    }
    
    // Pros & Cons
    if (in_array('pros_cons', $sections)) {
        $pros_cons = get_post_meta($product_id, '_ai_seo_pros_cons', true);
        if (!empty($pros_cons) && is_array($pros_cons)) {
            ai_seo_search_render_pros_cons_content($pros_cons);
        }
    }
    
    echo '</div>';
    
    return ob_get_clean();
}
add_shortcode('ai_seo_product_info', 'ai_seo_search_shortcode');
