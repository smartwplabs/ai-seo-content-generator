<?php
/**
 * SEOPress Provider
 * Free compatibility module
 * 
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Provider_SEOPress implements AI_SEO_Provider_Interface {
    
    public function get_name() {
        return 'SEOPress';
    }
    
    public function is_active() {
        return defined('SEOPRESS_VERSION') || function_exists('seopress_init');
    }
    
    public function get_fields($post_id) {
        if (!$this->is_active()) {
            return [];
        }
        
        return [
            'focus_keyword'      => get_post_meta($post_id, '_seopress_analysis_target_kw', true),
            'meta_title'         => get_post_meta($post_id, '_seopress_titles_title', true),
            'meta_description'   => get_post_meta($post_id, '_seopress_titles_desc', true),
            'canonical_url'      => get_post_meta($post_id, '_seopress_robots_canonical', true),
            'robots_meta'        => [
                'noindex'  => get_post_meta($post_id, '_seopress_robots_index', true),
                'nofollow' => get_post_meta($post_id, '_seopress_robots_follow', true),
            ],
            'facebook_title'     => get_post_meta($post_id, '_seopress_social_fb_title', true),
            'facebook_description' => get_post_meta($post_id, '_seopress_social_fb_desc', true),
            'twitter_title'      => get_post_meta($post_id, '_seopress_social_twitter_title', true),
            'twitter_description' => get_post_meta($post_id, '_seopress_social_twitter_desc', true),
        ];
    }
    
    public function set_fields($post_id, $fields) {
        if (!$this->is_active()) {
            return false;
        }
        
        $updated = false;
        
        // Core SEO fields
        if (isset($fields['focus_keyword'])) {
            update_post_meta($post_id, '_seopress_analysis_target_kw', sanitize_text_field($fields['focus_keyword']));
            $updated = true;
        }
        
        if (isset($fields['meta_title'])) {
            update_post_meta($post_id, '_seopress_titles_title', sanitize_text_field($fields['meta_title']));
            $updated = true;
        }
        
        if (isset($fields['meta_description'])) {
            update_post_meta($post_id, '_seopress_titles_desc', sanitize_textarea_field($fields['meta_description']));
            $updated = true;
        }
        
        // Optional fields
        if (isset($fields['canonical_url'])) {
            update_post_meta($post_id, '_seopress_robots_canonical', esc_url_raw($fields['canonical_url']));
            $updated = true;
        }
        
        // Social meta
        if (isset($fields['facebook_title'])) {
            update_post_meta($post_id, '_seopress_social_fb_title', sanitize_text_field($fields['facebook_title']));
        }
        
        if (isset($fields['facebook_description'])) {
            update_post_meta($post_id, '_seopress_social_fb_desc', sanitize_textarea_field($fields['facebook_description']));
        }
        
        if (isset($fields['twitter_title'])) {
            update_post_meta($post_id, '_seopress_social_twitter_title', sanitize_text_field($fields['twitter_title']));
        }
        
        if (isset($fields['twitter_description'])) {
            update_post_meta($post_id, '_seopress_social_twitter_desc', sanitize_textarea_field($fields['twitter_description']));
        }
        
        return $updated;
    }
    
    public function get_score($post_id) {
        // SEOPress doesn't provide a simple numeric score
        return null;
    }
    
    public function get_capabilities() {
        return [
            'supports_scoring'      => false,
            'supports_schema'       => true,
            'supports_social_meta'  => true,
            'supports_breadcrumbs'  => true,
        ];
    }
}

/**
 * Fallback Provider
 * Used when no SEO plugin is detected
 * Stores data in basic WordPress meta fields
 * 
 * @since 1.3.0
 */
class AI_SEO_Provider_Fallback implements AI_SEO_Provider_Interface {
    
    public function get_name() {
        return 'Basic WordPress (No SEO Plugin)';
    }
    
    public function is_active() {
        // Always "active" as fallback
        return true;
    }
    
    public function get_fields($post_id) {
        return [
            'focus_keyword'      => get_post_meta($post_id, '_ai_seo_focus_keyword', true),
            'meta_title'         => get_post_meta($post_id, '_ai_seo_meta_title', true),
            'meta_description'   => get_post_meta($post_id, '_ai_seo_meta_description', true),
            'canonical_url'      => get_post_meta($post_id, '_ai_seo_canonical_url', true),
        ];
    }
    
    public function set_fields($post_id, $fields) {
        $updated = false;
        
        if (isset($fields['focus_keyword'])) {
            update_post_meta($post_id, '_ai_seo_focus_keyword', sanitize_text_field($fields['focus_keyword']));
            $updated = true;
        }
        
        if (isset($fields['meta_title'])) {
            update_post_meta($post_id, '_ai_seo_meta_title', sanitize_text_field($fields['meta_title']));
            $updated = true;
        }
        
        if (isset($fields['meta_description'])) {
            update_post_meta($post_id, '_ai_seo_meta_description', sanitize_textarea_field($fields['meta_description']));
            $updated = true;
        }
        
        if (isset($fields['canonical_url'])) {
            update_post_meta($post_id, '_ai_seo_canonical_url', esc_url_raw($fields['canonical_url']));
            $updated = true;
        }
        
        return $updated;
    }
    
    public function get_score($post_id) {
        // No scoring without an SEO plugin
        return null;
    }
    
    public function get_capabilities() {
        return [
            'supports_scoring'      => false,
            'supports_schema'       => false,
            'supports_social_meta'  => false,
            'supports_breadcrumbs'  => false,
        ];
    }
}
