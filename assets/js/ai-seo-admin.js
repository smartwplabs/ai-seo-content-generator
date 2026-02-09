(function($) {
    if (!window || typeof $ === 'undefined') {
        console.error('AI SEO: jQuery or window not loaded');
        return;
    }

    function encodeForInput(str) {
        if (!str) return '';
        return str.replace(/[\u2013\u2014]/g, '-')
                  .replace(/\r\n|\n|\r/g, '\\n')
                  .replace(/\t/g, '\\t')
                  .replace(/"/g, '\\"');
    }

    $(function() {
        function ensurePopup() {
            if ($('#ai-seo-bulk-popup').length === 0) {
                var prompts = aiSeoSettings.prompts || {};
                $('body').append(
                    '<div id="ai-seo-bulk-popup">' +
                        '<h2>SEO Content Generator</h2>' +
                        '<p class="description">Click each section to expand/collapse. Prompts are pre-filled from your settings.</p>' +
                        
                        '<details open style="margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">' +
                        '<summary style="padding: 10px; background: #f0f0f1; cursor: pointer; font-weight: bold;">📌 Focus Keyword</summary>' +
                        '<div style="padding: 10px;">' +
                        '<textarea id="ai-seo-prompt-focus-keyword" rows="3" style="width: 100%;">' + encodeForInput(prompts.focus_keyword || '') + '</textarea>' +
                        '</div></details>' +
                        
                        '<details style="margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">' +
                        '<summary style="padding: 10px; background: #f0f0f1; cursor: pointer; font-weight: bold;">📝 Title</summary>' +
                        '<div style="padding: 10px;">' +
                        '<textarea id="ai-seo-prompt-title" rows="3" style="width: 100%;">' + encodeForInput(prompts.title || '') + '</textarea>' +
                        '</div></details>' +
                        
                        '<details style="margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">' +
                        '<summary style="padding: 10px; background: #f0f0f1; cursor: pointer; font-weight: bold;">📄 Short Description</summary>' +
                        '<div style="padding: 10px;">' +
                        '<textarea id="ai-seo-prompt-short-description" rows="3" style="width: 100%;">' + encodeForInput(prompts.short_description || '') + '</textarea>' +
                        '</div></details>' +
                        
                        '<details style="margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">' +
                        '<summary style="padding: 10px; background: #f0f0f1; cursor: pointer; font-weight: bold;">📃 Full Description</summary>' +
                        '<div style="padding: 10px;">' +
                        '<textarea id="ai-seo-prompt-full-description" rows="4" style="width: 100%;">' + encodeForInput(prompts.full_description || '') + '</textarea>' +
                        '</div></details>' +
                        
                        '<details style="margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">' +
                        '<summary style="padding: 10px; background: #f0f0f1; cursor: pointer; font-weight: bold;">🔍 Meta Description</summary>' +
                        '<div style="padding: 10px;">' +
                        '<textarea id="ai-seo-prompt-meta-description" rows="3" style="width: 100%;">' + encodeForInput(prompts.meta_description || '') + '</textarea>' +
                        '</div></details>' +
                        
                        '<details style="margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">' +
                        '<summary style="padding: 10px; background: #f0f0f1; cursor: pointer; font-weight: bold;">🏷️ Tags</summary>' +
                        '<div style="padding: 10px;">' +
                        '<textarea id="ai-seo-prompt-tags" rows="3" style="width: 100%;">' + encodeForInput(prompts.tags || '') + '</textarea>' +
                        '</div></details>' +
                        
                        '<div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #ddd;">' +
                        '<button type="button" class="button button-primary" id="ai-seo-bulk-start" style="margin-right: 10px;">Start Generation</button>' +
                        '<button type="button" class="button" id="ai-seo-bulk-cancel">Cancel</button>' +
                        '</div>' +
                        '<div id="ai-seo-bulk-results"></div>' +
                    '</div>' +
                    '<div id="ai-seo-bulk-overlay"></div>'
                );
                console.log('AI SEO: Popup created');
            }
        }

        // Add Generate Content button to product list with draggable positioning
        setTimeout(() => {
            var topNav = $('.tablenav.top');
            if (topNav.length && !topNav.find('.ai-seo-content-btn').length) {
                // Insert button (default position: top-right) - NO cursor style here!
                topNav.append('<a href="javascript:void(0)" class="button ai-seo-content-btn" style="float: right; margin-left: 8px;" title="Left-click and hold to Drag & reposition">Generate Content</a>');
                
                var $btn = $('.ai-seo-content-btn');
                console.log('AI SEO: Generate button added');
                
                // Check if we have saved position
                var savedPosition = typeof aiSeoButtonPosition !== 'undefined' ? aiSeoButtonPosition : null;
                
                // Apply sticky mode if enabled
                var isSticky = typeof aiSeoStickyButton !== 'undefined' && aiSeoStickyButton === '1';
                
                if (savedPosition && savedPosition.top && savedPosition.left) {
                    // Use saved position
                    $btn.css({
                        'position': isSticky ? 'fixed' : 'absolute',
                        'top': savedPosition.top + 'px',
                        'left': savedPosition.left + 'px',
                        'z-index': '9999',
                        'float': 'none',
                        'margin': '0',
                        'box-shadow': isSticky ? '0 4px 8px rgba(0,0,0,0.3)' : 'none'
                    });
                    console.log('AI SEO: Button positioned at saved location - top: ' + savedPosition.top + 'px, left: ' + savedPosition.left + 'px');
                } else if (isSticky) {
                    // No saved position, use default with sticky
                    var originalTop = $btn.offset().top;
                    var originalLeft = $btn.offset().left;
                    
                    $btn.css({
                        'position': 'fixed',
                        'top': originalTop + 'px',
                        'left': originalLeft + 'px',
                        'z-index': '9999',
                        'box-shadow': '0 4px 8px rgba(0,0,0,0.3)',
                        'float': 'none',
                        'margin': '0'
                    });
                    console.log('AI SEO: Sticky button enabled at default position');
                }
                
                // Make button draggable (requires jQuery UI)
                if (typeof $btn.draggable === 'function') {
                    // Set initial cursor (pointer for clicking) (v1.2.1.11)
                    $btn[0].style.cursor = 'pointer';
                    
                    $btn.draggable({
                        containment: 'window',
                        // NO cursor option - we handle it manually for better UX
                        opacity: 0.7,
                        distance: 10, // v1.4.3: Must drag at least 10px before drag starts - allows clean clicks
                        start: function() {
                            // Show move cursor ONLY while actively dragging
                            $btn[0].style.cursor = 'move';
                            console.log('AI SEO: Drag started - cursor set to move');
                        },
                        stop: function(event, ui) {
                            // Reset to pointer immediately after dragging stops
                            $btn[0].style.cursor = 'pointer';
                            console.log('AI SEO: Drag stopped - cursor reset to pointer');
                            
                            // Save position to database via AJAX
                            var position = {
                                top: ui.position.top,
                                left: ui.position.left
                            };
                            
                            $.ajax({
                                url: aiSeoSettings.ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'ai_seo_save_button_position',
                                    position: JSON.stringify(position),
                                    nonce: aiSeoSettings.nonce
                                },
                                success: function(response) {
                                    console.log('AI SEO: Button position saved', position);
                                }
                            });
                        }
                    });
                    
                    // v1.2.1.11 - Removed mouseenter/mouseleave handlers
                    // Button now always shows pointer cursor (clickable)
                    // Move cursor only appears during active dragging
                    // Result: Click once to use button, click-and-drag to move it
                    
                    console.log('AI SEO: Button is draggable (click-and-drag to reposition)');
                } else {
                    console.warn('AI SEO: jQuery UI draggable not available');
                }
            }
        }, 600);

        // Show popup when Generate Content button clicked
        $(document).on('click', '.ai-seo-content-btn', function(e) {
            e.preventDefault();
            
            var selectedProducts = $('input[name="post[]"]:checked');
            if (selectedProducts.length === 0) {
                alert('Please select at least one product to generate content for.');
                return false;
            }
            
            console.log('AI SEO: ' + selectedProducts.length + ' products selected');
            ensurePopup();
            $('#ai-seo-bulk-overlay, #ai-seo-bulk-popup').addClass('visible');
            $('#ai-seo-bulk-results').html('');
            
            return false;
        });

        // Cancel button - v2.0.3: Also cancels background batch if running
        $(document).on('click', '#ai-seo-bulk-cancel', function() {
            // Check if background processing is running
            if (window.aiSeoActiveProgressUI && window.aiSeoActiveProgressUI.queueManager.batchId) {
                if (!confirm('Are you sure you want to cancel? Progress will be lost.')) {
                    return;
                }
                window.aiSeoActiveProgressUI.cancelGeneration();
            }
            $('#ai-seo-bulk-overlay, #ai-seo-bulk-popup').removeClass('visible');
            console.log('AI SEO: Popup cancelled');
        });

        // Start generation
        $(document).on('click', '#ai-seo-bulk-start', function() {
            console.log('AI SEO: Start button clicked');
            
            // Get prompts from textareas
            var prompts = {
                focus_keyword: $('#ai-seo-prompt-focus-keyword').val() || '',
                title: $('#ai-seo-prompt-title').val() || '',
                short_description: $('#ai-seo-prompt-short-description').val() || '',
                full_description: $('#ai-seo-prompt-full-description').val() || '',
                meta_description: $('#ai-seo-prompt-meta-description').val() || '',
                tags: $('#ai-seo-prompt-tags').val() || ''
            };
            
            // Get selected product IDs
            var postIds = $('input[name="post[]"]:checked').map(function() { 
                return parseInt(this.value); 
            }).get();
            
            if (postIds.length === 0) {
                alert('Please select at least one product.');
                return;
            }
            
            console.log('AI SEO: Processing ' + postIds.length + ' products');
            console.log('AI SEO: Product IDs being sent:', postIds);
            
            // v2.0.0: Check if background processing is enabled
            if (aiSeoSettings.useBackgroundProcessing && window.AISEOProgressUI) {
                console.log('AI SEO: Using background processing (v2.0.0)');
                $('#ai-seo-bulk-start').prop('disabled', true).text('Starting...');
                
                var progressUI = new AISEOProgressUI('#ai-seo-bulk-results');
                window.aiSeoActiveProgressUI = progressUI; // v2.0.3: Store for cancel button
                progressUI.startGeneration(postIds, prompts);
                return;
            }
            
            // Legacy synchronous processing (fallback)
            console.log('AI SEO: Using legacy synchronous processing');
            
            // Show different UI based on product count (v1.2.1.9)
            var progressHtml;
            
            if (postIds.length === 1) {
                // Single product - show simple processing message (no progress bar)
                console.log('AI SEO: Single product - showing simple processing message');
                progressHtml = '<div style="margin: 20px 0; padding: 20px; background: #f0f6fc; border: 1px solid #c3dafe; border-radius: 6px; text-align: center;">';
                progressHtml += '<div style="font-size: 16px; font-weight: 600; color: #1e3a8a; margin-bottom: 10px;">Generating Content</div>';
                progressHtml += '<div style="font-size: 14px; color: #3b82f6; margin-bottom: 15px;">Processing product ' + postIds[0] + '...</div>';
                progressHtml += '<div style="font-size: 13px; color: #64748b; font-style: italic;">This may take 30-45 seconds. Please wait...</div>';
                progressHtml += '</div>';
            } else {
                // Multiple products - show full progress bar (v1.2.1.8a)
                console.log('AI SEO: Multiple products - showing progress bar');
                progressHtml = '<div class="ai-seo-progress-container">';
                progressHtml += '<div class="ai-seo-progress-header">';
                progressHtml += '<div class="ai-seo-progress-title">Generating Content</div>';
                progressHtml += '<div class="ai-seo-progress-count"><span id="ai-seo-current-product">0</span> of ' + postIds.length + '</div>';
                progressHtml += '</div>';
                progressHtml += '<div class="ai-seo-progress-bar-wrapper">';
                progressHtml += '<div class="ai-seo-progress-bar" id="ai-seo-progress-bar" style="width: 0%"></div>';
                progressHtml += '<div class="ai-seo-progress-percentage" id="ai-seo-progress-percentage">0%</div>';
                progressHtml += '</div>';
                progressHtml += '<div class="ai-seo-progress-status" id="ai-seo-progress-status">Initializing...</div>';
                progressHtml += '<div class="ai-seo-progress-eta" id="ai-seo-progress-eta"></div>';
                progressHtml += '</div>';
            }
            
            $('#ai-seo-bulk-results').html(progressHtml);
            $('#ai-seo-bulk-start').prop('disabled', true).text('Generating...');
            
            // Process products one at a time for progress tracking (v1.2.1.8a - FIXED TIMING)
            var currentIndex = 0;
            var completedCount = 0;
            var results = {};
            var debugInfo = {};
            var startTime = Date.now();
            
            function updateProgressBar() {
                // Only update if progress bar exists (multi-product mode) (v1.2.1.9)
                if ($('#ai-seo-progress-bar').length === 0) {
                    return; // Single product mode - no progress bar to update
                }
                
                var percentage = Math.round((completedCount / postIds.length) * 100);
                $('#ai-seo-current-product').text(completedCount);
                $('#ai-seo-progress-bar').css('width', percentage + '%');
                $('#ai-seo-progress-percentage').text(percentage + '%');
                
                // Calculate ETA based on completed products
                if (completedCount > 0 && completedCount < postIds.length) {
                    var elapsed = (Date.now() - startTime) / 1000;
                    var avgTimePerProduct = elapsed / completedCount;
                    var remaining = (postIds.length - completedCount) * avgTimePerProduct;
                    var minutes = Math.floor(remaining / 60);
                    var seconds = Math.round(remaining % 60);
                    var etaText = 'Estimated time remaining: ';
                    if (minutes > 0) {
                        etaText += minutes + 'm ' + seconds + 's';
                    } else {
                        etaText += seconds + 's';
                    }
                    $('#ai-seo-progress-eta').text(etaText);
                } else if (completedCount === 0) {
                    $('#ai-seo-progress-eta').text('Calculating...');
                }
            }
            
            function processNextProduct() {
                if (currentIndex >= postIds.length) {
                    // All done - show results
                    showGenerationResults(results, debugInfo, postIds.length);
                    return;
                }
                
                var productId = postIds[currentIndex];
                var productNum = currentIndex + 1;
                
                // Update status message if it exists (multi-product mode only) (v1.2.1.9)
                if ($('#ai-seo-progress-status').length > 0) {
                    $('#ai-seo-progress-status').html('⏳ Processing product ' + productId + ' (' + productNum + ' of ' + postIds.length + ')...');
                }
                
                console.log('AI SEO: Starting product ' + productId + ' (' + productNum + ' of ' + postIds.length + ')');
                
                // Send AJAX request for single product
                $.ajax({
                    url: aiSeoSettings.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'seo_generate_content',
                        posts: JSON.stringify([productId]),
                        prompts: JSON.stringify(prompts)
                    },
                    timeout: 120000, // 2 minutes per product
                    success: function(response) {
                        console.log('AI SEO: Completed product ' + productId);
                        
                        if (response.success && response.data && response.data.results) {
                            // Store results
                            Object.assign(results, response.data.results);
                            Object.assign(debugInfo, response.data.debug || {});
                            // v1.3.2: Store backup data from last response
                            if (response.data.backup) {
                                window.aiSeoBackupData = response.data.backup;
                            }
                        }
                        
                        // Increment completed count and update progress bar AFTER success
                        completedCount++;
                        updateProgressBar();
                        
                        // Move to next product
                        currentIndex++;
                        processNextProduct();
                    },
                    error: function(xhr, status, error) {
                        console.error('AI SEO: Error processing product ' + productId + ':', error);
                        debugInfo[productId] = { error: error, status: status };
                        
                        // Count as completed (even though failed) to keep progress moving
                        completedCount++;
                        updateProgressBar();
                        
                        // Continue with next product
                        currentIndex++;
                        processNextProduct();
                    }
                });
            }
            
            // Initialize progress bar at 0%
            updateProgressBar();
            
            // Start processing
            processNextProduct();
        });
        
        // Function to show final results (v1.2.1.8 - extracted from inline code)
        function showGenerationResults(results, debugInfo, totalProcessed) {
            console.log('AI SEO: All products processed');
            console.log('AI SEO: RESULTS:', results);
            console.log('AI SEO: DEBUG INFO:', debugInfo);
            
            var data = {
                processed: totalProcessed,
                results: results,
                debug: debugInfo,
                backup: window.aiSeoBackupData || null // v1.3.2: Include backup data
            };
            
            console.log('AI SEO: BACKUP DATA:', data.backup);
            
            // Original success handler code continues here...
                    
                    var html = '<div style="margin-top: 15px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">';
                    
                    // Check for API errors in debug info
                    var hasApiError = false;
                    var apiErrorMsg = '';
                    if (data.debug) {
                        for (var productId in data.debug) {
                            var debugItem = data.debug[productId];
                            if (debugItem.api_error) {
                                hasApiError = true;
                                apiErrorMsg = debugItem.api_error;
                                break;
                            }
                        }
                    }
                    
                    // Show API error prominently if found
                    if (hasApiError) {
                        html = '<div style="margin-top: 15px; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">';
                        html += '<h3 style="margin-top: 0; color: #721c24;">❌ API Error</h3>';
                        html += '<p style="font-size: 16px; color: #721c24;"><strong>' + apiErrorMsg + '</strong></p>';
                        html += '<p>Please check your API settings and billing status.</p>';
                        html += '</div>';
                        $('#ai-seo-bulk-results').html(html);
                        return;
                    }
                    
                    // Show debug information if processing failed
                    if (data.processed === 0 && data.debug) {
                        html = '<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
                        html += '<h3 style="margin-top: 0; color: #856404;">⚠️ Debug Information</h3>';
                        html += '<p><strong>Products processed:</strong> ' + data.processed + ' (expected: ' + Object.keys(data.debug).length + ')</p>';
                        html += '<p><strong>Why products were skipped:</strong></p>';
                        html += '<ul style="list-style: none; padding: 0;">';
                        
                        for (var productId in data.debug) {
                            var debugItem = data.debug[productId];
                            html += '<li style="margin: 10px 0; padding: 10px; background: #fff; border-left: 3px solid #ffc107;">';
                            html += '<strong>Product ID ' + productId + ':</strong><br>';
                            html += '&nbsp;&nbsp;• <strong>Status:</strong> ' + debugItem.status + '<br>';
                            
                            if (debugItem.title) {
                                html += '&nbsp;&nbsp;• <strong>Title:</strong> ' + debugItem.title + '<br>';
                            }
                            if (debugItem.product_type) {
                                html += '&nbsp;&nbsp;• <strong>Type:</strong> ' + debugItem.product_type + '<br>';
                            }
                            if (debugItem.post_status) {
                                html += '&nbsp;&nbsp;• <strong>Post Status:</strong> ' + debugItem.post_status + '<br>';
                            }
                            if (debugItem.ai_engine) {
                                html += '&nbsp;&nbsp;• <strong>AI Engine:</strong> ' + debugItem.ai_engine + '<br>';
                            }
                            if (debugItem.api_key_present !== undefined) {
                                html += '&nbsp;&nbsp;• <strong>API Key Present:</strong> ' + (debugItem.api_key_present ? 'Yes' : 'No') + '<br>';
                            }
                            if (debugItem.error_step) {
                                html += '&nbsp;&nbsp;• <strong>Failed At:</strong> ' + debugItem.error_step + '<br>';
                            }
                            if (debugItem.api_error) {
                                html += '&nbsp;&nbsp;• <strong>API Error:</strong> <span style="color: #d9534f;">' + debugItem.api_error + '</span><br>';
                            }
                            if (debugItem.message) {
                                html += '&nbsp;&nbsp;• <strong>Error:</strong> ' + debugItem.message + '<br>';
                            }
                            
                            html += '</li>';
                        }
                        
                        html += '</ul>';
                        html += '<p style="margin-bottom: 0;"><em>Please screenshot this and send to support.</em></p>';
                        html += '</div>';
                        
                        $('#ai-seo-bulk-results').html(html);
                        return; // Don't reload page, show debug info
                    }
                    
                    html += '<h3 style="margin-top: 0; color: #155724;">✓ Successfully Generated Content</h3>';
                    html += '<p><strong>Products Processed:</strong> ' + data.processed + '</p>';
                    
                    // v1.3.2b: Show backup/restore summary if enabled
                    if (data.backup && data.backup.enabled) {
                        if (data.backup.mode === 'auto') {
                            // Auto mode - always show PENDING at this stage (before score calculation)
                            html += '<div style="margin: 10px 0; padding: 10px; background: #e7f3ff; border: 1px solid #2271b1; border-radius: 4px;">';
                            html += '<strong>⏳ Auto-Restore Pending</strong> (Threshold: ' + data.backup.threshold + ')<br>';
                            html += '<small>Original content backed up. Auto-restore will run <strong>after</strong> you calculate scores below.</small><br>';
                            html += '<small>Products scoring ≤ ' + data.backup.threshold + ' will be automatically restored to original.</small>';
                            html += '</div>';
                        } else if (data.backup.mode === 'manual' && data.backup.pending_review) {
                            html += '<div style="margin: 10px 0; padding: 10px; background: #e7f3ff; border: 1px solid #2271b1; border-radius: 4px;">';
                            html += '<strong>💾 Backup Review</strong><br>';
                            html += '<small>Original content backed up. Review results below and choose to keep or restore each product.</small>';
                            html += '</div>';
                        }
                    }
                    
                    html += '<ul style="list-style: none; padding: 0;">';
                    
                    for (var postId in data.results) {
                        var result = data.results[postId];
                        var scoreChange = '';
                        var borderColor = '#28a745';
                        
                        // v1.3.2: Show score comparison if backup was created
                        if (result.backup_created && result.original_score !== null) {
                            var newScore = result.seo_score || 0;
                            var oldScore = result.original_score || 0;
                            if (newScore > oldScore) {
                                scoreChange = ' <span style="color: #28a745;">⬆ ' + oldScore + ' → ' + newScore + '</span>';
                            } else if (newScore < oldScore) {
                                scoreChange = ' <span style="color: #dc3545;">⬇ ' + oldScore + ' → ' + newScore + '</span>';
                                borderColor = '#ffc107';
                            } else {
                                scoreChange = ' <span style="color: #6c757d;">→ ' + newScore + ' (no change)</span>';
                            }
                        }
                        
                        // v1.3.2: Mark restored products
                        if (result.restored) {
                            html += '<li style="margin: 10px 0; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;">';
                            html += '<strong>Product ID ' + postId + ':</strong> <span style="color: #856404;">↩ RESTORED</span><br>';
                            html += '<small>' + result.restore_reason + '</small>';
                        } else {
                            html += '<li style="margin: 10px 0; padding: 10px; background: #fff; border-left: 3px solid ' + borderColor + ';">';
                            html += '<strong>Product ID ' + postId + ':</strong>' + scoreChange + '<br>';
                        }
                        
                        if (result.focus_keyword && !result.restored) {
                            html += '&nbsp;&nbsp;• <strong>Keyword:</strong> ' + result.focus_keyword + '<br>';
                        }
                        if (result.title && !result.restored) {
                            html += '&nbsp;&nbsp;• <strong>Title:</strong> ' + result.title.substring(0, 60) + (result.title.length > 60 ? '...' : '') + '<br>';
                        }
                        if (result.meta_description && !result.restored) {
                            html += '&nbsp;&nbsp;• <strong>Meta:</strong> ' + result.meta_description.substring(0, 80) + (result.meta_description.length > 80 ? '...' : '') + '<br>';
                        }
                        if (result.tags && !result.restored) {
                            html += '&nbsp;&nbsp;• <strong>Tags:</strong> ' + result.tags + '<br>';
                        }
                        
                        // v1.3.2: Add restore/keep buttons for manual mode
                        if (data.backup && data.backup.mode === 'manual' && result.has_backup && !result.restored) {
                            html += '<div style="margin-top: 8px;">';
                            html += '<button class="button ai-seo-keep-btn" data-post-id="' + postId + '" style="margin-right: 5px;">✓ Keep New</button>';
                            html += '<button class="button ai-seo-restore-btn" data-post-id="' + postId + '">↩ Restore Original</button>';
                            html += '</div>';
                        }
                        
                        html += '</li>';
                    }
                    
                    html += '</ul>';
                    
                    // v1.3.2: Add bulk action buttons for manual mode
                    if (data.backup && data.backup.mode === 'manual' && data.backup.pending_review) {
                        var hasBackups = false;
                        for (var pid in data.results) {
                            if (data.results[pid].has_backup) {
                                hasBackups = true;
                                break;
                            }
                        }
                        if (hasBackups) {
                            html += '<div style="margin: 15px 0; padding: 10px; background: #f0f0f1; border-radius: 4px; text-align: center;">';
                            html += '<button id="ai-seo-keep-all-btn" class="button button-primary" style="margin-right: 10px;">✓ Keep All New Content</button>';
                            html += '<span style="color: #666;">or use individual buttons above</span>';
                            html += '</div>';
                        }
                    }
                    
                    // Add OPTIONAL RankMath Score Calculation (v1.2.1.7a - Check if enabled in Tools)
                    console.log('AI SEO: Checking score calculation setting...');
                    console.log('AI SEO: aiSeoEnableScoreCalculation = ' + (typeof aiSeoEnableScoreCalculation !== 'undefined' ? aiSeoEnableScoreCalculation : 'undefined'));
                    var scoreCalcEnabled = typeof aiSeoEnableScoreCalculation !== 'undefined' && aiSeoEnableScoreCalculation === '1';
                    console.log('AI SEO: scoreCalcEnabled = ' + scoreCalcEnabled);
                    
                    if (scoreCalcEnabled) {
                        console.log('AI SEO: Score calculation is ENABLED - showing buttons');
                        
                        // v1.3.2b: Check if auto-restore is pending
                        var autoRestorePending = data.backup && data.backup.mode === 'auto';
                        var autoRestoreNote = '';
                        
                        if (autoRestorePending) {
                            autoRestoreNote = '<div style="margin-bottom: 10px; padding: 8px; background: #fff3cd; border-radius: 4px;">';
                            autoRestoreNote += '<strong>⚠️ Required for Auto-Restore:</strong> Score calculation must run to determine which products to keep or restore.';
                            autoRestoreNote += '</div>';
                        }
                        
                        html += '<div style="margin: 20px 0; padding: 15px; background: #e7f3ff; border: 1px solid #2271b1; border-radius: 4px;">';
                        html += autoRestoreNote;
                        html += '<button id="ai-seo-calculate-scores-btn" class="button button-primary" style="margin-right: 10px;">Calculate Scores Now</button>';
                        html += '<button id="ai-seo-close-popup-btn" class="button">Close Without Calculating</button>';
                        html += '<div id="ai-seo-refresh-status" style="margin-top: 10px;"></div>';
                        html += '</div>';
                    } else {
                        // Score calculation disabled - just show close button
                        console.log('AI SEO: Score calculation is DISABLED - showing only close button');
                        html += '<div style="margin: 20px 0;">';
                        html += '<button id="ai-seo-close-popup-btn" class="button button-primary">Close</button>';
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    
                    $('#ai-seo-bulk-results').html(html);
                    
                    console.log('AI SEO: SUCCESS - Showing results');
                    
                    // Store product IDs for refresh function
                    var productIds = Object.keys(data.results);
                    
                    // NO auto-reload - user controls when to close
                    console.log('AI SEO: Results displayed - waiting for user action');
                    
                    // Close button handler (v1.2.1.5) - Skip score calculation
                    $('#ai-seo-close-popup-btn').on('click', function() {
                        console.log('AI SEO: User chose to skip score calculation');
                        $('#ai-seo-bulk-overlay, #ai-seo-bulk-popup').removeClass('visible');
                        window.location.reload();
                    });
                    
                    // v1.3.2: Individual Restore button handler
                    $(document).on('click', '.ai-seo-restore-btn', function() {
                        var $btn = $(this);
                        var postId = $btn.data('post-id');
                        $btn.prop('disabled', true).text('Restoring...');
                        
                        $.ajax({
                            url: aiSeoSettings.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ai_seo_restore_product',
                                post_id: postId,
                                nonce: aiSeoSettings.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    $btn.closest('li').css('background', '#fff3cd').find('strong:first').after(' <span style="color: #856404;">↩ RESTORED</span>');
                                    $btn.parent().html('<span style="color: #28a745;">✓ Restored to original</span>');
                                } else {
                                    alert('Restore failed: ' + (response.data ? response.data.message : 'Unknown error'));
                                    $btn.prop('disabled', false).text('↩ Restore Original');
                                }
                            },
                            error: function() {
                                alert('Error restoring product');
                                $btn.prop('disabled', false).text('↩ Restore Original');
                            }
                        });
                    });
                    
                    // v1.3.2: Individual Keep button handler
                    $(document).on('click', '.ai-seo-keep-btn', function() {
                        var $btn = $(this);
                        var postId = $btn.data('post-id');
                        $btn.prop('disabled', true).text('Saving...');
                        
                        $.ajax({
                            url: aiSeoSettings.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ai_seo_approve_product',
                                post_id: postId,
                                nonce: aiSeoSettings.nonce
                            },
                            success: function(response) {
                                $btn.parent().html('<span style="color: #28a745;">✓ New content saved</span>');
                            },
                            error: function() {
                                alert('Error saving');
                                $btn.prop('disabled', false).text('✓ Keep New');
                            }
                        });
                    });
                    
                    // v1.3.2: Keep All button handler
                    $(document).on('click', '#ai-seo-keep-all-btn', function() {
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('Saving all...');
                        
                        // Collect all product IDs with backups
                        var approveIds = [];
                        $('.ai-seo-keep-btn').each(function() {
                            approveIds.push($(this).data('post-id'));
                        });
                        
                        $.ajax({
                            url: aiSeoSettings.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ai_seo_bulk_backup_action',
                                restore_ids: [],
                                approve_ids: approveIds,
                                nonce: aiSeoSettings.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Update all buttons to show saved
                                    $('.ai-seo-keep-btn, .ai-seo-restore-btn').parent().html('<span style="color: #28a745;">✓ New content saved</span>');
                                    $btn.text('✓ All Saved!');
                                } else {
                                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                                    $btn.prop('disabled', false).text('✓ Keep All New Content');
                                }
                            },
                            error: function() {
                                alert('Error saving');
                                $btn.prop('disabled', false).text('✓ Keep All New Content');
                            }
                        });
                    });
                    
                    // Calculate Scores button handler (v1.3.2b - simplified, no checkbox needed)
                    $('#ai-seo-calculate-scores-btn').on('click', function() {
                        console.log('AI SEO: Starting score calculation');
                        
                        var $btn = $(this);
                        var $closeBtn = $('#ai-seo-close-popup-btn');
                        var $status = $('#ai-seo-refresh-status');
                        
                        $btn.prop('disabled', true).text('Calculating...');
                        $closeBtn.prop('disabled', true);
                        
                        // v1.3.2b: In manual mode, clicking Calculate = implicit "Keep All"
                        // Delete all backups before calculating scores
                        if (data.backup && data.backup.mode === 'manual') {
                            console.log('AI SEO: Manual mode - deleting backups (implicit Keep All)');
                            $status.html('<em style="color: #856404;">Keeping all new content...</em>');
                            
                            // Collect all product IDs
                            var approveIds = productIds;
                            
                            $.ajax({
                                url: aiSeoSettings.ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'ai_seo_bulk_backup_action',
                                    restore_ids: [],
                                    approve_ids: approveIds,
                                    nonce: aiSeoSettings.nonce
                                },
                                success: function(response) {
                                    console.log('AI SEO: Backups deleted, proceeding with score calculation');
                                    startScoreCalculation();
                                },
                                error: function() {
                                    console.log('AI SEO: Failed to delete backups, proceeding anyway');
                                    startScoreCalculation();
                                }
                            });
                        } else {
                            startScoreCalculation();
                        }
                        
                        function startScoreCalculation() {
                            $status.html('<em style="color: #856404;">Starting score calculation...</em>');
                            
                            console.log('AI SEO: Starting RankMath score calculation for ' + productIds.length + ' products');
                            console.log('AI SEO: Process: Load page → Wait 7 sec → Click Update → Wait 2 sec → Verify');
                            console.log('AI SEO: Total time: ~9-10 seconds per product');
                        
                        var currentIndex = 0;
                        var completedProducts = [];
                        var failedProducts = [];
                        var calculatedScores = {}; // v1.3.2a: Store scores for auto-restore check
                        
                        function calculateNextProduct() {
                            if (currentIndex >= productIds.length) {
                                // All done!
                                console.log('AI SEO: Calculation complete!');
                                console.log('AI SEO: ✓ Successful: ' + completedProducts.length);
                                console.log('AI SEO: ✗ Failed: ' + failedProducts.length);
                                
                                var statusMsg = '<strong style="color: #28a745;">✓ Calculated ' + completedProducts.length + ' scores!</strong>';
                                if (failedProducts.length > 0) {
                                    statusMsg += '<br><span style="color: #856404;">⚠ ' + failedProducts.length + ' products need manual update (open edit page, wait 10 sec, click Update)</span>';
                                }
                                
                                // v1.3.2b: Check if auto-restore mode - trigger after score calculation
                                if (data.backup && data.backup.mode === 'auto' && completedProducts.length > 0) {
                                    statusMsg += '<br><em>Running auto-restore check...</em>';
                                    $status.html(statusMsg);
                                    
                                    console.log('AI SEO: Triggering auto-restore check for ' + completedProducts.length + ' products');
                                    
                                    // Build scores object - fetch current scores from server
                                    $.ajax({
                                        url: aiSeoSettings.ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'ai_seo_auto_restore_check',
                                            scores: JSON.stringify(calculatedScores),
                                            nonce: aiSeoSettings.nonce
                                        },
                                        success: function(response) {
                                            console.log('AI SEO: Auto-restore response:', response);
                                            
                                            if (response.success) {
                                                var restoreMsg = '<strong style="color: #28a745;">✓ Auto-restore complete!</strong><br>';
                                                restoreMsg += 'Kept: ' + response.data.kept + ' products<br>';
                                                if (response.data.restored > 0) {
                                                    restoreMsg += '<span style="color: #856404;">Restored: ' + response.data.restored + ' products (below threshold ' + response.data.threshold + ')</span><br>';
                                                }
                                                restoreMsg += '<em>Reloading page...</em>';
                                                $status.html(restoreMsg);
                                            }
                                            
                                            setTimeout(function() {
                                                $('#ai-seo-bulk-overlay, #ai-seo-bulk-popup').removeClass('visible');
                                                window.location.reload();
                                            }, 3000);
                                        },
                                        error: function() {
                                            console.log('AI SEO: Auto-restore check failed');
                                            $status.html(statusMsg + '<br><span style="color: red;">Auto-restore check failed</span><br><em>Reloading page...</em>');
                                            setTimeout(function() {
                                                $('#ai-seo-bulk-overlay, #ai-seo-bulk-popup').removeClass('visible');
                                                window.location.reload();
                                            }, 2500);
                                        }
                                    });
                                    return;
                                }
                                
                                statusMsg += '<br><em>Reloading page...</em>';
                                $status.html(statusMsg);
                                
                                setTimeout(function() {
                                    $('#ai-seo-bulk-overlay, #ai-seo-bulk-popup').removeClass('visible');
                                    window.location.reload();
                                }, 2500);
                                return;
                            }
                            
                            var productId = productIds[currentIndex];
                            console.log('AI SEO: Processing product ' + productId + ' (' + (currentIndex + 1) + ' of ' + productIds.length + ')');
                            
                            $status.html('<em style="color: #856404;">Product ' + (currentIndex + 1) + ' of ' + productIds.length + ': Loading edit page...</em>');
                            
                            // STEP 1: Create hidden iframe to load product edit page
                            // This makes RankMath's JavaScript run and calculate the score
                            var editUrl = aiSeoSettings.ajaxurl.replace('admin-ajax.php', 'post.php?post=' + productId + '&action=edit');
                            var $iframe = $('<iframe>', {
                                id: 'ai-seo-score-iframe-' + productId,
                                src: editUrl,
                                style: 'position: absolute; left: -9999px; width: 1px; height: 1px;'
                            }).appendTo('body');
                            
                            console.log('AI SEO: Iframe created for product ' + productId);
                            
                            // STEP 2: Wait for page to load and RankMath to calculate score
                            // v1.3.1: Use configurable timing from settings (default 5 seconds = 5000ms)
                            var scoreWaitTime = aiSeoSettings.scoreWaitTime || 5000;
                            console.log('AI SEO: Waiting ' + (scoreWaitTime / 1000) + ' seconds for page load and score calculation...');
                            
                            setTimeout(function() {
                                console.log('AI SEO: Wait time elapsed - SEO plugin should have calculated score for product ' + productId);
                                $status.html('<em style="color: #856404;">Product ' + (currentIndex + 1) + ' of ' + productIds.length + ': Clicking Update button...</em>');
                                
                                // STEP 3: Try to click the Update button in the iframe
                                var buttonClicked = false;
                                var clickMethod = 'none';
                                
                                try {
                                    // Try to access iframe content (may fail due to cross-origin)
                                    var iframeDoc = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
                                    
                                    if (iframeDoc) {
                                        console.log('AI SEO: ✓ Iframe document accessible for product ' + productId);
                                        
                                        // Try multiple button selectors in order of preference
                                        var buttonSelectors = [
                                            '#publish',           // Standard publish/update button
                                            '#post-preview',      // Preview might trigger save
                                            '.editor-post-publish-button',  // Gutenberg
                                            'button[type="submit"]'  // Any submit button
                                        ];
                                        
                                        for (var i = 0; i < buttonSelectors.length && !buttonClicked; i++) {
                                            var $button = $(iframeDoc).find(buttonSelectors[i]);
                                            if ($button.length > 0) {
                                                console.log('AI SEO: Found button with selector: ' + buttonSelectors[i]);
                                                $button.click();
                                                buttonClicked = true;
                                                clickMethod = buttonSelectors[i];
                                                console.log('AI SEO: ✓ Clicked Update button (' + clickMethod + ') for product ' + productId);
                                                break;
                                            }
                                        }
                                        
                                        if (!buttonClicked) {
                                            console.warn('AI SEO: ⚠ Update button not found in iframe for product ' + productId);
                                            console.log('AI SEO: Will try backend save as fallback');
                                        }
                                    }
                                } catch (e) {
                                    console.warn('AI SEO: ⚠ Could not access iframe due to cross-origin restrictions for product ' + productId);
                                    console.log('AI SEO: Error:', e.message);
                                    console.log('AI SEO: Will try backend save as fallback');
                                }
                                
                                // STEP 4: Wait for save to complete (if button was clicked) or proceed with backend save
                                var saveWaitTime = buttonClicked ? 2000 : 500; // 2 seconds if button clicked, 0.5 if not (optimized v1.2.1.6)
                                
                                setTimeout(function() {
                                    $status.html('<em style="color: #856404;">Product ' + (currentIndex + 1) + ' of ' + productIds.length + ': Verifying score...</em>');
                                    
                                    // STEP 5: Call backend to verify/trigger final save
                                    $.ajax({
                                        url: aiSeoSettings.ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'ai_seo_calculate_rankmath_score',
                                            product_id: productId,
                                            button_clicked: buttonClicked,
                                            click_method: clickMethod,
                                            nonce: aiSeoSettings.nonce
                                        },
                                        timeout: 10000,
                                        success: function(response) {
                                            console.log('AI SEO: Backend verification response for product ' + productId + ':', response);
                                            
                                            if (response.success) {
                                                var scoreData = response.data;
                                                if (scoreData.score_after && scoreData.score_after !== 'NOT SET') {
                                                    console.log('AI SEO: ✓ Score saved for product ' + productId + ' (Score: ' + scoreData.score_after + ')');
                                                    completedProducts.push(productId);
                                                    calculatedScores[productId] = parseInt(scoreData.score_after) || 0; // v1.3.2a: Store for auto-restore
                                                } else {
                                                    console.warn('AI SEO: ⚠ Score still not set for product ' + productId + ' - needs manual update');
                                                    failedProducts.push(productId);
                                                }
                                            } else {
                                                console.warn('AI SEO: ✗ Backend error for product ' + productId + ':', response.data ? response.data.error : 'Unknown error');
                                                failedProducts.push(productId);
                                            }
                                            
                                            // STEP 6: Clean up iframe
                                            $iframe.remove();
                                            console.log('AI SEO: Iframe removed for product ' + productId);
                                            
                                            // STEP 7: Move to next product
                                            currentIndex++;
                                            calculateNextProduct();
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('AI SEO: AJAX error for product ' + productId + ':', status, error);
                                            failedProducts.push(productId);
                                            
                                            // Clean up and continue
                                            $iframe.remove();
                                            currentIndex++;
                                            calculateNextProduct();
                                        }
                                    });
                                }, saveWaitTime);
                            }, scoreWaitTime); // v1.3.1: Dynamic timing from settings
                        }
                        
                        // Start the calculation process
                        calculateNextProduct();
                        } // End of startScoreCalculation function
                    });
                    
                    // Re-enable the generate button
                    $('#ai-seo-bulk-start').prop('disabled', false).text('Start Generation');
                }
                // End of showGenerationResults function (v1.2.1.8)
            
    });
})(jQuery);

// --- Tab Switching Logic (v2.0.9: URL hash persistence) ---
jQuery(document).ready(function ($) {
    // Function to show a specific tab
    function showTab(tabId) {
        if (!tabId || tabId === '#') {
            tabId = '#ai-settings'; // Default tab
        }
        
        // Update nav tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[href="' + tabId + '"]').addClass('nav-tab-active');
        
        // Show tab content
        $('.tab-content').hide();
        $(tabId).show();
    }
    
    // On page load, check for URL hash or settings-updated
    var initialTab = window.location.hash || '#ai-settings';
    
    // Show settings saved notice if settings-updated is in URL
    if (window.location.search.indexOf('settings-updated=true') !== -1) {
        // Create notice
        var notice = '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>Settings saved successfully.</strong></p></div>';
        $('.ai-seo-dashboard h1').after(notice);
        
        // If we came from a specific tab, show it
        if (window.location.hash) {
            initialTab = window.location.hash;
        }
    }
    
    // Show initial tab
    showTab(initialTab);
    
    // Tab click handler
    $('.nav-tab').on('click', function (e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Update URL hash without scrolling
        history.replaceState(null, null, target);
        
        // Show the tab
        showTab(target);
        
        console.log('AI SEO: Switched to tab ' + target);
    });
    
    // v2.0.9: Before form submit, save current tab to localStorage
    // so we can restore it after the page reloads
    $('form').on('submit', function() {
        var currentTab = window.location.hash || '#ai-settings';
        localStorage.setItem('ai_seo_last_tab', currentTab);
    });
    
    // Restore tab from localStorage if settings were updated
    if (window.location.search.indexOf('settings-updated=true') !== -1) {
        var savedTab = localStorage.getItem('ai_seo_last_tab');
        if (savedTab) {
            showTab(savedTab);
            // Update URL to include the hash
            history.replaceState(null, null, window.location.pathname + window.location.search + savedTab);
            localStorage.removeItem('ai_seo_last_tab');
        }
    }
});

// Model dropdown switching based on AI engine
jQuery(document).ready(function($) {
    // Model options for each engine
    const modelOptions = {
        'chatgpt': {
            'gpt-4o': 'GPT-4o (Recommended) - Balanced',
            'gpt-4o-mini': 'GPT-4o Mini - Fast & Affordable',
            'gpt-4-turbo': 'GPT-4 Turbo - High Quality',
            'o1-preview': 'o1 Preview - Advanced Reasoning',
            'o1-mini': 'o1 Mini - Fast Reasoning',
            'custom': 'Custom Model (enter below)'
        },
        'claude': {
            'claude-sonnet-4-5-20250929': 'Claude Sonnet 4.5 (Recommended) - Latest & Best',
            'claude-opus-4-5-20251101': 'Claude Opus 4.5 - Most Powerful',
            'claude-haiku-4-5-20251001': 'Claude Haiku 4.5 - Fastest & Most Affordable',
            'claude-sonnet-4-20250514': 'Claude Sonnet 4',
            'claude-opus-4-1-20250805': 'Claude Opus 4.1',
            'claude-opus-4-20250514': 'Claude Opus 4',
            'claude-3-5-haiku-20241022': 'Claude 3.5 Haiku',
            'claude-3-haiku-20240307': 'Claude 3 Haiku',
            'custom': 'Custom Model (enter below)'
        },
        'google': {
            'gemini-1.5-pro': 'Gemini 1.5 Pro',
            'gemini-1.5-flash': 'Gemini 1.5 Flash',
            'custom': 'Custom Model (enter below)'
        },
        'openrouter': {
            'custom': 'Enter OpenRouter Model'
        },
        'microsoft': {
            'custom': 'Enter Azure Deployment Name'
        },
        'xai': {
            'grok-beta': 'Grok Beta',
            'custom': 'Custom Model (enter below)'
        }
    };
    
    // Store API keys per engine
    var apiKeys = {
        'chatgpt': $('#ai-seo-api-key').data('chatgpt-key') || '',
        'claude': $('#ai-seo-api-key').data('claude-key') || '',
        'google': $('#ai-seo-api-key').data('google-key') || '',
        'openrouter': $('#ai-seo-api-key').data('openrouter-key') || '',
        'microsoft': $('#ai-seo-api-key').data('microsoft-key') || '',
        'xai': $('#ai-seo-api-key').data('xai-key') || ''
    };
    
    // Store current engine on page load
    const currentEngine = $('#ai-seo-ai-engine').val();
    $('#ai-seo-ai-engine').data('previous-engine', currentEngine);
    
    // Update model dropdown when engine changes
    $('#ai-seo-ai-engine').on('change', function() {
        const engine = $(this).val();
        const $modelSelect = $('#ai-seo-model');
        const $apiKey = $('input[name="ai_seo_settings[ai_seo_api_key]"]');
        const previousEngine = $(this).data('previous-engine') || 'chatgpt';
        
        // Save current API key to the previous engine
        const currentKey = $apiKey.val();
        if (currentKey) {
            apiKeys[previousEngine] = currentKey;
            // Also update the hidden field for the previous engine
            $('#ai-seo-hidden-key-' + previousEngine).val(currentKey);
        }
        
        // Load API key for new engine
        $apiKey.val(apiKeys[engine] || '');
        
        // Update previous engine tracker
        $(this).data('previous-engine', engine);
        
        // Update placeholder
        const engineName = $(this).find('option:selected').text();
        $apiKey.attr('placeholder', 'Enter API key for ' + engineName);
        
        // Clear current model options
        $modelSelect.empty();
        
        // Add new options for selected engine
        if (modelOptions[engine]) {
            $.each(modelOptions[engine], function(value, label) {
                $modelSelect.append($('<option>', {
                    value: value,
                    text: label
                }));
            });
        }
        
        // Select first option (recommended model) for new engine
        $modelSelect.prop('selectedIndex', 0);
        
        // Hide custom model input (it's not selected when changing engines)
        $('#ai-seo-custom-model').hide();
        
        // Store current engine for next change
        $(this).data('previous-engine', engine);
        
        // Show brief notice if switching to an engine with no saved key
        if (!apiKeys[engine]) {
            const notice = $('<div class="notice notice-info is-dismissible" style="margin: 10px 0;"><p><strong>Switched to ' + engineName + '.</strong> Please enter your API key for this engine.</p></div>');
            $('.form-table').first().before(notice);
            setTimeout(function() { notice.fadeOut(function() { $(this).remove(); }); }, 4000);
        }
    });
    
    // Show/hide custom model input
    $('#ai-seo-model').on('change', function() {
        const isCustom = $(this).val() === 'custom';
        const $customDiv = $('#ai-seo-custom-model');
        
        if (isCustom) {
            $customDiv.slideDown();
        } else {
            $customDiv.slideUp();
            // Update the actual form field value when using preset
            $('input[name="ai_seo_settings[ai_seo_model]"]').remove();
            $(this).after('<input type="hidden" name="ai_seo_settings[ai_seo_model]" value="' + $(this).val() + '">');
        }
    });
    
    // Sync custom model input with form
    $('#ai-seo-custom-model-input').on('input', function() {
        $('input[name="ai_seo_settings[ai_seo_model]"]').remove();
        $('#ai-seo-model').after('<input type="hidden" name="ai_seo_settings[ai_seo_model]" value="' + $(this).val() + '">');
    });
    
    // Update hidden field when API key is changed
    $('#ai-seo-api-key').on('input change', function() {
        const currentEngine = $('#ai-seo-ai-engine').val();
        const apiKey = $(this).val();
        $('#ai-seo-hidden-key-' + currentEngine).val(apiKey);
        apiKeys[currentEngine] = apiKey;
    });
    
    // CSP-COMPLIANT: Range slider event listeners (no inline oninput handlers)
    $('.ai-seo-range-slider').on('input', function() {
        $(this).next('.ai-seo-range-output').text(this.value);
    });
    
    // v2.0.0: Check for active batch on page load (products page only)
    if (aiSeoSettings.useBackgroundProcessing && window.AISEOQueueManager && $('input[name="post[]"]').length > 0) {
        var queueManager = new AISEOQueueManager();
        queueManager.checkForActiveBatch(function(batch) {
            if (batch) {
                // Show notification about active batch
                var $notice = $('<div class="notice notice-info is-dismissible" style="margin: 10px 0;">' +
                    '<p><strong>AI SEO:</strong> You have an active generation in progress (' + batch.progress + '% complete). ' +
                    '<a href="#" class="ai-seo-resume-link">Click here to monitor progress</a></p>' +
                    '</div>');
                
                $('.wrap h1').first().after($notice);
                
                $notice.find('.ai-seo-resume-link').on('click', function(e) {
                    e.preventDefault();
                    $notice.remove();
                    
                    // Open the popup and show progress
                    ensurePopup();
                    $('#ai-seo-bulk-overlay, #ai-seo-bulk-popup').addClass('visible');
                    
                    var progressUI = new AISEOProgressUI('#ai-seo-bulk-results');
                    progressUI.showProgressUI();
                    
                    var resumeManager = new AISEOQueueManager();
                    resumeManager.resumeBatch(batch.batch_id, {
                        onProgress: function(data) { progressUI.updateProgress(data); },
                        onComplete: function(results) { progressUI.showResults(results); },
                        onError: function(message) { progressUI.showError(message); }
                    });
                });
            }
        });
    }
});
