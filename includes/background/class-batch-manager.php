<?php
/**
 * AI SEO Content Generator - Batch Manager
 * 
 * Handles creation and management of generation batches
 * 
 * @package AI_SEO_Content_Generator
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Batch_Manager {
    
    /**
     * Job Manager instance
     */
    private $job_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->job_manager = new AI_SEO_Job_Manager();
    }
    
    /**
     * Get the batches table name
     */
    private function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_seo_generation_batches';
    }
    
    /**
     * Create a new generation batch
     * 
     * @param array $product_ids Array of WooCommerce product IDs
     * @param array $prompts Prompts for each field (from UI - core SEO fields only)
     * @param array $settings AI settings (engine, model, api_key, etc.)
     * @return string|WP_Error Batch UUID or error
     */
    public function create_batch($product_ids, $prompts, $settings) {
        global $wpdb;
        
        if (empty($product_ids)) {
            return new WP_Error('no_products', 'No products selected');
        }
        
        // Generate batch UUID
        $batch_id = wp_generate_uuid4();
        $user_id = get_current_user_id();
        
        // Determine which fields to generate
        $tools = get_option('ai_seo_tools', []);
        $ai_search_licensed = function_exists('ai_seo_search_is_licensed') && ai_seo_search_is_licensed();
        $fields = $this->job_manager->get_fields_to_generate($tools, $ai_search_licensed);
        
        // Merge AI Search prompts if licensed
        // The $prompts from JS only contains core SEO fields (focus_keyword, title, etc.)
        // AI Search prompts are stored server-side
        if ($ai_search_licensed && function_exists('ai_seo_search_get_prompts')) {
            $ai_search_prompts = ai_seo_search_get_prompts();
            $prompts = array_merge($prompts, $ai_search_prompts);
            ai_seo_log("Merged " . count($ai_search_prompts) . " AI Search prompts into batch");
        }
        
        // Calculate total jobs
        $total_jobs = count($product_ids) * count($fields);
        
        // Insert batch record
        $table = $this->get_table();
        $result = $wpdb->insert($table, [
            'batch_id'     => $batch_id,
            'user_id'      => $user_id,
            'product_ids'  => wp_json_encode($product_ids),
            'prompts'      => wp_json_encode($prompts),
            'settings'     => wp_json_encode($settings),
            'total_jobs'   => $total_jobs,
            'status'       => 'pending',
            'created_at'   => current_time('mysql'),
        ], ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create batch record');
        }
        
        // Create jobs for each product
        foreach ($product_ids as $product_id) {
            $this->job_manager->create_product_jobs($batch_id, (int)$product_id, $fields);
        }
        
        ai_seo_log("Created batch $batch_id with $total_jobs jobs for " . count($product_ids) . " products");
        
        // Schedule the batch processor
        $this->schedule_processing($batch_id);
        
        return $batch_id;
    }
    
    /**
     * Schedule batch processing via Action Scheduler
     * 
     * @param string $batch_id
     */
    public function schedule_processing($batch_id) {
        // Use Action Scheduler if available (comes with WooCommerce)
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('ai_seo_process_batch', ['batch_id' => $batch_id], 'ai-seo-generator');
            ai_seo_log("Scheduled batch $batch_id for processing via Action Scheduler");
        } else {
            // Fallback to direct processing (not recommended but works)
            ai_seo_log("Action Scheduler not available - falling back to direct processing");
            do_action('ai_seo_process_batch', $batch_id);
        }
    }
    
    /**
     * Get a batch by ID
     * 
     * @param string $batch_id
     * @return object|null
     */
    public function get_batch($batch_id) {
        global $wpdb;
        $table = $this->get_table();
        
        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE batch_id = %s",
            $batch_id
        ));
        
        if ($batch) {
            // Decode JSON fields
            $batch->product_ids = json_decode($batch->product_ids, true);
            $batch->prompts = json_decode($batch->prompts, true);
            $batch->settings = json_decode($batch->settings, true);
        }
        
        return $batch;
    }
    
    /**
     * Get batch status for polling
     * 
     * @param string $batch_id
     * @return array Status information
     */
    public function get_batch_status($batch_id) {
        $batch = $this->get_batch($batch_id);
        
        if (!$batch) {
            return [
                'error' => true,
                'message' => 'Batch not found',
            ];
        }
        
        // Get job statistics
        $job_stats = $this->job_manager->get_batch_stats($batch_id);
        
        // Get current processing job
        $current_job = $this->job_manager->get_current_job($batch_id);
        
        // Calculate progress
        $finished_jobs = $job_stats['completed'] + $job_stats['failed'] + $job_stats['skipped'];
        $progress = $batch->total_jobs > 0 
            ? round(($finished_jobs / $batch->total_jobs) * 100) 
            : 0;
        
        // Determine overall status
        $status = $batch->status;
        ai_seo_log("get_batch_status: batch status from DB = {$status}, pending = {$job_stats['pending']}, processing = {$job_stats['processing']}");
        
        // Check if all jobs are finished
        $all_jobs_done = ($job_stats['pending'] === 0 && $job_stats['processing'] === 0);
        
        if ($all_jobs_done && $status === 'processing') {
            // All jobs finished - update batch status
            $this->finalize_batch($batch_id);
            // Re-fetch batch to get actual finalized status
            $batch = $this->get_batch($batch_id);
            $status = $batch->status;
            ai_seo_log("get_batch_status: after finalize, status = {$status}");
        }
        
        // Fallback: if all jobs done but status still shows processing, force it
        if ($all_jobs_done && $status === 'processing') {
            $status = ($job_stats['failed'] > 0 || $job_stats['skipped'] > 0) 
                ? 'completed_with_errors' 
                : 'completed';
            ai_seo_log("get_batch_status: FORCED status to {$status}");
        }
        
        return [
            'batch_id'        => $batch_id,
            'status'          => $status,
            'progress'        => $progress,
            'total_jobs'      => $batch->total_jobs,
            'completed_jobs'  => $job_stats['completed'],
            'failed_jobs'     => $job_stats['failed'],
            'skipped_jobs'    => $job_stats['skipped'],
            'pending_jobs'    => $job_stats['pending'],
            'processing_jobs' => $job_stats['processing'],
            'current_product' => $current_job ? $current_job->product_id : null,
            'current_field'   => $current_job ? $current_job->field_name : null,
            'created_at'      => $batch->created_at,
            'products'        => $batch->product_ids,
            'error_message'   => $batch->error_message,
        ];
    }
    
    /**
     * Get detailed results for a completed batch
     * 
     * @param string $batch_id
     * @return array Results by product
     */
    public function get_batch_results($batch_id) {
        $batch = $this->get_batch($batch_id);
        
        if (!$batch) {
            return [];
        }
        
        $results = [];
        
        foreach ($batch->product_ids as $product_id) {
            $product_jobs = $this->job_manager->get_product_jobs($batch_id, $product_id);
            
            $product_result = [
                'product_id' => $product_id,
                'status'     => 'completed',
                'fields'     => [],
                'errors'     => [],
            ];
            
            foreach ($product_jobs as $job) {
                if ($job->status === 'completed') {
                    $result = json_decode($job->result, true);
                    $product_result['fields'][$job->field_name] = ($result !== null) ? $result : $job->result;
                } elseif ($job->status === 'failed') {
                    $product_result['errors'][$job->field_name] = $job->error_message;
                    $product_result['status'] = 'partial';
                } elseif ($job->status === 'skipped') {
                    $product_result['errors'][$job->field_name] = 'Skipped: ' . $job->error_message;
                    $product_result['status'] = 'partial';
                }
            }
            
            // Check if all fields failed
            if (empty($product_result['fields'])) {
                $product_result['status'] = 'failed';
            }
            
            $results[$product_id] = $product_result;
        }
        
        return $results;
    }
    
    /**
     * Mark batch as processing
     * 
     * @param string $batch_id
     */
    public function start_batch($batch_id) {
        global $wpdb;
        $table = $this->get_table();
        
        $wpdb->update(
            $table,
            [
                'status'     => 'processing',
                'started_at' => current_time('mysql'),
            ],
            ['batch_id' => $batch_id],
            ['%s', '%s'],
            ['%s']
        );
        
        ai_seo_log("Batch $batch_id started processing");
    }
    
    /**
     * Finalize a completed batch
     * 
     * @param string $batch_id
     */
    public function finalize_batch($batch_id) {
        global $wpdb;
        $table = $this->get_table();
        
        $job_stats = $this->job_manager->get_batch_stats($batch_id);
        
        // Determine final status
        $status = 'completed';
        if ($job_stats['failed'] > 0 || $job_stats['skipped'] > 0) {
            $status = 'completed_with_errors';
        }
        
        $wpdb->update(
            $table,
            [
                'status'         => $status,
                'completed_jobs' => $job_stats['completed'],
                'failed_jobs'    => $job_stats['failed'] + $job_stats['skipped'],
                'completed_at'   => current_time('mysql'),
            ],
            ['batch_id' => $batch_id],
            ['%s', '%d', '%d', '%s'],
            ['%s']
        );
        
        ai_seo_log("Batch $batch_id finalized with status: $status");
        
        // Trigger completion hook for any post-processing
        do_action('ai_seo_batch_completed', $batch_id, $status);
    }
    
    /**
     * Cancel a batch
     * 
     * @param string $batch_id
     * @return bool
     */
    public function cancel_batch($batch_id) {
        global $wpdb;
        
        // Update batch status
        $table = $this->get_table();
        $wpdb->update(
            $table,
            [
                'status'       => 'cancelled',
                'completed_at' => current_time('mysql'),
            ],
            ['batch_id' => $batch_id],
            ['%s', '%s'],
            ['%s']
        );
        
        // Mark pending jobs as cancelled
        $jobs_table = $wpdb->prefix . 'ai_seo_generation_jobs';
        $wpdb->update(
            $jobs_table,
            [
                'status'        => 'skipped',
                'error_message' => 'Batch cancelled by user',
                'completed_at'  => current_time('mysql'),
            ],
            [
                'batch_id' => $batch_id,
                'status'   => 'pending',
            ],
            ['%s', '%s', '%s'],
            ['%s', '%s']
        );
        
        // Unschedule any pending actions
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('ai_seo_process_batch', ['batch_id' => $batch_id], 'ai-seo-generator');
            as_unschedule_all_actions('ai_seo_process_field', ['batch_id' => $batch_id], 'ai-seo-generator');
        }
        
        ai_seo_log("Batch $batch_id cancelled");
        
        return true;
    }
    
    /**
     * Get batches for current user
     * 
     * @param int $limit
     * @return array
     */
    public function get_user_batches($limit = 10) {
        global $wpdb;
        $table = $this->get_table();
        $user_id = get_current_user_id();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT batch_id, status, total_jobs, completed_jobs, failed_jobs, created_at, completed_at 
             FROM $table 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
    }
    
    /**
     * Get active batch for current user (if any)
     * 
     * @return object|null
     */
    public function get_active_batch() {
        global $wpdb;
        $table = $this->get_table();
        $user_id = get_current_user_id();
        
        // First, auto-cleanup stuck batches older than 30 minutes
        $this->cleanup_stuck_batches($user_id);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d AND status IN ('pending', 'processing')
             ORDER BY created_at DESC 
             LIMIT 1",
            $user_id
        ));
    }
    
    /**
     * Clean up batches stuck in processing for too long
     * 
     * @param int $user_id
     */
    private function cleanup_stuck_batches($user_id) {
        global $wpdb;
        $table = $this->get_table();
        
        // Find batches stuck in 'processing' for more than 30 minutes
        $stuck_batches = $wpdb->get_results($wpdb->prepare(
            "SELECT batch_id FROM $table 
             WHERE user_id = %d 
             AND status = 'processing'
             AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)",
            $user_id
        ));
        
        foreach ($stuck_batches as $batch) {
            ai_seo_log("Auto-cancelling stuck batch: {$batch->batch_id}");
            
            // Check job status to determine final state
            $job_stats = $this->job_manager->get_batch_stats($batch->batch_id);
            
            // Mark any still-pending/processing jobs as skipped
            $jobs_table = $wpdb->prefix . 'ai_seo_generation_jobs';
            $wpdb->query($wpdb->prepare(
                "UPDATE $jobs_table 
                 SET status = 'skipped', 
                     error_message = 'Batch timed out after 30 minutes',
                     completed_at = %s
                 WHERE batch_id = %s AND status IN ('pending', 'processing')",
                current_time('mysql'),
                $batch->batch_id
            ));
            
            // Finalize the batch
            $final_status = ($job_stats['failed'] > 0 || $job_stats['skipped'] > 0) 
                ? 'completed_with_errors' 
                : 'completed';
            
            $wpdb->update(
                $table,
                [
                    'status' => $final_status,
                    'error_message' => 'Batch auto-completed due to timeout',
                    'completed_at' => current_time('mysql'),
                ],
                ['batch_id' => $batch->batch_id],
                ['%s', '%s', '%s'],
                ['%s']
            );
        }
    }
    
    /**
     * Check if Action Scheduler is available
     * 
     * @return bool
     */
    public static function is_action_scheduler_available() {
        return function_exists('as_enqueue_async_action');
    }
    
    /**
     * Update batch job counts
     * 
     * @param string $batch_id
     */
    public function update_job_counts($batch_id) {
        global $wpdb;
        $table = $this->get_table();
        
        $job_stats = $this->job_manager->get_batch_stats($batch_id);
        
        $wpdb->update(
            $table,
            [
                'completed_jobs' => $job_stats['completed'],
                'failed_jobs'    => $job_stats['failed'] + $job_stats['skipped'],
            ],
            ['batch_id' => $batch_id],
            ['%d', '%d'],
            ['%s']
        );
    }
}
