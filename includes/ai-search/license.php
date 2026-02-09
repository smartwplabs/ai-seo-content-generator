<?php
/**
 * AI SEO Search Optimization - License & Feature Management
 * 
 * Handles license validation and feature unlocking for AI Search Suite
 * 
 * @package AI_SEO_Content_Generator
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if AI Search license is valid
 */
function ai_seo_search_is_licensed() {
    $license_data = get_option('ai_seo_search_license', []);
    
    // Check if license exists and is valid
    if (empty($license_data['key']) || empty($license_data['valid'])) {
        return false;
    }
    
    // Check expiration if set
    if (!empty($license_data['expires']) && strtotime($license_data['expires']) < time()) {
        return false;
    }
    
    return true;
}

/**
 * Validate license key with server
 * For now, accepts any non-empty key for testing
 * Replace with actual API validation later
 */
function ai_seo_search_validate_license($license_key) {
    $license_key = sanitize_text_field(trim($license_key));
    
    if (empty($license_key)) {
        return [
            'valid' => false,
            'message' => 'Please enter a license key.'
        ];
    }
    
    // TODO: Replace with actual API call to smartwplabs.com
    // For now, validate format: AISEO-XXXX-XXXX-XXXX
    if (preg_match('/^AISEO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license_key)) {
        return [
            'valid' => true,
            'message' => 'License activated successfully!',
            'expires' => date('Y-m-d', strtotime('+1 year'))
        ];
    }
    
    // For testing: accept "test" as valid
    if ($license_key === 'test' || $license_key === 'TEST') {
        return [
            'valid' => true,
            'message' => 'Test license activated!',
            'expires' => date('Y-m-d', strtotime('+1 year'))
        ];
    }
    
    return [
        'valid' => false,
        'message' => 'Invalid license key. Please check and try again.'
    ];
}

/**
 * AJAX handler for license activation
 */
function ai_seo_search_activate_license() {
    check_ajax_referer('ai_seo_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
    
    $result = ai_seo_search_validate_license($license_key);
    
    if ($result['valid']) {
        update_option('ai_seo_search_license', [
            'key' => $license_key,
            'valid' => true,
            'expires' => $result['expires'] ?? '',
            'activated' => current_time('mysql')
        ]);
        
        wp_send_json_success([
            'message' => $result['message'],
            'expires' => $result['expires'] ?? ''
        ]);
    } else {
        wp_send_json_error([
            'message' => $result['message']
        ]);
    }
}
add_action('wp_ajax_ai_seo_search_activate_license', 'ai_seo_search_activate_license');

/**
 * AJAX handler for license deactivation
 */
function ai_seo_search_deactivate_license() {
    check_ajax_referer('ai_seo_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    delete_option('ai_seo_search_license');
    delete_option('ai_seo_search_tools');
    
    wp_send_json_success([
        'message' => 'License deactivated.'
    ]);
}
add_action('wp_ajax_ai_seo_search_deactivate_license', 'ai_seo_search_deactivate_license');

/**
 * Get AI Search tools settings (only if licensed)
 */
function ai_seo_search_get_tools() {
    if (!ai_seo_search_is_licensed()) {
        return [];
    }
    
    return get_option('ai_seo_search_tools', []);
}
