<?php
/**
 * Yoast SEO Provider
 * Free compatibility module
 * 
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Provider_Yoast implements AI_SEO_Provider_Interface {
    
    public function get_name() {
        return 'Yoast SEO';
    }
    
    public function is_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    }
    
    public function get_fields($post_id) {
        if (!$this->is_active()) {
            return [];
        }
        
        return [
            'focus_keyword'      => get_post_meta($post_id, '_yoast_wpseo_focuskw', true),
            'meta_title'         => get_post_meta($post_id, '_yoast_wpseo_title', true),
            'meta_description'   => get_post_meta($post_id, '_yoast_wpseo_metadesc', true),
            'canonical_url'      => get_post_meta($post_id, '_yoast_wpseo_canonical', true),
            'robots_meta'        => [
                'noindex'  => get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true),
                'nofollow' => get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true),
            ],
            'breadcrumb_title'   => get_post_meta($post_id, '_yoast_wpseo_bctitle', true),
            'facebook_title'     => get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true),
            'facebook_description' => get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true),
            'twitter_title'      => get_post_meta($post_id, '_yoast_wpseo_twitter-title', true),
            'twitter_description' => get_post_meta($post_id, '_yoast_wpseo_twitter-description', true),
        ];
    }
    
    public function set_fields($post_id, $fields) {
        if (!$this->is_active()) {
            return false;
        }
        
        $updated = false;
        
        // Core SEO fields
        if (isset($fields['focus_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($fields['focus_keyword']));
            $updated = true;
        }
        
        if (isset($fields['meta_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($fields['meta_title']));
            $updated = true;
        }
        
        if (isset($fields['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($fields['meta_description']));
            $updated = true;
        }
        
        // Optional fields
        if (isset($fields['canonical_url'])) {
            update_post_meta($post_id, '_yoast_wpseo_canonical', esc_url_raw($fields['canonical_url']));
            $updated = true;
        }
        
        // Social meta
        if (isset($fields['facebook_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-title', sanitize_text_field($fields['facebook_title']));
        }
        
        if (isset($fields['facebook_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-description', sanitize_textarea_field($fields['facebook_description']));
        }
        
        if (isset($fields['twitter_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_twitter-title', sanitize_text_field($fields['twitter_title']));
        }
        
        if (isset($fields['twitter_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_twitter-description', sanitize_textarea_field($fields['twitter_description']));
        }
        
        return $updated;
    }
    
    public function get_score($post_id) {
        // Yoast doesn't expose a simple numeric score
        // We could parse their traffic light system, but returning null is cleaner
        return null;
    }
    
    public function get_capabilities() {
        return [
            'supports_scoring'      => false, // Yoast uses traffic light, not numeric score
            'supports_schema'       => true,
            'supports_social_meta'  => true,
            'supports_breadcrumbs'  => true,
        ];
    }
    
    /**
     * Get Yoast SEO score indicator (red/orange/green)
     * @param int $post_id
     * @return string|null 'good', 'ok', 'bad', or null
     */
    public function get_score_indicator($post_id) {
        if (!$this->is_active()) {
            return null;
        }
        
        $score = get_post_meta($post_id, '_yoast_wpseo_linkdex', true);
        
        if ($score === '') {
            return null;
        }
        
        $score = intval($score);
        
        // Yoast uses 0-100 scale internally
        // 0-40 = bad (red)
        // 41-70 = ok (orange)  
        // 71-100 = good (green)
        
        if ($score >= 71) {
            return 'good';
        } elseif ($score >= 41) {
            return 'ok';
        } else {
            return 'bad';
        }
    }
}
