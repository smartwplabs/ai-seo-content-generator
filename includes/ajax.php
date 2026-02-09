<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to update SEO fields using provider system
 * v1.3.0 - Works with any SEO plugin (Rank Math, Yoast, AIOSEO, SEOPress)
 * 
 * @param int $post_id Product ID
 * @param array $fields SEO fields to update
 * @return bool Success
 */
function ai_seo_update_seo_fields($post_id, $fields) {
    $provider = ai_seo_get_provider();
    $success = $provider->set_fields($post_id, $fields);
    
    if ($success) {
        ai_seo_log("Updated SEO fields via " . $provider->get_name() . " for Product $post_id");
    }
    
    return $success;
}

/**
 * Helper function to get SEO score using provider system
 * v1.3.0 - Works with any SEO plugin that supports scoring
 * 
 * @param int $post_id Product ID
 * @return int|null Score 0-100, or null if not supported
 */
function ai_seo_get_score($post_id) {
    $provider = ai_seo_get_provider();
    return $provider->get_score($post_id);
}

add_action('wp_ajax_seo_generate_content', function() {
    error_log('AI SEO DEBUG: AJAX handler called');
    
    // Check dependencies (v1.3.0 - SEO plugin now optional)
    if (!ai_seo_check_dependencies()) {
        error_log('AI SEO DEBUG: Dependencies check failed');
        wp_send_json_error(['error' => 'WooCommerce is required for this plugin.']);
        return;
    }
    
    error_log('AI SEO DEBUG: Dependencies OK');

    // Get and validate input
    $post_ids = json_decode(stripslashes($_POST['posts'] ?? '[]'), true);
    $prompts = json_decode(stripslashes($_POST['prompts'] ?? '{}'), true);
    
    error_log('AI SEO DEBUG: Post IDs: ' . print_r($post_ids, true));
    error_log('AI SEO DEBUG: Number of products: ' . count($post_ids));
    
    if (empty($post_ids) || !is_array($post_ids)) {
        error_log('AI SEO DEBUG: No products selected');
        wp_send_json_error(['error' => 'No products selected. Please select at least one product.']);
        return;
    }

    // Get settings
    $settings = get_option('ai_seo_settings', []);
    $tools = get_option('ai_seo_tools', []);
    
    // v1.3.1: Disable image optimizers for faster bulk operations
    ai_seo_disable_image_optimizers();
    
    // Get AI engine first, then load its specific API key
    $ai_engine = $settings['ai_seo_ai_engine'] ?? 'chatgpt';
    
    // v1.3.1: Load engine-specific API key
    $api_key_option_name = 'ai_seo_api_key_' . $ai_engine;
    $api_key = get_option($api_key_option_name, '');
    
    // Fallback to old storage method if engine-specific key not found
    if (empty($api_key)) {
        $api_key = $settings['ai_seo_api_key'] ?? '';
    }
    
    $model = $settings['ai_seo_model'] ?? 'gpt-4o';
    
    error_log('AI SEO DEBUG: AI Engine: ' . $ai_engine);
    error_log('AI SEO DEBUG: Model: ' . $model);
    error_log('AI SEO DEBUG: API Key present: ' . (!empty($api_key) ? 'YES' : 'NO'));
    
    error_log('AI SEO DEBUG: AI Engine: ' . $ai_engine);
    error_log('AI SEO DEBUG: Model: ' . $model);
    error_log('AI SEO DEBUG: API Key present: ' . (!empty($api_key) ? 'YES' : 'NO'));
    
    $max_tokens = (int) ($settings['ai_seo_max_tokens'] ?? 2048);
    $temperature = (float) ($settings['ai_seo_temperature'] ?? 0.7);
    $frequency_penalty = (float) ($settings['ai_seo_frequency_penalty'] ?? 0);
    $presence_penalty = (float) ($settings['ai_seo_presence_penalty'] ?? 0);

    if (empty($api_key)) {
        error_log('AI SEO DEBUG: API key is empty - returning error');
        wp_send_json_error(['error' => 'API key is not configured. Please add your API key in AI Settings and click Save.']);
        return;
    }
    
    error_log('AI SEO DEBUG: Starting generation...');

    // v1.3.1O: Log SEO provider once at generation start (not on every page load)
    $seo_provider = ai_seo_get_provider();
    ai_seo_log("SEO Provider: " . $seo_provider->get_name());
    ai_seo_log("Starting generation for " . count($post_ids) . " products using engine: $ai_engine");

    $results = [];
    $processed = 0;
    $batch_size = 5;
    $debug_info = []; // Track debug information

    foreach (array_chunk($post_ids, $batch_size) as $batch) {
        foreach ($batch as $post_id) {
            $post_id = intval($post_id);
            error_log('AI SEO DEBUG: Processing product ID: ' . $post_id);
            
            $debug_info[$post_id] = ['status' => 'starting'];
            
            // Check if WooCommerce function exists
            if (!function_exists('wc_get_product')) {
                $debug_info[$post_id] = ['status' => 'error', 'message' => 'wc_get_product function does not exist'];
                continue;
            }
            
            $product = wc_get_product($post_id);
            
            if (!$product) {
                error_log('AI SEO DEBUG: Product ID ' . $post_id . ' not found - wc_get_product() returned false');
                $debug_info[$post_id] = ['status' => 'error', 'message' => 'wc_get_product returned false/null'];
                ai_seo_log("Product ID $post_id not found");
                continue;
            }
            
            error_log('AI SEO DEBUG: Product ID ' . $post_id . ' loaded successfully');
            $debug_info[$post_id]['status'] = 'product_loaded';
            $debug_info[$post_id]['product_type'] = $product->get_type();

            $post = get_post($post_id);
            if (!$post) {
                error_log('AI SEO DEBUG: Post ID ' . $post_id . ' not found - get_post() returned null');
                $debug_info[$post_id] = ['status' => 'error', 'message' => 'get_post returned null'];
                continue;
            }
            
            error_log('AI SEO DEBUG: Post object retrieved for ID ' . $post_id);
            $debug_info[$post_id]['post_loaded'] = true;
            $debug_info[$post_id]['post_status'] = $post->post_status;
            
            $product_title = $post->post_title;
            error_log('AI SEO DEBUG: Product title: ' . $product_title);
            $debug_info[$post_id]['title'] = $product_title;
            $current_short_description = $post->post_excerpt ?: '';
            $current_full_description = $post->post_content ?: '';

            // Get product attributes (v1.4.0 fix: properly handle taxonomy vs custom attributes)
            $current_attributes = [];
            $attributes_data = [];
            foreach ($product->get_attributes() as $attr_key => $attribute) {
                $attribute_data = $attribute->get_data();
                $name = wc_attribute_label($attribute_data['name']);
                $slug = sanitize_title($attribute_data['name']);
                
                // Check if this is a taxonomy attribute (global) or custom attribute
                if ($attribute->is_taxonomy()) {
                    // Taxonomy attribute - get term names from term IDs
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
                    // Custom attribute - get_options returns actual values
                    $values = implode(', ', $attribute->get_options());
                }
                
                if (!empty($values)) {
                    $current_attributes[] = "$name: $values";
                    $attributes_data[$slug] = $values;
                }
            }
            $current_attributes = implode('; ', $current_attributes);
            
            // Log attributes for debugging
            if (!empty($current_attributes)) {
                ai_seo_log("Product $post_id attributes: " . substr($current_attributes, 0, 500) . (strlen($current_attributes) > 500 ? '...' : ''));
            }

            // Build replacements array
            $replacements = [
                '[product_title]' => $product_title,
                '[current_short_description]' => $current_short_description,
                '[current_full_description]' => $current_full_description,
                '[current_attributes]' => $current_attributes,
                '[current_categories]' => implode(', ', wp_get_post_terms($post_id, 'product_cat', ['fields' => 'names'])),
                '[current_price]' => $product->get_price() ?: '',
                '[current_sku]' => $product->get_sku() ?: '',
                '[current_tags]' => implode(', ', wp_get_post_terms($post_id, 'product_tag', ['fields' => 'names'])),
            ];
            
            foreach ($attributes_data as $slug => $value) {
                $replacements["[current_$slug]"] = $value;
            }
            
            // v2.1.20: Description length setting
            $desc_length = isset($tools['description_length']) ? $tools['description_length'] : 'standard';
            $length_map = [
                'standard' => '300-400 words',
                'long'     => '800-1000 words',
                'premium'  => '1500-2000 words',
            ];
            $replacements['[description_length]'] = $length_map[$desc_length] ?? '300-400 words';

            // v1.3.2: Create backup before generation (if enabled)
            $backup_created = false;
            $original_score = null;
            if (!empty($tools['enable_backup'])) {
                $backup = ai_seo_create_backup($post_id);
                if ($backup) {
                    $backup_created = true;
                    $original_score = $backup['seo_score'];
                    ai_seo_log("Backup created for Product $post_id - Original score: " . ($original_score ?? 'N/A'));
                }
            }

            // Step 1: Generate focus keyword
            $focus_keyword_prompt = ai_seo_process_prompt(
                $prompts['focus_keyword'] ?? 'Generate a focus keyword for [product_title].',
                $replacements
            );
            
            ai_seo_log("Focus keyword prompt for Product $post_id: " . substr($focus_keyword_prompt, 0, 100));
            $debug_info[$post_id]['attempting_focus_keyword'] = true;
            $debug_info[$post_id]['ai_engine'] = $ai_engine;
            $debug_info[$post_id]['api_key_present'] = !empty($api_key);
            
            $focus_keyword = ai_seo_call_ai_engine($ai_engine, $api_key, $model, $focus_keyword_prompt, $max_tokens, $temperature, $frequency_penalty, $presence_penalty, $top_p);
            
            if (is_wp_error($focus_keyword)) {
                $error_message = $focus_keyword->get_error_message();
                ai_seo_log("Failed to generate focus keyword for Product $post_id: " . $error_message);
                $debug_info[$post_id]['status'] = 'api_error';
                $debug_info[$post_id]['error_step'] = 'focus_keyword_generation';
                $debug_info[$post_id]['api_error'] = $error_message;
                continue;
            }
            
            // Clean up AI output (v1.2.1.10 - Smart sanitization)
            $focus_keyword_raw = trim($focus_keyword, '"\'');
            ai_seo_log("Raw focus keyword for Product $post_id: $focus_keyword_raw");
            
            // Apply smart sanitization to remove formatting artifacts
            $focus_keyword = ai_seo_sanitize_focus_keyword($focus_keyword_raw);
            ai_seo_log("Sanitized focus keyword for Product $post_id: $focus_keyword");
            
            // v1.3.1i: Fallback if AI generated prose instead of keyword
            // If keyword is empty or obviously wrong, generate simple fallback from product title
            if (empty($focus_keyword) || strlen($focus_keyword) > 100 || str_word_count($focus_keyword) > 12) {
                ai_seo_log("WARNING: AI generated invalid focus keyword, using fallback from product title");
                // Simple fallback: Take first 5-8 words of product title
                $title_words = explode(' ', $product_title);
                $focus_keyword = implode(' ', array_slice($title_words, 0, min(8, count($title_words))));
                $focus_keyword = trim($focus_keyword);
                ai_seo_log("Fallback focus keyword for Product $post_id: $focus_keyword");
            }
            
            $debug_info[$post_id]['focus_keyword_generated'] = $focus_keyword;
            
            $replacements['[focus_keyword]'] = $focus_keyword;
            $result = ['focus_keyword' => $focus_keyword];

            // Step 2: Update focus keyword IMMEDIATELY (before other content)
            // v1.3.0 - Using SEO provider system (works with any SEO plugin)
            // This ensures the SEO plugin uses the NEW keyword, not one from the old title
            if (!empty($result['focus_keyword'])) {
                ai_seo_update_seo_fields($post_id, [
                    'focus_keyword' => $result['focus_keyword']
                ]);
                ai_seo_log("Updated focus keyword for Product $post_id: {$result['focus_keyword']}");
            }

            // Step 3: Generate fields in correct order
            // CRITICAL: Title must be generated BEFORE descriptions so they can reference it
            $generation_order = [
                'title',              // Generate title first (uses focus keyword)
                'short_description',  // Then short description (uses focus keyword + new title)
                'full_description',   // Then full description (uses focus keyword + new title)
                'meta_description',   // Then meta description (uses focus keyword)
                'tags'                // Finally tags (uses focus keyword)
            ];
            
            // v1.4.0: Allow add-ons to add fields to generation order
            $generation_order = apply_filters('ai_seo_generation_fields', $generation_order);
            
            // v1.4.0: Allow add-ons to add prompts
            $prompts = apply_filters('ai_seo_generation_prompts', $prompts);
            
            // v1.4.0-fix4: Track generation failures for immediate restore
            $failed_fields = [];
            $critical_fields = ['full_description', 'short_description', 'title']; // If these fail, restore immediately
            $skip_product = false; // Flag to skip remaining processing if critical failure

            foreach ($generation_order as $field) {
                // Check if we should generate this field
                $should_generate = false;
                
                switch ($field) {
                    case 'title':
                        $should_generate = !empty($tools['generate_title_from_keywords']);
                        break;
                    case 'meta_description':
                        $should_generate = !empty($tools['generate_meta_description']);
                        break;
                    case 'tags':
                        $should_generate = !empty($tools['generate_tags']);
                        break;
                    case 'short_description':
                    case 'full_description':
                        $should_generate = true; // Always generate if prompt exists
                        break;
                    default:
                        // v1.4.0: For add-on fields, generate if prompt exists
                        $should_generate = !empty($prompts[$field]);
                        break;
                }
                
                // v1.4.0: Allow add-ons to filter should_generate
                $should_generate = apply_filters('ai_seo_should_generate_field', $should_generate, $field, $tools);
                
                // Skip if disabled or no prompt
                if (!$should_generate || empty($prompts[$field])) {
                    continue;
                }

                // Process prompt with current replacements
                $prompt = ai_seo_process_prompt($prompts[$field], $replacements);
                
                // Add modifiers for title generation
                if ($field === 'title') {
                    if (!empty($tools['include_original_title'])) {
                        $prompt .= " Include reference to the original title: $product_title.";
                    }
                    if (!empty($tools['use_sentiment_in_title'])) {
                        $prompt .= " Use a positive emotional sentiment word that evokes excitement (e.g., amazing, fantastic, incredible, wonderful, extraordinary, remarkable, stunning, brilliant).";
                    }
                    // Power words are controlled in the title prompt - no extra append needed
                    if (!empty($tools['include_number_in_title'])) {
                        $prompt .= " Include a specific number or statistic (e.g., '5 Ways', '10 Best', '#1 Rated', '2024 Edition', '99% Satisfaction', '3-Pack').";
                    }
                }

                ai_seo_log("Generating $field for Product $post_id");
                
                // v1.4.0-fix4: Retry logic for timeouts (try up to 2 times)
                $max_retries = 2;
                $retry_count = 0;
                $content = null;
                
                while ($retry_count < $max_retries) {
                    $content = ai_seo_call_ai_engine($ai_engine, $api_key, $model, $prompt, $max_tokens, $temperature, $frequency_penalty, $presence_penalty, $top_p);
                    
                    if (is_wp_error($content)) {
                        $error_message = $content->get_error_message();
                        $retry_count++;
                        
                        // Only retry on timeout errors
                        if (strpos($error_message, 'timed out') !== false || strpos($error_message, 'cURL error 28') !== false) {
                            if ($retry_count < $max_retries) {
                                ai_seo_log("Timeout generating $field for Product $post_id (attempt $retry_count/$max_retries) - retrying...");
                                sleep(2); // Wait 2 seconds before retry
                                continue;
                            }
                        }
                        
                        // Log the failure
                        ai_seo_log("Failed to generate $field for Product $post_id: " . $error_message);
                        $failed_fields[] = $field;
                        
                        // v1.4.0-fix4: If a CRITICAL field failed, stop and restore immediately
                        if (in_array($field, $critical_fields)) {
                            ai_seo_log("⚠️ CRITICAL FIELD '$field' FAILED - Initiating immediate restore for Product $post_id");
                            
                            if ($backup_created && function_exists('ai_seo_restore_backup')) {
                                $restore_success = ai_seo_restore_backup($post_id);
                                if ($restore_success) {
                                    ai_seo_log("✓ Product $post_id RESTORED to original content due to critical field failure");
                                    $debug_info[$post_id]['status'] = 'restored_on_failure';
                                    $debug_info[$post_id]['failed_field'] = $field;
                                    $debug_info[$post_id]['error'] = $error_message;
                                    $results[$post_id] = [
                                        'status' => 'restored',
                                        'reason' => "Critical field '$field' failed to generate: $error_message",
                                        'restored' => true
                                    ];
                                } else {
                                    ai_seo_log("⚠️ RESTORE FAILED for Product $post_id - original content may be lost!");
                                    $debug_info[$post_id]['status'] = 'restore_failed';
                                }
                            }
                            
                            // Set flag to skip the rest of this product's processing
                            $skip_product = true;
                            break 2; // Break out of while and foreach field loops
                        }
                        
                        break; // Exit retry loop for non-critical fields
                    } else {
                        break; // Success - exit retry loop
                    }
                }
                
                // Skip if content is still an error (non-critical field)
                if (is_wp_error($content)) {
                    continue;
                }

                // Store result (use wp_kses_post for descriptions to preserve formatting)
                if ($field === 'short_description' || $field === 'full_description') {
                    // v1.3.1k: Strip markdown code fences that AI sometimes adds
                    $content = preg_replace('/^```html\s*/i', '', $content);
                    $content = preg_replace('/^```\s*/m', '', $content);
                    $content = preg_replace('/\s*```$/s', '', $content);
                    $content = trim($content);
                    
                    $result[$field] = wp_kses_post($content);
                } else {
                    // v1.3.1M: Only apply chattiness removal to title and focus_keyword
                    // Meta_description and tags need full content preserved!
                    if ($field === 'title' || $field === 'focus_keyword') {
                        $clean_content = ai_seo_remove_ai_chattiness($content);
                    } else {
                        $clean_content = $content; // No chattiness removal for meta/tags
                    }
                    
                    // v1.3.1L: Use appropriate sanitization based on field
                    if ($field === 'meta_description' || $field === 'tags') {
                        // Allow longer text for meta and tags
                        $result[$field] = sanitize_textarea_field($clean_content);
                    } else {
                        // Title, focus_keyword use standard field
                        $result[$field] = sanitize_text_field($clean_content);
                    }
                }
                
                ai_seo_log("Generated $field for Product $post_id: " . substr($result[$field], 0, 100));
                
                // v1.4.0: Allow add-ons to process generated content
                do_action('ai_seo_content_generated', $post_id, $field, $result[$field]);
                
                // v1.4.2: Add pause between API calls to prevent rate limiting and reduce load
                sleep(2);
                
                // IMPORTANT: After generating title, update replacements so descriptions can use it
                if ($field === 'title') {
                    // v1.3.2c: Check for duplicate titles and make unique if needed (if enabled)
                    if (!empty($tools['prevent_duplicate_titles'])) {
                        $title_check = ai_seo_ensure_unique_title($result['title'], $post_id);
                        $result['title'] = $title_check['title'];
                        
                        if ($title_check['was_duplicate']) {
                            ai_seo_log("Title was duplicate, modified: '{$title_check['original']}' → '{$title_check['title']}'");
                            $result['title_was_duplicate'] = true;
                            $result['original_generated_title'] = $title_check['original'];
                        }
                    }
                    
                    $replacements['[product_title]'] = $result['title'];
                    
                    // Also update the product title immediately so Rank Math sees it
                    wp_update_post(['ID' => $post_id, 'post_title' => $result['title']]);
                    
                    if (!empty($tools['add_meta_tag_to_head'])) {
                        update_post_meta($post_id, '_ai_seo_title_tag', $result['title']);
                    }
                    
                    ai_seo_log("Updated product title for Product $post_id");
                }
            }
            
            // v1.4.0-fix4: Log any failed fields and mark generation as incomplete if needed
            if (!empty($failed_fields)) {
                ai_seo_log("⚠️ Product $post_id had " . count($failed_fields) . " failed fields: " . implode(', ', $failed_fields));
                $result['generation_incomplete'] = true;
                $result['failed_fields'] = $failed_fields;
                
                // Mark in post meta so auto-restore knows NOT to delete backup
                update_post_meta($post_id, '_ai_seo_generation_incomplete', true);
                update_post_meta($post_id, '_ai_seo_failed_fields', $failed_fields);
            } else {
                // Clear any previous incomplete flags
                delete_post_meta($post_id, '_ai_seo_generation_incomplete');
                delete_post_meta($post_id, '_ai_seo_failed_fields');
            }
            
            // v1.4.0-fix4: If we restored due to critical failure, skip save step and go to next product
            if ($skip_product) {
                ai_seo_log("Skipping save step for Product $post_id (restored due to critical failure)");
                $processed++;
                continue;
            }

            // Step 4: Update product with remaining generated content
            
            // Update short description (product excerpt)
            if (!empty($result['short_description'])) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_excerpt' => $result['short_description']
                ]);
                ai_seo_log("Updated short description for Product $post_id");
            }
            
            // Update full description (product content)
            if (!empty($result['full_description'])) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $result['full_description']
                ]);
                ai_seo_log("Updated full description for Product $post_id");
            }
            
            // Update meta description (v1.3.0 - Using SEO provider system)
            if (!empty($result['meta_description']) && !empty($tools['update_rank_math_meta'])) {
                ai_seo_update_seo_fields($post_id, [
                    'meta_description' => $result['meta_description']
                ]);
            }
            
            // Update tags
            if (!empty($result['tags'])) {
                $tags = array_map('trim', explode(',', $result['tags']));
                wp_set_post_terms($post_id, $tags, 'product_tag', false); // v1.4.3: Replace tags instead of appending
            }

            // Update URL slug with Permalink Manager Pro compatibility
            if (!empty($tools['shorten_url']) || !empty($tools['enforce_focus_keyword_url'])) {
                // v1.3.1O: Create slug from title but strip power words for shorter URL
                $new_slug = sanitize_title($result['title'] ?? $product_title);
                
                // v1.3.1O: Strip common power word suffixes from slug (keep in title for SEO)
                $power_words_pattern = '/-(stunning|premium|genuine|perfect|exclusive|brilliant|amazing|best)$/i';
                $new_slug = preg_replace($power_words_pattern, '', $new_slug);
                
                if (!empty($tools['enforce_focus_keyword_url']) && !empty($result['focus_keyword'])) {
                    $keyword_slug = sanitize_title($result['focus_keyword']);
                    if (strpos($new_slug, $keyword_slug) === false) {
                        $new_slug = $keyword_slug . '-' . $new_slug;
                    }
                }
                
                // v1.3.1O: Ensure slug isn't too long (max ~50 chars for typical domains)
                // RankMath recommends total URL < 75 chars
                // Domain like chaneysjewelry.com = ~27 chars, leaving ~48 for slug
                if (strlen($new_slug) > 50) {
                    // Truncate at last complete word before 50 chars
                    $new_slug = substr($new_slug, 0, 50);
                    $last_dash = strrpos($new_slug, '-');
                    if ($last_dash !== false && $last_dash > 30) {
                        $new_slug = substr($new_slug, 0, $last_dash);
                    }
                }
                
                // v1.3.1k: Update WordPress core slug (always)
                wp_update_post(['ID' => $post_id, 'post_name' => $new_slug]);
                ai_seo_log("Updated WordPress permalink for Product $post_id: $new_slug");
                
                // v1.3.1k: If Permalink Manager Pro exists, override its custom storage too
                if (class_exists('Permalink_Manager_URI_Functions_Post')) {
                    global $permalink_manager_uris;
                    $permalink_manager_uris[$post_id] = $new_slug;
                    update_option('permalink-manager-uris', $permalink_manager_uris);
                    ai_seo_log("Overrode Permalink Manager Pro storage for Product $post_id");
                }
                
                // Verify permalink actually changed
                $check_post = get_post($post_id);
                if ($check_post && $check_post->post_name === $new_slug) {
                    ai_seo_log("✓ Permalink verified: $new_slug");
                } else {
                    ai_seo_log("⚠ WARNING: Permalink verification failed for Product $post_id");
                }
            }
            
            // v1.3.1k: Update ALL image metadata (Alt, Title, Caption, Description)
            if (!empty($tools['update_image_alt_tags']) && !empty($result['focus_keyword'])) {
                $focus_keyword = $result['focus_keyword'];
                
                // v1.3.1P: Use GENERATED title, not original (avoid "(Copy)" etc.)
                $generated_title = $result['title'] ?? $product_title;
                $generated_title_short = substr($generated_title, 0, 50);
                
                // v1.3.1O: Get ALL product images using WooCommerce functions (more reliable)
                $image_ids = [];
                
                // Get featured image
                $featured_id = get_post_thumbnail_id($post_id);
                if ($featured_id) {
                    $image_ids[] = $featured_id;
                }
                
                // Get gallery images (WooCommerce stores these in post meta)
                $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
                if (!empty($gallery_ids)) {
                    $gallery_array = explode(',', $gallery_ids);
                    $image_ids = array_merge($image_ids, array_map('intval', $gallery_array));
                }
                
                // Remove duplicates
                $image_ids = array_unique(array_filter($image_ids));
                
                ai_seo_log("Found " . count($image_ids) . " images for Product $post_id");
                
                if (empty($image_ids)) {
                    ai_seo_log("⚠ No images found for Product $post_id");
                }
                
                // v1.3.1N: Smart alt text variations for AI shopping (Google, ChatGPT, Amazon, etc.)
                $view_types = ['Close-Up', 'Side View', 'Detail Shot', 'Worn', 'Angled View', 'Lifestyle', 'Top View', 'Back View'];
                
                $count = 0;
                $verified = 0;
                foreach ($image_ids as $attachment_id) {
                    
                    // v1.3.1N: Generate UNIQUE alt text for each image (AI shopping best practice)
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
                    
                    // v1.3.1k: Debug logging
                    ai_seo_log("Image #$attachment_id metadata BEFORE save:");
                    ai_seo_log("  Alt: " . $alt_text);
                    ai_seo_log("  Title: " . $title);
                    ai_seo_log("  Caption: " . $caption);
                    ai_seo_log("  Description: " . $description);
                    
                    // v1.3.1k: Write directly to WordPress core (override any plugin)
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
                    
                    // v1.3.1L: Update image post data (use sanitize_textarea_field for longer fields)
                    $update_result = wp_update_post([
                        'ID' => $attachment_id,
                        'post_title' => sanitize_text_field($title),
                        'post_excerpt' => sanitize_textarea_field($caption),  // Caption can be longer
                        'post_content' => sanitize_textarea_field($description)  // Description can be longer
                    ]);
                    
                    if (is_wp_error($update_result)) {
                        ai_seo_log("ERROR updating image #$attachment_id: " . $update_result->get_error_message());
                    }
                    
                    // Verify what was actually saved
                    $check_post = get_post($attachment_id);
                    ai_seo_log("Image #$attachment_id metadata AFTER save:");
                    ai_seo_log("  Title saved: " . $check_post->post_title);
                    ai_seo_log("  Caption saved: " . $check_post->post_excerpt);
                    ai_seo_log("  Description saved: " . substr($check_post->post_content, 0, 100));
                    
                    // Verify alt text actually changed
                    $check_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                    if ($check_alt === sanitize_text_field($alt_text)) {
                        $verified++;
                    }
                    
                    $count++;
                }
                
                if ($count > 0) {
                    ai_seo_log("Updated comprehensive metadata for $count images on Product $post_id ($verified verified)");
                    if ($verified < $count) {
                        ai_seo_log("⚠ WARNING: Only $verified/$count image alt texts verified for Product $post_id");
                    }
                }
            }

            // Trigger RankMath score calculation and save (v1.2.0.1 - Enhanced)
            // This ensures the SEO Score column updates on the products page
            ai_seo_log("=== STARTING RANKMATH SCORE UPDATE FOR PRODUCT $post_id ===");
            
            // Check if RankMath is active
            if (!function_exists('rank_math')) {
                ai_seo_log("ERROR: rank_math() function not found - RankMath may not be active!");
            } else {
                ai_seo_log("✓ RankMath is active");
                
                // Check what RankMath classes are available
                $rm_classes = [
                    'RankMath\Post' => class_exists('RankMath\Post'),
                    'RankMath\Paper\Paper' => class_exists('RankMath\Paper\Paper'),
                    'RankMath\Admin\Admin_Helper' => class_exists('RankMath\Admin\Admin_Helper'),
                ];
                ai_seo_log("RankMath classes available: " . print_r($rm_classes, true));
                
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
                
                // METHOD 7: Multiple post saves to trigger RankMath score persistence (v1.2.0.3)
                // RankMath may need multiple save_post hooks to fully calculate and save the score
                ai_seo_log("✓ Method 7: Starting multiple post save sequence");
                
                // First forced save
                wp_update_post(['ID' => $post_id]);
                ai_seo_log("✓ First forced save completed");
                
                // Small delay
                usleep(500000); // 0.5 seconds
                
                // Second forced save
                wp_update_post(['ID' => $post_id]);
                ai_seo_log("✓ Second forced save completed");
                
                // Check if score was saved now (v1.3.0 - Using SEO provider system)
                $score_after = ai_seo_get_score($post_id);
                ai_seo_log("SEO score after forced saves: " . ($score_after !== null ? $score_after : 'NOT AVAILABLE (Plugin may not support scoring)'));
                
                // If score still not set, try one more hook trigger (Rank Math specific)
                $provider = ai_seo_get_provider();
                if (empty($score_after) && $provider->get_name() === 'Rank Math') {
                    do_action('rank_math/after_save_post', $post_id);
                    usleep(250000); // 0.25 seconds
                    $score_final = ai_seo_get_score($post_id);
                    ai_seo_log("SEO score after final attempt: " . ($score_final !== null ? $score_final : 'STILL NOT SET - May need manual update'));
                }
                
                // Check focus keyword was saved (v1.3.0 - Using SEO provider system)
                $provider = ai_seo_get_provider();
                $fields = $provider->get_fields($post_id);
                $focus_kw = $fields['focus_keyword'] ?? '';
                ai_seo_log("Focus keyword in database (" . $provider->get_name() . "): " . ($focus_kw ? $focus_kw : 'NOT SET'));
                
                ai_seo_log("=== SEO SCORE UPDATE COMPLETE FOR PRODUCT $post_id ===");
            }

            // v1.3.2: Add backup info to result
            $result['backup_created'] = $backup_created;
            $result['original_score'] = $original_score;
            $result['has_backup'] = ai_seo_has_backup($post_id);

            $results[$post_id] = $result;
            $processed++;
            
            // Apply buffer delay between products (prevents rate limits)
            $buffer = $settings['ai_seo_buffer'] ?? 3;
            if ($buffer > 0 && $processed < count($post_ids)) {
                sleep($buffer);
                ai_seo_log("Buffer: Waited {$buffer} seconds before next product");
            }
        }
    }

    ai_seo_log("Completed generation for $processed products");
    
    error_log('AI SEO DEBUG: Generation complete');
    error_log('AI SEO DEBUG: Processed: ' . $processed);
    error_log('AI SEO DEBUG: Results count: ' . count($results));
    error_log('AI SEO DEBUG: Sending success response');
    
    // v1.3.1: Re-enable image optimizers after processing
    ai_seo_reenable_image_optimizers();
    
    // v1.3.1P: Clear cache for all processed products (ensures fresh scores on All Products page)
    foreach ($results as $pid => $res) {
        clean_post_cache($pid);
        wp_cache_delete($pid, 'post_meta');
        wp_cache_delete($pid . '_rank_math_seo_score', 'post_meta');
    }
    ai_seo_log("Cache cleared for " . count($results) . " products");
    
    // v1.3.1P: Get FRESH scores after cache clear
    foreach ($results as $pid => &$res) {
        $fresh_score = ai_seo_get_score($pid);
        if ($fresh_score !== null) {
            $res['seo_score'] = $fresh_score;
        }
    }
    
    // v1.3.2: Handle auto-restore if enabled
    $backup_mode = $tools['backup_mode'] ?? 'manual';
    $restore_threshold = intval($tools['restore_threshold'] ?? 80);
    $restored_count = 0;
    $kept_count = 0;
    
    // v1.3.2a: Auto-restore now happens AFTER score calculation (client-side triggers it)
    // Server-side just marks products as pending for auto-restore
    $pending_auto_restore = false;
    
    if (!empty($tools['enable_backup']) && $backup_mode === 'auto') {
        ai_seo_log("=== AUTO-RESTORE MODE: Scores will be checked after calculation ===");
        ai_seo_log("Threshold set to: $restore_threshold");
        $pending_auto_restore = true;
        
        // Don't restore yet - wait for client to trigger after score calculation
        // Just log what we have so far
        foreach ($results as $pid => &$res) {
            if (!empty($res['has_backup'])) {
                $res['pending_auto_check'] = true;
                ai_seo_log("Product $pid: Backup exists, pending auto-check after scores calculated");
            }
        }
    }
    
    // v1.3.2: Include backup/restore summary in response
    $backup_summary = [
        'enabled' => !empty($tools['enable_backup']),
        'mode' => $backup_mode,
        'threshold' => $restore_threshold,
        'restored_count' => $restored_count,
        'kept_count' => $kept_count,
        'pending_review' => ($backup_mode === 'manual' && !empty($tools['enable_backup'])),
        'pending_auto_restore' => $pending_auto_restore
    ];
    
    wp_send_json_success([
        'processed' => $processed, 
        'results' => $results,
        'debug' => $debug_info,
        'backup' => $backup_summary
    ]);
});

/**
 * Call AI engine with given parameters
 * Returns generated text or WP_Error on failure
 */
function ai_seo_call_ai_engine($engine, $api_key, $model, $prompt, $max_tokens, $temperature, $frequency_penalty, $presence_penalty, $top_p) {
    switch ($engine) {
        case 'chatgpt':
            return ai_seo_call_chatgpt($api_key, $model, $prompt, $max_tokens, $temperature, $frequency_penalty, $presence_penalty, $top_p);
        
        case 'claude':
            return ai_seo_call_claude($api_key, $model, $prompt, $max_tokens, $temperature);
        
        case 'openrouter':
            return ai_seo_call_openrouter($api_key, $model, $prompt, $max_tokens, $temperature);
        
        case 'google':
            return ai_seo_call_google($api_key, $model, $prompt, $max_tokens, $temperature);
        
        case 'microsoft':
            return ai_seo_call_microsoft($api_key, $model, $prompt, $max_tokens, $temperature);
        
        case 'xai':
            return ai_seo_call_xai($api_key, $model, $prompt, $max_tokens, $temperature);
        
        default:
            return new WP_Error('unsupported_engine', "Unsupported AI engine: $engine");
    }
}

/**
 * ChatGPT (OpenAI) API
 */
function ai_seo_call_chatgpt($api_key, $model, $prompt, $max_tokens, $temperature, $frequency_penalty, $presence_penalty, $top_p) {
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful SEO assistant for WooCommerce products. Generate concise, SEO-optimized content.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'frequency_penalty' => $frequency_penalty,
            'presence_penalty' => $presence_penalty,
            'top_p' => $top_p,
        ]),
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for API errors
    if ($status_code === 402 || (isset($data['error']) && stripos(json_encode($data['error']), 'quota') !== false)) {
        return new WP_Error('insufficient_credits', '⚠️ OUT OF CREDITS: Your account has exceeded its quota. Please check your billing at platform.openai.com/account/billing');
    }
    
    if ($status_code === 429) {
        return new WP_Error('rate_limit', '⚠️ RATE LIMIT: Too many requests. Please wait a moment and try again, or increase the delay between API calls in Settings.');
    }
    
    if ($status_code === 401) {
        return new WP_Error('invalid_api_key', '⚠️ INVALID API KEY: Your API key is invalid. Please check your API key in AI Settings.');
    }
    
    if (isset($data['error'])) {
        $error_msg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
        return new WP_Error('api_error', 'API Error: ' . $error_msg);
    }
    
    if (!isset($data['choices'][0]['message']['content'])) {
        return new WP_Error('invalid_response', 'Invalid API response from ChatGPT');
    }

    return $data['choices'][0]['message']['content'];
}

/**
 * Claude (Anthropic) API
 */
function ai_seo_call_claude($api_key, $model, $prompt, $max_tokens, $temperature) {
    $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => json_encode([
            'model' => $model,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]),
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $status_code = wp_remote_retrieve_response_code($response);
    $data = json_decode($body, true);
    
    // Log full response for debugging
    error_log('AI SEO DEBUG: Claude API Status Code: ' . $status_code);
    error_log('AI SEO DEBUG: Claude API Response: ' . substr($body, 0, 500));
    
    // Check for specific API errors
    $error_json = isset($data['error']) ? json_encode($data['error']) : '';
    
    // Out of credits - Anthropic returns 403 when billing disabled
    if ($status_code === 402 || $status_code === 403 || 
        stripos($error_json, 'credit') !== false || 
        stripos($error_json, 'billing') !== false ||
        stripos($error_json, 'disabled') !== false ||
        stripos($error_json, 'quota') !== false) {
        return new WP_Error('insufficient_credits', '⚠️ OUT OF CREDITS: Your account has insufficient credits. Please add credits at console.anthropic.com/settings/billing');
    }
    
    if ($status_code === 429) {
        return new WP_Error('rate_limit', '⚠️ RATE LIMIT: Too many requests. Please wait a moment and try again, or increase the delay between API calls in Settings.');
    }
    
    if ($status_code === 401) {
        return new WP_Error('invalid_api_key', '⚠️ INVALID API KEY: Your API key is invalid. Please check your API key in AI Settings.');
    }
    
    if (!isset($data['content'][0]['text'])) {
        // Build comprehensive error message
        $error_parts = [];
        
        if (isset($data['error'])) {
            if (isset($data['error']['type'])) {
                $error_parts[] = 'Type: ' . $data['error']['type'];
            }
            if (isset($data['error']['message'])) {
                $error_parts[] = $data['error']['message'];
            }
        }
        
        if ($status_code !== 200) {
            $error_parts[] = 'HTTP Status: ' . $status_code;
        }
        
        if (empty($error_parts)) {
            $error_parts[] = 'Invalid API response from Claude';
            $error_parts[] = 'Response: ' . substr($body, 0, 200);
        }
        
        $error_message = implode(' | ', $error_parts);
        return new WP_Error('claude_api_error', $error_message);
    }

    return $data['content'][0]['text'];
}

/**
 * OpenRouter API
 */
function ai_seo_call_openrouter($api_key, $model, $prompt, $max_tokens, $temperature) {
    $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
            'HTTP-Referer' => home_url(),
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ]),
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for API errors
    if ($status_code === 402 || (isset($data['error']) && stripos(json_encode($data['error']), 'credit') !== false)) {
        return new WP_Error('insufficient_credits', '⚠️ OUT OF CREDITS: Your account has insufficient credits. Please add credits at openrouter.ai/credits');
    }
    
    if ($status_code === 429 || (isset($data['error']) && stripos(json_encode($data['error']), 'rate') !== false)) {
        return new WP_Error('rate_limit', '⚠️ RATE LIMIT: Too many requests. Please wait a moment and try again, or increase the delay between API calls in Settings.');
    }
    
    if ($status_code === 401 || (isset($data['error']) && stripos(json_encode($data['error']), 'invalid') !== false && stripos(json_encode($data['error']), 'key') !== false)) {
        return new WP_Error('invalid_api_key', '⚠️ INVALID API KEY: Your API key is invalid. Please check your API key in AI Settings.');
    }
    
    if (isset($data['error'])) {
        $error_msg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
        return new WP_Error('api_error', 'API Error: ' . $error_msg);
    }
    
    if (!isset($data['choices'][0]['message']['content'])) {
        return new WP_Error('invalid_response', 'Invalid API response from OpenRouter');
    }

    return $data['choices'][0]['message']['content'];
}

/**
 * Google Gemini API
 */
function ai_seo_call_google($api_key, $model, $prompt, $max_tokens, $temperature) {
    $response = wp_remote_post('https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . $api_key, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $max_tokens,
                'temperature' => $temperature,
            ]
        ]),
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for API errors
    if ($status_code === 403 || (isset($data['error']) && stripos(json_encode($data['error']), 'quota') !== false)) {
        return new WP_Error('insufficient_credits', '⚠️ QUOTA EXCEEDED: Your API quota has been exceeded. Please check billing at console.cloud.google.com/billing');
    }
    
    if ($status_code === 429) {
        return new WP_Error('rate_limit', '⚠️ RATE LIMIT: Too many requests. Please wait a moment and try again, or increase the delay between API calls in Settings.');
    }
    
    if ($status_code === 400 && isset($data['error'])) {
        return new WP_Error('invalid_api_key', '⚠️ INVALID API KEY: Your API key is invalid. Please check your API key in AI Settings.');
    }
    
    if (isset($data['error'])) {
        $error_msg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
        return new WP_Error('api_error', 'API Error: ' . $error_msg);
    }
    
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return new WP_Error('invalid_response', 'Invalid API response from Google Gemini');
    }

    return $data['candidates'][0]['content']['parts'][0]['text'];
}

/**
 * Microsoft Azure OpenAI API
 */
function ai_seo_call_microsoft($api_key, $model, $prompt, $max_tokens, $temperature) {
    // Note: Azure requires a deployment endpoint which should be configured
    $azure_endpoint = get_option('ai_seo_azure_endpoint', '');
    
    if (empty($azure_endpoint)) {
        return new WP_Error('missing_config', 'Azure endpoint not configured');
    }

    $response = wp_remote_post($azure_endpoint . '/openai/deployments/' . $model . '/chat/completions?api-version=2023-05-15', [
        'headers' => [
            'Content-Type' => 'application/json',
            'api-key' => $api_key,
        ],
        'body' => json_encode([
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ]),
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for API errors
    if ($status_code === 402 || (isset($data['error']) && stripos(json_encode($data['error']), 'quota') !== false)) {
        return new WP_Error('insufficient_credits', '⚠️ QUOTA EXCEEDED: Your account quota has been exceeded. Please check billing at portal.azure.com');
    }
    
    if ($status_code === 429) {
        return new WP_Error('rate_limit', '⚠️ RATE LIMIT: Too many requests. Please wait a moment and try again, or increase the delay between API calls in Settings.');
    }
    
    if ($status_code === 401) {
        return new WP_Error('invalid_api_key', '⚠️ INVALID API KEY: Your API key is invalid. Please check your API key in AI Settings.');
    }
    
    if (isset($data['error'])) {
        $error_msg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
        return new WP_Error('api_error', 'API Error: ' . $error_msg);
    }
    
    if (!isset($data['choices'][0]['message']['content'])) {
        return new WP_Error('invalid_response', 'Invalid API response from Azure OpenAI');
    }

    return $data['choices'][0]['message']['content'];
}

/**
 * X.AI Grok API
 */
function ai_seo_call_xai($api_key, $model, $prompt, $max_tokens, $temperature) {
    $response = wp_remote_post('https://api.x.ai/v1/chat/completions', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ]),
        'timeout' => 120,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for API errors
    if ($status_code === 402 || (isset($data['error']) && stripos(json_encode($data['error']), 'credit') !== false)) {
        return new WP_Error('insufficient_credits', '⚠️ OUT OF CREDITS: Your account has insufficient credits. Please check your account at x.ai');
    }
    
    if ($status_code === 429) {
        return new WP_Error('rate_limit', '⚠️ RATE LIMIT: Too many requests. Please wait a moment and try again, or increase the delay between API calls in Settings.');
    }
    
    if ($status_code === 401) {
        return new WP_Error('invalid_api_key', '⚠️ INVALID API KEY: Your API key is invalid. Please check your API key in AI Settings.');
    }
    
    if (isset($data['error'])) {
        $error_msg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
        return new WP_Error('api_error', 'API Error: ' . $error_msg);
    }
    
    if (!isset($data['choices'][0]['message']['content'])) {
        return new WP_Error('invalid_response', 'Invalid API response from X.AI');
    }

    return $data['choices'][0]['message']['content'];
}

/**
 * AJAX handler to save Generate Content button position
 */
add_action('wp_ajax_ai_seo_save_button_position', function() {
    // Get position data
    $position = json_decode(stripslashes($_POST['position'] ?? '{}'), true);
    
    if (empty($position) || !isset($position['top']) || !isset($position['left'])) {
        wp_send_json_error(['error' => 'Invalid position data']);
        return;
    }
    
    // Save to user meta (per-user setting)
    $user_id = get_current_user_id();
    update_user_meta($user_id, 'ai_seo_button_position', $position);
    
    wp_send_json_success(['message' => 'Button position saved', 'position' => $position]);
});

/**
 * AJAX handler to reset button position to default
 */
add_action('wp_ajax_ai_seo_reset_button_position', function() {
    $user_id = get_current_user_id();
    delete_user_meta($user_id, 'ai_seo_button_position');
    
    wp_send_json_success(['message' => 'Button position reset to default']);
});

/**
 * AJAX handler to calculate and save RankMath SEO score
 * v1.2.1.2 - NEW APPROACH
 * 
 * This triggers WordPress save hooks which RankMath listens to.
 * This persists any scores that RankMath has calculated.
 */
add_action('wp_ajax_ai_seo_calculate_rankmath_score', function() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_seo_nonce')) {
        wp_send_json_error(['error' => 'Invalid nonce']);
        return;
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if (!$product_id) {
        wp_send_json_error(['error' => 'Invalid product ID']);
        return;
    }
    
    ai_seo_log("=== VERIFYING RANKMATH SCORE FOR PRODUCT $product_id (v1.2.1.4) ===");
    
    // Check if frontend clicked the Update button
    $button_clicked = isset($_POST['button_clicked']) && $_POST['button_clicked'] === 'true';
    $click_method = $_POST['click_method'] ?? 'none';
    
    if ($button_clicked) {
        ai_seo_log("✓ Frontend successfully clicked Update button using: $click_method");
        ai_seo_log("Waiting for WordPress save to complete, then verifying score...");
    } else {
        ai_seo_log("⚠ Frontend could not click Update button (cross-origin restrictions)");
        ai_seo_log("Attempting backend save as fallback...");
    }
    
    // Check if post exists
    $post = get_post($product_id);
    if (!$post) {
        ai_seo_log("ERROR: Product $product_id not found");
        wp_send_json_error(['error' => 'Product not found']);
        return;
    }
    
    ai_seo_log("✓ Product exists: " . $post->post_title);
    
    // Check which SEO provider is active (v1.3.0)
    $provider = ai_seo_get_provider();
    $capabilities = $provider->get_capabilities();
    
    if (!$capabilities['supports_scoring']) {
        ai_seo_log("INFO: " . $provider->get_name() . " does not support numeric scoring");
        wp_send_json_success([
            'message' => $provider->get_name() . ' does not provide numeric scoring',
            'provider' => $provider->get_name()
        ]);
        return;
    }
    
    ai_seo_log("✓ SEO Provider: " . $provider->get_name() . " (supports scoring)");
    
    // Get current score before
    $score_before = ai_seo_get_score($product_id);
    ai_seo_log("Score before: " . ($score_before !== null ? $score_before : 'NOT SET'));
    
    // Trigger SEO plugin hooks to recalculate and save score
    // Method 1: Trigger save_post hook (this should make SEO plugin save its calculated score)
    do_action('save_post', $product_id, $post, true);
    ai_seo_log("✓ Triggered save_post hook");
    
    // Method 2: Also try provider-specific hooks (e.g., Rank Math)
    if ($provider->get_name() === 'Rank Math') {
        do_action('rank_math/after_save_post', $product_id);
        ai_seo_log("✓ Triggered rank_math/after_save_post hook");
    }
    
    // Method 3: Force a post update to ensure save hooks fire
    wp_update_post(['ID' => $product_id]);
    ai_seo_log("✓ Executed wp_update_post");
    
    // Small delay to let hooks finish
    usleep(500000); // 0.5 seconds
    
    // Get score after (v1.3.0 - Using SEO provider system)
    $score_after = ai_seo_get_score($product_id);
    ai_seo_log("Score after: " . ($score_after !== null ? $score_after : 'NOT SET'));
    
    // Check if score changed
    if ($score_after && $score_after !== $score_before) {
        ai_seo_log("✓ SUCCESS: Score updated from '$score_before' to '$score_after'");
        wp_send_json_success([
            'message' => 'Score calculated successfully',
            'product_id' => $product_id,
            'score_before' => $score_before,
            'score_after' => $score_after,
            'button_clicked' => $button_clicked
        ]);
    } else if ($score_after) {
        ai_seo_log("✓ Score exists: $score_after (no change needed)");
        wp_send_json_success([
            'message' => 'Score already calculated',
            'product_id' => $product_id,
            'score_after' => $score_after,
            'button_clicked' => $button_clicked
        ]);
    } else {
        ai_seo_log("✗ PROBLEM: Score still not set after all attempts");
        ai_seo_log("This means either:");
        ai_seo_log("  1. Button click failed (cross-origin issue)");
        ai_seo_log("  2. RankMath didn't calculate a score (content issue)");
        ai_seo_log("  3. RankMath calculated but didn't save (plugin issue)");
        ai_seo_log("SOLUTION: Manually open product edit page, wait 10 seconds, click Update");
        wp_send_json_success([
            'message' => 'Score not set - needs manual update',
            'product_id' => $product_id,
            'score_after' => 'NOT SET',
            'button_clicked' => $button_clicked,
            'note' => 'RankMath score requires manual page visit. Check debug log for details.'
        ]);
    }
    
    ai_seo_log("=== RANKMATH SCORE VERIFICATION COMPLETE FOR PRODUCT $product_id ===");
});

// v1.3.1Q: AJAX handler to clear ALL debug log files
add_action('wp_ajax_ai_seo_clear_log', function() {
    check_ajax_referer('ai_seo_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    // v1.3.1Q: Clear ALL AI SEO related log files
    $log_files = [
        'ai-seo-debug.log',
        'ai-seo-activation.log',
        'ai-seo.log',
        'seo-focus-debug.log',
        'seo-focus-activation.log'
    ];
    
    $deleted = 0;
    $failed = [];
    
    foreach ($log_files as $filename) {
        $filepath = WP_CONTENT_DIR . '/' . $filename;
        if (file_exists($filepath)) {
            if (@unlink($filepath)) {
                $deleted++;
            } else {
                $failed[] = $filename;
            }
        }
    }
    
    if (empty($failed)) {
        wp_send_json_success([
            'message' => "Cleared $deleted log file(s) successfully",
            'deleted' => $deleted
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Could not delete: ' . implode(', ', $failed) . '. Check file permissions.',
            'deleted' => $deleted,
            'failed' => $failed
        ]);
    }
});

// v2.1.5: AJAX handler to view log file contents
add_action('wp_ajax_ai_seo_view_log', function() {
    check_ajax_referer('ai_seo_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    $filename = sanitize_file_name($_POST['filename'] ?? '');
    
    // Only allow specific log files
    $allowed_files = [
        'ai-seo-debug.log',
        'ai-seo-activation.log',
        'ai-seo.log',
        'seo-focus-debug.log',
        'seo-focus-activation.log'
    ];
    
    if (!in_array($filename, $allowed_files)) {
        wp_send_json_error(['message' => 'Invalid file']);
        return;
    }
    
    $filepath = WP_CONTENT_DIR . '/' . $filename;
    
    if (!file_exists($filepath)) {
        wp_send_json_error(['message' => 'Log file not found']);
        return;
    }
    
    // Read last 100KB to avoid memory issues
    $max_size = 100 * 1024;
    $filesize = filesize($filepath);
    
    if ($filesize > $max_size) {
        $fp = fopen($filepath, 'r');
        fseek($fp, -$max_size, SEEK_END);
        $content = "... (showing last 100KB of " . size_format($filesize) . ")\n\n" . fread($fp, $max_size);
        fclose($fp);
    } else {
        $content = file_get_contents($filepath);
    }
    
    wp_send_json_success([
        'content' => $content,
        'size' => size_format($filesize)
    ]);
});

// v1.3.2: AJAX handler for manual restore
add_action('wp_ajax_ai_seo_restore_product', function() {
    check_ajax_referer('ai_seo_nonce', 'nonce');
    
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    
    if (!$post_id) {
        wp_send_json_error(['message' => 'Invalid product ID']);
        return;
    }
    
    $restored = ai_seo_restore_backup($post_id);
    
    if ($restored) {
        wp_send_json_success([
            'message' => 'Product restored successfully',
            'post_id' => $post_id
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Restore failed - no backup found or error occurred',
            'post_id' => $post_id
        ]);
    }
});

// v1.3.2: AJAX handler for approving (keeping) new content
add_action('wp_ajax_ai_seo_approve_product', function() {
    check_ajax_referer('ai_seo_nonce', 'nonce');
    
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    
    if (!$post_id) {
        wp_send_json_error(['message' => 'Invalid product ID']);
        return;
    }
    
    // Delete backup (keep new content)
    $deleted = ai_seo_delete_backup($post_id);
    
    wp_send_json_success([
        'message' => 'New content approved',
        'post_id' => $post_id,
        'backup_deleted' => $deleted
    ]);
});

// v1.3.2: AJAX handler for bulk approve/restore
add_action('wp_ajax_ai_seo_bulk_backup_action', function() {
    check_ajax_referer('ai_seo_nonce', 'nonce');
    
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    $restore_ids = isset($_POST['restore_ids']) ? array_map('intval', (array)$_POST['restore_ids']) : [];
    $approve_ids = isset($_POST['approve_ids']) ? array_map('intval', (array)$_POST['approve_ids']) : [];
    
    $restored = 0;
    $approved = 0;
    $errors = [];
    
    // Restore selected products
    foreach ($restore_ids as $post_id) {
        if ($post_id && ai_seo_restore_backup($post_id)) {
            $restored++;
            ai_seo_log("Bulk action: Restored Product $post_id");
        } else {
            $errors[] = "Failed to restore Product $post_id";
        }
    }
    
    // Approve (delete backup for) remaining products
    foreach ($approve_ids as $post_id) {
        if ($post_id) {
            ai_seo_delete_backup($post_id);
            $approved++;
            ai_seo_log("Bulk action: Approved Product $post_id");
        }
    }
    
    wp_send_json_success([
        'message' => "Restored: $restored, Approved: $approved",
        'restored' => $restored,
        'approved' => $approved,
        'errors' => $errors
    ]);
});

// v1.3.2a: AJAX handler for auto-restore AFTER score calculation
add_action('wp_ajax_ai_seo_auto_restore_check', function() {
    check_ajax_referer('ai_seo_nonce', 'nonce');
    
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    // Get scores from client (calculated by RankMath)
    $scores = isset($_POST['scores']) ? json_decode(stripslashes($_POST['scores']), true) : [];
    
    if (empty($scores)) {
        wp_send_json_error(['message' => 'No scores provided']);
        return;
    }
    
    // Get threshold from settings
    $tools = get_option('ai_seo_tools', []);
    $threshold = intval($tools['restore_threshold'] ?? 80);
    
    ai_seo_log("=== AUTO-RESTORE CHECK (Threshold: $threshold) ===");
    ai_seo_log("Received " . count($scores) . " products with calculated scores");
    
    $restored = 0;
    $kept = 0;
    $results = [];
    
    foreach ($scores as $post_id => $new_score) {
        $post_id = intval($post_id);
        $new_score = intval($new_score);
        
        // Check if product has backup
        $backup = ai_seo_get_backup($post_id);
        
        if (!$backup) {
            ai_seo_log("Product $post_id: No backup found, skipping");
            $results[$post_id] = ['action' => 'skipped', 'reason' => 'No backup'];
            continue;
        }
        
        $original_score = intval($backup['seo_score'] ?? 0);
        
        ai_seo_log("Product $post_id: Original=$original_score, New=$new_score, Threshold=$threshold");
        
        if ($new_score <= $threshold) {
            // Restore original content
            $success = ai_seo_restore_backup($post_id);
            if ($success) {
                $restored++;
                $results[$post_id] = [
                    'action' => 'restored',
                    'original_score' => $original_score,
                    'new_score' => $new_score,
                    'reason' => "Score $new_score ≤ threshold $threshold"
                ];
                ai_seo_log("Product $post_id: RESTORED (Score $new_score ≤ $threshold)");
            }
        } else {
            // v1.4.0-fix4: Check if generation was incomplete before deleting backup
            $generation_incomplete = get_post_meta($post_id, '_ai_seo_generation_incomplete', true);
            $failed_fields = get_post_meta($post_id, '_ai_seo_failed_fields', true);
            
            if ($generation_incomplete) {
                // Generation was incomplete - RESTORE instead of keeping partial content
                ai_seo_log("Product $post_id: Generation was INCOMPLETE (failed fields: " . implode(', ', (array)$failed_fields) . ") - Restoring original content");
                
                $success = ai_seo_restore_backup($post_id);
                if ($success) {
                    $restored++;
                    $results[$post_id] = [
                        'action' => 'restored',
                        'original_score' => $original_score,
                        'new_score' => $new_score,
                        'reason' => "Generation incomplete - failed fields: " . implode(', ', (array)$failed_fields)
                    ];
                    ai_seo_log("Product $post_id: RESTORED due to incomplete generation");
                    
                    // Clear the incomplete flags
                    delete_post_meta($post_id, '_ai_seo_generation_incomplete');
                    delete_post_meta($post_id, '_ai_seo_failed_fields');
                }
            } else {
                // Keep new content, delete backup
                ai_seo_delete_backup($post_id);
                $kept++;
                $results[$post_id] = [
                    'action' => 'kept',
                    'original_score' => $original_score,
                    'new_score' => $new_score,
                    'reason' => "Score $new_score > threshold $threshold"
                ];
                ai_seo_log("Product $post_id: KEPT (Score $new_score > $threshold)");
            }
        }
    }
    
    ai_seo_log("Auto-restore complete: Kept $kept, Restored $restored");
    
    wp_send_json_success([
        'message' => "Auto-restore complete: Kept $kept, Restored $restored",
        'kept' => $kept,
        'restored' => $restored,
        'threshold' => $threshold,
        'results' => $results
    ]);
});
