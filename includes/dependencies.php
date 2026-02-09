<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check plugin dependencies
 * v1.3.0 - SEO plugin now optional (works with Rank Math, Yoast, AIOSEO, SEOPress, or standalone)
 * v1.3.1Q - Respect debug logging toggle
 */
function ai_seo_check_dependencies() {
    // v1.3.1Q: Check if debug logging is enabled
    $tools = get_option('ai_seo_tools', []);
    $logging_enabled = !empty($tools['enable_debug_logging']);
    
    $log = 'Checking dependencies: ' . date('Y-m-d H:i:s') . "\n";
    $has_woocommerce = class_exists('WooCommerce');
    
    // Check WooCommerce (required)
    if (!$has_woocommerce) {
        $log .= "WooCommerce missing - REQUIRED\n";
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>AI SEO Content Generator:</strong> WooCommerce is required.</p></div>';
        });
        // Always log activation errors (even if logging disabled)
        file_put_contents(WP_CONTENT_DIR . '/ai-seo-activation.log', $log, FILE_APPEND);
        return false;
    }
    
    $log .= "WooCommerce found ✓\n";
    
    // Check SEO plugins (optional - we have fallback)
    // Detect which SEO provider is active
    if (function_exists('ai_seo_get_provider')) {
        $provider = ai_seo_get_provider();
        $provider_name = $provider->get_name();
        $capabilities = $provider->get_capabilities();
        
        $log .= "SEO Provider: $provider_name\n";
        $log .= "Supports Scoring: " . ($capabilities['supports_scoring'] ? 'Yes' : 'No') . "\n";
        
        // Show informational notice about detected SEO plugin
        add_action('admin_notices', function() use ($provider_name, $capabilities) {
            $message = '<strong>AI SEO Content Generator:</strong> Working with ' . esc_html($provider_name);
            
            if ($capabilities['supports_scoring']) {
                $message .= ' <span style="color: #2271b1;">✓ SEO Scoring Enabled</span>';
                $class = 'notice-success';
            } else if ($provider_name === 'Basic WordPress (No SEO Plugin)') {
                $message .= ' <span style="color: #999;">ℹ️ Install Rank Math, Yoast, AIOSEO, or SEOPress for SEO scoring</span>';
                $class = 'notice-info';
            } else {
                $message .= ' <span style="color: #999;">ℹ️ Basic compatibility (no scoring)</span>';
                $class = 'notice-info';
            }
            
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . $message . '</p></div>';
        }, 5); // Priority 5 to show early
    }
    
    // v1.3.1Q: Only write activation log if logging enabled
    if ($logging_enabled) {
        file_put_contents(WP_CONTENT_DIR . '/ai-seo-activation.log', $log, FILE_APPEND);
    }
    
    return $has_woocommerce; // Only WooCommerce is required
}
