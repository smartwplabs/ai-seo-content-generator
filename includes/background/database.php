<?php
/**
 * AI SEO Content Generator - Database Migrations
 * 
 * Creates tables for background job processing
 * 
 * @package AI_SEO_Content_Generator
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database version - increment when schema changes
 */
define('AI_SEO_DB_VERSION', '1.0.0');

/**
 * Create or update database tables
 * 
 * Called on plugin activation and version updates
 */
function ai_seo_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Table for tracking generation batches (one per "Generate" click)
    $batches_table = $wpdb->prefix . 'ai_seo_generation_batches';
    
    // Table for individual field generation jobs
    $jobs_table = $wpdb->prefix . 'ai_seo_generation_jobs';
    
    $sql_batches = "CREATE TABLE $batches_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(36) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        product_ids LONGTEXT NOT NULL,
        prompts LONGTEXT NOT NULL,
        settings LONGTEXT NOT NULL,
        total_jobs INT UNSIGNED DEFAULT 0,
        completed_jobs INT UNSIGNED DEFAULT 0,
        failed_jobs INT UNSIGNED DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        error_message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        started_at DATETIME,
        completed_at DATETIME,
        UNIQUE KEY batch_id (batch_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    $sql_jobs = "CREATE TABLE $jobs_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(36) NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        field_name VARCHAR(50) NOT NULL,
        field_order TINYINT UNSIGNED DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        result LONGTEXT,
        error_message TEXT,
        retry_count TINYINT UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        started_at DATETIME,
        completed_at DATETIME,
        KEY batch_id (batch_id),
        KEY product_id (product_id),
        KEY status (status),
        KEY batch_status (batch_id, status),
        KEY batch_product (batch_id, product_id),
        KEY batch_order (batch_id, field_order, status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($sql_batches);
    dbDelta($sql_jobs);
    
    // Store DB version
    update_option('ai_seo_db_version', AI_SEO_DB_VERSION);
    
    ai_seo_log("Database tables created/updated to version " . AI_SEO_DB_VERSION);
}

/**
 * Check if tables need updating on admin init
 */
function ai_seo_maybe_update_tables() {
    $installed_version = get_option('ai_seo_db_version', '0');
    
    if (version_compare($installed_version, AI_SEO_DB_VERSION, '<')) {
        ai_seo_create_tables();
    }
}
add_action('admin_init', 'ai_seo_maybe_update_tables');

/**
 * Get the batches table name
 */
function ai_seo_get_batches_table() {
    global $wpdb;
    return $wpdb->prefix . 'ai_seo_generation_batches';
}

/**
 * Get the jobs table name
 */
function ai_seo_get_jobs_table() {
    global $wpdb;
    return $wpdb->prefix . 'ai_seo_generation_jobs';
}

/**
 * Cleanup old batches (called via WP Cron)
 * 
 * Removes batches older than 7 days to prevent table bloat
 */
function ai_seo_cleanup_old_batches() {
    global $wpdb;
    
    $batches_table = ai_seo_get_batches_table();
    $jobs_table = ai_seo_get_jobs_table();
    
    // Get batch IDs older than 7 days
    $cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    $old_batches = $wpdb->get_col($wpdb->prepare(
        "SELECT batch_id FROM $batches_table WHERE created_at < %s",
        $cutoff
    ));
    
    if (empty($old_batches)) {
        return;
    }
    
    // Delete jobs first (foreign key relationship)
    $placeholders = implode(',', array_fill(0, count($old_batches), '%s'));
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $jobs_table WHERE batch_id IN ($placeholders)",
        ...$old_batches
    ));
    
    // Delete batches
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $batches_table WHERE batch_id IN ($placeholders)",
        ...$old_batches
    ));
    
    ai_seo_log("Cleaned up " . count($old_batches) . " old batches");
}

/**
 * Schedule daily cleanup
 */
function ai_seo_schedule_cleanup() {
    if (!wp_next_scheduled('ai_seo_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'ai_seo_daily_cleanup');
    }
}
add_action('wp', 'ai_seo_schedule_cleanup');
add_action('ai_seo_daily_cleanup', 'ai_seo_cleanup_old_batches');

/**
 * Unschedule cleanup on deactivation
 */
function ai_seo_unschedule_cleanup() {
    wp_clear_scheduled_hook('ai_seo_daily_cleanup');
}

/**
 * Drop tables on uninstall (optional - called from uninstall.php)
 */
function ai_seo_drop_tables() {
    global $wpdb;
    
    $batches_table = ai_seo_get_batches_table();
    $jobs_table = ai_seo_get_jobs_table();
    
    $wpdb->query("DROP TABLE IF EXISTS $jobs_table");
    $wpdb->query("DROP TABLE IF EXISTS $batches_table");
    
    delete_option('ai_seo_db_version');
}
