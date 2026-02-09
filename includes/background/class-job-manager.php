<?php
/**
 * AI SEO Content Generator - Job Manager
 * 
 * Handles CRUD operations for individual generation jobs
 * 
 * @package AI_SEO_Content_Generator
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Job_Manager {
    
    /**
     * Field generation order with dependencies
     * 
     * Format: field_name => [order, dependencies]
     */
    const FIELD_ORDER = [
        // Core SEO fields (always available)
        'focus_keyword'     => ['order' => 1,  'dependencies' => [], 'critical' => true],
        'title'             => ['order' => 2,  'dependencies' => ['focus_keyword'], 'critical' => true],
        'short_description' => ['order' => 3,  'dependencies' => ['focus_keyword', 'title'], 'critical' => true],
        'full_description'  => ['order' => 4,  'dependencies' => ['focus_keyword', 'title'], 'critical' => true],
        'meta_description'  => ['order' => 5,  'dependencies' => ['focus_keyword'], 'critical' => false],
        'tags'              => ['order' => 6,  'dependencies' => ['focus_keyword'], 'critical' => false],
        'image_alt'         => ['order' => 7,  'dependencies' => ['focus_keyword', 'title'], 'critical' => false], // v2.0.4: Image metadata update
        
        // AI Search fields (require license) - v2.1.0: Only 6 proven fields
        'product_summary'   => ['order' => 10, 'dependencies' => ['focus_keyword', 'title'], 'critical' => false],
        'faq_schema'        => ['order' => 11, 'dependencies' => ['focus_keyword', 'title'], 'critical' => false],
        'care_instructions' => ['order' => 12, 'dependencies' => ['focus_keyword', 'title'], 'critical' => false],
        'product_highlights'=> ['order' => 13, 'dependencies' => ['focus_keyword', 'title'], 'critical' => false],
        'pros_cons'         => ['order' => 14, 'dependencies' => ['focus_keyword', 'title'], 'critical' => false],
        'alt_names'         => ['order' => 15, 'dependencies' => ['focus_keyword'], 'critical' => false],
    ];
    
    /**
     * Maximum retry attempts for failed jobs
     */
    const MAX_RETRIES = 2;
    
    /**
     * Get the jobs table name
     */
    private function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_seo_generation_jobs';
    }
    
    /**
     * Create jobs for a single product
     * 
     * @param string $batch_id Batch UUID
     * @param int $product_id WooCommerce product ID
     * @param array $fields Fields to generate (from settings)
     * @return int Number of jobs created
     */
    public function create_product_jobs($batch_id, $product_id, $fields) {
        global $wpdb;
        $table = $this->get_table();
        
        $jobs_created = 0;
        ai_seo_log("Creating jobs for product $product_id with fields: " . implode(', ', $fields));
        
        foreach ($fields as $field_name) {
            if (!isset(self::FIELD_ORDER[$field_name])) {
                ai_seo_log("Unknown field '$field_name' - skipping");
                continue;
            }
            
            $field_config = self::FIELD_ORDER[$field_name];
            
            $wpdb->insert($table, [
                'batch_id'    => $batch_id,
                'product_id'  => $product_id,
                'field_name'  => $field_name,
                'field_order' => $field_config['order'],
                'status'      => 'pending',
                'created_at'  => current_time('mysql'),
            ], ['%s', '%d', '%s', '%d', '%s', '%s']);
            
            ai_seo_log("Created job for field '$field_name' (order: {$field_config['order']})");
            $jobs_created++;
        }
        
        return $jobs_created;
    }
    
    /**
     * Get a specific job by ID
     * 
     * @param int $job_id
     * @return object|null
     */
    public function get_job($job_id) {
        global $wpdb;
        $table = $this->get_table();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $job_id
        ));
    }
    
    /**
     * Get the next pending job for a batch
     * 
     * Respects field order and dependencies
     * 
     * @param string $batch_id
     * @return object|null
     */
    public function get_next_job($batch_id) {
        global $wpdb;
        $table = $this->get_table();
        
        // Get all pending jobs ordered by field_order
        $pending_jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE batch_id = %s AND status = 'pending'
             ORDER BY product_id ASC, field_order ASC
             LIMIT 50",
            $batch_id
        ));
        
        if (empty($pending_jobs)) {
            return null;
        }
        
        // Find the first job whose dependencies are satisfied
        foreach ($pending_jobs as $job) {
            if ($this->are_dependencies_satisfied($job)) {
                return $job;
            }
        }
        
        // No job with satisfied dependencies found
        // This could mean there's a circular dependency or all dependencies failed
        ai_seo_log("No jobs with satisfied dependencies found for batch $batch_id");
        return null;
    }
    
    /**
     * Check if all dependencies for a job are complete
     * 
     * @param object $job
     * @return bool
     */
    private function are_dependencies_satisfied($job) {
        if (!isset(self::FIELD_ORDER[$job->field_name])) {
            return true; // Unknown field, no dependencies
        }
        
        $dependencies = self::FIELD_ORDER[$job->field_name]['dependencies'];
        
        if (empty($dependencies)) {
            return true;
        }
        
        global $wpdb;
        $table = $this->get_table();
        
        foreach ($dependencies as $dep_field) {
            $dep_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM $table 
                 WHERE batch_id = %s AND product_id = %d AND field_name = %s",
                $job->batch_id,
                $job->product_id,
                $dep_field
            ));
            
            // Dependency must be completed (not pending, processing, or failed)
            if ($dep_status !== 'completed') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Mark a job as processing
     * 
     * @param int $job_id
     * @return bool
     */
    public function start_job($job_id) {
        global $wpdb;
        $table = $this->get_table();
        
        return $wpdb->update(
            $table,
            [
                'status' => 'processing',
                'started_at' => current_time('mysql'),
            ],
            ['id' => $job_id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }
    
    /**
     * Mark a job as completed with result
     * 
     * @param int $job_id
     * @param mixed $result Generated content
     * @return bool
     */
    public function complete_job($job_id, $result) {
        global $wpdb;
        $table = $this->get_table();
        
        // Encode result as JSON if it's an array
        $result_value = is_array($result) ? wp_json_encode($result) : $result;
        
        return $wpdb->update(
            $table,
            [
                'status' => 'completed',
                'result' => $result_value,
                'completed_at' => current_time('mysql'),
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s'],
            ['%d']
        ) !== false;
    }
    
    /**
     * Mark a job as failed
     * 
     * @param int $job_id
     * @param string $error Error message
     * @param bool $can_retry Whether this error is retryable
     * @return bool
     */
    public function fail_job($job_id, $error, $can_retry = true) {
        global $wpdb;
        $table = $this->get_table();
        
        $job = $this->get_job($job_id);
        if (!$job) {
            return false;
        }
        
        $new_retry_count = $job->retry_count + 1;
        
        // Check if we should retry or mark as permanently failed
        if ($can_retry && $new_retry_count <= self::MAX_RETRIES) {
            // Reset to pending for retry
            return $wpdb->update(
                $table,
                [
                    'status' => 'pending',
                    'error_message' => $error,
                    'retry_count' => $new_retry_count,
                    'started_at' => null,
                ],
                ['id' => $job_id],
                ['%s', '%s', '%d', null],
                ['%d']
            ) !== false;
        } else {
            // Mark as permanently failed
            return $wpdb->update(
                $table,
                [
                    'status' => 'failed',
                    'error_message' => $error,
                    'retry_count' => $new_retry_count,
                    'completed_at' => current_time('mysql'),
                ],
                ['id' => $job_id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            ) !== false;
        }
    }
    
    /**
     * Skip remaining jobs for a product (after critical failure)
     * 
     * @param string $batch_id
     * @param int $product_id
     * @param string $reason
     * @return int Number of jobs skipped
     */
    public function skip_product_jobs($batch_id, $product_id, $reason) {
        global $wpdb;
        $table = $this->get_table();
        
        return $wpdb->update(
            $table,
            [
                'status' => 'skipped',
                'error_message' => $reason,
                'completed_at' => current_time('mysql'),
            ],
            [
                'batch_id' => $batch_id,
                'product_id' => $product_id,
                'status' => 'pending',
            ],
            ['%s', '%s', '%s'],
            ['%s', '%d', '%s']
        );
    }
    
    /**
     * Get completed results for a product (for dependency resolution)
     * 
     * @param string $batch_id
     * @param int $product_id
     * @return array Field name => result
     */
    public function get_completed_results($batch_id, $product_id) {
        global $wpdb;
        $table = $this->get_table();
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT field_name, result FROM $table 
             WHERE batch_id = %s AND product_id = %d AND status = 'completed'",
            $batch_id,
            $product_id
        ));
        
        $output = [];
        foreach ($results as $row) {
            // Try to decode JSON, fall back to raw value
            $decoded = json_decode($row->result, true);
            $output[$row->field_name] = ($decoded !== null) ? $decoded : $row->result;
        }
        
        return $output;
    }
    
    /**
     * Get job statistics for a batch
     * 
     * @param string $batch_id
     * @return array
     */
    public function get_batch_stats($batch_id) {
        global $wpdb;
        $table = $this->get_table();
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM $table 
             WHERE batch_id = %s 
             GROUP BY status",
            $batch_id
        ), OBJECT_K);
        
        return [
            'total'      => array_sum(array_column((array)$stats, 'count')),
            'pending'    => isset($stats['pending']) ? $stats['pending']->count : 0,
            'processing' => isset($stats['processing']) ? $stats['processing']->count : 0,
            'completed'  => isset($stats['completed']) ? $stats['completed']->count : 0,
            'failed'     => isset($stats['failed']) ? $stats['failed']->count : 0,
            'skipped'    => isset($stats['skipped']) ? $stats['skipped']->count : 0,
        ];
    }
    
    /**
     * Get the currently processing job for a batch
     * 
     * @param string $batch_id
     * @return object|null
     */
    public function get_current_job($batch_id) {
        global $wpdb;
        $table = $this->get_table();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE batch_id = %s AND status = 'processing'
             ORDER BY started_at DESC
             LIMIT 1",
            $batch_id
        ));
    }
    
    /**
     * Check if a field is critical (failure should stop product processing)
     * 
     * @param string $field_name
     * @return bool
     */
    public function is_critical_field($field_name) {
        return isset(self::FIELD_ORDER[$field_name]) 
            && self::FIELD_ORDER[$field_name]['critical'];
    }
    
    /**
     * Get all jobs for a product
     * 
     * @param string $batch_id
     * @param int $product_id
     * @return array
     */
    public function get_product_jobs($batch_id, $product_id) {
        global $wpdb;
        $table = $this->get_table();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE batch_id = %s AND product_id = %d
             ORDER BY field_order ASC",
            $batch_id,
            $product_id
        ));
    }
    
    /**
     * Get all jobs for a batch
     * 
     * @param string $batch_id Batch UUID
     * @return array Job objects
     */
    public function get_jobs_for_batch($batch_id) {
        global $wpdb;
        $table = $this->get_table();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE batch_id = %s
             ORDER BY product_id, field_order ASC",
            $batch_id
        ));
    }
    
    /**
     * Get list of fields to generate based on settings
     * 
     * @param array $tools Tool settings
     * @param bool $ai_search_licensed Whether AI Search is licensed
     * @return array Field names
     */
    public function get_fields_to_generate($tools, $ai_search_licensed = false) {
        $fields = [];
        
        // v2.0.15: Check Generation Mode setting
        $generation_mode = isset($tools['generation_mode']) ? $tools['generation_mode'] : 'both';
        ai_seo_log("DEBUG get_fields_to_generate: generation_mode = " . $generation_mode);
        
        // SEO fields (if mode is 'both' or 'seo_only')
        if ($generation_mode === 'both' || $generation_mode === 'seo_only') {
            // Core SEO fields
            $fields[] = 'focus_keyword';
            
            ai_seo_log("DEBUG get_fields_to_generate: generate_title_from_keywords = " . (!empty($tools['generate_title_from_keywords']) ? 'true' : 'false'));
            
            if (!empty($tools['generate_title_from_keywords'])) {
                $fields[] = 'title';
            }
            
            // Short and full description
            $fields[] = 'short_description';
            $fields[] = 'full_description';
            
            if (!empty($tools['generate_meta_description'])) {
                $fields[] = 'meta_description';
            }
            
            // Tags (only if enabled)
            if (!empty($tools['generate_tags'])) {
                $fields[] = 'tags';
            }
            
            // Image alt tags (if enabled)
            if (!empty($tools['update_image_alt_tags'])) {
                $fields[] = 'image_alt';
            }
        }
        
        ai_seo_log("DEBUG get_fields_to_generate: SEO fields = " . implode(', ', $fields));
        
        // AI Search fields (if mode is 'both' or 'ai_search_only', AND licensed and enabled)
        if (($generation_mode === 'both' || $generation_mode === 'ai_search_only') && $ai_search_licensed) {
            $search_tools = get_option('ai_seo_search_tools', []);
            
            // v2.1.0: Only 6 proven fields for AI search
            $ai_search_map = [
                'generate_product_summary'   => 'product_summary',
                'generate_faq_schema'        => 'faq_schema',
                'generate_care_instructions' => 'care_instructions',
                'generate_product_highlights'=> 'product_highlights',
                'generate_pros_cons'         => 'pros_cons',
                'generate_alt_names'         => 'alt_names',
            ];
            
            foreach ($ai_search_map as $tool_key => $field_name) {
                if (!empty($search_tools[$tool_key])) {
                    $fields[] = $field_name;
                }
            }
        }
        
        ai_seo_log("DEBUG get_fields_to_generate: final fields = " . implode(', ', $fields));
        
        return $fields;
    }
    
    /**
     * Check if all jobs for a product are complete (completed, failed, or skipped)
     * v2.0.12: Used to trigger score calculation after all fields are done
     * 
     * @param string $batch_id
     * @param int $product_id
     * @return bool
     */
    public function is_product_complete($batch_id, $product_id) {
        global $wpdb;
        $table = $this->get_table();
        
        // Count jobs that are NOT in a finished state
        $pending_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE batch_id = %s 
             AND product_id = %d 
             AND status NOT IN ('completed', 'failed', 'skipped')",
            $batch_id,
            $product_id
        ));
        
        return intval($pending_count) === 0;
    }
}
