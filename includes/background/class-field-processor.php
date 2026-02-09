<?php
/**
 * AI SEO Content Generator - Field Processor
 * 
 * Handles generation of individual content fields
 * This is the workhorse called by Action Scheduler
 * 
 * @package AI_SEO_Content_Generator
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Field_Processor {
    
    /**
     * Job Manager instance
     */
    private $job_manager;
    
    /**
     * Batch Manager instance
     */
    private $batch_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->job_manager = new AI_SEO_Job_Manager();
        $this->batch_manager = new AI_SEO_Batch_Manager();
    }
    
    /**
     * Process the next job in a batch
     * 
     * Called by Action Scheduler hook 'ai_seo_process_batch'
     * 
     * @param string $batch_id
     */
    public function process_batch($batch_id) {
        $batch = $this->batch_manager->get_batch($batch_id);
        
        if (!$batch) {
            ai_seo_log("Batch $batch_id not found - aborting");
            return;
        }
        
        // Check if batch was cancelled
        if ($batch->status === 'cancelled') {
            ai_seo_log("Batch $batch_id was cancelled - skipping");
            return;
        }
        
        // Mark batch as processing if not already
        if ($batch->status === 'pending') {
            $this->batch_manager->start_batch($batch_id);
        }
        
        // Get next job
        $job = $this->job_manager->get_next_job($batch_id);
        
        if (!$job) {
            // No more jobs - finalize batch
            ai_seo_log("No more jobs for batch $batch_id - finalizing");
            $this->batch_manager->finalize_batch($batch_id);
            return;
        }
        
        // Process this job
        $this->process_job($job, $batch);
        
        // Schedule next job (with configurable delay from Tools settings)
        $tools = get_option('ai_seo_tools', []);
        $delay = isset($tools['post_save_delay']) ? intval($tools['post_save_delay']) : 2;
        $delay = max(1, $delay); // Minimum 1 second between jobs
        
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(
                time() + $delay,
                'ai_seo_process_batch',
                ['batch_id' => $batch_id],
                'ai-seo-generator'
            );
        } else {
            // Direct fallback (not recommended)
            sleep($delay);
            $this->process_batch($batch_id);
        }
    }
    
    /**
     * Process a single job
     * 
     * @param object $job Job record
     * @param object $batch Batch record
     */
    private function process_job($job, $batch) {
        ai_seo_log("Processing job {$job->id}: {$job->field_name} for product {$job->product_id}");
        
        // Store batch_id for use in save_to_product
        $this->current_batch_id = $job->batch_id;
        
        // Mark job as processing
        $this->job_manager->start_job($job->id);
        
        try {
            // Get product
            $product = wc_get_product($job->product_id);
            if (!$product) {
                throw new Exception("Product {$job->product_id} not found");
            }
            
            // v2.0.4: Special handling for image_alt (not an AI prompt)
            if ($job->field_name === 'image_alt') {
                $result = $this->process_image_alt($job, $product);
                $this->job_manager->complete_job($job->id, $result);
                $this->batch_manager->update_job_counts($job->batch_id);
                ai_seo_log("Completed job {$job->id}: image_alt for product {$job->product_id}");
                
                // v2.0.12: Check if all jobs for this product are now complete
                if ($this->job_manager->is_product_complete($job->batch_id, $job->product_id)) {
                    ai_seo_log("All jobs complete for product {$job->product_id} - triggering score calculation");
                    $this->trigger_score_calculation($job->product_id);
                }
                return;
            }
            
            // Build replacements (product data for prompts)
            $replacements = $this->build_replacements($product, $job->batch_id, $job->product_id);
            
            // Get the prompt for this field
            $prompts = $batch->prompts;
            
            // Debug logging for title
            if ($job->field_name === 'title') {
                error_log("AI SEO TITLE DEBUG: prompts type = " . gettype($prompts));
                error_log("AI SEO TITLE DEBUG: prompts keys = " . (is_array($prompts) ? implode(', ', array_keys($prompts)) : 'N/A'));
                error_log("AI SEO TITLE DEBUG: title prompt exists = " . (isset($prompts['title']) ? 'yes' : 'no'));
                error_log("AI SEO TITLE DEBUG: title prompt empty = " . (empty($prompts['title']) ? 'yes' : 'no'));
                if (isset($prompts['title'])) {
                    error_log("AI SEO TITLE DEBUG: title prompt = " . substr($prompts['title'], 0, 100));
                }
                ai_seo_log("TITLE DEBUG: prompts type = " . gettype($prompts));
                ai_seo_log("TITLE DEBUG: prompts keys = " . (is_array($prompts) ? implode(', ', array_keys($prompts)) : 'N/A'));
                ai_seo_log("TITLE DEBUG: title prompt exists = " . (isset($prompts['title']) ? 'yes' : 'no'));
                ai_seo_log("TITLE DEBUG: title prompt empty = " . (empty($prompts['title']) ? 'yes' : 'no'));
                if (isset($prompts['title'])) {
                    ai_seo_log("TITLE DEBUG: title prompt = " . substr($prompts['title'], 0, 100));
                }
            }
            
            if (!isset($prompts[$job->field_name]) || empty($prompts[$job->field_name])) {
                throw new Exception("No prompt configured for field {$job->field_name}");
            }
            
            // Process the prompt with replacements
            $prompt = ai_seo_process_prompt($prompts[$job->field_name], $replacements);
            
            // Add field-specific modifiers (like power words for title)
            $prompt = $this->add_field_modifiers($job->field_name, $prompt, $batch->settings);
            
            // Call AI API
            $settings = $batch->settings;
            $content = ai_seo_call_ai_engine(
                $settings['ai_engine'] ?? 'chatgpt',
                $settings['api_key'] ?? '',
                $settings['model'] ?? 'gpt-4o',
                $prompt,
                $settings['max_tokens'] ?? 2048,
                $settings['temperature'] ?? 0.7,
                $settings['frequency_penalty'] ?? 0,
                $settings['presence_penalty'] ?? 0,
                $settings['top_p'] ?? 1
            );
            
            if (is_wp_error($content)) {
                throw new Exception($content->get_error_message());
            }
            
            // Sanitize the generated content
            $sanitized = $this->sanitize_content($job->field_name, $content);
            
            // Save to product immediately
            $this->save_to_product($job->product_id, $job->field_name, $sanitized);
            
            // Mark job complete
            $this->job_manager->complete_job($job->id, $sanitized);
            
            // Update batch counts
            $this->batch_manager->update_job_counts($job->batch_id);
            
            ai_seo_log("Completed job {$job->id}: {$job->field_name} for product {$job->product_id}");
            
            // v2.0.12: Check if all jobs for this product are now complete
            // If so, trigger RankMath score calculation (like legacy did)
            if ($this->job_manager->is_product_complete($job->batch_id, $job->product_id)) {
                ai_seo_log("All jobs complete for product {$job->product_id} - triggering score calculation");
                $this->trigger_score_calculation($job->product_id);
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            ai_seo_log("Job {$job->id} failed: $error_message");
            
            // Check if this is a retryable error
            $is_retryable = $this->is_retryable_error($error_message);
            
            // Mark job as failed (may be retried)
            $this->job_manager->fail_job($job->id, $error_message, $is_retryable);
            
            // Handle critical field failures
            if ($this->job_manager->is_critical_field($job->field_name)) {
                $this->handle_critical_failure($job, $error_message);
            }
            
            // Update batch counts
            $this->batch_manager->update_job_counts($job->batch_id);
        }
    }
    
    /**
     * Process image alt tags update (not an AI prompt)
     * v2.0.4: Moved from legacy ajax.php to background processor
     * 
     * @param object $job Job record
     * @param WC_Product $product
     * @return string Summary of updates
     */
    private function process_image_alt($job, $product) {
        $product_id = $job->product_id;
        
        // Get completed focus_keyword and title from earlier jobs
        $completed = $this->job_manager->get_completed_results($job->batch_id, $product_id);
        
        $focus_keyword = $completed['focus_keyword'] ?? '';
        $generated_title = $completed['title'] ?? $product->get_title();
        
        if (empty($focus_keyword)) {
            ai_seo_log("image_alt: No focus keyword available for Product $product_id");
            return 'No focus keyword available';
        }
        
        $generated_title_short = substr($generated_title, 0, 50);
        
        // Get ALL product images
        $image_ids = [];
        
        // Get featured image
        $featured_id = get_post_thumbnail_id($product_id);
        if ($featured_id) {
            $image_ids[] = $featured_id;
        }
        
        // Get gallery images
        $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
        if (!empty($gallery_ids)) {
            $gallery_array = explode(',', $gallery_ids);
            $image_ids = array_merge($image_ids, array_map('intval', $gallery_array));
        }
        
        // Remove duplicates
        $image_ids = array_unique(array_filter($image_ids));
        
        ai_seo_log("image_alt: Found " . count($image_ids) . " images for Product $product_id");
        
        if (empty($image_ids)) {
            return 'No images found';
        }
        
        // Smart alt text variations for AI shopping
        $view_types = ['Close-Up', 'Side View', 'Detail Shot', 'Worn', 'Angled View', 'Lifestyle', 'Top View', 'Back View'];
        
        $count = 0;
        $updated = 0;
        
        foreach ($image_ids as $attachment_id) {
            // Generate UNIQUE alt text for each image
            if ($count === 0) {
                // First image: Clean focus keyword only
                $alt_text = $focus_keyword;
            } else {
                // Subsequent images: Add view context
                $view_index = ($count - 1) % count($view_types);
                $alt_text = $focus_keyword . ' ' . $view_types[$view_index];
            }
            
            $title = $focus_keyword;
            $caption = 'Premium ' . $focus_keyword . ' - ' . $generated_title_short;
            $description = $focus_keyword . ' product image showing detailed view';
            
            // Update alt text
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            
            // Update image post data
            $update_result = wp_update_post([
                'ID' => $attachment_id,
                'post_title' => sanitize_text_field($title),
                'post_excerpt' => sanitize_textarea_field($caption),
                'post_content' => sanitize_textarea_field($description)
            ]);
            
            if (!is_wp_error($update_result)) {
                $updated++;
            }
            
            $count++;
        }
        
        ai_seo_log("image_alt: Updated $updated of $count images for Product $product_id");
        
        return "Updated $updated images";
    }
    
    /**
     * Build replacement array for prompts
     * 
     * @param WC_Product $product
     * @param string $batch_id
     * @param int $product_id
     * @return array
     */
    private function build_replacements($product, $batch_id, $product_id) {
        $post = get_post($product_id);
        
        // Base product data
        $replacements = [
            '[product_title]'            => $post->post_title,
            '[current_short_description]'=> $post->post_excerpt ?: '',
            '[current_full_description]' => $post->post_content ?: '',
            '[current_categories]'       => implode(', ', wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names'])),
            '[current_price]'            => $product->get_price() ?: '',
            '[current_sku]'              => $product->get_sku() ?: '',
            '[current_tags]'             => implode(', ', wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names'])),
        ];
        
        // Product attributes
        $attributes = [];
        $attributes_data = [];
        foreach ($product->get_attributes() as $attr_key => $attribute) {
            $attribute_data = $attribute->get_data();
            $name = wc_attribute_label($attribute_data['name']);
            $slug = sanitize_title($attribute_data['name']);
            
            if ($attribute->is_taxonomy()) {
                $term_ids = $attribute->get_options();
                $term_names = [];
                foreach ($term_ids as $term_id) {
                    $term = get_term($term_id);
                    if ($term && !is_wp_error($term)) {
                        $term_names[] = $term->name;
                    }
                }
                $values = implode(', ', $term_names);
            } else {
                $values = implode(', ', $attribute->get_options());
            }
            
            if (!empty($values)) {
                $attributes[] = "$name: $values";
                $attributes_data[$slug] = $values;
                $replacements["[current_$slug]"] = $values;
            }
        }
        $replacements['[current_attributes]'] = implode('; ', $attributes);
        
        // Add previously generated fields from this batch
        $completed = $this->job_manager->get_completed_results($batch_id, $product_id);
        
        if (isset($completed['focus_keyword'])) {
            $replacements['[focus_keyword]'] = $completed['focus_keyword'];
        }
        
        // If title was generated, use it instead of original
        if (isset($completed['title'])) {
            $replacements['[product_title]'] = $completed['title'];
        }
        
        // v2.1.20: Description length setting
        $tools = get_option('ai_seo_tools', []);
        $desc_length = isset($tools['description_length']) ? $tools['description_length'] : 'standard';
        $length_map = [
            'standard' => '300-400 words',
            'long'     => '800-1000 words',
            'premium'  => '1500-2000 words',
        ];
        $replacements['[description_length]'] = $length_map[$desc_length] ?? '300-400 words';
        
        return $replacements;
    }
    
    /**
     * Add field-specific modifiers to prompt
     * 
     * @param string $field_name
     * @param string $prompt
     * @param array $settings
     * @return string
     */
    private function add_field_modifiers($field_name, $prompt, $settings) {
        if ($field_name !== 'title') {
            return $prompt;
        }
        
        $tools = get_option('ai_seo_tools', []);
        
        if (!empty($tools['include_original_title'])) {
            // Original title already in replacements
        }
        
        if (!empty($tools['use_sentiment_in_title'])) {
            $prompt .= " Use a positive emotional sentiment word that evokes excitement (e.g., amazing, fantastic, incredible, wonderful, extraordinary, remarkable, stunning, brilliant).";
        }
        
        // Power words are controlled in the title prompt - no extra append needed
        
        if (!empty($tools['include_number_in_title'])) {
            $prompt .= " Include a specific number or statistic (e.g., '5 Ways', '10 Best', '#1 Rated', '2024 Edition', '99% Satisfaction', '3-Pack').";
        }
        
        return $prompt;
    }
    
    /**
     * Sanitize generated content based on field type
     * 
     * @param string $field_name
     * @param string $content
     * @return mixed
     */
    private function sanitize_content($field_name, $content) {
        $content = trim($content);
        
        switch ($field_name) {
            case 'focus_keyword':
                // Clean up quotes and formatting artifacts
                $content = trim($content, '"\'');
                if (function_exists('ai_seo_sanitize_focus_keyword')) {
                    $content = ai_seo_sanitize_focus_keyword($content);
                }
                // Fallback if AI generated prose
                if (empty($content) || strlen($content) > 100 || str_word_count($content) > 12) {
                    ai_seo_log("WARNING: Invalid focus keyword generated, content may need review");
                }
                return sanitize_text_field($content);
                
            case 'title':
                if (function_exists('ai_seo_remove_ai_chattiness')) {
                    $content = ai_seo_remove_ai_chattiness($content);
                }
                return sanitize_text_field($content);
                
            case 'short_description':
            case 'full_description':
                // Strip markdown code fences
                $content = preg_replace('/^```html\s*/i', '', $content);
                $content = preg_replace('/^```\s*/m', '', $content);
                $content = preg_replace('/\s*```$/s', '', $content);
                return wp_kses_post(trim($content));
                
            case 'meta_description':
            case 'tags':
                return sanitize_textarea_field($content);
                
            // AI Search fields
            case 'faq_schema':
                return $this->parse_faq($content);
                
            case 'care_instructions':
                return $this->parse_numbered_list($content);
                
            case 'product_highlights':
                return $this->parse_bullet_list($content);
                
            case 'pros_cons':
                return $this->parse_pros_cons($content);
                
            case 'alt_names':
                return $this->parse_comma_list($content);
                
            case 'product_summary':
            default:
                // Plain text cleanup for other AI Search fields
                $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
                $content = preg_replace('/\*(.+?)\*/', '$1', $content);
                $content = preg_replace('/^#+\s*/m', '', $content);
                return trim($content);
        }
    }
    
    /**
     * Save generated content to product
     * 
     * @param int $product_id
     * @param string $field_name
     * @param mixed $content
     */
    private function save_to_product($product_id, $field_name, $content) {
        switch ($field_name) {
            case 'focus_keyword':
                // Save via SEO provider
                if (function_exists('ai_seo_update_seo_fields')) {
                    ai_seo_update_seo_fields($product_id, ['focus_keyword' => $content]);
                }
                break;
                
            case 'title':
                error_log("AI SEO TITLE SAVE: Product $product_id - Content length: " . strlen($content));
                error_log("AI SEO TITLE SAVE: Content: " . substr($content, 0, 100));
                ai_seo_log("TITLE SAVE: Product $product_id - Content length: " . strlen($content));
                ai_seo_log("TITLE SAVE: Content: " . substr($content, 0, 100));
                
                // v2.0.9: Check for duplicate titles and make unique if needed (if enabled)
                $tools = get_option('ai_seo_tools', []);
                if (!empty($tools['prevent_duplicate_titles']) && function_exists('ai_seo_ensure_unique_title')) {
                    $title_check = ai_seo_ensure_unique_title($content, $product_id);
                    $content = $title_check['title'];
                    
                    if ($title_check['was_duplicate']) {
                        ai_seo_log("Title was duplicate, modified: '{$title_check['original']}' → '{$title_check['title']}'");
                    }
                }
                
                $result = wp_update_post(['ID' => $product_id, 'post_title' => $content]);
                
                if (is_wp_error($result)) {
                    error_log("AI SEO TITLE SAVE ERROR: " . $result->get_error_message());
                    ai_seo_log("TITLE SAVE ERROR: " . $result->get_error_message());
                } else {
                    error_log("AI SEO TITLE SAVE: wp_update_post returned: " . $result);
                    ai_seo_log("TITLE SAVE: wp_update_post returned: " . $result);
                    // Verify it actually saved
                    $check = get_post($product_id);
                    error_log("AI SEO TITLE VERIFY: Post title is now: " . $check->post_title);
                    ai_seo_log("TITLE VERIFY: Post title is now: " . $check->post_title);
                }
                
                // Update title meta tag if enabled
                if (!empty($tools['add_meta_tag_to_head'])) {
                    update_post_meta($product_id, '_ai_seo_title_tag', $content);
                }
                
                // v2.0.9: URL handling (shorten URL and/or focus keyword in URL)
                if (!empty($tools['shorten_url']) || !empty($tools['enforce_focus_keyword_url'])) {
                    // Get focus keyword from completed jobs
                    $focus_keyword = '';
                    if (isset($this->current_batch_id)) {
                        $completed = $this->job_manager->get_completed_results($this->current_batch_id, $product_id);
                        $focus_keyword = $completed['focus_keyword'] ?? '';
                    }
                    
                    // Create slug from title but strip power words for shorter URL
                    $new_slug = sanitize_title($content);
                    
                    // Strip common power word suffixes from slug
                    $power_words_pattern = '/-(stunning|premium|genuine|perfect|exclusive|brilliant|amazing|best)$/i';
                    $new_slug = preg_replace($power_words_pattern, '', $new_slug);
                    
                    // Add focus keyword to URL if enabled
                    if (!empty($tools['enforce_focus_keyword_url']) && !empty($focus_keyword)) {
                        $keyword_slug = sanitize_title($focus_keyword);
                        if (strpos($new_slug, $keyword_slug) === false) {
                            $new_slug = $keyword_slug . '-' . $new_slug;
                        }
                    }
                    
                    // Ensure slug isn't too long (max ~50 chars)
                    if (strlen($new_slug) > 50) {
                        $new_slug = substr($new_slug, 0, 50);
                        $last_dash = strrpos($new_slug, '-');
                        if ($last_dash !== false && $last_dash > 30) {
                            $new_slug = substr($new_slug, 0, $last_dash);
                        }
                    }
                    
                    // Update WordPress core slug
                    wp_update_post(['ID' => $product_id, 'post_name' => $new_slug]);
                    ai_seo_log("Updated WordPress permalink for Product $product_id: $new_slug");
                    
                    // If Permalink Manager Pro exists, override its custom storage too
                    if (class_exists('Permalink_Manager_URI_Functions_Post')) {
                        global $permalink_manager_uris;
                        $permalink_manager_uris[$product_id] = $new_slug;
                        update_option('permalink-manager-uris', $permalink_manager_uris);
                        ai_seo_log("Overrode Permalink Manager Pro storage for Product $product_id");
                    }
                }
                break;
                
            case 'short_description':
                wp_update_post(['ID' => $product_id, 'post_excerpt' => $content]);
                break;
                
            case 'full_description':
                wp_update_post(['ID' => $product_id, 'post_content' => $content]);
                break;
                
            case 'meta_description':
                $tools = get_option('ai_seo_tools', []);
                if (!empty($tools['update_rank_math_meta']) && function_exists('ai_seo_update_seo_fields')) {
                    ai_seo_update_seo_fields($product_id, ['meta_description' => $content]);
                }
                break;
                
            case 'tags':
                $tags = array_map('trim', explode(',', $content));
                wp_set_post_terms($product_id, $tags, 'product_tag', false);
                break;
                
            // AI Search fields - save to post meta (v2.1.0: 6 proven fields)
            default:
                if (in_array($field_name, [
                    'product_summary', 'faq_schema', 'care_instructions',
                    'product_highlights', 'pros_cons', 'alt_names'
                ])) {
                    update_post_meta($product_id, '_ai_seo_' . $field_name, $content);
                    
                    // Special handling: Product Summary prepends to short description
                    if ($field_name === 'product_summary' && !empty($content)) {
                        $this->prepend_summary_to_short_description($product_id, $content);
                    }
                }
                break;
        }
        
        ai_seo_log("Saved $field_name for product $product_id");
    }
    
    /**
     * Prepend Product Summary to WooCommerce short description
     */
    private function prepend_summary_to_short_description($product_id, $summary) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        $current_excerpt = $product->get_short_description();
        
        // Don't prepend if summary is already there
        if (strpos($current_excerpt, $summary) !== false) {
            return;
        }
        
        // Wrap summary in a paragraph and prepend
        $new_excerpt = '<p class="ai-seo-product-summary">' . esc_html($summary) . '</p>' . "\n\n" . $current_excerpt;
        
        $product->set_short_description($new_excerpt);
        $product->save();
        
        ai_seo_log("Prepended summary to short description for Product $product_id");
    }
    
    /**
     * Handle critical field failure
     * 
     * @param object $job
     * @param string $error
     */
    private function handle_critical_failure($job, $error) {
        ai_seo_log("CRITICAL FIELD FAILURE: {$job->field_name} for product {$job->product_id}");
        
        // Skip remaining jobs for this product
        $skipped = $this->job_manager->skip_product_jobs(
            $job->batch_id, 
            $job->product_id, 
            "Skipped due to critical field '{$job->field_name}' failure: $error"
        );
        
        ai_seo_log("Skipped $skipped remaining jobs for product {$job->product_id}");
        
        // Attempt to restore backup if available
        if (function_exists('ai_seo_restore_backup')) {
            $restored = ai_seo_restore_backup($job->product_id);
            if ($restored) {
                ai_seo_log("Restored backup for product {$job->product_id}");
            }
        }
    }
    
    /**
     * Check if an error is retryable
     * 
     * @param string $error
     * @return bool
     */
    private function is_retryable_error($error) {
        $retryable_patterns = [
            'timed out',
            'cURL error 28',
            'rate limit',
            '429',
            '503',
            '502',
            'temporarily unavailable',
            'overloaded',
        ];
        
        $error_lower = strtolower($error);
        
        foreach ($retryable_patterns as $pattern) {
            if (strpos($error_lower, strtolower($pattern)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    // Parsing helpers (adapted from existing generation.php)
    
    private function parse_faq($content) {
        $faqs = [];
        preg_match_all('/Q:\s*(.+?)\s*A:\s*(.+?)(?=Q:|$)/s', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $question = trim($match[1]);
            $answer = trim($match[2]);
            if (!empty($question) && !empty($answer)) {
                $faqs[] = ['question' => $question, 'answer' => $answer];
            }
        }
        return $faqs;
    }
    
    private function parse_numbered_list($content) {
        $items = [];
        $content = preg_replace('/^#+\s*.+$/m', '', $content);
        $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
        preg_match_all('/\d+\.\s*(.+?)(?=\d+\.|$)/s', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $item) {
                $item = trim($item);
                if (!empty($item) && strlen($item) > 3) {
                    $items[] = $item;
                }
            }
        }
        return $items;
    }
    
    private function parse_bullet_list($content) {
        $items = [];
        $content = preg_replace('/^#+\s*.+$/m', '', $content);
        $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = preg_replace('/^[\•\-\*]\s*/', '', trim($line));
            if (!empty($line) && strlen($line) > 3) {
                $items[] = $line;
            }
        }
        return $items;
    }
    
    private function parse_pros_cons($content) {
        $result = ['pros' => [], 'cons' => []];
        $content = preg_replace('/^#+\s*.+$/m', '', $content);
        $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
        
        $parts = preg_split('/CONS?:/i', $content, 2);
        
        if (!empty($parts[0])) {
            $pros_section = preg_replace('/PROS?:/i', '', $parts[0]);
            preg_match_all('/\+\s*(.+?)(?=\+|$)/s', $pros_section, $pros_matches);
            if (!empty($pros_matches[1])) {
                foreach ($pros_matches[1] as $pro) {
                    $pro = trim($pro);
                    if (!empty($pro) && strlen($pro) > 5) {
                        $result['pros'][] = $pro;
                    }
                }
            }
        }
        
        if (!empty($parts[1])) {
            preg_match_all('/\-\s*(.+?)(?=\-|$)/s', $parts[1], $cons_matches);
            if (!empty($cons_matches[1])) {
                foreach ($cons_matches[1] as $con) {
                    $con = trim($con);
                    if (!empty($con) && strlen($con) > 5) {
                        $result['cons'][] = $con;
                    }
                }
            }
        }
        
        return $result;
    }
    
    private function parse_comma_list($content) {
        $items = [];
        $parts = explode(',', $content);
        foreach ($parts as $part) {
            $part = trim($part);
            if (!empty($part)) {
                $items[] = $part;
            }
        }
        return $items;
    }
    
    /**
     * Trigger RankMath score calculation for a product
     * v2.0.12: Copied from legacy ajax.php - runs after all fields are saved
     * 
     * @param int $post_id
     */
    private function trigger_score_calculation($post_id) {
        ai_seo_log("=== STARTING RANKMATH SCORE UPDATE FOR PRODUCT $post_id ===");
        
        // Check if RankMath is active
        if (!function_exists('rank_math')) {
            ai_seo_log("RankMath not active - skipping score update");
            return;
        }
        
        ai_seo_log("✓ RankMath is active");
        
        // METHOD 1: Direct action hook
        try {
            do_action('rank_math/analyzer/update_score', $post_id);
            ai_seo_log("✓ Method 1: Triggered rank_math/analyzer/update_score");
        } catch (Exception $e) {
            ai_seo_log("✗ Method 1 failed: " . $e->getMessage());
        }
        
        // METHOD 2: Save post hook (forces full RankMath processing)
        try {
            do_action('save_post', $post_id, get_post($post_id), true);
            ai_seo_log("✓ Method 2: Triggered save_post hook");
        } catch (Exception $e) {
            ai_seo_log("✗ Method 2 failed: " . $e->getMessage());
        }
        
        // METHOD 3: Direct Paper update (RankMath's scoring engine)
        if (class_exists('RankMath\Paper\Paper')) {
            try {
                $paper = \RankMath\Paper\Paper::get();
                if ($paper && method_exists($paper, 'setup_paper')) {
                    $paper->setup_paper($post_id);
                    ai_seo_log("✓ Method 3: Setup RankMath Paper for product");
                }
            } catch (Exception $e) {
                ai_seo_log("✗ Method 3 failed: " . $e->getMessage());
            }
        }
        
        // METHOD 3B: Try RankMath's Content_AI class if available
        if (class_exists('RankMath\ContentAI\Content_AI')) {
            try {
                do_action('rank_math/contentai/process_score', $post_id);
                ai_seo_log("✓ Method 3B: Triggered ContentAI score processing");
            } catch (Exception $e) {
                ai_seo_log("✗ Method 3B failed: " . $e->getMessage());
            }
        }
        
        // METHOD 3C: Try calling RankMath's score calculator directly
        if (function_exists('rank_math_the_seo_score')) {
            try {
                $score = rank_math_the_seo_score($post_id);
                ai_seo_log("✓ Method 3C: Called rank_math_the_seo_score, result: " . $score);
            } catch (Exception $e) {
                ai_seo_log("✗ Method 3C failed: " . $e->getMessage());
            }
        }
        
        // METHOD 4: Check and log current score
        $score_meta_key = 'rank_math_seo_score';
        $current_score = get_post_meta($post_id, $score_meta_key, true);
        ai_seo_log("Current score in database: " . ($current_score ? $current_score : 'NOT SET'));
        
        // METHOD 5: Try rank_math_get_post_meta if available
        if (function_exists('rank_math_get_post_meta')) {
            $rm_score = rank_math_get_post_meta($score_meta_key, $post_id);
            ai_seo_log("Score via rank_math_get_post_meta: " . ($rm_score ? $rm_score : 'NOT SET'));
        }
        
        // METHOD 6: Force recalculate by clearing cache
        if (function_exists('rank_math_clear_cache')) {
            rank_math_clear_cache();
            ai_seo_log("✓ Method 6: Cleared RankMath cache");
        }
        
        // Get timing settings from Tools
        $tools = get_option('ai_seo_tools', []);
        $score_wait_time = isset($tools['score_wait_time']) ? intval($tools['score_wait_time']) : 5;
        $post_save_delay = isset($tools['post_save_delay']) ? intval($tools['post_save_delay']) : 1;
        
        // Convert to microseconds for usleep
        $score_wait_usec = $score_wait_time * 1000000;
        $save_delay_usec = $post_save_delay * 1000000;
        
        // METHOD 7: Multiple post saves to trigger RankMath score persistence
        ai_seo_log("✓ Method 7: Starting multiple post save sequence (delay: {$post_save_delay}s, score wait: {$score_wait_time}s)");
        
        // First forced save
        wp_update_post(['ID' => $post_id]);
        ai_seo_log("✓ First forced save completed");
        
        // Delay based on user settings
        usleep($save_delay_usec);
        
        // Second forced save
        wp_update_post(['ID' => $post_id]);
        ai_seo_log("✓ Second forced save completed");
        
        // Wait for RankMath to calculate score
        usleep($score_wait_usec);
        
        // Check if score was saved now
        $score_after = function_exists('ai_seo_get_score') ? ai_seo_get_score($post_id) : null;
        ai_seo_log("SEO score after forced saves: " . ($score_after !== null ? $score_after : 'NOT AVAILABLE'));
        
        // If score still not set, try one more hook trigger (Rank Math specific)
        if (empty($score_after)) {
            do_action('rank_math/after_save_post', $post_id);
            usleep($save_delay_usec);
            $score_final = function_exists('ai_seo_get_score') ? ai_seo_get_score($post_id) : null;
            ai_seo_log("SEO score after final attempt: " . ($score_final !== null ? $score_final : 'STILL NOT SET'));
        }
        
        // Clear caches for the All Products page
        clean_post_cache($post_id);
        wp_cache_delete($post_id, 'post_meta');
        wp_cache_delete($post_id . '_rank_math_seo_score', 'post_meta');
        
        ai_seo_log("=== SEO SCORE UPDATE COMPLETE FOR PRODUCT $post_id ===");
    }
}

// Register Action Scheduler hook
add_action('ai_seo_process_batch', function($batch_id) {
    $processor = new AI_SEO_Field_Processor();
    $processor->process_batch($batch_id);
});
