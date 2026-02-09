<?php
if (!defined('ABSPATH')) {
    exit;
}

// ✅ Register AI SEO plugin settings
add_action('admin_init', function() {
    // AI Settings Group
    register_setting('ai_seo_settings_group', 'ai_seo_settings', [
        'sanitize_callback' => function($input) {
            $sanitized = [];
            $sanitized['ai_seo_ai_engine'] = sanitize_text_field($input['ai_seo_ai_engine'] ?? 'chatgpt');
            $sanitized['ai_seo_api_key'] = sanitize_text_field($input['ai_seo_api_key'] ?? '');
            $sanitized['ai_seo_model'] = sanitize_text_field($input['ai_seo_model'] ?? 'gpt-4o');
            $sanitized['ai_seo_max_tokens'] = intval($input['ai_seo_max_tokens'] ?? 2048);
            $sanitized['ai_seo_temperature'] = floatval($input['ai_seo_temperature'] ?? 0.7);
            $sanitized['ai_seo_frequency_penalty'] = floatval($input['ai_seo_frequency_penalty'] ?? 0);
            $sanitized['ai_seo_presence_penalty'] = floatval($input['ai_seo_presence_penalty'] ?? 0);
            $sanitized['ai_seo_buffer'] = intval($input['ai_seo_buffer'] ?? 3);
            
            // v1.3.1 - Timing controls
            $sanitized['ai_seo_score_wait_time'] = intval($input['ai_seo_score_wait_time'] ?? 5);
            $sanitized['ai_seo_post_save_delay'] = intval($input['ai_seo_post_save_delay'] ?? 1);
            
            // Save API key to engine-specific option when saving
            $engine = $sanitized['ai_seo_ai_engine'];
            $api_key = $sanitized['ai_seo_api_key'];
            if (!empty($api_key)) {
                update_option('ai_seo_api_key_' . $engine, $api_key);
            }
            
            // Also save all engine-specific keys that were submitted via hidden fields
            $engines = ['chatgpt', 'claude', 'google', 'openrouter', 'microsoft', 'xai'];
            foreach ($engines as $eng) {
                if (isset($input['ai_seo_api_key_' . $eng])) {
                    $eng_key = sanitize_text_field($input['ai_seo_api_key_' . $eng]);
                    if (!empty($eng_key)) {
                        update_option('ai_seo_api_key_' . $eng, $eng_key);
                    }
                }
            }
            
            return $sanitized;
        }
    ]);
    
    // Register individual API key options for each engine
    $engines = ['chatgpt', 'claude', 'google', 'openrouter', 'microsoft', 'xai'];
    foreach ($engines as $engine) {
        register_setting('ai_seo_settings_group', 'ai_seo_api_key_' . $engine, [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    // Prompts Group
    register_setting('ai_seo_prompts_group', 'ai_seo_prompts', [
        'sanitize_callback' => function($input) {
            $sanitized = [];
            $fields = ['focus_keyword', 'title', 'short_description', 'full_description', 'meta_description', 'tags'];
            foreach ($fields as $field) {
                $sanitized[$field] = wp_kses_post($input[$field] ?? '');
            }
            return $sanitized;
        }
    ]);

    // Tools Group
    register_setting('ai_seo_tools_group', 'ai_seo_tools', [
        'sanitize_callback' => function($input) {
            $sanitized = [];
            $tools = [
                'generate_meta_description',
                'add_meta_tag_to_head',
                'update_rank_math_meta',
                'shorten_url',
                'generate_title_from_keywords',
                'include_original_title',
                'enforce_focus_keyword_url',
                'use_sentiment_in_title',
                'include_number_in_title',
                'update_image_alt_tags',
                'permalink_manager_compat',
                'sticky_generate_button',
                'enable_score_calculation', // v1.2.1.7b - CRITICAL FIX
                'disable_image_optimization', // v1.3.1 - Speed up bulk operations
                'enable_debug_logging', // v1.3.1Q - Debug logging toggle
                'enable_backup', // v1.3.2 - Backup/Restore feature
                'prevent_duplicate_titles' // v1.3.2c - Duplicate title detection
            ];
            foreach ($tools as $tool) {
                $sanitized[$tool] = isset($input[$tool]) ? 1 : 0;
            }
            
            // v1.3.1P: Add timing settings (integer values, not checkboxes)
            $sanitized['score_wait_time'] = isset($input['score_wait_time']) ? intval($input['score_wait_time']) : 5;
            $sanitized['post_save_delay'] = isset($input['post_save_delay']) ? intval($input['post_save_delay']) : 1;
            
            // v1.3.2: Backup/Restore settings
            $sanitized['backup_mode'] = isset($input['backup_mode']) && in_array($input['backup_mode'], ['manual', 'auto']) ? $input['backup_mode'] : 'manual';
            $sanitized['restore_threshold'] = isset($input['restore_threshold']) ? intval($input['restore_threshold']) : 80;
            
            // v2.1.20: Description Length setting
            $sanitized['description_length'] = isset($input['description_length']) && in_array($input['description_length'], ['standard', 'long', 'premium']) ? $input['description_length'] : 'standard';
            
            // v2.0.2: Generation mode
            if (isset($input['generation_mode'])) {
                $sanitized['generation_mode'] = in_array($input['generation_mode'], ['both', 'seo_only', 'ai_search_only']) ? $input['generation_mode'] : 'seo_only';
            }
            
            // Validate threshold for Rank Math (max 95)
            if (function_exists('ai_seo_get_provider')) {
                $provider = ai_seo_get_provider();
                if ($provider->get_name() === 'Rank Math' && $sanitized['restore_threshold'] > 95) {
                    $sanitized['restore_threshold'] = 95;
                }
            }
            
            return $sanitized;
        }
    ]);
    
    // v2.1.0: AI Search Optimization Tools (only fields with proven value)
    register_setting('ai_seo_search_tools_group', 'ai_seo_search_tools', [
        'sanitize_callback' => function($input) {
            if (!function_exists('ai_seo_search_is_licensed') || !ai_seo_search_is_licensed()) {
                return []; // Don't save if not licensed
            }
            
            $sanitized = [];
            // v2.1.0 - Only fields with proven value for AI search
            $tools = [
                'generate_product_summary',
                'generate_faq_schema',
                'generate_care_instructions',
                'generate_product_highlights',
                'generate_pros_cons',
                'generate_alt_names'
            ];
            
            foreach ($tools as $tool) {
                $sanitized[$tool] = isset($input[$tool]) ? 1 : 0;
            }
            
            return $sanitized;
        }
    ]);
    
    // v2.1.0: AI Search Display Settings
    register_setting('ai_seo_search_display_group', 'ai_seo_search_display', [
        'sanitize_callback' => function($input) {
            if (!function_exists('ai_seo_search_is_licensed') || !ai_seo_search_is_licensed()) {
                return []; // Don't save if not licensed
            }
            
            $sanitized = [];
            $sanitized['display_mode'] = isset($input['display_mode']) && in_array($input['display_mode'], ['combined', 'separate', 'additional_info', 'append', 'shortcode', 'none']) 
                ? $input['display_mode'] 
                : 'combined';
            $sanitized['show_faq'] = isset($input['show_faq']) ? 1 : 0;
            $sanitized['show_care'] = isset($input['show_care']) ? 1 : 0;
            $sanitized['show_highlights'] = isset($input['show_highlights']) ? 1 : 0;
            $sanitized['show_pros_cons'] = isset($input['show_pros_cons']) ? 1 : 0;
            
            return $sanitized;
        }
    ]);
    
    // v1.4.0: AI Search Prompts (separate group to avoid overwriting main prompts)
    register_setting('ai_seo_search_prompts_group', 'ai_seo_search_prompts', [
        'sanitize_callback' => function($input) {
            if (!function_exists('ai_seo_search_is_licensed') || !ai_seo_search_is_licensed()) {
                return []; // Don't save if not licensed
            }
            
            $sanitized = [];
            if (is_array($input)) {
                foreach ($input as $key => $value) {
                    $sanitized[$key] = wp_kses_post($value);
                }
            }
            return $sanitized;
        }
    ]);
});

// ✅ Dashboard output
function ai_seo_generator_dashboard() {
    $is_activation = (isset($_GET['action']) && $_GET['action'] === 'activate');
    if (!ai_seo_check_dependencies() && !$is_activation) {
        echo '<div class="wrap"><p>Please resolve dependency issues to use this plugin.</p></div>';
        return;
    }

    // Get current settings
    $settings = get_option('ai_seo_settings', []);
    $prompts = get_option('ai_seo_prompts', []);
    $tools = get_option('ai_seo_tools', []);

    // Default values
    $ai_engine = $settings['ai_seo_ai_engine'] ?? 'chatgpt';
    $api_key = $settings['ai_seo_api_key'] ?? '';
    $model = $settings['ai_seo_model'] ?? 'gpt-4o';
    $max_tokens = $settings['ai_seo_max_tokens'] ?? 2048;
    $temperature = $settings['ai_seo_temperature'] ?? 0.7;
    $frequency_penalty = $settings['ai_seo_frequency_penalty'] ?? 0;
    $presence_penalty = $settings['ai_seo_presence_penalty'] ?? 0;
    $top_p = $settings['ai_seo_top_p'] ?? 1;
    $buffer = $settings['ai_seo_buffer'] ?? 0;

    // Default prompts
    // v1.3.1: Updated title prompt for better SEO scoring
    $prompt_defaults = [
        'focus_keyword' => 'Generate a focus keyword for the product [product_title].',
        'title' => 'Generate an SEO-optimized product title that STARTS with the focus keyword [focus_keyword] exactly as given. Keep under 60 characters total. If power word is enabled, add it at the END with a dash or pipe separator. Example: "[focus_keyword] - Stunning" or "[focus_keyword] | Premium Quality". Never put power words at the beginning.',
        'short_description' => 'Generate a short product description (50 words) for [product_title] using the focus keyword [focus_keyword].',
        'full_description' => 'Write a 200–300 word product description using a sophisticated tone for Jewelry. Use 2–3 paragraphs, separated by [PARA]. Start and end with the focus keyword: [focus_keyword]. Use [current_categories] and [current_full_description].',
        'meta_description' => 'Generate a meta description (160 characters) for [product_title] using the focus keyword [focus_keyword].',
        'tags' => 'Generate 5 relevant tags for [product_title] using the focus keyword [focus_keyword].'
    ];

    foreach ($prompt_defaults as $key => $default) {
        if (!isset($prompts[$key])) {
            $prompts[$key] = $default;
        }
    }

    echo '<div class="wrap ai-seo-dashboard">';
    echo '<h1>AI SEO Content Generator</h1>';
    
    // Add tooltip and accordion CSS
    echo '<style>
        /* Tooltip Styles */
        .ai-seo-tooltip {
            display: inline-block;
            margin-left: 5px;
            cursor: help;
            color: #2271b1;
            font-weight: bold;
            font-size: 14px;
            position: relative;
        }
        .ai-seo-tooltip:hover::after {
            content: attr(data-tip);
            position: absolute;
            left: 25px;
            top: -5px;
            min-width: 200px;
            max-width: 300px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            font-size: 12px;
            font-weight: normal;
            color: #333;
            line-height: 1.4;
            white-space: normal;
        }
        
        /* Accordion Styles */
        /* Removed accordion CSS - using native <details> in v1.2.1.17 */
        
        /* API Key Field - Mask characters to look like password */
        .ai-seo-api-key-field {
            -webkit-text-security: disc !important;
            -moz-text-security: disc !important;
            text-security: disc !important;
            font-family: text-security-disc !important;
        }
        
        /* Show actual text when focused (for editing) */
        .ai-seo-api-key-field:focus {
            -webkit-text-security: none !important;
            -moz-text-security: none !important;
            text-security: none !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
        }
    </style>';

    // Tabs
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="#ai-settings" class="nav-tab nav-tab-active">AI Settings</a>';
    echo '<a href="#tools" class="nav-tab">Tools</a>';
    echo '<a href="#prompts" class="nav-tab">Prompts</a>';
    echo '<a href="#ai-search" class="nav-tab">🤖 AI Search</a>';
    echo '</h2>';

    // ========== AI SETTINGS TAB ==========
    echo '<div id="ai-settings" class="tab-content">';
    echo '<form method="post" action="options.php">';
    settings_fields('ai_seo_settings_group');

    echo '<table class="form-table">';
    
    // AI Engine
    echo '<tr><th scope="row">AI Engine <span class="ai-seo-tooltip" data-tip="Choose your AI provider. Each has different strengths and pricing.">(?)</span></th><td>';
    echo '<select name="ai_seo_settings[ai_seo_ai_engine]" id="ai-seo-ai-engine">';
    $engines = [
        'chatgpt' => 'ChatGPT (OpenAI)', 
        'claude' => 'Claude (Anthropic)',
        'openrouter' => 'OpenRouter', 
        'google' => 'Google Gemini', 
        'microsoft' => 'Microsoft Azure', 
        'xai' => 'X.AI Grok'
    ];
    foreach ($engines as $value => $label) {
        $selected = selected($value, $ai_engine, false);
        echo "<option value=\"$value\" $selected>$label</option>";
    }
    echo '</select>';
    echo '</td></tr>';

    // API Key
    echo '<tr><th scope="row">API Key <span class="ai-seo-tooltip" data-tip="Get your API key from your provider\'s dashboard. Keys are saved per engine.">(?)</span></th><td>';
    
    // Load saved API keys for all engines
    $saved_keys = [
        'chatgpt' => get_option('ai_seo_api_key_chatgpt', ''),
        'claude' => get_option('ai_seo_api_key_claude', ''),
        'google' => get_option('ai_seo_api_key_google', ''),
        'openrouter' => get_option('ai_seo_api_key_openrouter', ''),
        'microsoft' => get_option('ai_seo_api_key_microsoft', ''),
        'xai' => get_option('ai_seo_api_key_xai', '')
    ];
    
    // Use current engine's saved key, or fall back to api_key in settings
    $current_key = !empty($saved_keys[$ai_engine]) ? $saved_keys[$ai_engine] : $api_key;
    
    // API key field with show/hide toggle
    echo '<div style="display: flex; align-items: center; gap: 8px;">';
    echo '<input type="password" name="ai_seo_settings[ai_seo_api_key]" id="ai-seo-api-key" value="' . esc_attr($current_key) . '" class="regular-text ai-seo-api-key-field" placeholder="Enter your API key" autocomplete="off" spellcheck="false"';
    
    // Add data attributes for all saved keys
    foreach ($saved_keys as $engine => $key) {
        echo ' data-' . $engine . '-key="' . esc_attr($key) . '"';
    }
    
    echo ' />';
    echo '<button type="button" id="ai-seo-toggle-api-key" class="button button-secondary" style="padding: 0 8px; min-width: 40px;" title="Show/Hide API Key">';
    echo '<span class="dashicons dashicons-visibility" style="margin-top: 4px;"></span>';
    echo '</button>';
    echo '</div>';
    echo '<p class="description">Enter your API key for the selected AI engine. Keys are saved per engine.</p>';
    
    // Add hidden fields for all engine API keys so they're submitted with the form
    foreach ($saved_keys as $engine => $key) {
        echo '<input type="hidden" name="ai_seo_api_key_' . $engine . '" id="ai-seo-hidden-key-' . $engine . '" value="' . esc_attr($key) . '" />';
    }
    
    echo '</td></tr>';

    // Model (Dynamic dropdown based on AI engine)
    echo '<tr><th scope="row">Model <span class="ai-seo-tooltip" data-tip="Select the AI model. Recommended models are highlighted. Different models have different capabilities and costs.">(?)</span></th><td>';
    echo '<select name="ai_seo_settings[ai_seo_model]" id="ai-seo-model" class="regular-text">';
    
    // Model options will be populated by JavaScript based on selected engine
    // Default to showing ChatGPT models
    $model_options = [
        'chatgpt' => [
            'gpt-4o' => 'GPT-4o (Recommended) - Balanced',
            'gpt-4o-mini' => 'GPT-4o Mini - Fast & Affordable',
            'gpt-4-turbo' => 'GPT-4 Turbo - High Quality',
            'o1-preview' => 'o1 Preview - Advanced Reasoning',
            'o1-mini' => 'o1 Mini - Fast Reasoning',
            'custom' => 'Custom Model (enter below)'
        ],
        'claude' => [
            'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5 (Recommended) - Latest & Best',
            'claude-opus-4-5-20251101' => 'Claude Opus 4.5 - Most Powerful',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 - Fastest & Most Affordable',
            'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
            'claude-opus-4-1-20250805' => 'Claude Opus 4.1',
            'claude-opus-4-20250514' => 'Claude Opus 4',
            'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku',
            'custom' => 'Custom Model (enter below)'
        ],
        'google' => [
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash',
            'custom' => 'Custom Model (enter below)'
        ],
        'openrouter' => [
            'custom' => 'Enter OpenRouter Model'
        ],
        'microsoft' => [
            'custom' => 'Enter Azure Deployment Name'
        ],
        'xai' => [
            'grok-beta' => 'Grok Beta',
            'custom' => 'Custom Model (enter below)'
        ]
    ];
    
    // Output options for current engine
    $current_engine_models = $model_options[$ai_engine] ?? $model_options['chatgpt'];
    foreach ($current_engine_models as $value => $label) {
        $selected = selected($value, $model, false);
        if ($value === 'custom' && !in_array($model, array_keys($current_engine_models))) {
            $selected = 'selected';
        }
        echo "<option value=\"$value\" $selected>$label</option>";
    }
    
    echo '</select>';
    
    // Custom model input (shown when 'custom' is selected)
    $show_custom = !in_array($model, array_keys($current_engine_models));
    echo '<div id="ai-seo-custom-model" style="margin-top: 10px; ' . ($show_custom ? '' : 'display:none;') . '">';
    echo '<input type="text" id="ai-seo-custom-model-input" value="' . esc_attr($show_custom ? $model : '') . '" class="regular-text" placeholder="Enter custom model name" />';
    echo '</div>';
    
    echo '<p class="description">Select AI model for content generation.</p>';
    echo '</td></tr>';

    // Advanced Settings Toggle
    echo '<tr><th colspan="2">';
    echo '<a href="#" id="ai-seo-advanced-toggle" style="text-decoration: none; font-size: 14px;">▶ Advanced Settings</a>';
    echo '</th></tr>';
    echo '</table>';

    // Advanced Settings (Hidden by default)
    echo '<div id="ai-seo-advanced-settings" style="display: none;">';
    echo '<table class="form-table">';

    // Max Tokens
    echo '<tr><th scope="row">Max Tokens <span class="ai-seo-tooltip" data-tip="Maximum length of AI response. Higher = longer content but higher cost. Recommended: 2048-4096.">(?)</span></th><td>';
    echo '<input type="range" name="ai_seo_settings[ai_seo_max_tokens]" value="' . esc_attr($max_tokens) . '" min="1024" max="8192" step="256" class="ai-seo-range-slider" oninput="this.nextElementSibling.textContent=this.value" />';
    echo '<output>' . esc_html($max_tokens) . '</output>';
    echo '<p class="description">Maximum tokens for AI response (1024-8192)</p>';
    echo '</td></tr>';

    // Temperature
    echo '<tr><th scope="row">Temperature <span class="ai-seo-tooltip" data-tip="Controls creativity. 0.0-0.3 = focused/consistent, 0.7-1.0 = creative/varied, 1.0-2.0 = very creative. Recommended: 0.7.">(?)</span></th><td>';
    echo '<input type="range" name="ai_seo_settings[ai_seo_temperature]" value="' . esc_attr($temperature) . '" min="0" max="2" step="0.1" class="ai-seo-range-slider" oninput="this.nextElementSibling.textContent=this.value" />';
    echo '<output>' . esc_html($temperature) . '</output>';
    echo '<p class="description">Creativity level (0 = focused, 2 = creative)</p>';
    echo '</td></tr>';

    // Frequency Penalty
    echo '<tr><th scope="row">Frequency Penalty <span class="ai-seo-tooltip" data-tip="Reduces word repetition. Higher values make AI less likely to repeat the same phrases. 0 = no penalty, 2 = maximum penalty.">(?)</span></th><td>';
    echo '<input type="range" name="ai_seo_settings[ai_seo_frequency_penalty]" value="' . esc_attr($frequency_penalty) . '" min="0" max="2" step="0.1" class="ai-seo-range-slider" oninput="this.nextElementSibling.textContent=this.value" />';
    echo '<output>' . esc_html($frequency_penalty) . '</output>';
    echo '<p class="description">Reduce repetition (0-2)</p>';
    echo '</td></tr>';

    // Presence Penalty
    echo '<tr><th scope="row">Presence Penalty <span class="ai-seo-tooltip" data-tip="Encourages discussing new topics. Higher values push AI to mention more diverse subjects rather than focusing on same topics.">(?)</span></th><td>';
    echo '<input type="range" name="ai_seo_settings[ai_seo_presence_penalty]" value="' . esc_attr($presence_penalty) . '" min="0" max="2" step="0.1" class="ai-seo-range-slider" oninput="this.nextElementSibling.textContent=this.value" />';
    echo '<output>' . esc_html($presence_penalty) . '</output>';
    echo '<p class="description">Encourage new topics (0-2)</p>';
    echo '</td></tr>';

    // Buffer
    echo '<tr><th scope="row">Buffer (seconds) <span class="ai-seo-tooltip" data-tip="Delay between products in bulk generation. Prevents API rate limits. 3 seconds = 20 products/minute. Recommended: 2-5 seconds.">(?)</span></th><td>';
    echo '<input type="number" name="ai_seo_settings[ai_seo_buffer]" value="' . esc_attr($buffer) . '" min="0" max="30" step="1" class="small-text" />';
    echo '<p class="description">Seconds to wait between products (prevents rate limits)</p>';
    echo '</td></tr>';

    echo '</table>';
    echo '</div>'; // End advanced settings

    // v1.4.0: AI Search License Section
    $is_licensed = function_exists('ai_seo_search_is_licensed') && ai_seo_search_is_licensed();
    $license_data = get_option('ai_seo_search_license', []);
    
    echo '<div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px;">';
    echo '<h3 style="margin: 0 0 15px 0; color: #fff;">🤖 AI Search Optimization License</h3>';
    
    if ($is_licensed) {
        echo '<div style="background: rgba(255,255,255,0.95); padding: 15px; border-radius: 4px;">';
        echo '<span style="color: #28a745; font-weight: bold;">✓ License Active</span>';
        if (!empty($license_data['expires'])) {
            echo ' &nbsp;|&nbsp; Expires: ' . esc_html($license_data['expires']);
        }
        if (!empty($license_data['key'])) {
            echo ' &nbsp;|&nbsp; Key: ' . esc_html(substr($license_data['key'], 0, 9)) . '...';
        }
        echo ' &nbsp;|&nbsp; <a href="#" id="ai-seo-search-deactivate-btn" style="color: #dc3545;">Deactivate</a>';
        echo '</div>';
    } else {
        echo '<div style="background: rgba(255,255,255,0.95); padding: 15px; border-radius: 4px;">';
        echo '<p style="margin: 0 0 15px 0; color: #333;">Unlock 12 AI-optimized content fields to get found by ChatGPT, Google AI, Perplexity & voice assistants.</p>';
        echo '<input type="text" id="ai-seo-search-license-key" placeholder="AISEO-XXXX-XXXX-XXXX" style="width: 220px; padding: 8px; margin-right: 10px;">';
        echo '<button type="button" id="ai-seo-search-activate-btn" class="button button-primary">Activate License</button>';
        echo ' <a href="https://smartwplabs.com/ai-search-optimization" target="_blank" class="button">Get License →</a>';
        echo '<p id="ai-seo-search-license-message" style="margin-top: 10px; margin-bottom: 0; display: none;"></p>';
        echo '</div>';
    }
    echo '</div>';

    submit_button('Save AI Settings');
    echo '</form>';
    echo '</div>'; // End AI Settings tab

    // ========== TOOLS TAB ==========
    echo '<div id="tools" class="tab-content" style="display:none;">';
    echo '<form method="post" action="options.php">';
    settings_fields('ai_seo_tools_group');
    
    // v1.3.0 - Detect active SEO provider
    $provider = ai_seo_get_provider();
    $provider_name = $provider->get_name();
    $capabilities = $provider->get_capabilities();
    
    // Show SEO provider status
    echo '<div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px;">';
    echo '<p style="margin: 0;"><strong>🔌 Active SEO Plugin:</strong> ' . esc_html($provider_name);
    if ($capabilities['supports_scoring']) {
        echo ' <span style="color: #2271b1;">✓ Scoring Enabled</span>';
    } else {
        echo ' <span style="color: #999;">ℹ️ Basic Compatibility (No Numeric Scoring)</span>';
    }
    echo '</p></div>';
    
    // v2.0.15: Generation Mode - Choose what to generate
    $generation_mode = isset($tools['generation_mode']) ? $tools['generation_mode'] : 'both';
    $ai_search_licensed = function_exists('ai_seo_search_is_licensed') && ai_seo_search_is_licensed();
    
    echo '<div style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; margin-bottom: 20px;">';
    echo '<h4 style="margin: 0 0 10px 0;">🎯 Generation Mode</h4>';
    echo '<p style="margin: 0 0 15px 0; color: #666;">Choose what to generate when running bulk content generation:</p>';
    echo '<fieldset>';
    
    // Both option - grayed out if no license
    echo '<label style="display: block; margin-bottom: 8px; ' . (!$ai_search_licensed ? 'opacity: 0.5;' : '') . '">';
    echo '<input type="radio" name="ai_seo_tools[generation_mode]" value="both" ' . ($generation_mode === 'both' ? 'checked' : '') . ' ' . (!$ai_search_licensed ? 'disabled' : '') . ' /> ';
    echo '<strong>Both SEO + AI Search</strong> <span style="color: #666;">— Full optimization</span>';
    if (!$ai_search_licensed) {
        echo ' <span style="color: #dc3545;">🔒 AI Search license required</span>';
    }
    echo '</label>';
    
    // SEO Only - always available
    echo '<label style="display: block; margin-bottom: 8px;">';
    echo '<input type="radio" name="ai_seo_tools[generation_mode]" value="seo_only" ' . ($generation_mode === 'seo_only' || !$ai_search_licensed ? 'checked' : '') . ' /> ';
    echo '<strong>SEO Only</strong> <span style="color: #666;">— Focus Keyword, Title, Meta, Tags, Image Alt</span>';
    echo '</label>';
    
    // AI Search Only - grayed out if no license
    echo '<label style="display: block; margin-bottom: 0; ' . (!$ai_search_licensed ? 'opacity: 0.5;' : '') . '">';
    echo '<input type="radio" name="ai_seo_tools[generation_mode]" value="ai_search_only" ' . ($generation_mode === 'ai_search_only' ? 'checked' : '') . ' ' . (!$ai_search_licensed ? 'disabled' : '') . ' /> ';
    echo '<strong>AI Search Only</strong> <span style="color: #666;">— Product Summary, FAQ, Care, Highlights, Pros/Cons, Alt Names</span>';
    if (!$ai_search_licensed) {
        echo ' <span style="color: #dc3545;">🔒 License required</span>';
    }
    echo '</label>';
    
    echo '</fieldset>';
    echo '</div>';
    
    echo '<table class="form-table">';
    echo '<tr><th colspan="2"><h3>SEO Tools Configuration</h3></th></tr>';
    echo '<tr><td colspan="2"><p class="description">Enable or disable specific SEO features for content generation.</p></td></tr>';
    
    // CATEGORY 1: Content Generation
    echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">📝 Content Generation</h4></th></tr>';
    
    $content_tools = [
        'generate_title_from_keywords' => ['label' => 'Generate Product Title', 'desc' => 'Generate SEO-optimized title using focus keyword'],
        'generate_meta_description' => ['label' => 'Generate Meta Description', 'desc' => 'Create 160-character meta description'],
        'generate_tags' => ['label' => 'Generate Product Tags', 'desc' => 'Create WooCommerce product tags (not recommended - use attributes for filtering instead)'],
        'include_number_in_title' => ['label' => 'Include Number in Title', 'desc' => 'Add numbers/stats (5 Ways, #1 Rated) for 36% higher CTR'],
        'use_sentiment_in_title' => ['label' => 'Use Positive Sentiment', 'desc' => 'Add positive emotional sentiment words'],
        'include_original_title' => ['label' => 'Reference Original Title', 'desc' => 'Include existing title in AI prompt for context'],
        'prevent_duplicate_titles' => ['label' => 'Prevent Duplicate Titles', 'desc' => 'Auto-modify titles if another product has the same title (avoids SEO penalties)']
    ];
    
    foreach ($content_tools as $key => $info) {
        $checked = !empty($tools[$key]) ? 'checked' : '';
        echo '<tr>';
        echo '<th scope="row">' . esc_html($info['label']) . '<br><small style="font-weight:normal; color: #666;">' . $info['desc'] . '</small></th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="ai_seo_tools[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> Enable</label>';
        echo '</td></tr>';
    }
    
    // Description Length setting
    $desc_length = isset($tools['description_length']) ? $tools['description_length'] : 'standard';
    echo '<tr>';
    echo '<th scope="row">Description Length<br><small style="font-weight:normal; color: #666;">Controls how long the AI-generated full description will be</small></th>';
    echo '<td>';
    echo '<select name="ai_seo_tools[description_length]">';
    echo '<option value="standard" ' . selected($desc_length, 'standard', false) . '>Standard (300-400 words)</option>';
    echo '<option value="long" ' . selected($desc_length, 'long', false) . '>Long (800-1000 words)</option>';
    echo '<option value="premium" ' . selected($desc_length, 'premium', false) . '>Premium (1500-2000 words)</option>';
    echo '</select>';
    echo '</td></tr>';
    
    // CATEGORY 2: SEO Integration (v1.3.0 - Provider-aware)
    echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">🎯 SEO Integration</h4></th></tr>';
    
    // Dynamic label based on detected SEO provider
    $seo_meta_label = 'Update ' . $provider_name . ' Fields';
    $seo_meta_desc = 'Save focus keyword and meta description to ' . $provider_name;
    
    $seo_tools = [
        'update_rank_math_meta' => ['label' => $seo_meta_label, 'desc' => $seo_meta_desc],
        'add_meta_tag_to_head' => ['label' => 'Add Custom Meta Tags', 'desc' => 'Insert additional meta tags in <head>']
    ];
    
    foreach ($seo_tools as $key => $info) {
        $checked = !empty($tools[$key]) ? 'checked' : '';
        echo '<tr>';
        echo '<th scope="row">' . esc_html($info['label']) . '<br><small style="font-weight:normal; color: #666;">' . $info['desc'] . '</small></th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="ai_seo_tools[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> Enable</label>';
        echo '</td></tr>';
    }
    
    // CATEGORY 3: URL Optimization
    echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">🔗 URL Optimization</h4></th></tr>';
    
    $url_tools = [
        'enforce_focus_keyword_url' => ['label' => 'Focus Keyword in URL', 'desc' => 'Ensure permalink contains focus keyword (overrides any permalink plugin during generation)'],
        'shorten_url' => ['label' => 'Auto-Shorten URL', 'desc' => 'Remove stop words for cleaner URLs']
    ];
    
    foreach ($url_tools as $key => $info) {
        $checked = !empty($tools[$key]) ? 'checked' : '';
        echo '<tr>';
        echo '<th scope="row">' . esc_html($info['label']) . '<br><small style="font-weight:normal; color: #666;">' . $info['desc'] . '</small></th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="ai_seo_tools[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> Enable</label>';
        echo '</td></tr>';
    }
    
    // CATEGORY 4: Third-Party Integrations
    echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">🔌 Third-Party Integrations</h4></th></tr>';
    
    $integration_tools = [
        'update_image_alt_tags' => ['label' => 'Update Image Metadata (Alt, Title, Caption, Description)', 'desc' => 'Generate comprehensive image metadata for ALL product images - overrides any image-attribute plugin during generation - optimized for Google Shopping AI']
    ];
    
    foreach ($integration_tools as $key => $info) {
        $checked = !empty($tools[$key]) ? 'checked' : '';
        echo '<tr>';
        echo '<th scope="row">' . esc_html($info['label']) . '<br><small style="font-weight:normal; color: #666;">' . $info['desc'] . '</small></th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="ai_seo_tools[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> Enable</label>';
        echo '</td></tr>';
    }
    
    // v1.3.1: Image Optimizer Bypass Feature
    $detected_optimizers = ai_seo_detect_image_optimizers();
    $optimizer_names = [
        'shortpixel' => 'ShortPixel',
        'smush' => 'WP Smush',
        'imagify' => 'Imagify',
        'ewww' => 'EWWW Image Optimizer',
        'optimole' => 'Optimole'
    ];
    
    $disable_img_opt_checked = !empty($tools['disable_image_optimization']) ? 'checked' : '';
    $is_disabled = empty($detected_optimizers) ? 'disabled' : '';
    
    echo '<tr>';
    echo '<th scope="row">';
    echo '⚡ Disable Image Optimization During Generation';
    echo '<span class="ai-seo-tooltip" style="margin-left: 5px;">';
    echo '<span class="ai-seo-help-icon">?</span>';
    echo '<span class="ai-seo-tooltiptext">';
    echo '<strong>Speed up bulk operations by temporarily pausing image plugins.</strong><br><br>';
    echo 'During AI content generation, no images are changed - only text content (title, description, keywords). ';
    echo 'Disabling image optimization can dramatically reduce generation time.<br><br>';
    echo '<strong>PERFORMANCE IMPACT:</strong><br>';
    echo '• With image optimization: 15-20 seconds per product<br>';
    echo '• Without image optimization: 3-5 seconds per product<br>';
    echo '• Savings: ~12-15 seconds per product<br><br>';
    echo '<strong>SAFE TO USE WHEN:</strong><br>';
    echo '• Bulk AI content generation (text only)<br>';
    echo '• No images being uploaded or changed<br>';
    echo '• Images already optimized<br><br>';
    echo '<strong>DISABLE THIS WHEN:</strong><br>';
    echo '• Uploading new product images<br>';
    echo '• Changing product images<br>';
    echo '• First-time product creation with images<br><br>';
    echo '<strong>Supported plugins:</strong><br>';
    echo '• ShortPixel<br>• WP Smush<br>• Imagify<br>• EWWW Image Optimizer<br>• Optimole';
    echo '</span>';
    echo '</span>';
    echo '<br><small style="font-weight:normal; color: #666;">';
    if (!empty($detected_optimizers)) {
        $detected_names = array_map(function($opt) use ($optimizer_names) {
            return $optimizer_names[$opt] ?? $opt;
        }, $detected_optimizers);
        echo '📊 Detected: ' . implode(', ', $detected_names) . ' • Estimated savings: ~12-15 sec/product';
    } else {
        echo 'No supported image optimizer detected';
    }
    echo '</small>';
    echo '</th>';
    echo '<td>';
    echo '<label><input type="checkbox" name="ai_seo_tools[disable_image_optimization]" value="1" ' . $disable_img_opt_checked . ' ' . $is_disabled . ' /> Enable</label>';
    if (empty($detected_optimizers)) {
        echo '<p class="description" style="color: #999;">Feature requires ShortPixel, Smush, Imagify, EWWW, or Optimole</p>';
    } else {
        echo '<p class="description">Safe for bulk text-only updates</p>';
    }
    echo '</td></tr>';
    
    // CATEGORY 6: UI/UX Settings
    echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">🎨 User Interface</h4></th></tr>';
    
    $ui_tools = [
        'sticky_generate_button' => ['label' => 'Sticky Generate Content Button', 'desc' => 'Button follows you when scrolling the products page for easier access']
    ];
    
    foreach ($ui_tools as $key => $info) {
        $checked = !empty($tools[$key]) ? 'checked' : '';
        echo '<tr>';
        echo '<th scope="row">' . esc_html($info['label']) . '<br><small style="font-weight:normal; color: #666;">' . $info['desc'] . '</small></th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="ai_seo_tools[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> Enable</label>';
        echo '</td></tr>';
    }
    
    // Add Reset Button Position option (v1.3.1 - moved from score calculation section)
    echo '<tr>';
    echo '<th scope="row">Reset Button Position<br><small style="font-weight:normal; color: #666;">Restore Generate Content button to default position</small></th>';
    echo '<td>';
    echo '<button type="button" id="ai-seo-reset-button-position" class="button">Reset to Default Position</button>';
    echo '<p class="description" id="ai-seo-reset-status" style="display:none; color: #46b450; margin-top: 10px;">✓ Button position reset! Refresh the products page to see changes.</p>';
    echo '</td></tr>';
    
    // CATEGORY 6: SEO Score Calculation (v1.3.0 - Only show if provider supports scoring)
    if ($capabilities['supports_scoring']) {
        $score_section_title = $provider_name . ' Score Calculation';
        $score_label = 'Enable ' . $provider_name . ' Score Calculation';
        $score_desc = 'Show/hide score calculation option after content generation';
        
        // Add timing info for Rank Math (which uses iframe method)
        if ($provider_name === 'Rank Math') {
            $score_desc .= ' (~7 seconds per product)';
        }
        
        echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">📊 ' . esc_html($score_section_title) . '</h4></th></tr>';
        
        $score_calc_enabled = !empty($tools['enable_score_calculation']) ? 'checked' : '';
        echo '<tr>';
        echo '<th scope="row">';
        echo esc_html($score_label);
        echo '<span class="ai-seo-tooltip" style="margin-left: 5px;">';
        echo '<span class="ai-seo-help-icon">?</span>';
        echo '<span class="ai-seo-tooltiptext">';
        echo '<strong>What this does:</strong><br>';
        echo 'Controls whether SEO score calculation is available after content generation.<br><br>';
        echo '<strong>When ENABLED (checked):</strong><br>';
        echo '• After generating content, you\'ll see an option to calculate scores<br>';
        echo '• You decide per-generation whether to calculate scores<br>';
        
        if ($provider_name === 'Rank Math') {
            echo '• Takes ~7 seconds per product (iframe method)<br><br>';
        } else {
            echo '• Scores calculate automatically<br><br>';
        }
        
        echo '<strong>When DISABLED (unchecked):</strong><br>';
        echo '• Score calculation section is completely hidden<br>';
        echo '• Faster workflow - just generate and close<br>';
        echo '• Scores will still calculate when you manually edit products later<br><br>';
        echo '<strong>Use cases:</strong><br>';
        echo '• Disable if you always calculate scores manually<br>';
        echo '• Disable for faster bulk operations<br>';
        echo '• Enable if you want the flexibility to choose';
        echo '</span>';
        echo '</span>';
        echo '<br><small style="font-weight:normal; color: #666;">' . esc_html($score_desc) . '</small>';
        echo '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="ai_seo_tools[enable_score_calculation]" value="1" ' . $score_calc_enabled . ' /> Enable</label>';
        echo '</td></tr>';
    } else {
        // Provider doesn't support numeric scoring - show informational message
        echo '<tr><th colspan="2" style="background: #f9f9f9; padding: 10px; border-left: 3px solid #999;">';
        echo '<p style="margin: 0; color: #666;">';
        echo '<strong>ℹ️ SEO Score Calculation:</strong> ';
        echo esc_html($provider_name) . ' does not provide numeric SEO scoring. ';
        echo 'Fields will still be updated correctly. ';
        if ($provider_name === 'Yoast SEO') {
            echo 'Yoast uses a traffic light system (red/orange/green) instead of numeric scores.';
        } else if ($provider_name === 'Basic WordPress (No SEO Plugin)') {
            echo 'Install Rank Math or All in One SEO for numeric scoring support.';
        }
        echo '</p>';
        echo '</th></tr>';
    }
    
    // CATEGORY 7: Performance & Timing Controls (v1.3.1 - Universal settings)
    echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">⚡ Performance & Timing Controls</h4></th></tr>';
    
    // v1.3.1P: Fixed - use $tools not $settings (these are saved with Tools form)
    $score_wait_time = isset($tools['score_wait_time']) ? intval($tools['score_wait_time']) : 5;
    $post_save_delay = isset($tools['post_save_delay']) ? intval($tools['post_save_delay']) : 1;
    
    echo '<tr>';
    echo '<th scope="row">';
    echo 'Score Calculation Wait Time';
    echo '<span class="ai-seo-tooltip" style="margin-left: 5px;">';
    echo '<span class="ai-seo-help-icon">?</span>';
    echo '<span class="ai-seo-tooltiptext">';
    echo '<strong>How long to wait for your product page to fully load.</strong><br><br>';
    echo '<strong>TO FIND YOUR SITE\'S TIME:</strong><br>';
    echo '1. Edit any product manually<br>';
    echo '2. Click \'Update\' button<br>';
    echo '3. Time how long until page reloads<br>';
    echo '4. Add 1-2 seconds buffer<br>';
    echo '5. Set slider to that time<br><br>';
    echo '<strong>TYPICAL TIMES:</strong><br>';
    echo '• Fast sites (no image optimization): 3-5 seconds<br>';
    echo '• Medium sites (basic optimization): 5-10 seconds<br>';
    echo '• Slow sites (ShortPixel/Smush): 10-15 seconds<br>';
    echo '• Very slow sites (complex processing): 15-25 seconds<br><br>';
    echo '<strong>APPLIES TO:</strong><br>';
    echo '• Rank Math score calculation<br>';
    echo '• All in One SEO (AIOSEO) score calculation<br>';
    echo '• Any SEO plugin that calculates scores';
    echo '</span>';
    echo '</span>';
    echo '<br><small style="font-weight:normal; color: #666;">Adjust based on your site speed (for Rank Math, AIOSEO scoring)</small>';
    echo '</th>';
    echo '<td>';
    echo '<input type="range" name="ai_seo_tools[score_wait_time]" value="' . esc_attr($score_wait_time) . '" min="3" max="25" step="1" class="ai-seo-range-slider" oninput="this.nextElementSibling.textContent=this.value" />';
    echo '<o>' . esc_html($score_wait_time) . '</o> seconds';
    echo '<p class="description">Wait time for page to load and score to calculate (3-25 seconds)</p>';
    echo '</td></tr>';
    
    echo '<tr>';
    echo '<th scope="row">';
    echo 'Post-Save Processing Delay';
    echo '<span class="ai-seo-tooltip" style="margin-left: 5px;">';
    echo '<span class="ai-seo-help-icon">?</span>';
    echo '<span class="ai-seo-tooltiptext">';
    echo '<strong>Wait time after saving before updating permalinks/images.</strong><br><br>';
    echo 'Allows other plugins (Permalink Manager, image optimizers, etc.) to process first.<br><br>';
    echo '<strong>RECOMMENDED:</strong><br>';
    echo '• 0-1 seconds: Fast sites, no conflicts<br>';
    echo '• 2-3 seconds: Medium sites with multiple plugins<br>';
    echo '• 4-5 seconds: Slow sites or many plugins running on save<br><br>';
    echo '<strong>APPLIES TO:</strong><br>';
    echo '• Permalink Manager permalink updates<br>';
    echo '• Image alt tag updates<br>';
    echo '• Any plugin that processes on product save';
    echo '</span>';
    echo '</span>';
    echo '<br><small style="font-weight:normal; color: #666;">Let other plugins process before updating (universal)</small>';
    echo '</th>';
    echo '<td>';
    echo '<input type="range" name="ai_seo_tools[post_save_delay]" value="' . esc_attr($post_save_delay) . '" min="0" max="5" step="1" class="ai-seo-range-slider" oninput="this.nextElementSibling.textContent=this.value" />';
    echo '<o>' . esc_html($post_save_delay) . '</o> seconds';
    echo '<p class="description">Delay before updating permalinks and alt tags (0-5 seconds)</p>';
    echo '</td></tr>';
    
    // CATEGORY 8: Backup & Restore (v1.3.2)
    echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">💾 Backup & Restore</h4></th></tr>';
    
    $backup_enabled = !empty($tools['enable_backup']) ? 'checked' : '';
    $backup_mode = isset($tools['backup_mode']) ? $tools['backup_mode'] : 'manual';
    $restore_threshold = isset($tools['restore_threshold']) ? intval($tools['restore_threshold']) : 80;
    
    // Get SEO provider for threshold validation
    $provider = ai_seo_get_provider();
    $provider_name = $provider->get_name();
    $max_threshold = ($provider_name === 'Rank Math') ? 95 : 100;
    
    echo '<tr>';
    echo '<th scope="row">';
    echo 'Enable Auto-Backup Before Generation';
    echo '<span class="ai-seo-tooltip" style="margin-left: 5px;">';
    echo '<span class="ai-seo-help-icon">?</span>';
    echo '<span class="ai-seo-tooltiptext">';
    echo '<strong>Save original content before AI generates new content.</strong><br><br>';
    echo '<strong>WHAT GETS BACKED UP:</strong><br>';
    echo '• Title, descriptions, meta<br>';
    echo '• Focus keyword, tags, permalink<br>';
    echo '• All image metadata (Alt, Title, Caption, Description)<br>';
    echo '• Current SEO score<br><br>';
    echo '<strong>WHY USE THIS:</strong><br>';
    echo '• Restore if new score is worse<br>';
    echo '• Compare before/after results<br>';
    echo '• Safe experimentation with AI content<br><br>';
    echo '<strong>STORAGE:</strong><br>';
    echo '• ~10 KB per product<br>';
    echo '• Deleted after you approve/restore';
    echo '</span>';
    echo '</span>';
    echo '<br><small style="font-weight:normal; color: #666;">Save original content to allow restore if new score is worse</small>';
    echo '</th>';
    echo '<td>';
    echo '<label><input type="checkbox" name="ai_seo_tools[enable_backup]" value="1" ' . $backup_enabled . ' id="ai-seo-enable-backup" /> Enable</label>';
    echo '</td></tr>';
    
    // Backup Mode (only show if backup enabled)
    echo '<tr class="ai-seo-backup-options" ' . (empty($backup_enabled) ? 'style="display:none;"' : '') . '>';
    echo '<th scope="row">';
    echo 'Approval Mode';
    echo '<br><small style="font-weight:normal; color: #666;">How to handle generated content</small>';
    echo '</th>';
    echo '<td>';
    echo '<label style="display: block; margin-bottom: 8px;">';
    echo '<input type="radio" name="ai_seo_tools[backup_mode]" value="manual" ' . ($backup_mode === 'manual' ? 'checked' : '') . ' class="ai-seo-backup-mode" /> ';
    echo '<strong>Manual approval</strong> - Review and select products to restore';
    echo '</label>';
    echo '<label style="display: block;">';
    echo '<input type="radio" name="ai_seo_tools[backup_mode]" value="auto" ' . ($backup_mode === 'auto' ? 'checked' : '') . ' class="ai-seo-backup-mode" /> ';
    echo '<strong>Auto-restore</strong> - Automatically restore if new score is below threshold';
    echo '</label>';
    echo '</td></tr>';
    
    // Auto-restore threshold (only show if auto mode)
    echo '<tr class="ai-seo-backup-options ai-seo-threshold-row" ' . (empty($backup_enabled) || $backup_mode !== 'auto' ? 'style="display:none;"' : '') . '>';
    echo '<th scope="row">';
    echo 'Restore Threshold';
    echo '<span class="ai-seo-tooltip" style="margin-left: 5px;">';
    echo '<span class="ai-seo-help-icon">?</span>';
    echo '<span class="ai-seo-tooltiptext">';
    echo '<strong>Auto-restore if new score is at or below this value.</strong><br><br>';
    echo '<strong>SEO PLUGIN LIMITS:</strong><br>';
    echo '• Rank Math: Max ~95 (their AI caps it)<br>';
    echo '• Yoast: Uses color mapping<br>';
    echo '• AIOSEO/SEOPress: Max 100<br><br>';
    echo '<strong>RECOMMENDED:</strong><br>';
    echo '• Conservative: 85+<br>';
    echo '• Moderate: 80+<br>';
    echo '• Aggressive: 75+';
    echo '</span>';
    echo '</span>';
    echo '<br><small style="font-weight:normal; color: #666;">Restore original if new score ≤ this value</small>';
    echo '</th>';
    echo '<td>';
    echo '<input type="range" name="ai_seo_tools[restore_threshold]" value="' . esc_attr($restore_threshold) . '" min="50" max="' . $max_threshold . '" step="5" class="ai-seo-range-slider" oninput="this.nextElementSibling.textContent=this.value" />';
    echo '<o>' . esc_html($restore_threshold) . '</o>';
    if ($provider_name === 'Rank Math') {
        echo '<p class="description" style="color: #d63638;">Rank Math detected - Max threshold: 95</p>';
    }
    echo '</td></tr>';
    
    // CATEGORY 9: Debug & Troubleshooting (v1.3.1Q)
    echo '<tr><th colspan="2" style="background: #f0f0f1; padding: 10px;"><h4 style="margin: 0;">🔍 Debug & Troubleshooting</h4></th></tr>';
    
    $debug_logging_enabled = !empty($tools['enable_debug_logging']) ? 'checked' : '';
    
    echo '<tr>';
    echo '<th scope="row">';
    echo 'Enable Debug Logging';
    echo '<span class="ai-seo-tooltip" style="margin-left: 5px;">';
    echo '<span class="ai-seo-help-icon">?</span>';
    echo '<span class="ai-seo-tooltiptext">';
    echo '<strong>Write detailed logs for troubleshooting.</strong><br><br>';
    echo '<strong>WHEN ENABLED:</strong><br>';
    echo '• Logs all generation steps<br>';
    echo '• Records API calls and responses<br>';
    echo '• Tracks image/permalink updates<br>';
    echo '• Saves SEO score calculations<br><br>';
    echo '<strong>LOG FILE LOCATION:</strong><br>';
    echo '<code>/wp-content/ai-seo-debug.log</code><br><br>';
    echo '<strong>RECOMMENDATION:</strong><br>';
    echo '• Enable when troubleshooting issues<br>';
    echo '• Disable for normal operation<br>';
    echo '• Delete log file periodically to save space';
    echo '</span>';
    echo '</span>';
    echo '<br><small style="font-weight:normal; color: #666;">Creates detailed log file for debugging (disable for better performance)</small>';
    echo '</th>';
    echo '<td>';
    echo '<label><input type="checkbox" name="ai_seo_tools[enable_debug_logging]" value="1" ' . $debug_logging_enabled . ' /> Enable</label>';
    
    // v1.3.1Q: Show ALL AI SEO related log files
    $log_files = [
        'ai-seo-debug.log' => 'Debug Log',
        'ai-seo-activation.log' => 'Activation Log',
        'ai-seo.log' => 'General Log',
        'seo-focus-debug.log' => 'Legacy Debug',
        'seo-focus-activation.log' => 'Legacy Activation'
    ];
    
    $total_size = 0;
    $existing_logs = [];
    
    foreach ($log_files as $filename => $label) {
        $filepath = WP_CONTENT_DIR . '/' . $filename;
        if (file_exists($filepath)) {
            $size = filesize($filepath);
            $total_size += $size;
            $existing_logs[$filename] = [
                'label' => $label,
                'size' => size_format($size),
                'modified' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }
    
    if (!empty($existing_logs)) {
        echo '<div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<strong>Log Files Found:</strong> (' . size_format($total_size) . ' total)<br><br>';
        echo '<table style="width: 100%; font-size: 12px;">';
        echo '<tr style="border-bottom: 1px solid #ddd;"><th style="text-align: left; padding: 3px;">File</th><th style="text-align: right; padding: 3px;">Size</th><th style="text-align: right; padding: 3px;">Modified</th><th style="text-align: right; padding: 3px;">Action</th></tr>';
        foreach ($existing_logs as $filename => $info) {
            echo '<tr>';
            echo '<td style="padding: 3px;"><code>' . esc_html($filename) . '</code></td>';
            echo '<td style="text-align: right; padding: 3px;">' . esc_html($info['size']) . '</td>';
            echo '<td style="text-align: right; padding: 3px; color: #666;">' . esc_html($info['modified']) . '</td>';
            echo '<td style="text-align: right; padding: 3px;"><button type="button" class="button button-small ai-seo-view-log" data-file="' . esc_attr($filename) . '">View</button></td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        echo '<div id="ai-seo-log-viewer" style="display: none; margin-top: 15px; padding: 15px; background: #1d2327; border-radius: 4px;">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
        echo '<strong style="color: #fff;">📄 <span id="ai-seo-log-filename"></span></strong>';
        echo '<button type="button" id="ai-seo-close-log" class="button button-small">Close</button>';
        echo '</div>';
        echo '<pre id="ai-seo-log-content" style="max-height: 400px; overflow: auto; background: #23282d; color: #50fa7b; padding: 15px; border-radius: 4px; font-size: 11px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; margin: 0;"></pre>';
        echo '</div>';
        echo '<button type="button" id="ai-seo-clear-log" class="button button-secondary" style="margin-top: 10px;">Clear All Log Files</button>';
        echo '<span id="ai-seo-clear-log-status" style="margin-left: 10px; display: none; color: #46b450;">✓ All logs cleared!</span>';
    } else {
        echo '<p class="description" style="margin-top: 10px; color: #666;">No log files exist yet.</p>';
    }
    
    echo '</td></tr>';
    
    echo '</table>';
    submit_button('Save Tools Settings');
    echo '</form>';
    echo '</div>'; // End Tools tab

    // ========== PROMPTS TAB ==========
    echo '<div id="prompts" class="tab-content" style="display:none;">';
    echo '<form method="post" action="options.php">';
    settings_fields('ai_seo_prompts_group');
    
    echo '<table class="form-table">';
    
    // ========== CUSTOM PROMPT TEMPLATES ==========
    echo '<tr><th colspan="2" style="background: #2271b1; color: white; padding: 12px;"><h3 style="margin: 0; color: white;">📝 Custom Prompt Templates</h3></th></tr>';
    echo '<tr><td colspan="2">';
    // v1.3.0 - Provider-aware description
    if ($capabilities['supports_scoring']) {
        echo '<p class="description">Customize AI prompts for each content type. These prompts are optimized for high SEO scores in ' . esc_html($provider_name) . '.</p>';
    } else {
        echo '<p class="description">Customize AI prompts for each content type. These prompts are optimized for SEO best practices.</p>';
    }
    echo '<details style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1;">';
    echo '<summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">📋 Available Placeholders (Click to Expand)</summary>';
    echo '<div style="margin-top: 10px;">';
    echo '<strong>Product Data:</strong><br>';
    echo '• <code>[product_title]</code> - Full product name<br>';
    echo '• <code>[product_sku]</code> - Product SKU code<br>';
    echo '• <code>[price]</code> - Current price<br>';
    echo '• <code>[sale_price]</code> - Sale price (if applicable)<br>';
    echo '• <code>[current_categories]</code> - Product categories<br><br>';
    echo '<strong>SEO Data:</strong><br>';
    echo '• <code>[focus_keyword]</code> - AI-generated focus keyword<br>';
    echo '• <code>[current_title]</code> - Existing product title<br>';
    echo '• <code>[current_short_description]</code> - Existing excerpt<br>';
    echo '• <code>[current_full_description]</code> - Existing content<br>';
    echo '• <code>[description_length]</code> - Auto-filled from Description Length setting (e.g., "300-400 words")<br><br>';
    echo '<strong>WooCommerce Attributes (if set):</strong><br>';
    echo '• <code>[current_color]</code>, <code>[current_size]</code>, <code>[current_material]</code>, <code>[current_brand]</code><br><br>';
    echo '<strong>💡 SEO Optimization Tips:</strong><br>';
    echo '• Always START descriptions with <code>[focus_keyword]</code><br>';
    echo '• Use keyword 3-4 times in content (2-3% density)<br>';
    echo '• Include in at least one <code>&lt;h2&gt;</code> heading<br>';
    echo '• Keep titles under 60 characters<br>';
    echo '• Keep meta descriptions 150-160 characters<br>';
    echo '</div>';
    echo '</details>';
    echo '</td></tr>';

    $prompt_fields = [
        'focus_keyword' => 'Focus Keyword Prompt',
        'title' => 'Title Prompt',
        'short_description' => 'Short Description Prompt',
        'full_description' => 'Full Description Prompt',
        'meta_description' => 'Meta Description Prompt',
        'tags' => 'Tags Prompt'
    ];

    // Render prompts as native details/summary (v1.2.1.17 - NO JavaScript interference!)
    echo '<tr><td colspan="2">';
    
    $prompt_descriptions = [
        'focus_keyword' => 'Generates the main SEO keyword/phrase for the product',
        'title' => 'Creates SEO-optimized product title (max 60 chars)',
        'short_description' => 'Writes the product excerpt/summary (50-60 words)',
        'full_description' => 'Creates detailed product description with HTML',
        'meta_description' => 'Generates meta description for search results (150-160 chars)',
        'tags' => 'Creates product tags for categorization'
    ];
    
    $is_first = true;
    foreach ($prompt_fields as $key => $label) {
        echo '<details' . ($is_first ? ' open' : '') . ' style="margin-bottom: 10px; border: 1px solid #ccd0d4; border-radius: 4px;">';
        echo '<summary style="padding: 15px; background: #f6f7f7; cursor: pointer; font-weight: 600; font-size: 14px; user-select: none;">';
        echo esc_html($label);
        echo '</summary>';
        echo '<div style="padding: 15px; border-top: 1px solid #ccd0d4;">';
        echo '<textarea name="ai_seo_prompts[' . esc_attr($key) . ']" id="ai-seo-prompt-' . esc_attr($key) . '" style="width: 100%; min-height: 150px; font-family: monospace;">' . esc_textarea($prompts[$key]) . '</textarea>';
        if (isset($prompt_descriptions[$key])) {
            echo '<p class="description" style="margin-top: 10px; font-style: italic; color: #666;">' . esc_html($prompt_descriptions[$key]) . '</p>';
        }
        echo '</div>';
        echo '</details>';
        $is_first = false;
    }
    
    echo '</td></tr>';

    // v1.2.1.18: Removed duplicate "Available Placeholders" section - it's already shown at the top in a better format
    
    echo '</table>';
    
    submit_button('Save Prompts');
    echo '</form>';
    echo '</div>'; // End Prompts tab

    // ========== AI SEARCH TAB (v1.4.0) ==========
    echo '<div id="ai-search" class="tab-content" style="display:none;">';
    
    $is_search_licensed = function_exists('ai_seo_search_is_licensed') && ai_seo_search_is_licensed();
    $license_data = get_option('ai_seo_search_license', []);
    $search_tools = get_option('ai_seo_search_tools', []);
    
    // Header
    echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<h2 style="margin: 0; color: #fff;">🤖 AI Search Optimization Suite</h2>';
    echo '<p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9);">Get your products found by ChatGPT, Google AI, Perplexity, voice assistants & others</p>';
    echo '</div>';
    
    if (!$is_search_licensed) {
        // LOCKED STATE
        echo '<div style="text-align: center; padding: 40px; background: #f9f9f9; border: 2px dashed #ddd; border-radius: 8px;">';
        echo '<div style="font-size: 64px; margin-bottom: 20px;">🔒</div>';
        echo '<h2 style="margin: 0 0 15px 0; color: #333;">Unlock AI Search Optimization</h2>';
        echo '<p style="color: #666; margin-bottom: 25px; max-width: 500px; margin-left: auto; margin-right: auto;">Make your products irresistible to AI-powered search engines. Generate FAQ schemas, voice assistant content, and 10 more AI-optimized fields.</p>';
        
        echo '<div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; margin-bottom: 30px;">';
        echo '<div style="text-align: left;">';
        echo '<strong style="color: #333;">What You Get:</strong>';
        echo '<ul style="color: #555; margin-top: 10px;">';
        echo '<li>❓ FAQ Schema (Google Rich Results)</li>';
        echo '<li>🤖 AI Summary for ChatGPT/Perplexity</li>';
        echo '<li>🗣️ Voice Assistant Content</li>';
        echo '<li>🎯 Target Audience Matching</li>';
        echo '<li>💎 Value Proposition</li>';
        echo '<li>⭐ Product Highlights</li>';
        echo '</ul>';
        echo '</div>';
        echo '<div style="text-align: left;">';
        echo '<strong style="color: #333;">&nbsp;</strong>';
        echo '<ul style="color: #555; margin-top: 10px;">';
        echo '<li>🧹 Care Instructions (HowTo)</li>';
        echo '<li>⚖️ Pros & Cons</li>';
        echo '<li>🎁 Use Cases & Gift Ideas</li>';
        echo '<li>🔧 Problem/Solution</li>';
        echo '<li>🔄 Alternative Names</li>';
        echo '<li>📅 Seasonal Relevance</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        
        echo '<p style="color: #666; margin-bottom: 20px;">Enter your license key in the <strong>AI Settings</strong> tab to unlock.</p>';
        echo '<a href="#ai-settings" class="button button-primary button-hero" onclick="jQuery(\'.nav-tab\').removeClass(\'nav-tab-active\'); jQuery(\'.nav-tab:first\').addClass(\'nav-tab-active\'); jQuery(\'.tab-content\').hide(); jQuery(\'#ai-settings\').show(); return false;">Go to AI Settings →</a>';
        echo '</div>';
        
    } else {
        // UNLOCKED STATE - License status
        echo '<div style="background: #f0fff0; border: 1px solid #28a745; border-radius: 4px; padding: 10px 15px; margin-bottom: 20px;">';
        echo '<span style="color: #155724;">✓ <strong>License Active</strong></span>';
        if (!empty($license_data['expires'])) {
            echo ' &nbsp;|&nbsp; Expires: ' . esc_html($license_data['expires']);
        }
        echo '</div>';
        
        // SUB-TABS for AI Search
        echo '<div class="ai-search-subtabs">';
        echo '<a href="#ai-search-tools" class="ai-search-subtab ai-search-subtab-active" data-subtab="ai-search-tools">⚙️ Tools</a>';
        echo '<a href="#ai-search-prompts" class="ai-search-subtab" data-subtab="ai-search-prompts" style="margin-left: 10px;">📝 Prompts</a>';
        echo '<a href="#ai-search-display" class="ai-search-subtab" data-subtab="ai-search-display" style="margin-left: 10px;">👁️ Display</a>';
        echo '</div>';
        
        echo '<style>
        .ai-search-subtabs { margin-bottom: 20px; border-bottom: 1px solid #ccc; }
        .ai-search-subtab { 
            display: inline-block; 
            padding: 10px 20px; 
            text-decoration: none; 
            color: #555; 
            border: 1px solid transparent;
            border-bottom: none;
            margin-bottom: -1px;
            background: #f1f1f1;
            border-radius: 4px 4px 0 0;
        }
        .ai-search-subtab:hover { background: #e5e5e5; }
        .ai-search-subtab-active { 
            background: #fff; 
            border-color: #ccc; 
            color: #333; 
            font-weight: 600;
        }
        .ai-search-subtab-content { display: none; }
        .ai-search-subtab-content.active { display: block; }
        </style>';
        
        // ===== SUB-TAB: TOOLS =====
        echo '<div id="ai-search-tools" class="ai-search-subtab-content active">';
        
        echo '<form method="post" action="options.php">';
        settings_fields('ai_seo_search_tools_group');
        
        echo '<table class="form-table">';
        
        // v2.1.0 - Only fields with proven value for AI search
        $ai_search_tools = [
            'generate_product_summary' => ['label' => 'Product Summary', 'desc' => '40-80 words, keyword first - prepends to short description', 'icon' => '📝'],
            'generate_faq_schema' => ['label' => 'FAQ Schema', 'desc' => 'FAQPage schema - 4 Q&As optimized for AI extraction', 'icon' => '❓'],
            'generate_care_instructions' => ['label' => 'Care Instructions', 'desc' => 'HowTo schema for product care/maintenance', 'icon' => '🧹'],
            'generate_product_highlights' => ['label' => 'Product Highlights', 'desc' => 'Key features in Product schema', 'icon' => '⭐'],
            'generate_pros_cons' => ['label' => 'Pros & Cons', 'desc' => 'Balanced assessment displayed in product tabs', 'icon' => '⚖️'],
            'generate_alt_names' => ['label' => 'Alternative Names', 'desc' => 'Synonyms as Product.alternateName schema', 'icon' => '🔄']
        ];
        
        foreach ($ai_search_tools as $key => $info) {
            $checked = !empty($search_tools[$key]) ? 'checked' : '';
            echo '<tr>';
            echo '<th scope="row">' . $info['icon'] . ' ' . esc_html($info['label']);
            echo '<br><small style="font-weight:normal; color: #666;">' . $info['desc'] . '</small></th>';
            echo '<td>';
            echo '<label><input type="checkbox" name="ai_seo_search_tools[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> Enable</label>';
            echo '</td></tr>';
        }
        
        echo '</table>';
        
        submit_button('Save AI Search Tools');
        echo '</form>';
        echo '</div>'; // End ai-search-tools
        
        // ===== SUB-TAB: PROMPTS =====
        echo '<div id="ai-search-prompts" class="ai-search-subtab-content">';
        
        echo '<p style="color: #666; margin-bottom: 20px;">Fine-tune how AI generates each field. Available placeholders: <code>[product_title]</code>, <code>[current_full_description]</code>, <code>[current_attributes]</code>, <code>[current_categories]</code>, <code>[current_price]</code>, <code>[focus_keyword]</code></p>';
        
        echo '<form method="post" action="options.php">';
        settings_fields('ai_seo_search_prompts_group');
        
        $search_prompts = function_exists('ai_seo_search_get_prompts') ? ai_seo_search_get_prompts() : [];
        $default_prompts = function_exists('ai_seo_search_get_default_prompts') ? ai_seo_search_get_default_prompts() : [];
        
        // v2.1.0 - Only fields with proven value
        $prompt_labels = [
            'product_summary' => ['label' => '📝 Product Summary', 'desc' => '40-80 words, keyword first, prepends to short description'],
            'faq_schema' => ['label' => '❓ FAQ Schema', 'desc' => 'Generates 4 Q&As (40-80 words each, includes product name)'],
            'care_instructions' => ['label' => '🧹 Care Instructions', 'desc' => 'Product maintenance steps (1 sentence each)'],
            'product_highlights' => ['label' => '⭐ Product Highlights', 'desc' => 'Key features list (1 sentence each)'],
            'pros_cons' => ['label' => '⚖️ Pros & Cons', 'desc' => 'Balanced assessment (1 sentence each)'],
            'alt_names' => ['label' => '🔄 Alternative Names', 'desc' => 'Synonyms and search terms']
        ];
        
        foreach ($prompt_labels as $key => $info) {
            $value = isset($search_prompts[$key]) ? $search_prompts[$key] : ($default_prompts[$key] ?? '');
            
            echo '<details style="margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">';
            echo '<summary style="background: #f9f9f9; padding: 12px 15px; cursor: pointer; font-weight: 600;">';
            echo $info['label'] . ' <span style="color: #666; font-weight: normal; font-size: 12px;">— ' . $info['desc'] . '</span>';
            echo '</summary>';
            echo '<div style="padding: 15px;">';
            echo '<textarea name="ai_seo_search_prompts[' . esc_attr($key) . ']" rows="8" style="width: 100%; font-family: monospace; font-size: 12px;">' . esc_textarea($value) . '</textarea>';
            echo '</div>';
            echo '</details>';
        }
        
        submit_button('Save AI Search Prompts');
        echo '</form>';
        echo '</div>'; // End ai-search-prompts
        
        // ===== SUB-TAB: DISPLAY =====
        echo '<div id="ai-search-display" class="ai-search-subtab-content">';
        
        echo '<p style="color: #666; margin-bottom: 20px;">Control how AI Search content is displayed on your product pages. Visible content helps AI search engines extract and cite your products.</p>';
        
        echo '<form method="post" action="options.php">';
        settings_fields('ai_seo_search_display_group');
        
        $display_settings = get_option('ai_seo_search_display', [
            'display_mode' => 'combined',
            'show_faq' => 1,
            'show_care' => 1,
            'show_highlights' => 1,
            'show_pros_cons' => 1
        ]);
        
        echo '<table class="form-table">';
        
        // Display Mode
        echo '<tr><th scope="row">📍 Display Mode</th><td>';
        echo '<fieldset>';
        $modes = [
            'combined' => ['label' => 'Combined Tab', 'desc' => 'One "Product Info" tab with all content'],
            'separate' => ['label' => 'Separate Tabs', 'desc' => 'FAQ, Care, and Details in separate tabs'],
            'additional_info' => ['label' => 'Additional Information Tab', 'desc' => 'Append to the WooCommerce "Additional Information" tab'],
            'append' => ['label' => 'Append to Description', 'desc' => 'Add content below the product description'],
            'shortcode' => ['label' => 'Shortcode Only', 'desc' => 'Use [ai_seo_product_info] shortcode for custom placement'],
            'none' => ['label' => 'Schema Only', 'desc' => 'Output schema but don\'t display content (not recommended)']
        ];
        foreach ($modes as $value => $info) {
            $checked = ($display_settings['display_mode'] ?? 'combined') === $value ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 8px;">';
            echo '<input type="radio" name="ai_seo_search_display[display_mode]" value="' . esc_attr($value) . '" ' . $checked . '> ';
            echo '<strong>' . esc_html($info['label']) . '</strong> — ' . esc_html($info['desc']);
            echo '</label>';
        }
        echo '</fieldset>';
        echo '</td></tr>';
        
        // Content to Display
        echo '<tr><th scope="row">📝 Content to Display</th><td>';
        $content_options = [
            'show_faq' => 'FAQ (Frequently Asked Questions)',
            'show_care' => 'Care Instructions',
            'show_highlights' => 'Product Highlights',
            'show_pros_cons' => 'Pros & Cons'
        ];
        foreach ($content_options as $key => $label) {
            $checked = !empty($display_settings[$key]) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="ai_seo_search_display[' . esc_attr($key) . ']" value="1" ' . $checked . '> ';
            echo esc_html($label);
            echo '</label>';
        }
        echo '</td></tr>';
        
        echo '</table>';
        
        echo '<div style="background: #f0f8ff; border: 1px solid #bee5eb; border-radius: 4px; padding: 15px; margin-top: 20px;">';
        echo '<strong>💡 Why Display Content?</strong><br>';
        echo 'AI search engines (Perplexity, ChatGPT, Google AI) primarily read <strong>visible content</strong> on your pages. ';
        echo 'Hidden schema and meta tags help, but visible content with proper headings (H2, H3) is what gets extracted and cited.';
        echo '</div>';
        
        echo '<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-top: 15px;">';
        echo '<strong>📋 Shortcode Usage</strong><br>';
        echo 'Use <code>[ai_seo_product_info]</code> to display AI Search content anywhere.<br><br>';
        echo '<strong>Examples:</strong><br>';
        echo '<code>[ai_seo_product_info]</code> — Show all sections<br>';
        echo '<code>[ai_seo_product_info show="faq,pros_cons"]</code> — Show only FAQ and Pros/Cons<br>';
        echo '<code>[ai_seo_product_info show="summary,highlights"]</code> — Show Summary and Highlights<br><br>';
        echo '<strong>Available sections:</strong> summary, faq, care, highlights, pros_cons';
        echo '</div>';
        
        submit_button('Save Display Settings');
        echo '</form>';
        echo '</div>'; // End ai-search-display
    }
    
    echo '</div>'; // End AI Search tab

    echo '</div>'; // End wrap

    // Add JavaScript for advanced settings toggle
    ?>
    <script>
    jQuery(document).ready(function($) {
        // API Key show/hide toggle
        $('#ai-seo-toggle-api-key').on('click', function() {
            var $input = $('#ai-seo-api-key');
            var $icon = $(this).find('.dashicons');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $input.removeClass('ai-seo-api-key-field'); // Remove masking CSS
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                $(this).attr('title', 'Hide API Key');
            } else {
                $input.attr('type', 'password');
                $input.addClass('ai-seo-api-key-field'); // Add masking CSS back
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $(this).attr('title', 'Show API Key');
            }
        });
        
        // AI Search sub-tabs
        $('.ai-search-subtab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('subtab');
            
            // Update active tab
            $('.ai-search-subtab').removeClass('ai-search-subtab-active');
            $(this).addClass('ai-search-subtab-active');
            
            // Show target content
            $('.ai-search-subtab-content').removeClass('active');
            $('#' + target).addClass('active');
        });
        
        $('#ai-seo-advanced-toggle').on('click', function(e) {
            e.preventDefault();
            var $advanced = $('#ai-seo-advanced-settings');
            var $toggle = $(this);
            
            if ($advanced.is(':visible')) {
                $advanced.slideUp();
                $toggle.html('▶ Advanced Settings');
            } else {
                $advanced.slideDown();
                $toggle.html('▼ Advanced Settings');
            }
        });
        
        // v1.2.1.17: Removed accordion JavaScript - using native HTML <details>/<summary> instead
        // No JavaScript needed - works natively in all browsers!
        
        // v1.3.1b: Real-time slider value updates with debugging
        console.log('AI SEO: Initializing slider listeners...');
        
        // Method 1: Event delegation (for dynamically loaded content)
        $(document).on('input change', '.ai-seo-range-slider', function() {
            console.log('AI SEO: Slider moved!', $(this).attr('name'), $(this).val());
            var value = $(this).val();
            var $output = $(this).next('o');
            if ($output.length) {
                $output.text(value);
                console.log('AI SEO: Updated output to:', value);
            } else {
                console.warn('AI SEO: No <o> tag found next to slider');
            }
        });
        
        // Method 2: Direct binding after Advanced Settings are revealed
        $('#ai-seo-advanced-toggle').on('click', function() {
            setTimeout(function() {
                console.log('AI SEO: Rebinding sliders after Advanced Settings toggle');
                $('.ai-seo-range-slider').each(function() {
                    var $slider = $(this);
                    var $output = $slider.next('o');
                    console.log('AI SEO: Found slider:', $slider.attr('name'), 'Output element:', $output.length);
                });
            }, 100);
        });
        
        // Reset button position handler
        $('#ai-seo-reset-button-position').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Resetting...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_reset_button_position'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Reset to Default Position');
                    $('#ai-seo-reset-status').fadeIn();
                    setTimeout(function() {
                        $('#ai-seo-reset-status').fadeOut();
                    }, 5000);
                },
                error: function() {
                    $btn.prop('disabled', false).text('Reset to Default Position');
                    alert('Error resetting button position. Please try again.');
                }
            });
        });
        
        // v1.3.1Q: Clear log file handler
        $('#ai-seo-clear-log').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_clear_log',
                    nonce: '<?php echo wp_create_nonce('ai_seo_nonce'); ?>'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Clear All Log Files');
                    if (response.success) {
                        $('#ai-seo-clear-log-status').fadeIn();
                        // Hide the log table - find the div with the table inside
                        $btn.siblings('div').not('#ai-seo-log-viewer').html('<p style="color: #666; padding: 10px;">All log files cleared. They will be recreated when logging is enabled.</p>');
                        $btn.hide();
                        $('#ai-seo-log-viewer').hide();
                        setTimeout(function() {
                            $('#ai-seo-clear-log-status').fadeOut();
                        }, 3000);
                    } else {
                        alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Clear All Log Files');
                    alert('Error clearing log files. Please try again.');
                }
            });
        });
        
        // v2.1.5: View log file handler
        $('.ai-seo-view-log').on('click', function() {
            var $btn = $(this);
            var filename = $btn.data('file');
            $btn.prop('disabled', true).text('Loading...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_view_log',
                    filename: filename,
                    nonce: '<?php echo wp_create_nonce('ai_seo_nonce'); ?>'
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('View');
                    if (response.success) {
                        $('#ai-seo-log-filename').text(filename + ' (' + response.data.size + ')');
                        $('#ai-seo-log-content').text(response.data.content);
                        $('#ai-seo-log-viewer').slideDown();
                        // Scroll to bottom of log
                        var $pre = $('#ai-seo-log-content');
                        $pre.scrollTop($pre[0].scrollHeight);
                    } else {
                        alert('Error: ' + (response.data ? response.data.message : 'Could not read log file'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('View');
                    alert('Error reading log file. Please try again.');
                }
            });
        });
        
        // v2.1.5: Close log viewer
        $('#ai-seo-close-log').on('click', function() {
            $('#ai-seo-log-viewer').slideUp();
        });
        
        // v1.3.2: Backup options toggle handlers
        $('#ai-seo-enable-backup').on('change', function() {
            if ($(this).is(':checked')) {
                $('.ai-seo-backup-options').slideDown();
                // Also check if auto mode to show threshold
                if ($('input[name="ai_seo_tools[backup_mode]"]:checked').val() === 'auto') {
                    $('.ai-seo-threshold-row').slideDown();
                }
            } else {
                $('.ai-seo-backup-options').slideUp();
            }
        });
        
        // Show/hide threshold based on backup mode
        $('input[name="ai_seo_tools[backup_mode]"]').on('change', function() {
            if ($(this).val() === 'auto') {
                $('.ai-seo-threshold-row').slideDown();
            } else {
                $('.ai-seo-threshold-row').slideUp();
            }
        });
        
        // v1.4.0: AI Search License Activation
        $('#ai-seo-search-activate-btn').on('click', function() {
            var $btn = $(this);
            var $input = $('#ai-seo-search-license-key');
            var $msg = $('#ai-seo-search-license-message');
            var licenseKey = $input.val().trim();
            
            if (!licenseKey) {
                $msg.html('<span style="color: #dc3545;">Please enter a license key.</span>').show();
                return;
            }
            
            $btn.prop('disabled', true).text('Activating...');
            $msg.html('<span style="color: #856404;">Validating license...</span>').show();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_search_activate_license',
                    license_key: licenseKey,
                    nonce: '<?php echo wp_create_nonce('ai_seo_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $msg.html('<span style="color: #28a745;">✓ ' + response.data.message + ' Reloading...</span>').show();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $msg.html('<span style="color: #dc3545;">✗ ' + response.data.message + '</span>').show();
                        $btn.prop('disabled', false).text('Activate License');
                    }
                },
                error: function() {
                    $msg.html('<span style="color: #dc3545;">Connection error. Please try again.</span>').show();
                    $btn.prop('disabled', false).text('Activate License');
                }
            });
        });
        
        // License Deactivation
        $('#ai-seo-search-deactivate-btn').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Deactivate AI Search Optimization license? You can reactivate anytime.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_search_deactivate_license',
                    nonce: '<?php echo wp_create_nonce('ai_seo_nonce'); ?>'
                },
                success: function(response) {
                    location.reload();
                }
            });
        });
    });
    </script>
    <?php
}
