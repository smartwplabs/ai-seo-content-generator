<?php
/**
 * AI SEO Search Optimization - Admin Metabox
 * 
 * Displays AI Search content on product edit page
 * 
 * @package AI_SEO_Content_Generator
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the metabox (only if licensed)
 */
function ai_seo_search_register_metabox() {
    if (!ai_seo_search_is_licensed()) {
        return;
    }
    
    add_meta_box(
        'ai_seo_search_metabox',
        '🤖 AI Search Optimization',
        'ai_seo_search_render_metabox',
        'product',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'ai_seo_search_register_metabox');

/**
 * Render the metabox content
 * 
 * v2.1.0 - Updated to show only the 6 fields with proven value
 */
function ai_seo_search_render_metabox($post) {
    $post_id = $post->ID;
    
    // v2.1.0 - Only fields with proven value for AI search
    $fields = [
        'product_summary' => ['label' => '📝 Product Summary', 'type' => 'text'],
        'faq_schema' => ['label' => '❓ FAQ Schema', 'type' => 'faq'],
        'care_instructions' => ['label' => '🧹 Care Instructions', 'type' => 'numbered'],
        'product_highlights' => ['label' => '⭐ Product Highlights', 'type' => 'list'],
        'pros_cons' => ['label' => '⚖️ Pros & Cons', 'type' => 'pros_cons'],
        'alt_names' => ['label' => '🔄 Alternative Names', 'type' => 'comma']
    ];
    
    $has_content = false;
    
    echo '<style>
        .ai-seo-search-field { margin-bottom: 15px; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; }
        .ai-seo-search-field-header { background: #f9f9f9; padding: 8px 12px; border-bottom: 1px solid #e0e0e0; font-weight: 600; }
        .ai-seo-search-field-content { padding: 12px; background: #fff; }
        .ai-seo-search-field-content p { margin: 0; }
        .ai-seo-search-field-content ul, .ai-seo-search-field-content ol { margin: 0; padding-left: 20px; }
        .ai-seo-search-field-content li { margin: 3px 0; }
        .ai-seo-search-faq { margin: 8px 0; padding: 8px; background: #f7f7f7; border-radius: 4px; }
        .ai-seo-search-faq strong { color: #1e3a5f; }
        .ai-seo-search-pros { color: #28a745; }
        .ai-seo-search-cons { color: #dc3545; }
        .ai-seo-search-empty { color: #999; font-style: italic; padding: 20px; text-align: center; }
    </style>';
    
    echo '<div class="ai-seo-search-metabox">';
    echo '<p style="color: #666; margin-bottom: 15px;">This content is <strong>invisible to shoppers</strong> but readable by AI search engines (ChatGPT, Google AI, Perplexity, voice assistants).</p>';
    
    foreach ($fields as $key => $info) {
        $value = get_post_meta($post_id, '_ai_seo_' . $key, true);
        
        if (empty($value)) {
            continue;
        }
        
        $has_content = true;
        
        echo '<div class="ai-seo-search-field">';
        echo '<div class="ai-seo-search-field-header">' . $info['label'] . '</div>';
        echo '<div class="ai-seo-search-field-content">';
        
        switch ($info['type']) {
            case 'text':
                echo '<p>' . esc_html($value) . '</p>';
                break;
                
            case 'list':
                if (is_array($value)) {
                    echo '<ul>';
                    foreach ($value as $item) {
                        echo '<li>' . esc_html($item) . '</li>';
                    }
                    echo '</ul>';
                }
                break;
                
            case 'numbered':
                if (is_array($value)) {
                    echo '<ol>';
                    foreach ($value as $item) {
                        echo '<li>' . esc_html($item) . '</li>';
                    }
                    echo '</ol>';
                }
                break;
                
            case 'comma':
                if (is_array($value)) {
                    echo '<p>' . esc_html(implode(', ', $value)) . '</p>';
                } else {
                    echo '<p>' . esc_html($value) . '</p>';
                }
                break;
                
            case 'faq':
                if (is_array($value)) {
                    foreach ($value as $faq) {
                        echo '<div class="ai-seo-search-faq">';
                        echo '<strong>Q: ' . esc_html($faq['question']) . '</strong><br>';
                        echo 'A: ' . esc_html($faq['answer']);
                        echo '</div>';
                    }
                }
                break;
                
            case 'pros_cons':
                if (is_array($value)) {
                    if (!empty($value['pros'])) {
                        echo '<div class="ai-seo-search-pros"><strong>Pros:</strong><ul>';
                        foreach ($value['pros'] as $pro) {
                            echo '<li>+ ' . esc_html($pro) . '</li>';
                        }
                        echo '</ul></div>';
                    }
                    if (!empty($value['cons'])) {
                        echo '<div class="ai-seo-search-cons"><strong>Cons:</strong><ul>';
                        foreach ($value['cons'] as $con) {
                            echo '<li>- ' . esc_html($con) . '</li>';
                        }
                        echo '</ul></div>';
                    }
                }
                break;
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    if (!$has_content) {
        echo '<div class="ai-seo-search-empty">';
        echo '<p>No AI Search content generated yet.</p>';
        echo '<p>Run the SEO Content Generator with AI Search options enabled.</p>';
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * Add column to products list
 */
function ai_seo_search_add_product_column($columns) {
    if (!ai_seo_search_is_licensed()) {
        return $columns;
    }
    
    $columns['ai_search_status'] = '🤖';
    return $columns;
}
add_filter('manage_edit-product_columns', 'ai_seo_search_add_product_column');

/**
 * Render the column
 */
function ai_seo_search_render_product_column($column, $post_id) {
    if ($column !== 'ai_search_status' || !ai_seo_search_is_licensed()) {
        return;
    }
    
    // v2.1.0 - Check for the 6 fields with proven value
    $fields = ['product_summary', 'faq_schema', 'care_instructions', 'product_highlights', 'pros_cons', 'alt_names'];
    $has_content = false;
    
    foreach ($fields as $field) {
        $value = get_post_meta($post_id, '_ai_seo_' . $field, true);
        if (!empty($value)) {
            $has_content = true;
            break;
        }
    }
    
    echo $has_content ? '<span style="color: #28a745;">✓</span>' : '<span style="color: #ccc;">—</span>';
}
add_action('manage_product_posts_custom_column', 'ai_seo_search_render_product_column', 10, 2);
