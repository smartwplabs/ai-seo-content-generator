<?php
/**
 * All in One SEO (AIOSEO) Provider
 * Free compatibility module
 * 
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Provider_AIOSEO implements AI_SEO_Provider_Interface {
    
    public function get_name() {
        return 'All in One SEO';
    }
    
    public function is_active() {
        return defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin\\AIOSEO');
    }
    
    public function get_fields($post_id) {
        if (!$this->is_active()) {
            return [];
        }
        
        return [
            'focus_keyword'      => get_post_meta($post_id, '_aioseo_keywords', true),
            'meta_title'         => get_post_meta($post_id, '_aioseo_title', true),
            'meta_description'   => get_post_meta($post_id, '_aioseo_description', true),
            'canonical_url'      => get_post_meta($post_id, '_aioseo_canonical_url', true),
            'robots_meta'        => [
                'noindex'  => get_post_meta($post_id, '_aioseo_noindex', true),
                'nofollow' => get_post_meta($post_id, '_aioseo_nofollow', true),
            ],
            'facebook_title'     => get_post_meta($post_id, '_aioseo_og_title', true),
            'facebook_description' => get_post_meta($post_id, '_aioseo_og_description', true),
            'twitter_title'      => get_post_meta($post_id, '_aioseo_twitter_title', true),
            'twitter_description' => get_post_meta($post_id, '_aioseo_twitter_description', true),
        ];
    }
    
    public function set_fields($post_id, $fields) {
        if (!$this->is_active()) {
            return false;
        }
        
        $updated = false;
        
        // Core SEO fields
        if (isset($fields['focus_keyword'])) {
            update_post_meta($post_id, '_aioseo_keywords', sanitize_text_field($fields['focus_keyword']));
            $updated = true;
        }
        
        if (isset($fields['meta_title'])) {
            update_post_meta($post_id, '_aioseo_title', sanitize_text_field($fields['meta_title']));
            $updated = true;
        }
        
        if (isset($fields['meta_description'])) {
            update_post_meta($post_id, '_aioseo_description', sanitize_textarea_field($fields['meta_description']));
            $updated = true;
        }
        
        // Optional fields
        if (isset($fields['canonical_url'])) {
            update_post_meta($post_id, '_aioseo_canonical_url', esc_url_raw($fields['canonical_url']));
            $updated = true;
        }
        
        // Social meta
        if (isset($fields['facebook_title'])) {
            update_post_meta($post_id, '_aioseo_og_title', sanitize_text_field($fields['facebook_title']));
        }
        
        if (isset($fields['facebook_description'])) {
            update_post_meta($post_id, '_aioseo_og_description', sanitize_textarea_field($fields['facebook_description']));
        }
        
        if (isset($fields['twitter_title'])) {
            update_post_meta($post_id, '_aioseo_twitter_title', sanitize_text_field($fields['twitter_title']));
        }
        
        if (isset($fields['twitter_description'])) {
            update_post_meta($post_id, '_aioseo_twitter_description', sanitize_textarea_field($fields['twitter_description']));
        }
        
        return $updated;
    }
    
    public function get_score($post_id) {
        if (!$this->is_active()) {
            return null;
        }
        
        // AIOSEO has TruSEO score (0-100)
        $score = get_post_meta($post_id, '_aioseo_seo_score', true);
        
        return $score !== '' ? intval($score) : null;
    }
    
    public function get_capabilities() {
        return [
            'supports_scoring'      => true,  // TruSEO score
            'supports_schema'       => true,
            'supports_social_meta'  => true,
            'supports_breadcrumbs'  => true,
        ];
    }
}
