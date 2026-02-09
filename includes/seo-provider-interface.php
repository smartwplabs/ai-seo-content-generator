<?php
/**
 * SEO Provider Interface
 * 
 * Abstraction layer for different SEO plugins
 * Free compatibility for all major SEO plugins
 * 
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base SEO Provider Interface
 * All SEO providers must implement this interface
 */
interface AI_SEO_Provider_Interface {
    /**
     * Get provider name
     * @return string
     */
    public function get_name();
    
    /**
     * Check if this provider is active
     * @return bool
     */
    public function is_active();
    
    /**
     * Get SEO fields for a post
     * @param int $post_id
     * @return array {
     *     @type string $focus_keyword
     *     @type string $meta_title
     *     @type string $meta_description
     *     @type string $canonical_url
     *     @type array  $additional_meta
     * }
     */
    public function get_fields($post_id);
    
    /**
     * Set SEO fields for a post
     * @param int $post_id
     * @param array $fields Same structure as get_fields()
     * @return bool Success
     */
    public function set_fields($post_id, $fields);
    
    /**
     * Get SEO score (if supported)
     * @param int $post_id
     * @return int|null Score 0-100, or null if not supported
     */
    public function get_score($post_id);
    
    /**
     * Get provider capabilities
     * @return array {
     *     @type bool $supports_scoring
     *     @type bool $supports_schema
     *     @type bool $supports_social_meta
     *     @type bool $supports_breadcrumbs
     * }
     */
    public function get_capabilities();
}

/**
 * SEO Provider Manager
 * Detects and manages active SEO provider
 */
class AI_SEO_Provider_Manager {
    
    private static $instance = null;
    private $active_provider = null;
    private $providers = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_providers();
        $this->detect_active_provider();
    }
    
    /**
     * Register all available SEO providers
     */
    private function register_providers() {
        // Register providers in priority order
        $this->providers = [
            'rankmath'  => new AI_SEO_Provider_RankMath(),
            'yoast'     => new AI_SEO_Provider_Yoast(),
            'aioseo'    => new AI_SEO_Provider_AIOSEO(),
            'seopress'  => new AI_SEO_Provider_SEOPress(),
        ];
    }
    
    /**
     * Detect which SEO provider is active
     */
    private function detect_active_provider() {
        foreach ($this->providers as $key => $provider) {
            if ($provider->is_active()) {
                $this->active_provider = $provider;
                // v1.3.1O: Logging removed - was causing excessive log entries
                // Logged once when generation starts instead
                return;
            }
        }
        
        // No SEO plugin detected
        $this->active_provider = new AI_SEO_Provider_Fallback();
    }
    
    /**
     * Get active SEO provider
     * @return AI_SEO_Provider_Interface
     */
    public function get_provider() {
        return $this->active_provider;
    }
    
    /**
     * Get all available providers
     * @return array
     */
    public function get_all_providers() {
        return $this->providers;
    }
    
    /**
     * Check if a specific provider is active
     * @param string $provider_key
     * @return bool
     */
    public function is_provider_active($provider_key) {
        return isset($this->providers[$provider_key]) && 
               $this->providers[$provider_key]->is_active();
    }
}

/**
 * Convenience function to get active SEO provider
 * @return AI_SEO_Provider_Interface
 */
function ai_seo_get_provider() {
    return AI_SEO_Provider_Manager::get_instance()->get_provider();
}
