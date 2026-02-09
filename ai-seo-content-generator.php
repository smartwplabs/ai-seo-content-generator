<?php
ob_start(); // Start output buffering to prevent unexpected output
/*
Plugin Name: AI SEO Content Generator
Plugin URI: https://smartwplabs.com/ai-seo-content-generator
Description: Generates SEO content for WooCommerce products using various AI engines. Works with Rank Math, Yoast SEO, All in One SEO, SEOPress, or standalone. Optimized for 90-100/100 SEO scores.
Version: 2.1.20
Author: Smart WP Labs
Author URI: https://smartwplabs.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ai-seo-content
Requires PHP: 7.3
Requires at least: 6.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_SEO_VERSION', '2.1.20'); // Added Description Length setting (standard/long/premium)
define('AI_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Safely include files with existence checks
$plugin_dir = plugin_dir_path(__FILE__);
$includes = array(
    'includes/dependencies.php',
    'includes/utils.php',
    // SEO Provider System (v1.3.0)
    'includes/seo-provider-interface.php',
    'includes/providers/provider-rankmath.php',
    'includes/providers/provider-yoast.php',
    'includes/providers/provider-aioseo.php',
    'includes/providers/provider-seopress-fallback.php',
    // Standalone SEO Mode (v2.1.0) - when no SEO plugin detected
    'includes/standalone-seo.php',
    // Core functionality
    'includes/ajax.php',
    'admin/dashboard.php',
    'admin/functions.php',
    // AI Search Optimization Suite (v1.4.0 / rebuilt v2.1.0)
    'includes/ai-search/license.php',
    'includes/ai-search/prompts.php',
    'includes/ai-search/generation.php',
    'includes/ai-search/schema-output.php',
    'includes/ai-search/metabox.php',
    // Background Processing System (v2.0.0)
    'includes/background/database.php',
    'includes/background/class-job-manager.php',
    'includes/background/class-batch-manager.php',
    'includes/background/class-field-processor.php',
    'includes/ajax-queue.php'
);

foreach ($includes as $file) {
    if (file_exists($plugin_dir . $file)) {
        require_once $plugin_dir . $file;
    }
}

// Activation hook
function ai_seo_activate() {
    ob_start(); // Buffer activation output
    
    // Add default settings
    if (!get_option('ai_seo_settings')) {
        $default_settings = [
            'ai_seo_ai_engine' => 'chatgpt',
            'ai_seo_api_key' => '',
            'ai_seo_model' => 'gpt-4o',
            'ai_seo_max_tokens' => 2048,
            'ai_seo_temperature' => 0.7,
            'ai_seo_frequency_penalty' => 0,
            'ai_seo_presence_penalty' => 0,
            'ai_seo_top_p' => 1,
            'ai_seo_buffer' => 3  // 3 seconds delay between products in bulk (prevents rate limits)
        ];
        add_option('ai_seo_settings', $default_settings);
    }
    
    // Add default prompts - OPTIMIZED FOR RANK MATH 90-100/100
    if (!get_option('ai_seo_prompts')) {
        $default_prompts = [
            'focus_keyword' => 'Generate a detailed, SEO-optimized focus keyword for the product: [product_title]. PRODUCT ATTRIBUTES: [current_attributes]. Requirements: Include key product attributes from the specifications above (materials, metals, stone types, styles, finishes, settings, etc.), 5-8 words long, use terms serious buyers would search for. For jewelry, include: metal type (gold, silver, platinum, brass, alloy), stone type (diamond, crystal, CZ, gemstone), style (eternity, solitaire, three-stone, vintage), finish/plating if notable. Examples: "Rhodium Plated Swarovski Crystal Eternity Ring", "Sterling Silver Round Cut Diamond Cross Pendant", "Lead Free Brass Channel Set Crystal Ring". Return ONLY the keyword phrase, no quotes or explanations.',
            
            'title' => 'Generate an SEO-optimized product title for [product_title]. Product Categories: [current_categories]. Requirements: START with the focus keyword [focus_keyword], keep under 60 characters TOTAL (this is a HARD limit for Rank Math), include ONE power word if space allows. IMPORTANT RankMath-Recognized Power Words by Category: Fine Jewelry = Premium/Genuine/Stunning/Perfect/Exclusive/Brilliant, Fashion Jewelry = Stunning/Perfect/Amazing/Best/Brilliant (DO NOT use "chic" or "trendy" as RankMath does not recognize these), Other products = Amazing/Best/Ultimate/Essential/Perfect/Stunning/Brilliant. If focus keyword is 45+ characters, use it as-is without adding extra words. Priority Order: 1) Focus keyword must appear at start (CRITICAL), 2) Must stay under 60 characters (CRITICAL), 3) Add RankMath-recognized power word only if space permits (OPTIONAL). Return ONLY the title text, no quotes or explanations.',
            
            'short_description' => 'Write a 50-60 word product summary for [product_title]. PRODUCT SPECIFICATIONS: [current_attributes]. Product Categories: [current_categories]. Requirements: START first sentence with [focus_keyword], mention 3-4 KEY SPECIFICATIONS from the attributes (for jewelry: materials, plating, stone type, setting style, dimensions; for other products: key technical specs), highlight main benefit, use 7th-8th grade reading level, make it scannable and compelling. IMPORTANT: Tone based on category: Fine Jewelry = sophisticated, quality-focused, investment piece language; Fashion Jewelry = stylish, accessible, fashion-forward language; Other products = clear benefits and features. Return as plain text, NO HTML tags.',
            
            'full_description' => 'Write a detailed SEO-optimized description for [product_title]. PRODUCT SPECIFICATIONS: [current_attributes]. ORIGINAL DESCRIPTION (preserve key details): [current_full_description]. Product Categories: [current_categories]. CRITICAL REQUIREMENTS: 1) LENGTH: 300-400 words (standard), 800-1000 words (long), or 1500-2000 words (premium) based on user settings. 2) USE SHORT PARAGRAPHS: Maximum 2-3 sentences per paragraph. Break content into many small, scannable paragraphs for better readability and RankMath scoring. 3) KEYWORD OPTIMIZATION: START first sentence with [focus_keyword], use [focus_keyword] 3-4 more times throughout (2-3% density), include [focus_keyword] in at least ONE <h2> heading. 4) INCLUDE ALL PRODUCT SPECIFICATIONS: You MUST mention the specific attributes provided. For Jewelry, include: Materials (Swarovski Crystals, CZ, diamonds, etc.), Base Metal and Plating (sterling silver, brass, gold plated, rhodium plated, etc.), Stone Details (cut type like round/princess, setting type like channel/prong, carat weight, stone color, stone size in mm), Style characteristics (eternity, 3-stone, solitaire, vintage, classic), Dimensions (band width, stone size, weight), Setting and craftsmanship details. For Other Products: Include all technical specifications, materials, dimensions, features from the attributes. 5) PRESERVE EXACT TECHNICAL TERMS: If attributes say "channel setting", use "channel setting" not "modern setting". If attributes say "rhodium plating", use "rhodium plating" not "silver finish". Use the EXACT terminology provided in the product specifications. 6) CATEGORY-SPECIFIC LANGUAGE: Fine Jewelry = emphasize quality, authenticity, investment value, craftsmanship, genuine materials, timeless elegance; Fashion Jewelry = emphasize style, trends, versatility, affordability, fashion-forward design, statement-making; Other products = focus on benefits, quality, value proposition. 7) STRUCTURE (use this exact format with SHORT paragraphs): <h2>Premium [focus_keyword] Features</h2><p>Opening paragraph (2-3 sentences max) - START with [focus_keyword], highlight main appeal.</p><p>Second short paragraph mentioning 1-2 standout attributes.</p><h2>Exquisite Materials and Craftsmanship</h2><p>DETAILED specifications paragraph - Include ALL technical details: materials, metals, plating, stone specifications, settings, dimensions. Use exact terms from product attributes.</p><p>Additional craftsmanship details in a separate short paragraph.</p><h2>Why Choose This [focus_keyword]</h2><p>Benefits paragraph (2-3 sentences).</p><p>Use cases and occasions (2-3 sentences).</p><p>Closing with call-to-action (1-2 sentences). Include [focus_keyword] one more time.</p> 8) WRITING STYLE: Use short sentences (15-20 words maximum), short paragraphs (2-3 sentences each), 7th-8th grade reading level, sophisticated yet accessible tone, natural keyword integration. 9) CRITICAL: This is not a generic template - you must include the ACTUAL specifications from [current_attributes]. Example: If attributes show "Rhodium plated, Swarovski Crystals, Channel setting, .35ct, 1.5mm stones, 3mm band", your description must mention these exact specifications. Return as HTML with <h2> and <p> tags only. No other HTML tags.',
            
            'meta_description' => 'Generate an SEO meta description for [product_title]. PRODUCT SPECIFICATIONS: [current_attributes]. Product Categories: [current_categories]. Requirements: Exactly 150-160 characters total (this is a HARD limit), START with [focus_keyword], include 1-2 key specifications (material, stone type, or standout feature), add main benefit and call-to-action, make it compelling and click-worthy. IMPORTANT: Tone based on category: Fine Jewelry = "Premium quality", "genuine", "investment", "exquisite"; Fashion Jewelry = "Stunning", "perfect", "stylish", "affordable"; Other products = focus on key benefits and value. Formula: "[focus_keyword] - [Key Spec]. [Main Benefit]. [CTA]." Examples: Fine Jewelry = "Rhodium Plated Swarovski Crystal Ring - Genuine .35ct channel-set stones. Timeless elegance. Shop now!" (110 chars); Fashion Jewelry = "Crystal Eternity Ring - Stunning Swarovski stones in rhodium setting. Perfect gift. Order today!" (97 chars). Return ONLY the meta description text, no quotes. Must be under 160 characters.',
            
            'tags' => 'Generate 5-7 product tags for [product_title]. PRODUCT SPECIFICATIONS: [current_attributes]. Product Categories: [current_categories]. Requirements: First tag = use [focus_keyword] if it is 5 words or less, OR use a shortened version if keyword is 6+ words; Include tags based on: materials (from attributes like rhodium, brass, crystal, diamond), style (from attributes like eternity, 3-stone, channel), category type, features, target audience (if applicable); Keep individual tags 2-4 words each (except first tag which may be longer); Make tags searchable and relevant to how customers find products. IMPORTANT: Use category-appropriate descriptors based on specifications: Fine Jewelry (genuine metals/stones) = use "luxury", "genuine", "premium", "real gold/diamond/silver", "investment", "fine jewelry"; Fashion Jewelry (plated, crystals, costume) = use "fashion", "stunning", "perfect", "stylish", "affordable", "costume jewelry", "statement"; NEVER use "luxury" or "premium" for Fashion Jewelry/costume jewelry products. Examples: For ring with rhodium plating and Swarovski crystals: rhodium crystal ring, eternity band, Swarovski ring, fashion jewelry, channel setting, statement ring, affordable luxury. Return as comma-separated list, no hashtags or quotes.'
        ];
        add_option('ai_seo_prompts', $default_prompts);
    }
    
    // Add default tools settings
    if (!get_option('ai_seo_tools')) {
        $default_tools = [
            'generate_meta_description' => 1,
            'add_meta_tag_to_head' => 1,
            'update_rank_math_meta' => 1,
            'shorten_url' => 0,
            'generate_title_from_keywords' => 1,
            'include_original_title' => 0,
            'enforce_focus_keyword_url' => 1,
            'use_sentiment_in_title' => 0,
            'use_power_word_in_title' => 1,
            'include_number_in_title' => 0,
            'update_image_alt_tags' => 1,  // ENABLED BY DEFAULT for better RankMath scores
            'permalink_manager_compat' => 1,
            'sticky_generate_button' => 0  // Sticky "Generate Content" button on products page (OFF by default)
        ];
        add_option('ai_seo_tools', $default_tools);
    }
    
    // v2.0.0: Create background processing tables
    if (function_exists('ai_seo_create_tables')) {
        ai_seo_create_tables();
    }
    
    ob_end_clean();
}
register_activation_hook(__FILE__, 'ai_seo_activate');

// Validate plugin data to prevent undefined property warnings
function ai_seo_validate_plugin_data($plugins) {
    foreach ($plugins as $plugin_file => &$plugin_data) {
        if (is_object($plugin_data)) {
            $plugin_data = (array) $plugin_data;
        }
        if (!isset($plugin_data['plugin'])) {
            $plugin_data['plugin'] = $plugin_file;
        }
    }
    return $plugins;
}
add_filter('all_plugins', 'ai_seo_validate_plugin_data');

// Enqueue admin assets
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'ai-seo-content') !== false || $hook === 'edit.php') {
        wp_enqueue_style('ai-seo-admin', AI_SEO_PLUGIN_URL . 'assets/css/ai-seo-admin.css', [], AI_SEO_VERSION);
        
        // Enqueue jQuery UI for draggable functionality
        wp_enqueue_script('jquery-ui-draggable');
        
        wp_enqueue_script('ai-seo-admin', AI_SEO_PLUGIN_URL . 'assets/js/ai-seo-admin.js', ['jquery', 'jquery-ui-draggable'], AI_SEO_VERSION, true);
        
        // v2.0.0: Background processing queue manager
        wp_enqueue_script('ai-seo-queue', AI_SEO_PLUGIN_URL . 'assets/js/ai-seo-queue.js', ['jquery'], AI_SEO_VERSION, true);
        
        // Get current prompts for the popup
        $prompts = get_option('ai_seo_prompts', []);
        $tools = get_option('ai_seo_tools', []);
        
        // Get saved button position for current user
        $user_id = get_current_user_id();
        $button_position = get_user_meta($user_id, 'ai_seo_button_position', true);
        
        // v1.3.1P: Get timing settings from TOOLS option (not settings)
        $score_wait_time = isset($tools['score_wait_time']) ? intval($tools['score_wait_time']) : 5;
        $post_save_delay = isset($tools['post_save_delay']) ? intval($tools['post_save_delay']) : 1;
        
        wp_localize_script('ai-seo-admin', 'aiSeoSettings', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'prompts' => $prompts,
            'nonce' => wp_create_nonce('ai_seo_nonce'),
            'scoreWaitTime' => $score_wait_time * 1000, // Convert to milliseconds
            'postSaveDelay' => $post_save_delay * 1000,  // Convert to milliseconds
            'useBackgroundProcessing' => true  // v2.0.0 - always enabled
        ]);
        
        // Pass sticky button setting separately for products page
        wp_localize_script('ai-seo-admin', 'aiSeoStickyButton', !empty($tools['sticky_generate_button']) ? '1' : '0');
        
        // Pass score calculation enable setting (v1.2.1.7b - FIXED registration)
        // Default to enabled (true) if not set for backward compatibility
        // IMPORTANT: Always pass this to products page for score calculation UI
        $enable_score_calc = isset($tools['enable_score_calculation']) ? !empty($tools['enable_score_calculation']) : true;
        wp_localize_script('ai-seo-admin', 'aiSeoEnableScoreCalculation', $enable_score_calc ? '1' : '0');
        
        // Debug logging (v1.2.1.18 - Enhanced)
        error_log('[AI SEO v1.2.1.18] Tools array: ' . print_r($tools, true));
        error_log('[AI SEO v1.2.1.18] enable_score_calculation in array: ' . (isset($tools['enable_score_calculation']) ? 'YES' : 'NO'));
        error_log('[AI SEO v1.2.1.18] enable_score_calculation value: ' . (isset($tools['enable_score_calculation']) ? $tools['enable_score_calculation'] : 'NOT SET'));
        error_log('[AI SEO v1.2.1.18] Score calculation setting passed to JS: ' . ($enable_score_calc ? '1 (enabled)' : '0 (disabled)'));
        
        // Pass saved button position
        if (!empty($button_position)) {
            wp_localize_script('ai-seo-admin', 'aiSeoButtonPosition', $button_position);
        }
    }
});

// Add admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'AI SEO Content Generator',        // Page title
        'AI SEO Content',                  // Menu label
        'manage_options',                  // Capability
        'ai-seo-content-generator',        // Menu slug
        'ai_seo_generator_dashboard',      // Callback function
        'dashicons-chart-line',            // Icon
        55                                 // Position
    );
});

// Prevent fatal error from Rank Math's analytics module by sanitizing non-numeric values
add_filter('rank_math/analytics/data', function ($blocks) {
    if (!is_array($blocks)) {
        return $blocks;
    }
    
    foreach ($blocks as &$block) {
        if (is_array($block)) {
            foreach ($block as $key => &$value) {
                if (!is_numeric($value)) {
                    $value = 0;
                }
            }
        }
    }
    return $blocks;
});

// Initialize SEO Provider Manager (v1.3.0)
add_action('plugins_loaded', function() {
    // Initialize provider manager to detect active SEO plugin
    AI_SEO_Provider_Manager::get_instance();
    
    // v1.3.1O: Only log during actual generation, not on every page load
    // Logging moved to ajax.php when generation starts
}, 20); // Priority 20 to ensure SEO plugins are loaded first
