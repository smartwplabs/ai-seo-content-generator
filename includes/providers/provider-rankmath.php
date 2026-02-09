<?php
/**
 * Rank Math SEO Provider
 * Default provider with full scoring support
 * 
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Provider_RankMath implements AI_SEO_Provider_Interface {
    
    public function get_name() {
        return 'Rank Math';
    }
    
    public function is_active() {
        return defined('RANK_MATH_VERSION') || class_exists('RankMath');
    }
    
    public function get_fields($post_id) {
        if (!$this->is_active()) {
            return [];
        }
        
        return [
            'focus_keyword'      => get_post_meta($post_id, 'rank_math_focus_keyword', true),
            'meta_title'         => get_post_meta($post_id, 'rank_math_title', true),
            'meta_description'   => get_post_meta($post_id, 'rank_math_description', true),
            'canonical_url'      => get_post_meta($post_id, 'rank_math_canonical_url', true),
            'robots_meta'        => get_post_meta($post_id, 'rank_math_robots', true),
            'advanced_robots'    => get_post_meta($post_id, 'rank_math_advanced_robots', true),
            'breadcrumb_title'   => get_post_meta($post_id, 'rank_math_breadcrumb_title', true),
            'facebook_title'     => get_post_meta($post_id, 'rank_math_facebook_title', true),
            'facebook_description' => get_post_meta($post_id, 'rank_math_facebook_description', true),
            'twitter_title'      => get_post_meta($post_id, 'rank_math_twitter_title', true),
            'twitter_description' => get_post_meta($post_id, 'rank_math_twitter_description', true),
        ];
    }
    
    public function set_fields($post_id, $fields) {
        if (!$this->is_active()) {
            return false;
        }
        
        $updated = false;
        
        // Core SEO fields
        if (isset($fields['focus_keyword'])) {
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($fields['focus_keyword']));
            $updated = true;
        }
        
        if (isset($fields['meta_title'])) {
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($fields['meta_title']));
            $updated = true;
        }
        
        if (isset($fields['meta_description'])) {
            update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($fields['meta_description']));
            $updated = true;
        }
        
        // Optional fields
        if (isset($fields['canonical_url'])) {
            update_post_meta($post_id, 'rank_math_canonical_url', esc_url_raw($fields['canonical_url']));
            $updated = true;
        }
        
        if (isset($fields['robots_meta'])) {
            update_post_meta($post_id, 'rank_math_robots', $fields['robots_meta']);
            $updated = true;
        }
        
        // Social meta (if provided)
        if (isset($fields['facebook_title'])) {
            update_post_meta($post_id, 'rank_math_facebook_title', sanitize_text_field($fields['facebook_title']));
        }
        
        if (isset($fields['facebook_description'])) {
            update_post_meta($post_id, 'rank_math_facebook_description', sanitize_textarea_field($fields['facebook_description']));
        }
        
        if (isset($fields['twitter_title'])) {
            update_post_meta($post_id, 'rank_math_twitter_title', sanitize_text_field($fields['twitter_title']));
        }
        
        if (isset($fields['twitter_description'])) {
            update_post_meta($post_id, 'rank_math_twitter_description', sanitize_textarea_field($fields['twitter_description']));
        }
        
        return $updated;
    }
    
    public function get_score($post_id) {
        if (!$this->is_active()) {
            return null;
        }
        
        // Rank Math stores score as integer 0-100
        $score = get_post_meta($post_id, 'rank_math_seo_score', true);
        
        return $score !== '' ? intval($score) : null;
    }
    
    public function get_capabilities() {
        return [
            'supports_scoring'      => true,  // Rank Math has built-in scoring
            'supports_schema'       => true,  // Full schema.org support
            'supports_social_meta'  => true,  // Facebook, Twitter meta
            'supports_breadcrumbs'  => true,  // Breadcrumb customization
        ];
    }
    
    /**
     * Get Rank Math SEO analysis details
     * @param int $post_id
     * @return array Analysis results
     */
    public function get_seo_analysis($post_id) {
        if (!$this->is_active()) {
            return [];
        }
        
        // Get Rank Math's detailed analysis if available
        $analysis = get_post_meta($post_id, 'rank_math_seo_analysis', true);
        
        return is_array($analysis) ? $analysis : [];
    }
    
    /**
     * Trigger Rank Math score recalculation
     * @param int $post_id
     */
    public function recalculate_score($post_id) {
        if (!$this->is_active()) {
            return;
        }
        
        // Rank Math recalculates on save, but we can trigger it manually
        if (function_exists('rank_math_reindex_post')) {
            rank_math_reindex_post($post_id);
        }
    }
}
