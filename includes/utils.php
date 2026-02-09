<?php
if (!defined('ABSPATH')) {
    exit;
}

function ai_seo_get_cached_option($option_name, $default) {
    $transient_key = 'ai_seo_' . $option_name;
    $cached = get_transient($transient_key);
    if (false === $cached) {
        $value = get_option($option_name, $default);
        set_transient($transient_key, $value, HOUR_IN_SECONDS);
        return $value;
    }
    return $cached;
}

function ai_seo_log($message) {
    // v1.3.1Q: Check if debug logging is enabled
    static $logging_enabled = null;
    
    if ($logging_enabled === null) {
        $tools = get_option('ai_seo_tools', []);
        $logging_enabled = !empty($tools['enable_debug_logging']);
    }
    
    // Skip logging if disabled
    if (!$logging_enabled) {
        return;
    }
    
    $log_file = WP_CONTENT_DIR . '/ai-seo-debug.log';
    $formatted = 'AI SEO: ' . $message . ' at ' . date('Y-m-d H:i:s') . PHP_EOL;
    file_put_contents($log_file, $formatted, FILE_APPEND);
}

function ai_seo_process_prompt($prompt, $replacements) {
    $prompt = str_replace(
        array_keys($replacements),
        array_values($replacements),
        wp_kses_post($prompt)
    );

    preg_match_all('/\[current_([a-z0-9-]+)\]/i', $prompt, $matches);
    foreach ($matches[0] as $index => $match) {
        $key = "current_{$matches[1][$index]}";
        $prompt = str_replace($match, isset($replacements[$key]) ? $replacements[$key] : '', $prompt);
    }
    return $prompt;
}

/**
 * Sanitize focus keyword by removing AI formatting artifacts
 * v1.2.1.10 - Smart sanitization that preserves actual content
 * 
 * Removes:
 * - Markdown headers at line start (## Focus Keyword, ### SEO, etc.)
 * - Labels with colons (Focus Keyword: X, SEO-Optimized Focus Keyword: X)
 * - Exact duplicate lines (keyword repeated twice)
 * - Extra whitespace and line breaks
 * 
 * Preserves:
 * - Actual product keywords that contain these words (e.g. "SEO Analysis Tool")
 * - Legitimate content
 */
/**
 * Remove AI conversational patterns from any output
 * v1.3.1h - Bulletproof sanitization for commercial plugin
 * 
 * Removes patterns like:
 * - "I'll generate a more specific..."
 * - "Here's the title: ..."
 * - "Let me create..."
 * - "The keyword is: ..."
 * 
 * @param string $text Raw AI output
 * @return string Cleaned output with conversational patterns removed
 */
function ai_seo_remove_ai_chattiness($text) {
    if (empty($text)) {
        return $text;
    }
    
    // Remove common conversational patterns
    $chatty_patterns = array(
        // "I'll/I will..." patterns
        '/^I\'ll\s+.*?:\s*/i',
        '/^I\s+will\s+.*?:\s*/i',
        '/^I\'m\s+going\s+to\s+.*?:\s*/i',
        
        // "Let me..." patterns
        '/^Let\s+me\s+.*?:\s*/i',
        
        // "Here's/Here is..." patterns  
        '/^Here\'s\s+.*?:\s*/i',
        '/^Here\s+is\s+.*?:\s*/i',
        '/^Here\s+are\s+.*?:\s*/i',
        
        // "The X is/are..." patterns
        '/^The\s+\w+\s+is\s*:\s*/i',
        '/^The\s+\w+\s+are\s*:\s*/i',
        
        // "It X..." patterns (NEW - catches "It prioritizes...")
        '/^It\s+(prioritizes?|emphasizes?|highlights?|focuses?|includes?|features?|showcases?|combines?)\s+.*?[:.]?\s*/i',
        
        // "This X..." patterns
        '/^This\s+(keyword|title|phrase|focuses?|emphasizes?)\s+.*?[:.]?\s*/i',
        
        // "Based on..." patterns
        '/^Based\s+on\s+.*?,\s*/i',
        '/^Considering\s+.*?,\s*/i',
        '/^Given\s+.*?,\s*/i',
        
        // "Sure, ..." patterns
        '/^Sure,?\s+/i',
        '/^Certainly,?\s+/i',
        '/^Of\s+course,?\s+/i',
        
        // Remove anything before colon that's explanatory
        '/^.*?(includes?|with|that|differentiators?|as\s+follows?|would\s+be)\s*:\s*/i',
    );
    
    $original = $text;
    foreach ($chatty_patterns as $pattern) {
        $text = preg_replace($pattern, '', $text);
    }
    
    // If we removed the entire content, return original (better than nothing)
    $text = trim($text);
    if (empty($text)) {
        return trim($original);
    }
    
    // NEW: Check if output is a full sentence (description) instead of a keyword phrase
    // Keywords are noun phrases (3-8 words), not complete sentences with verbs
    $sentence_indicators = array(
        // Has subject-verb structure indicating it's a sentence not a keyword
        '/\b(prioritizes?|emphasizes?|highlights?|focuses?|includes?|features?|showcases?|combines?|ensures?|provides?|offers?|sets?|makes?|creates?)\b/i',
        // Has connectors that indicate prose
        '/\b(while|whereas|although|because|since|as|when|if|unless|whether|from|apart)\b/i',
        // Has relative clauses
        '/\b(that|which|who|whom|whose)\s+\w+\s+\w+/i',
        // Talks about buyers/customers/elements (meta-description of what makes a keyword)
        '/\b(buyers?|customers?|elements?|filter|composition|most)\b/i',
    );
    
    $is_sentence = false;
    foreach ($sentence_indicators as $pattern) {
        if (preg_match($pattern, $text)) {
            $is_sentence = true;
            break;
        }
    }
    
    // If it's clearly a sentence, try to extract a keyword or reject it
    if ($is_sentence || strlen($text) > 80) {
        // v1.3.1j: GENERIC keyword extraction for ANY product type
        // Instead of looking for jewelry patterns, use general heuristics
        
        // Strategy 1: Look for brand + model/descriptor + product type pattern
        // Works for: "Samsung 65-Inch Smart TV", "Nike Air Max Sneakers", etc.
        // Pattern: Capitalized words followed by descriptors
        if (preg_match('/\b([A-Z][a-z]+(?:\s+[A-Z]?[a-z]+){0,2})\s+([^.,;]+?)(?:\s+for\s+|\s+with\s+|$)/i', $text, $matches)) {
            $candidate = trim($matches[0]);
            // Take first 8 words maximum
            $words = explode(' ', $candidate);
            if (count($words) <= 10) {
                $text = $candidate;
            }
        }
        
        // Strategy 2: If no clear pattern, look for capitalized noun phrases
        // This catches product names even without obvious patterns
        if (strlen($text) > 80) {
            // Extract sequences of capitalized words + descriptors
            if (preg_match('/\b[A-Z][a-z]+(?:\s+[A-Z]?[a-z0-9-]+){1,6}\b/', $text, $matches)) {
                $text = $matches[0];
            }
        }
        
        // Strategy 3: Last resort - take first noun phrase (first 5-8 words before any verb)
        if (strlen($text) > 80 || str_word_count($text) > 10) {
            // Find first verb and stop there
            if (preg_match('/^((?:\w+\s+){1,8}?)(?:prioritizes?|emphasizes?|includes?|features?|sets?|makes?|provides?|ensures?|highlights?)/i', $text, $matches)) {
                $text = trim($matches[1]);
            } else {
                // Just take first 8 words
                $words = explode(' ', $text);
                $text = implode(' ', array_slice($words, 0, 8));
            }
        }
        
        // Clean up any remaining punctuation at the end
        $text = rtrim($text, '.,;:');
        
        // If we still have prose indicators after extraction, log it
        if (preg_match('/\b(prioritizes?|emphasizes?|buyers?|filter|composition)\b/i', $text)) {
            error_log('AI SEO: Focus keyword still contains prose after extraction: ' . substr($text, 0, 150));
        }
    }
    
    // If there are multiple sentences, keep only the last one
    // This catches: "I'll generate a specific keyword. 10K Gold Diamond Ring"
    if (preg_match('/^.*[.!]\s+([A-Z].*)$/s', $text, $matches)) {
        $text = $matches[1];
    }
    
    // Remove markdown formatting
    $text = preg_replace('/^#+\s*/', '', $text); // Headers
    $text = preg_replace('/^\*+\s*/', '', $text); // Bullets
    $text = preg_replace('/^-+\s*/', '', $text);  // Dashes
    
    // Remove quotes if the entire text is wrapped in them
    if (preg_match('/^["\'](.+)["\']$/', $text, $matches)) {
        $text = $matches[1];
    }
    
    return trim($text);
}

/**
 * Sanitize focus keyword with aggressive AI chattiness removal
 * v1.2.1.10 - Smart sanitization to remove AI-generated formatting artifacts
 * v1.3.1h - Enhanced with bulletproof chattiness removal
 */
function ai_seo_sanitize_focus_keyword($keyword) {
    if (empty($keyword)) {
        return $keyword;
    }
    
    // v1.4.4: Simplified sanitization - the aggressive chattiness removal was destroying good keywords
    // The AI usually returns good keywords, we just need to clean obvious formatting issues
    
    // Remove markdown separators and formatting
    $keyword = preg_replace('/[-]{2,}/', '', $keyword);
    $keyword = preg_replace('/[*]{2,}/', '', $keyword);
    $keyword = preg_replace('/[#]+\s*/', '', $keyword);
    
    // Take only the first non-empty line (ignore any explanatory text after)
    $lines = explode("\n", $keyword);
    $keyword = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strlen($line) > 2) {
            $keyword = $line;
            break;
        }
    }
    
    // Remove obvious labels at the start
    $label_patterns = array(
        '/^Focus\s+Keyword\s*:\s*/i',
        '/^SEO-Optimized\s+Focus\s+Keyword\s*:\s*/i',
        '/^SEO\s+Focus\s+Keyword\s*:\s*/i',
        '/^Keyword\s*:\s*/i',
        '/^Primary\s+Keyword\s*:\s*/i',
        '/^Target\s+Keyword\s*:\s*/i',
        '/^Here\'?s?\s+(the|a|your)?\s*/i',
        '/^The\s+focus\s+keyword\s+(is|would\s+be)\s*:?\s*/i',
    );
    
    foreach ($label_patterns as $pattern) {
        $keyword = preg_replace($pattern, '', $keyword);
    }
    
    // Remove surrounding quotes
    $keyword = trim($keyword, '"\' ');
    
    // Remove trailing punctuation
    $keyword = rtrim($keyword, '.,;:');
    
    // Final cleanup
    $keyword = preg_replace('/\s+/', ' ', $keyword);
    $keyword = trim($keyword);
    
    return $keyword;
}

/**
 * Detect active image optimizer plugins
 * v1.3.1 - Image optimizer bypass feature
 * 
 * @return array List of detected optimizer plugin slugs
 */
function ai_seo_detect_image_optimizers() {
    $optimizers = [];
    
    // ShortPixel
    if (class_exists('ShortPixel\ShortPixelPlugin') || class_exists('WPShortPixel')) {
        $optimizers[] = 'shortpixel';
    }
    
    // WP Smush
    if (class_exists('WpSmush') || class_exists('Smush\Core\Core')) {
        $optimizers[] = 'smush';
    }
    
    // Imagify
    if (class_exists('Imagify_Plugin') || defined('IMAGIFY_VERSION')) {
        $optimizers[] = 'imagify';
    }
    
    // EWWW Image Optimizer
    if (class_exists('EWWW_Image_Optimizer') || defined('EWWW_IMAGE_OPTIMIZER_VERSION')) {
        $optimizers[] = 'ewww';
    }
    
    // Optimole
    if (class_exists('Optml_Main') || defined('OPTIMOLE_VERSION')) {
        $optimizers[] = 'optimole';
    }
    
    return $optimizers;
}

/**
 * Temporarily disable image optimizer plugins during content generation
 * v1.3.1 - Speeds up bulk operations significantly
 */
/**
 * Temporarily disable image optimizer plugins during content generation
 * v1.3.1 - Speeds up bulk operations significantly
 */
function ai_seo_disable_image_optimizers() {
    $tools = get_option('ai_seo_tools', []);
    
    // Only disable if feature is enabled
    if (empty($tools['disable_image_optimization'])) {
        return;
    }
    
    // ShortPixel
    if (class_exists('WPShortPixel')) {
        remove_action('wp_generate_attachment_metadata', array('WPShortPixel', 'handleImageUploadHook'), 10);
        remove_action('save_post', array('WPShortPixel', 'handleSavePost'), 10);
        ai_seo_log("ShortPixel temporarily disabled for content generation");
    }
    
    // WP Smush
    if (class_exists('WpSmush')) {
        remove_filter('wp_generate_attachment_metadata', 'wp_smush_image', 10);
        ai_seo_log("Smush temporarily disabled for content generation");
    }
    
    // Imagify
    if (class_exists('Imagify_Plugin')) {
        remove_action('wp_generate_attachment_metadata', 'imagify_optimize_attachment', 10);
        ai_seo_log("Imagify temporarily disabled for content generation");
    }
    
    // EWWW
    if (class_exists('EWWW_Image_Optimizer')) {
        remove_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 10);
        ai_seo_log("EWWW temporarily disabled for content generation");
    }
    
    // Optimole
    if (defined('OPTIMOLE_VERSION')) {
        remove_filter('wp_update_attachment_metadata', 'optml_handle_upload', 10);
        ai_seo_log("Optimole temporarily disabled for content generation");
    }
}

/**
 * Re-enable image optimizer plugins after content generation
 * v1.3.1 - Restore normal functionality
 */
function ai_seo_reenable_image_optimizers() {
    $tools = get_option('ai_seo_tools', []);
    
    // Only re-enable if feature was enabled
    if (empty($tools['disable_image_optimization'])) {
        return;
    }
    
    // ShortPixel
    if (class_exists('WPShortPixel')) {
        add_action('wp_generate_attachment_metadata', array('WPShortPixel', 'handleImageUploadHook'), 10, 2);
        add_action('save_post', array('WPShortPixel', 'handleSavePost'), 10, 3);
        ai_seo_log("ShortPixel re-enabled");
    }
    
    // WP Smush
    if (class_exists('WpSmush')) {
        add_filter('wp_generate_attachment_metadata', 'wp_smush_image', 10, 2);
        ai_seo_log("Smush re-enabled");
    }
    
    // Imagify
    if (class_exists('Imagify_Plugin')) {
        add_action('wp_generate_attachment_metadata', 'imagify_optimize_attachment', 10, 2);
        ai_seo_log("Imagify re-enabled");
    }
    
    // EWWW
    if (class_exists('EWWW_Image_Optimizer')) {
        add_filter('wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 10, 2);
        ai_seo_log("EWWW re-enabled");
    }
    
    // Optimole
    if (defined('OPTIMOLE_VERSION')) {
        add_filter('wp_update_attachment_metadata', 'optml_handle_upload', 10, 2);
        ai_seo_log("Optimole re-enabled");
    }
}

/**
 * v1.3.2: Create backup of product content before generation
 * 
 * @param int $post_id Product ID
 * @return array|false Backup data or false on failure
 */
function ai_seo_create_backup($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        ai_seo_log("Backup failed: Product $post_id not found");
        return false;
    }
    
    // Check disk space (require at least 100MB free)
    $free_space = @disk_free_space(WP_CONTENT_DIR);
    if ($free_space !== false && $free_space < 100 * 1024 * 1024) {
        ai_seo_log("Backup skipped: Low disk space (" . size_format($free_space) . " free)");
        return false;
    }
    
    // Delete any existing backup first
    delete_post_meta($post_id, '_ai_seo_backup');
    
    // Get current SEO provider data
    $provider = ai_seo_get_provider();
    $seo_fields = $provider->get_fields($post_id);
    
    // Get current SEO score
    $current_score = ai_seo_get_score($post_id);
    
    // Get product data
    $product = wc_get_product($post_id);
    
    // Backup image metadata
    $images_backup = [];
    
    // Featured image
    $featured_id = get_post_thumbnail_id($post_id);
    if ($featured_id) {
        $images_backup[$featured_id] = ai_seo_get_image_metadata($featured_id);
    }
    
    // Gallery images
    $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
    if (!empty($gallery_ids)) {
        foreach (explode(',', $gallery_ids) as $img_id) {
            $img_id = intval($img_id);
            if ($img_id && !isset($images_backup[$img_id])) {
                $images_backup[$img_id] = ai_seo_get_image_metadata($img_id);
            }
        }
    }
    
    // Build backup array
    $backup = [
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'short_description' => $product ? $product->get_short_description() : '',
        'full_description' => $post->post_content,
        'focus_keyword' => $seo_fields['focus_keyword'] ?? '',
        'meta_description' => $seo_fields['meta_description'] ?? '',
        'tags' => wp_get_post_terms($post_id, 'product_tag', ['fields' => 'names']),
        'images' => $images_backup,
        'seo_score' => $current_score,
        'seo_provider' => $provider->get_name(),
        'timestamp' => current_time('mysql'),
        'version' => AI_SEO_VERSION
    ];
    
    // Save backup
    $saved = update_post_meta($post_id, '_ai_seo_backup', $backup);
    
    if ($saved) {
        ai_seo_log("✓ Backup created for Product $post_id (Score: " . ($current_score ?? 'N/A') . ")");
    } else {
        ai_seo_log("⚠ Backup save failed for Product $post_id");
    }
    
    return $saved ? $backup : false;
}

/**
 * v1.3.2: Get image metadata for backup
 * 
 * @param int $attachment_id Image attachment ID
 * @return array Image metadata
 */
function ai_seo_get_image_metadata($attachment_id) {
    $attachment = get_post($attachment_id);
    if (!$attachment) {
        return [];
    }
    
    return [
        'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
        'title' => $attachment->post_title,
        'caption' => $attachment->post_excerpt,
        'description' => $attachment->post_content
    ];
}

/**
 * v1.3.2: Restore product content from backup
 * 
 * @param int $post_id Product ID
 * @return bool Success
 */
function ai_seo_restore_backup($post_id) {
    $backup = get_post_meta($post_id, '_ai_seo_backup', true);
    
    if (empty($backup)) {
        ai_seo_log("Restore failed: No backup found for Product $post_id");
        return false;
    }
    
    ai_seo_log("Starting restore for Product $post_id from backup created at " . $backup['timestamp']);
    
    // v1.3.2b: Restore post data including short description (post_excerpt)
    $update_result = wp_update_post([
        'ID' => $post_id,
        'post_title' => $backup['title'] ?? '',
        'post_name' => $backup['slug'] ?? '',
        'post_content' => $backup['full_description'] ?? '',
        'post_excerpt' => $backup['short_description'] ?? '' // WooCommerce stores short desc here
    ]);
    
    if (is_wp_error($update_result)) {
        ai_seo_log("⚠ Restore failed: " . $update_result->get_error_message());
        return false;
    }
    
    ai_seo_log("Restored: title, slug, descriptions");
    
    // v1.3.2b: Always restore SEO fields (even if empty - clears new values)
    $provider = ai_seo_get_provider();
    $provider->set_fields($post_id, [
        'focus_keyword' => $backup['focus_keyword'] ?? '',
        'meta_description' => $backup['meta_description'] ?? ''
    ]);
    ai_seo_log("Restored: focus keyword, meta description");
    
    // v1.3.2b: Always restore tags (even if empty array - clears new tags)
    $original_tags = isset($backup['tags']) && is_array($backup['tags']) ? $backup['tags'] : [];
    wp_set_post_terms($post_id, $original_tags, 'product_tag');
    ai_seo_log("Restored: " . count($original_tags) . " tags");
    
    // Restore image metadata
    if (!empty($backup['images']) && is_array($backup['images'])) {
        foreach ($backup['images'] as $img_id => $img_data) {
            if (!empty($img_data)) {
                // Restore alt text
                if (isset($img_data['alt'])) {
                    update_post_meta($img_id, '_wp_attachment_image_alt', $img_data['alt']);
                }
                
                // Restore title, caption, description
                wp_update_post([
                    'ID' => $img_id,
                    'post_title' => $img_data['title'] ?? '',
                    'post_excerpt' => $img_data['caption'] ?? '',
                    'post_content' => $img_data['description'] ?? ''
                ]);
            }
        }
        ai_seo_log("Restored metadata for " . count($backup['images']) . " images");
    }
    
    // Delete backup after successful restore
    delete_post_meta($post_id, '_ai_seo_backup');
    
    ai_seo_log("✓ Product $post_id restored to backup state (Original Score: " . ($backup['seo_score'] ?? 'N/A') . ")");
    
    return true;
}

/**
 * v1.3.2: Get backup data for a product
 * 
 * @param int $post_id Product ID
 * @return array|null Backup data or null
 */
function ai_seo_get_backup($post_id) {
    return get_post_meta($post_id, '_ai_seo_backup', true) ?: null;
}

/**
 * v1.3.2: Delete backup for a product
 * 
 * @param int $post_id Product ID
 * @return bool Success
 */
function ai_seo_delete_backup($post_id) {
    $deleted = delete_post_meta($post_id, '_ai_seo_backup');
    if ($deleted) {
        ai_seo_log("Backup deleted for Product $post_id");
    }
    return $deleted;
}

/**
 * v1.3.2: Check if product has a backup
 * 
 * @param int $post_id Product ID
 * @return bool Has backup
 */
function ai_seo_has_backup($post_id) {
    return !empty(get_post_meta($post_id, '_ai_seo_backup', true));
}

/**
 * v1.3.2c: Check for duplicate titles and make unique if needed
 * 
 * @param string $title The generated title
 * @param int $post_id The current product ID (to exclude from check)
 * @return array ['title' => unique title, 'was_duplicate' => bool, 'original' => original title]
 */
function ai_seo_ensure_unique_title($title, $post_id) {
    global $wpdb;
    
    $original_title = $title;
    $was_duplicate = false;
    
    // Check if this exact title exists for another product
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} 
         WHERE post_title = %s 
         AND post_type = 'product' 
         AND post_status IN ('publish', 'draft', 'pending', 'private')
         AND ID != %d
         LIMIT 1",
        $title,
        $post_id
    ));
    
    if (!$existing) {
        // No duplicate found
        return [
            'title' => $title,
            'was_duplicate' => false,
            'original' => $original_title
        ];
    }
    
    ai_seo_log("Duplicate title detected: '$title' (exists as Product #$existing)");
    $was_duplicate = true;
    
    // Strategy 1: Try swapping the power word
    $power_words = ['Stunning', 'Premium', 'Elegant', 'Exquisite', 'Beautiful', 'Gorgeous', 'Luxury', 'Classic', 'Brilliant', 'Perfect'];
    $power_word_pattern = '/\s*-\s*(Stunning|Premium|Elegant|Exquisite|Beautiful|Gorgeous|Luxury|Classic|Brilliant|Perfect)$/i';
    
    if (preg_match($power_word_pattern, $title, $matches)) {
        $current_power_word = $matches[1];
        $base_title = preg_replace($power_word_pattern, '', $title);
        
        // Try other power words
        foreach ($power_words as $new_power_word) {
            if (strtolower($new_power_word) === strtolower($current_power_word)) {
                continue; // Skip current one
            }
            
            $new_title = $base_title . ' - ' . $new_power_word;
            
            // Check if this version exists
            $check = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_title = %s 
                 AND post_type = 'product' 
                 AND post_status IN ('publish', 'draft', 'pending', 'private')
                 AND ID != %d
                 LIMIT 1",
                $new_title,
                $post_id
            ));
            
            if (!$check) {
                ai_seo_log("Made unique by swapping power word: '$new_title'");
                return [
                    'title' => $new_title,
                    'was_duplicate' => true,
                    'original' => $original_title,
                    'method' => 'power_word_swap'
                ];
            }
        }
    }
    
    // Strategy 2: Add a Roman numeral suffix
    $suffixes = ['II', 'III', 'IV', 'V', 'VI'];
    
    foreach ($suffixes as $suffix) {
        $new_title = $title . ' ' . $suffix;
        
        $check = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_title = %s 
             AND post_type = 'product' 
             AND post_status IN ('publish', 'draft', 'pending', 'private')
             AND ID != %d
             LIMIT 1",
            $new_title,
            $post_id
        ));
        
        if (!$check) {
            ai_seo_log("Made unique by adding suffix: '$new_title'");
            return [
                'title' => $new_title,
                'was_duplicate' => true,
                'original' => $original_title,
                'method' => 'suffix'
            ];
        }
    }
    
    // Strategy 3: Last resort - add product ID
    $new_title = $title . ' #' . $post_id;
    ai_seo_log("Made unique by adding product ID: '$new_title'");
    
    return [
        'title' => $new_title,
        'was_duplicate' => true,
        'original' => $original_title,
        'method' => 'product_id'
    ];
}
