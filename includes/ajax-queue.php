<?php
/**
 * AI SEO Content Generator - Queue AJAX Handlers
 * 
 * AJAX endpoints for background processing system
 * 
 * @package AI_SEO_Content_Generator
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Start a new generation batch
 * 
 * POST parameters:
 * - product_ids: JSON array of product IDs
 * - prompts: JSON object of prompts by field
 * 
 * Returns: batch_id
 */
add_action('wp_ajax_ai_seo_start_batch', function() {
    // Verify nonce
    if (!check_ajax_referer('ai_seo_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    // Parse input
    $product_ids = json_decode(stripslashes($_POST['product_ids'] ?? '[]'), true);
    $prompts = json_decode(stripslashes($_POST['prompts'] ?? '{}'), true);
    
    if (empty($product_ids)) {
        wp_send_json_error(['message' => 'No products selected']);
        return;
    }
    
    // Get AI settings
    $settings = get_option('ai_seo_settings', []);
    $ai_engine = $settings['ai_seo_ai_engine'] ?? 'chatgpt';
    
    // Get engine-specific API key
    $api_key_option_name = 'ai_seo_api_key_' . $ai_engine;
    $api_key = get_option($api_key_option_name, '');
    if (empty($api_key)) {
        $api_key = $settings['ai_seo_api_key'] ?? '';
    }
    
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'API key not configured. Please add your API key in AI Settings.']);
        return;
    }
    
    // Build settings array for batch
    $batch_settings = [
        'ai_engine'         => $ai_engine,
        'api_key'           => $api_key,
        'model'             => $settings['ai_seo_model'] ?? 'gpt-4o',
        'max_tokens'        => (int)($settings['ai_seo_max_tokens'] ?? 2048),
        'temperature'       => (float)($settings['ai_seo_temperature'] ?? 0.7),
        'frequency_penalty' => (float)($settings['ai_seo_frequency_penalty'] ?? 0),
        'presence_penalty'  => (float)($settings['ai_seo_presence_penalty'] ?? 0),
        'top_p'             => (float)($settings['ai_seo_top_p'] ?? 1),
    ];
    
    // v2.0.2: Get backup settings (same as legacy)
    $tools = get_option('ai_seo_tools', []);
    $backup_enabled = !empty($tools['enable_backup']);
    $backup_mode = $tools['backup_mode'] ?? 'manual';
    $restore_threshold = intval($tools['restore_threshold'] ?? 80);
    
    // v2.0.2: Create backups BEFORE starting generation (same as legacy)
    $backup_info = [];
    if ($backup_enabled && function_exists('ai_seo_create_backup')) {
        ai_seo_log("=== CREATING BACKUPS BEFORE BATCH GENERATION ===");
        foreach ($product_ids as $pid) {
            $original_score = ai_seo_get_score($pid);
            $backup_created = ai_seo_create_backup($pid);
            $backup_info[$pid] = [
                'backup_created' => $backup_created,
                'original_score' => $original_score,
                'has_backup' => $backup_created
            ];
            if ($backup_created) {
                ai_seo_log("Backup created for Product $pid (original score: " . ($original_score ?? 'N/A') . ")");
            }
        }
    }
    
    // Store backup info in batch settings so we can retrieve it later
    $batch_settings['backup_info'] = $backup_info;
    $batch_settings['backup_enabled'] = $backup_enabled;
    $batch_settings['backup_mode'] = $backup_mode;
    $batch_settings['restore_threshold'] = $restore_threshold;
    
    // Check for existing active batch
    $batch_manager = new AI_SEO_Batch_Manager();
    $active_batch = $batch_manager->get_active_batch();
    
    if ($active_batch) {
        wp_send_json_error([
            'message' => 'You already have an active batch in progress. Please wait for it to complete or cancel it.',
            'active_batch_id' => $active_batch->batch_id,
        ]);
        return;
    }
    
    // Create the batch
    $batch_id = $batch_manager->create_batch($product_ids, $prompts, $batch_settings);
    
    if (is_wp_error($batch_id)) {
        wp_send_json_error(['message' => $batch_id->get_error_message()]);
        return;
    }
    
    ai_seo_log("Started batch $batch_id via AJAX for " . count($product_ids) . " products");
    
    wp_send_json_success([
        'batch_id'     => $batch_id,
        'product_count'=> count($product_ids),
        'message'      => 'Generation started. Processing in background...',
    ]);
});

/**
 * Get batch status for polling
 * 
 * GET parameters:
 * - batch_id: The batch UUID
 * 
 * Returns: status, progress, current job info
 */
add_action('wp_ajax_ai_seo_batch_status', function() {
    // Verify nonce
    if (!check_ajax_referer('ai_seo_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    $batch_id = sanitize_text_field($_GET['batch_id'] ?? $_POST['batch_id'] ?? '');
    
    if (empty($batch_id)) {
        wp_send_json_error(['message' => 'Batch ID required']);
        return;
    }
    
    $batch_manager = new AI_SEO_Batch_Manager();
    $status = $batch_manager->get_batch_status($batch_id);
    
    if (isset($status['error'])) {
        wp_send_json_error($status);
        return;
    }
    
    wp_send_json_success($status);
});

/**
 * Get detailed results for a completed batch
 * 
 * GET parameters:
 * - batch_id: The batch UUID
 * 
 * Returns: results by product
 */
add_action('wp_ajax_ai_seo_batch_results', function() {
    error_log('AI SEO: batch_results called');
    
    // Verify nonce
    if (!check_ajax_referer('ai_seo_nonce', 'nonce', false)) {
        error_log('AI SEO: batch_results - nonce check failed');
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    $batch_id = sanitize_text_field($_GET['batch_id'] ?? $_POST['batch_id'] ?? '');
    error_log('AI SEO: batch_results - batch_id: ' . $batch_id);
    
    if (empty($batch_id)) {
        wp_send_json_error(['message' => 'Batch ID required']);
        return;
    }
    
    try {
        error_log('AI SEO: batch_results - creating batch manager');
        $batch_manager = new AI_SEO_Batch_Manager();
        
        error_log('AI SEO: batch_results - getting batch');
        $batch_data = $batch_manager->get_batch($batch_id);
        
        if (!$batch_data) {
            error_log('AI SEO: batch_results - batch not found');
            wp_send_json_error(['message' => 'Batch not found']);
            return;
        }
        
        error_log('AI SEO: batch_results - batch found, getting jobs');
        
        // Get job results grouped by product
        $job_manager = new AI_SEO_Job_Manager();
        $jobs = $job_manager->get_jobs_for_batch($batch_id);
        
        error_log('AI SEO: batch_results - got ' . (is_array($jobs) ? count($jobs) : 'null') . ' jobs');
        
        if (!is_array($jobs)) {
            $jobs = [];
        }
        
        // get_batch() already decodes JSON fields, so these are already arrays
        $batch_settings = is_array($batch_data->settings) ? $batch_data->settings : [];
        $backup_info = $batch_settings['backup_info'] ?? [];
        $backup_enabled = $batch_settings['backup_enabled'] ?? false;
        $backup_mode = $batch_settings['backup_mode'] ?? 'manual';
        $restore_threshold = $batch_settings['restore_threshold'] ?? 80;
        
        // Build results in legacy format
        $results = [];
        $product_ids = is_array($batch_data->product_ids) ? $batch_data->product_ids : [];
        
        error_log('AI SEO: batch_results - processing ' . count($product_ids) . ' products');
        
        foreach ($product_ids as $pid) {
            $product_jobs = array_values(array_filter($jobs, function($j) use ($pid) {
                return $j->product_id == $pid;
            }));
            
            // Start with backup info
            $result = [
                'backup_created' => $backup_info[$pid]['backup_created'] ?? false,
                'original_score' => $backup_info[$pid]['original_score'] ?? null,
                'has_backup' => function_exists('ai_seo_has_backup') ? ai_seo_has_backup($pid) : false,
            ];
            
            // Add generated field values from completed jobs
            foreach ($product_jobs as $job) {
                if (isset($job->status) && $job->status === 'completed' && !empty($job->result)) {
                    $result[$job->field_name] = $job->result;
                }
            }
            
            // Get fresh SEO score
            $result['seo_score'] = function_exists('ai_seo_get_score') ? ai_seo_get_score($pid) : null;
            
            // Check if any jobs failed
            $failed_jobs = array_values(array_filter($product_jobs, function($j) {
                return isset($j->status) && $j->status === 'failed';
            }));
            
            // Check if product was restored due to failure
            $skipped_jobs = array_values(array_filter($product_jobs, function($j) {
                return isset($j->status) && $j->status === 'skipped';
            }));
            
            if (count($failed_jobs) > 0) {
                $result['status'] = 'failed';
                $result['failed_fields'] = array_values(array_map(function($j) {
                    return $j->field_name ?? 'unknown';
                }, $failed_jobs));
                // Include error message from first failed job
                $result['error_message'] = $failed_jobs[0]->error_message ?? 'Unknown error';
                
                // If there are skipped jobs, the product was likely restored
                if (count($skipped_jobs) > 0) {
                    $result['restored'] = true;
                    $result['restore_reason'] = 'Critical field failed: ' . ($failed_jobs[0]->error_message ?? 'Unknown error');
                }
            } else {
                $result['status'] = 'completed';
            }
            
            $results[$pid] = $result;
        }
        
        // Build backup summary (same as legacy)
        $backup_summary = [
            'enabled' => $backup_enabled,
            'mode' => $backup_mode,
            'threshold' => $restore_threshold,
            'restored_count' => 0,
            'kept_count' => 0,
            'pending_review' => ($backup_mode === 'manual' && $backup_enabled),
            'pending_auto_restore' => ($backup_mode === 'auto' && $backup_enabled)
        ];
        
        error_log('AI SEO: batch_results - about to send success response');
        
        // Return in legacy format
        wp_send_json_success([
            'batch_id'  => $batch_id,
            'processed' => count($product_ids),
            'results'   => $results,
            'backup'    => $backup_summary,
            'debug'     => []
        ]);
        
    } catch (Exception $e) {
        error_log('AI SEO: batch_results - caught exception: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error fetching results: ' . $e->getMessage()]);
    } catch (Error $e) {
        error_log('AI SEO: batch_results - caught error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'PHP Error: ' . $e->getMessage()]);
    }
});

/**
 * Cancel an active batch
 * 
 * POST parameters:
 * - batch_id: The batch UUID (or 'all' to cancel any active batch)
 */
add_action('wp_ajax_ai_seo_cancel_batch', function() {
    // Verify nonce
    if (!check_ajax_referer('ai_seo_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    $batch_id = sanitize_text_field($_POST['batch_id'] ?? '');
    
    if (empty($batch_id)) {
        wp_send_json_error(['message' => 'Batch ID required']);
        return;
    }
    
    $batch_manager = new AI_SEO_Batch_Manager();
    
    // If 'all' is passed, find and cancel any active batch
    if ($batch_id === 'all') {
        $active_batch = $batch_manager->get_active_batch();
        if ($active_batch) {
            $batch_id = $active_batch->batch_id;
        } else {
            wp_send_json_success(['message' => 'No active batch to cancel']);
            return;
        }
    }
    
    $success = $batch_manager->cancel_batch($batch_id);
    
    if ($success) {
        ai_seo_log("Batch $batch_id cancelled via AJAX");
        wp_send_json_success(['message' => 'Batch cancelled']);
    } else {
        wp_send_json_error(['message' => 'Failed to cancel batch']);
    }
});

/**
 * Get active batch for current user (if any)
 * 
 * Useful for checking on page load if there's ongoing work
 */
add_action('wp_ajax_ai_seo_get_active_batch', function() {
    // Verify nonce
    if (!check_ajax_referer('ai_seo_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    $batch_manager = new AI_SEO_Batch_Manager();
    $active_batch = $batch_manager->get_active_batch();
    
    if ($active_batch) {
        $status = $batch_manager->get_batch_status($active_batch->batch_id);
        wp_send_json_success([
            'has_active_batch' => true,
            'batch'            => $status,
        ]);
    } else {
        wp_send_json_success([
            'has_active_batch' => false,
        ]);
    }
});

/**
 * Get recent batches for current user
 */
add_action('wp_ajax_ai_seo_get_batches', function() {
    // Verify nonce
    if (!check_ajax_referer('ai_seo_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    $limit = (int)($_GET['limit'] ?? 10);
    $limit = max(1, min(50, $limit)); // Clamp to 1-50
    
    $batch_manager = new AI_SEO_Batch_Manager();
    $batches = $batch_manager->get_user_batches($limit);
    
    wp_send_json_success([
        'batches' => $batches,
    ]);
});

/**
 * Check if Action Scheduler is available
 */
add_action('wp_ajax_ai_seo_check_background_support', function() {
    wp_send_json_success([
        'action_scheduler_available' => AI_SEO_Batch_Manager::is_action_scheduler_available(),
        'woocommerce_active'         => class_exists('WooCommerce'),
    ]);
});

/**
 * Force score update for a product
 * v2.0.10: Added missing handler that JS was calling
 * v2.0.11: Enhanced with cache clearing and RankMath analysis
 * 
 * POST parameters:
 * - post_id: Product ID
 */
add_action('wp_ajax_ai_seo_force_score_update', function() {
    // Verify nonce
    if (!check_ajax_referer('ai_seo_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    
    if (empty($post_id)) {
        wp_send_json_error(['message' => 'Product ID required']);
        return;
    }
    
    ai_seo_log("Force score update for Product $post_id");
    
    // Check if RankMath is active
    if (!function_exists('rank_math')) {
        ai_seo_log("RankMath not active - skipping score update");
        wp_send_json_success(['message' => 'RankMath not active']);
        return;
    }
    
    // METHOD 1: Direct action hook
    try {
        do_action('rank_math/analyzer/update_score', $post_id);
    } catch (Exception $e) {
        ai_seo_log("Method 1 failed: " . $e->getMessage());
    }
    
    // METHOD 2: Save post hook (forces full RankMath processing)
    try {
        do_action('save_post', $post_id, get_post($post_id), true);
    } catch (Exception $e) {
        ai_seo_log("Method 2 failed: " . $e->getMessage());
    }
    
    // METHOD 3: Direct Paper update (RankMath's scoring engine)
    if (class_exists('RankMath\Paper\Paper')) {
        try {
            $paper = \RankMath\Paper\Paper::get();
            if ($paper && method_exists($paper, 'setup_paper')) {
                $paper->setup_paper($post_id);
            }
        } catch (Exception $e) {
            ai_seo_log("Method 3 failed: " . $e->getMessage());
        }
    }
    
    // METHOD 4: Try RankMath's SEO Analysis class
    if (class_exists('RankMath\SEO_Analysis\SEO_Analyzer')) {
        try {
            $analyzer = new \RankMath\SEO_Analysis\SEO_Analyzer();
            if (method_exists($analyzer, 'analyse')) {
                $analyzer->analyse($post_id);
            }
        } catch (Exception $e) {
            ai_seo_log("Method 4 (SEO_Analyzer) failed: " . $e->getMessage());
        }
    }
    
    // METHOD 5: Force wp_update_post to trigger all hooks
    wp_update_post(['ID' => $post_id]);
    usleep(250000); // 0.25 seconds
    wp_update_post(['ID' => $post_id]);
    
    // METHOD 6: Try RankMath's after_save_post hook
    do_action('rank_math/after_save_post', $post_id);
    
    // METHOD 7: Clear all caches to ensure fresh data
    clean_post_cache($post_id);
    wp_cache_delete($post_id, 'post_meta');
    wp_cache_delete($post_id . '_rank_math_seo_score', 'post_meta');
    
    // If RankMath has a cache clear function, use it
    if (function_exists('rank_math_clear_cache')) {
        rank_math_clear_cache();
    }
    
    // METHOD 8: Try to get score directly from RankMath functions
    $score = null;
    
    // METHOD 9: Try our provider's recalculate_score method
    if (function_exists('ai_seo_get_provider')) {
        $provider = ai_seo_get_provider();
        if (method_exists($provider, 'recalculate_score')) {
            $provider->recalculate_score($post_id);
        }
    }
    
    // METHOD 10: Try RankMath's reindex function
    if (function_exists('rank_math_reindex_post')) {
        rank_math_reindex_post($post_id);
        ai_seo_log("Called rank_math_reindex_post");
    }
    
    // Small delay to let reindex complete
    usleep(500000); // 0.5 seconds
    
    // Now try to get score
    // Try RankMath's get_post_meta
    if (function_exists('rank_math_get_post_meta')) {
        $score = rank_math_get_post_meta('rank_math_seo_score', $post_id);
    }
    
    // Fallback to WordPress meta
    if (empty($score)) {
        $score = get_post_meta($post_id, 'rank_math_seo_score', true);
    }
    
    // Try our provider system
    if (empty($score) && function_exists('ai_seo_get_score')) {
        $score = ai_seo_get_score($post_id);
    }
    
    ai_seo_log("Force score update completed for Product $post_id - Score: " . ($score !== null ? $score : 'N/A'));
    
    wp_send_json_success([
        'post_id' => $post_id,
        'score' => $score,
    ]);
});
