<?php
/**
 * AI SEO Search Optimization - Generation
 * 
 * v2.1.0 - Rebuilt based on AI search research:
 * - 6 fields with proven value
 * - Product Summary prepends to short description (keyword first for RankMath)
 * 
 * @package AI_SEO_Content_Generator
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add AI Search fields to generation order
 */
function ai_seo_search_add_generation_fields($fields) {
    ai_seo_log("AI Search: add_generation_fields filter called");
    
    if (!ai_seo_search_is_licensed()) {
        ai_seo_log("AI Search: NOT licensed - skipping AI Search fields");
        return $fields;
    }
    
    ai_seo_log("AI Search: License valid - checking enabled tools");
    
    $tools = get_option('ai_seo_search_tools', []);
    ai_seo_log("AI Search: Tools option: " . print_r($tools, true));
    
    // v2.1.0 - Only fields with proven value for AI search
    $ai_search_fields = [
        'generate_product_summary' => 'product_summary',
        'generate_faq_schema' => 'faq_schema',
        'generate_care_instructions' => 'care_instructions',
        'generate_product_highlights' => 'product_highlights',
        'generate_pros_cons' => 'pros_cons',
        'generate_alt_names' => 'alt_names'
    ];
    
    $added_fields = [];
    foreach ($ai_search_fields as $tool_key => $field_key) {
        if (!empty($tools[$tool_key])) {
            $fields[] = $field_key;
            $added_fields[] = $field_key;
        }
    }
    
    ai_seo_log("AI Search: Added " . count($added_fields) . " fields: " . implode(', ', $added_fields));
    ai_seo_log("AI Search: Total generation order: " . implode(', ', $fields));
    
    return $fields;
}
add_filter('ai_seo_generation_fields', 'ai_seo_search_add_generation_fields');

/**
 * Add AI Search prompts
 */
function ai_seo_search_add_prompts($prompts) {
    ai_seo_log("AI Search: add_prompts filter called");
    
    if (!ai_seo_search_is_licensed()) {
        ai_seo_log("AI Search: add_prompts - NOT licensed, returning original prompts");
        return $prompts;
    }
    
    $search_prompts = ai_seo_search_get_prompts();
    ai_seo_log("AI Search: add_prompts - merging " . count($search_prompts) . " AI Search prompts");
    
    return array_merge($prompts, $search_prompts);
}
add_filter('ai_seo_generation_prompts', 'ai_seo_search_add_prompts');

/**
 * Filter should_generate for AI Search fields
 */
function ai_seo_search_should_generate($should_generate, $field, $tools) {
    if (!ai_seo_search_is_licensed()) {
        return $should_generate;
    }
    
    // v2.1.0 - Only fields with proven value
    $ai_search_fields = [
        'product_summary' => 'generate_product_summary',
        'faq_schema' => 'generate_faq_schema',
        'care_instructions' => 'generate_care_instructions',
        'product_highlights' => 'generate_product_highlights',
        'pros_cons' => 'generate_pros_cons',
        'alt_names' => 'generate_alt_names'
    ];
    
    if (isset($ai_search_fields[$field])) {
        $search_tools = get_option('ai_seo_search_tools', []);
        return !empty($search_tools[$ai_search_fields[$field]]);
    }
    
    return $should_generate;
}
add_filter('ai_seo_should_generate_field', 'ai_seo_search_should_generate', 10, 3);

/**
 * Save AI Search content after generation
 */
function ai_seo_search_save_content($post_id, $field, $content) {
    ai_seo_log("AI Search: save_content called for field '$field' on Product $post_id");
    
    if (!ai_seo_search_is_licensed()) {
        ai_seo_log("AI Search: save_content - NOT licensed, skipping");
        return;
    }
    
    // v2.1.0 - Only fields with proven value
    $ai_search_fields = [
        'product_summary', 'faq_schema', 'care_instructions', 
        'product_highlights', 'pros_cons', 'alt_names'
    ];
    
    if (!in_array($field, $ai_search_fields)) {
        ai_seo_log("AI Search: save_content - field '$field' not an AI Search field, skipping");
        return;
    }
    
    ai_seo_log("AI Search: Parsing content for $field (length: " . strlen($content) . ")");
    
    // Parse and clean content based on field type
    $parsed_content = ai_seo_search_parse_content($field, $content);
    
    ai_seo_log("AI Search: Parsed content type: " . gettype($parsed_content));
    
    // Save to post meta
    $saved = update_post_meta($post_id, '_ai_seo_' . $field, $parsed_content);
    
    ai_seo_log("AI Search: Saved $field for Product $post_id - result: " . ($saved ? 'success' : 'no change or failed'));
    
    // Special handling: Product Summary also prepends to short description
    if ($field === 'product_summary' && !empty($parsed_content)) {
        ai_seo_search_prepend_summary_to_short_description($post_id, $parsed_content);
    }
}
add_action('ai_seo_content_generated', 'ai_seo_search_save_content', 10, 3);

/**
 * Prepend Product Summary to WooCommerce short description
 */
function ai_seo_search_prepend_summary_to_short_description($post_id, $summary) {
    $product = wc_get_product($post_id);
    if (!$product) {
        ai_seo_log("AI Search: Could not get product for prepending summary");
        return;
    }
    
    $current_excerpt = $product->get_short_description();
    
    // Don't prepend if summary is already there
    if (strpos($current_excerpt, $summary) !== false) {
        ai_seo_log("AI Search: Summary already in short description, skipping prepend");
        return;
    }
    
    // Wrap summary in a paragraph and prepend
    $new_excerpt = '<p class="ai-seo-product-summary">' . esc_html($summary) . '</p>' . "\n\n" . $current_excerpt;
    
    // Update the product
    $product->set_short_description($new_excerpt);
    $product->save();
    
    ai_seo_log("AI Search: Prepended summary to short description for Product $post_id");
}

/**
 * Parse and structure content based on field type
 */
function ai_seo_search_parse_content($field, $content) {
    $content = trim($content);
    
    switch ($field) {
        case 'faq_schema':
            return ai_seo_search_parse_faq($content);
            
        case 'care_instructions':
            return ai_seo_search_parse_numbered_list($content);
            
        case 'product_highlights':
            return ai_seo_search_parse_bullet_list($content);
            
        case 'pros_cons':
            return ai_seo_search_parse_pros_cons($content);
            
        case 'alt_names':
            return ai_seo_search_parse_comma_list($content);
            
        case 'product_summary':
        default:
            return ai_seo_search_clean_text($content);
    }
}

/**
 * Parse FAQ format into structured array
 */
function ai_seo_search_parse_faq($content) {
    $faqs = [];
    
    preg_match_all('/Q:\s*(.+?)\s*A:\s*(.+?)(?=Q:|$)/s', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $question = trim($match[1]);
        $answer = trim($match[2]);
        
        if (!empty($question) && !empty($answer)) {
            $faqs[] = [
                'question' => $question,
                'answer' => $answer
            ];
        }
    }
    
    return $faqs;
}

/**
 * Parse numbered list
 */
function ai_seo_search_parse_numbered_list($content) {
    $items = [];
    
    // Strip markdown headers and formatting
    $content = preg_replace('/^#+\s*.+$/m', '', $content);
    $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
    $content = preg_replace('/\*(.+?)\*/', '$1', $content);
    
    preg_match_all('/\d+\.\s*(.+?)(?=\d+\.|$)/s', $content, $matches);
    
    if (!empty($matches[1])) {
        foreach ($matches[1] as $item) {
            $item = trim($item);
            if (!empty($item) && strlen($item) > 3) {
                $items[] = $item;
            }
        }
    }
    
    if (empty($items)) {
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = preg_replace('/^\d+\.\s*/', '', trim($line));
            if (!empty($line) && strlen($line) > 3) {
                $items[] = $line;
            }
        }
    }
    
    return $items;
}

/**
 * Parse bullet list
 */
function ai_seo_search_parse_bullet_list($content) {
    $items = [];
    
    // Strip markdown headers and formatting
    $content = preg_replace('/^#+\s*.+$/m', '', $content);
    $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
    $content = preg_replace('/\*(.+?)\*/', '$1', $content);
    
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $line = preg_replace('/^[\•\-\*]\s*/', '', trim($line));
        if (!empty($line) && strlen($line) > 3) {
            $items[] = $line;
        }
    }
    
    return $items;
}

/**
 * Parse pros and cons
 */
function ai_seo_search_parse_pros_cons($content) {
    $result = ['pros' => [], 'cons' => []];
    
    // Strip markdown headers and formatting
    $content = preg_replace('/^#+\s*.+$/m', '', $content);
    $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
    $content = preg_replace('/\*(.+?)\*/', '$1', $content);
    
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

/**
 * Parse comma-separated list
 */
function ai_seo_search_parse_comma_list($content) {
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
 * Clean plain text content
 */
function ai_seo_search_clean_text($content) {
    $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
    $content = preg_replace('/\*(.+?)\*/', '$1', $content);
    $content = preg_replace('/^#+\s*/m', '', $content);
    $content = preg_replace('/^[A-Za-z\s]+:\s*/m', '', $content);
    
    return trim($content);
}
