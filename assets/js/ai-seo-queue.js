/**
 * AI SEO Content Generator - Queue Manager
 * 
 * Handles background processing via polling
 * 
 * @package AI_SEO_Content_Generator
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    // Queue Manager Class
    class AISEOQueueManager {
        constructor() {
            this.batchId = null;
            this.pollInterval = null;
            this.pollDelay = 3000; // 3 seconds
            this.startTime = null;
            this.onComplete = null;
            this.onError = null;
            this.onProgress = null;
        }
        
        /**
         * Start a new generation batch
         */
        startBatch(productIds, prompts, callbacks = {}) {
            this.onComplete = callbacks.onComplete || function() {};
            this.onError = callbacks.onError || function() {};
            this.onProgress = callbacks.onProgress || function() {};
            this.startTime = Date.now();
            
            // Show initial status
            this.onProgress({
                status: 'starting',
                message: 'Queuing jobs...',
                progress: 0
            });
            
            $.ajax({
                url: aiSeoSettings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_start_batch',
                    nonce: aiSeoSettings.nonce,
                    product_ids: JSON.stringify(productIds),
                    prompts: JSON.stringify(prompts)
                },
                success: (response) => {
                    if (response.success) {
                        this.batchId = response.data.batch_id;
                        console.log('AI SEO Queue: Batch started', this.batchId);
                        
                        this.onProgress({
                            status: 'processing',
                            message: 'Processing started...',
                            progress: 0,
                            batchId: this.batchId
                        });
                        
                        this.startPolling();
                    } else {
                        console.error('AI SEO Queue: Failed to start batch', response.data);
                        // Pass full data object so we can check for active_batch_id
                        this.onError(response.data || {message: 'Failed to start generation'});
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AI SEO Queue: AJAX error', error);
                    this.onError({message: 'Network error: ' + error});
                }
            });
        }
        
        /**
         * Start polling for status updates
         */
        startPolling() {
            // Immediate first check
            this.checkStatus();
            
            // Then poll at interval
            this.pollInterval = setInterval(() => {
                this.checkStatus();
            }, this.pollDelay);
        }
        
        /**
         * Stop polling
         */
        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        }
        
        /**
         * Check batch status
         */
        checkStatus() {
            if (!this.batchId) {
                this.stopPolling();
                return;
            }
            
            $.ajax({
                url: aiSeoSettings.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_seo_batch_status',
                    nonce: aiSeoSettings.nonce,
                    batch_id: this.batchId
                },
                success: (response) => {
                    if (response.success) {
                        this.handleStatusUpdate(response.data);
                    } else {
                        console.error('AI SEO Queue: Status check failed', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AI SEO Queue: Status poll error', error);
                    // Don't stop polling on error - might be temporary
                }
            });
        }
        
        /**
         * Handle status update from server
         */
        handleStatusUpdate(data) {
            const progress = data.progress || 0;
            const status = data.status;
            
            console.log('AI SEO: Status update - status:', status, 'progress:', progress, 'failed:', data.failed_jobs);
            
            // Calculate ETA
            let eta = null;
            if (progress > 0 && progress < 100) {
                const elapsed = (Date.now() - this.startTime) / 1000;
                const rate = progress / elapsed;
                const remaining = (100 - progress) / rate;
                eta = this.formatTime(remaining);
            }
            
            // Build status message
            let message = 'Processing...';
            if (data.current_product && data.current_field) {
                message = `Generating ${this.formatFieldName(data.current_field)} for Product #${data.current_product}`;
            } else if (data.pending_jobs > 0) {
                message = `${data.pending_jobs} jobs waiting...`;
            }
            
            // Report progress
            this.onProgress({
                status: status,
                progress: progress,
                message: message,
                eta: eta,
                completed: data.completed_jobs,
                failed: data.failed_jobs,
                total: data.total_jobs,
                currentProduct: data.current_product,
                currentField: data.current_field,
                products: data.products
            });
            
            // Check for completion
            if (status === 'completed' || status === 'completed_with_errors') {
                console.log('AI SEO: Batch complete with status:', status);
                this.stopPolling();
                this.fetchResults();
            } else if (status === 'cancelled') {
                this.stopPolling();
                this.onError('Batch was cancelled');
            } else if (status === 'failed') {
                this.stopPolling();
                this.onError(data.error_message || 'Batch failed');
            }
        }
        
        /**
         * Fetch detailed results after completion
         */
        fetchResults() {
            console.log('AI SEO: Fetching results for batch', this.batchId);
            
            $.ajax({
                url: aiSeoSettings.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_seo_batch_results',
                    nonce: aiSeoSettings.nonce,
                    batch_id: this.batchId
                },
                success: (response) => {
                    console.log('AI SEO: Fetch results response:', response);
                    if (response.success) {
                        // Pass full data object (processed, results, backup, debug)
                        this.onComplete(response.data);
                    } else {
                        var msg = (response.data && response.data.message) ? response.data.message : 'Unknown error';
                        this.onError('Failed to fetch results: ' + msg);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AI SEO: Fetch results AJAX error');
                    console.error('AI SEO: Status:', status);
                    console.error('AI SEO: Error:', error);
                    console.error('AI SEO: XHR status:', xhr.status);
                    console.error('AI SEO: XHR statusText:', xhr.statusText);
                    if (xhr.responseText) {
                        console.error('AI SEO: Response text (first 1000 chars):', xhr.responseText.substring(0, 1000));
                    }
                    var errorMsg = error || status || 'Network error';
                    this.onError('Failed to fetch results: ' + errorMsg);
                }
            });
        }
        
        /**
         * Cancel the current batch
         */
        cancelBatch(callback) {
            if (!this.batchId) {
                callback && callback(false, 'No active batch');
                return;
            }
            
            this.stopPolling();
            
            $.ajax({
                url: aiSeoSettings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_seo_cancel_batch',
                    nonce: aiSeoSettings.nonce,
                    batch_id: this.batchId
                },
                success: (response) => {
                    if (response.success) {
                        console.log('AI SEO Queue: Batch cancelled');
                        this.batchId = null;
                        callback && callback(true);
                    } else {
                        callback && callback(false, response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    callback && callback(false, error);
                }
            });
        }
        
        /**
         * Check for active batch on page load
         */
        checkForActiveBatch(callback) {
            $.ajax({
                url: aiSeoSettings.ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_seo_get_active_batch',
                    nonce: aiSeoSettings.nonce
                },
                success: (response) => {
                    if (response.success && response.data.has_active_batch) {
                        this.batchId = response.data.batch.batch_id;
                        callback(response.data.batch);
                    } else {
                        callback(null);
                    }
                },
                error: () => {
                    callback(null);
                }
            });
        }
        
        /**
         * Resume polling for an existing batch
         */
        resumeBatch(batchId, callbacks = {}) {
            this.batchId = batchId;
            this.onComplete = callbacks.onComplete || function() {};
            this.onError = callbacks.onError || function() {};
            this.onProgress = callbacks.onProgress || function() {};
            this.startTime = Date.now();
            
            this.startPolling();
        }
        
        /**
         * Format field name for display
         */
        formatFieldName(fieldName) {
            const names = {
                'focus_keyword': 'Focus Keyword',
                'title': 'Title',
                'short_description': 'Short Description',
                'full_description': 'Full Description',
                'meta_description': 'Meta Description',
                'tags': 'Tags',
                'image_alt': 'Image Alt Tags',
                'faq_schema': 'FAQ Schema',
                'ai_summary': 'AI Summary',
                'target_audience': 'Target Audience',
                'value_proposition': 'Value Proposition',
                'care_instructions': 'Care Instructions',
                'product_highlights': 'Product Highlights',
                'pros_cons': 'Pros & Cons',
                'use_cases': 'Use Cases',
                'problem_solved': 'Problem Solved',
                'speakable': 'Speakable Content',
                'alt_names': 'Alternative Names',
                'seasonal': 'Seasonal Tags'
            };
            return names[fieldName] || fieldName;
        }
        
        /**
         * Format time in seconds to human-readable
         */
        formatTime(seconds) {
            if (seconds < 60) {
                return Math.round(seconds) + 's';
            }
            const minutes = Math.floor(seconds / 60);
            const secs = Math.round(seconds % 60);
            return minutes + 'm ' + secs + 's';
        }
    }
    
    // Progress UI Component
    class AISEOProgressUI {
        constructor(container) {
            this.$container = $(container);
            this.queueManager = new AISEOQueueManager();
        }
        
        /**
         * Initialize with existing popup
         */
        init() {
            // Check for active batch on load
            this.queueManager.checkForActiveBatch((batch) => {
                if (batch) {
                    this.showResumeDialog(batch);
                }
            });
        }
        
        /**
         * Start generation for selected products
         */
        startGeneration(productIds, prompts) {
            this.showProgressUI();
            
            this.queueManager.startBatch(productIds, prompts, {
                onProgress: (data) => this.updateProgress(data),
                onComplete: (results) => this.showResults(results),
                onError: (message) => this.showError(message)
            });
        }
        
        /**
         * Show progress UI
         */
        showProgressUI() {
            const html = `
                <div class="ai-seo-queue-progress">
                    <div class="ai-seo-progress-header">
                        <div class="ai-seo-progress-title">Generating Content</div>
                        <div class="ai-seo-progress-stats">
                            <span class="completed">0</span> / <span class="total">0</span> jobs
                        </div>
                    </div>
                    <div class="ai-seo-progress-bar-wrapper">
                        <div class="ai-seo-progress-bar" style="width: 0%"></div>
                        <div class="ai-seo-progress-percentage">0%</div>
                    </div>
                    <div class="ai-seo-progress-status">Initializing...</div>
                    <div class="ai-seo-progress-eta"></div>
                </div>
            `;
            
            this.$container.html(html);
        }
        
        /**
         * Update progress display
         */
        updateProgress(data) {
            this.$container.find('.ai-seo-progress-bar').css('width', data.progress + '%');
            this.$container.find('.ai-seo-progress-percentage').text(data.progress + '%');
            this.$container.find('.ai-seo-progress-status').text(data.message);
            this.$container.find('.ai-seo-progress-stats .completed').text(data.completed || 0);
            this.$container.find('.ai-seo-progress-stats .total').text(data.total || 0);
            
            if (data.eta) {
                this.$container.find('.ai-seo-progress-eta').text('ETA: ' + data.eta);
            }
            
            // Update failed count (replace existing or add new)
            var $failedSpan = this.$container.find('.ai-seo-progress-stats .failed');
            if (data.failed > 0) {
                if ($failedSpan.length) {
                    $failedSpan.text(data.failed + ' failed');
                } else {
                    this.$container.find('.ai-seo-progress-stats').append(
                        ` (<span class="failed" style="color: #dc3545;">${data.failed} failed</span>)`
                    );
                }
            }
        }
        
        /**
         * Show completion results - MATCHES LEGACY FORMAT EXACTLY
         */
        showResults(data) {
            // data contains: processed, results, backup, debug (same as legacy)
            var results = data.results || {};
            var backup = data.backup || {};
            var processed = data.processed || Object.keys(results).length;
            
            // Check for any failed products with error messages
            var hasApiError = false;
            var apiErrorMsg = '';
            for (var postId in results) {
                var result = results[postId];
                // Check error_message field
                if (result.error_message && result.error_message.indexOf('⚠️') !== -1) {
                    hasApiError = true;
                    apiErrorMsg = result.error_message;
                    break;
                }
                // Check restore_reason field
                if (result.restore_reason && result.restore_reason.indexOf('⚠️') !== -1) {
                    hasApiError = true;
                    apiErrorMsg = result.restore_reason.replace('Critical field failed: ', '');
                    break;
                }
            }
            
            // Show API error prominently if found
            if (hasApiError) {
                var html = '<div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px;">';
                html += '<h3 style="margin-top: 0; color: #721c24;">❌ Generation Failed</h3>';
                html += '<p style="font-size: 16px; color: #721c24; margin-bottom: 10px;"><strong>' + apiErrorMsg + '</strong></p>';
                html += '<p style="margin-bottom: 0;">Please check your API settings and billing status, then try again.</p>';
                html += '</div>';
                this.$container.html(html);
                return;
            }
            
            var html = '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px;">';
            
            html += '<h3 style="margin-top: 0; color: #155724;">✓ Successfully Generated Content</h3>';
            html += '<p><strong>Products Processed:</strong> ' + processed + '</p>';
            
            // Show backup/restore summary if enabled (same as legacy)
            if (backup && backup.enabled) {
                if (backup.mode === 'auto') {
                    html += '<div style="margin: 10px 0; padding: 10px; background: #e7f3ff; border: 1px solid #2271b1; border-radius: 4px;">';
                    html += '<strong>⏳ Auto-Restore Pending</strong> (Threshold: ' + backup.threshold + ')<br>';
                    html += '<small>Original content backed up. Auto-restore will run <strong>after</strong> you calculate scores below.</small><br>';
                    html += '<small>Products scoring ≤ ' + backup.threshold + ' will be automatically restored to original.</small>';
                    html += '</div>';
                } else if (backup.mode === 'manual' && backup.pending_review) {
                    html += '<div style="margin: 10px 0; padding: 10px; background: #e7f3ff; border: 1px solid #2271b1; border-radius: 4px;">';
                    html += '<strong>💾 Backup Review</strong><br>';
                    html += '<small>Original content backed up. Review results below and choose to keep or restore each product.</small>';
                    html += '</div>';
                }
            }
            
            html += '<ul style="list-style: none; padding: 0;">';
            
            // Store product IDs for later use
            var productIds = Object.keys(results);
            
            // Filter out restored products for score calculation
            var successfulProductIds = productIds.filter(function(pid) {
                return !results[pid].restored;
            });
            var restoredCount = productIds.length - successfulProductIds.length;
            
            for (var postId in results) {
                var result = results[postId];
                var scoreChange = '';
                var borderColor = '#28a745';
                
                // Show score comparison if backup was created
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
                
                // Mark restored products
                if (result.restored) {
                    html += '<li style="margin: 10px 0; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;">';
                    html += '<strong>Product ID ' + postId + ':</strong> <span style="color: #856404;">↩ RESTORED</span><br>';
                    html += '<small>' + (result.restore_reason || '') + '</small>';
                } else {
                    html += '<li style="margin: 10px 0; padding: 10px; background: #fff; border-left: 3px solid ' + borderColor + ';">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
                    html += '<strong>Product ID ' + postId + ':</strong>' + scoreChange;
                    
                    // Add expand button for manual review mode
                    if (backup && backup.mode === 'manual') {
                        html += '<button type="button" class="button button-small ai-seo-expand-btn" data-post-id="' + postId + '" style="font-size: 11px;">▼ Show Details</button>';
                    }
                    html += '</div>';
                }
                
                // Show generated content - summary view
                var summaryHtml = '';
                if (result.focus_keyword && !result.restored) {
                    summaryHtml += '&nbsp;&nbsp;• <strong>Keyword:</strong> ' + result.focus_keyword + '<br>';
                }
                if (result.title && !result.restored) {
                    var title = result.title;
                    summaryHtml += '&nbsp;&nbsp;• <strong>Title:</strong> ' + title.substring(0, 60) + (title.length > 60 ? '...' : '') + '<br>';
                }
                if (result.meta_description && !result.restored) {
                    var meta = result.meta_description;
                    summaryHtml += '&nbsp;&nbsp;• <strong>Meta:</strong> ' + meta.substring(0, 80) + (meta.length > 80 ? '...' : '') + '<br>';
                }
                if (result.tags && !result.restored) {
                    summaryHtml += '&nbsp;&nbsp;• <strong>Tags:</strong> ' + result.tags + '<br>';
                }
                
                html += '<div class="ai-seo-summary">' + summaryHtml + '</div>';
                
                // Full details view (hidden by default for manual mode)
                if (backup && backup.mode === 'manual' && !result.restored) {
                    html += '<div class="ai-seo-details" data-post-id="' + postId + '" style="display: none; margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
                    
                    if (result.focus_keyword) {
                        html += '<div style="margin-bottom: 8px;"><strong>🔑 Focus Keyword:</strong><br>';
                        html += '<span style="background: #e7f3ff; padding: 2px 6px; border-radius: 3px;">' + result.focus_keyword + '</span></div>';
                    }
                    
                    if (result.title) {
                        html += '<div style="margin-bottom: 8px;"><strong>📝 Title:</strong><br>';
                        html += '<span style="color: #1e3a8a;">' + result.title + '</span>';
                        html += ' <small style="color: #666;">(' + result.title.length + ' chars)</small></div>';
                    }
                    
                    if (result.meta_description) {
                        html += '<div style="margin-bottom: 8px;"><strong>📋 Meta Description:</strong><br>';
                        html += '<span style="color: #166534;">' + result.meta_description + '</span>';
                        html += ' <small style="color: #666;">(' + result.meta_description.length + ' chars)</small></div>';
                    }
                    
                    if (result.short_description) {
                        html += '<div style="margin-bottom: 8px;"><strong>📄 Short Description:</strong><br>';
                        html += '<div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 12px;">' + result.short_description + '</div></div>';
                    }
                    
                    if (result.full_description) {
                        html += '<div style="margin-bottom: 8px;"><strong>📄 Full Description:</strong><br>';
                        html += '<div style="max-height: 150px; overflow-y: auto; padding: 5px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 12px;">' + result.full_description + '</div></div>';
                    }
                    
                    if (result.tags) {
                        html += '<div style="margin-bottom: 8px;"><strong>🏷️ Tags:</strong><br>';
                        html += '<span style="color: #7c3aed;">' + result.tags + '</span></div>';
                    }
                    
                    // AI Search fields
                    if (result.faq_schema) {
                        html += '<div style="margin-bottom: 8px;"><strong>❓ FAQ:</strong><br>';
                        html += '<div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 12px;">' + result.faq_schema + '</div></div>';
                    }
                    
                    if (result.care_instructions) {
                        html += '<div style="margin-bottom: 8px;"><strong>🧴 Care Instructions:</strong><br>';
                        html += '<div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 12px;">' + result.care_instructions + '</div></div>';
                    }
                    
                    if (result.product_highlights) {
                        html += '<div style="margin-bottom: 8px;"><strong>✨ Highlights:</strong><br>';
                        html += '<div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 12px;">' + result.product_highlights + '</div></div>';
                    }
                    
                    if (result.pros_cons) {
                        html += '<div style="margin-bottom: 8px;"><strong>⚖️ Pros & Cons:</strong><br>';
                        html += '<div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 12px;">' + result.pros_cons + '</div></div>';
                    }
                    
                    html += '</div>';
                }
                
                // Add restore/keep buttons for manual mode
                if (backup && backup.mode === 'manual' && result.has_backup && !result.restored) {
                    html += '<div style="margin-top: 8px;">';
                    html += '<button class="button ai-seo-keep-btn" data-post-id="' + postId + '" style="margin-right: 5px;">✓ Keep New</button>';
                    html += '<button class="button ai-seo-restore-btn" data-post-id="' + postId + '">↩ Restore Original</button>';
                    html += '</div>';
                }
                
                html += '</li>';
            }
            
            html += '</ul>';
            
            // Add bulk action buttons for manual mode (only when multiple products)
            if (backup && backup.mode === 'manual' && backup.pending_review) {
                var backupCount = 0;
                for (var pid in results) {
                    if (results[pid].has_backup) {
                        backupCount++;
                    }
                }
                // Only show "Keep All" when there are 2+ products with backups
                if (backupCount > 1) {
                    html += '<div style="margin: 15px 0; padding: 10px; background: #f0f0f1; border-radius: 4px; text-align: center;">';
                    html += '<button id="ai-seo-keep-all-btn" class="button button-primary" style="margin-right: 10px;">✓ Keep All New Content</button>';
                    html += '<span style="color: #666;">or review each product individually</span>';
                    html += '</div>';
                }
            }
            
            // Add Calculate Scores button (check if enabled in tools)
            var scoreCalcEnabled = typeof aiSeoEnableScoreCalculation !== 'undefined' && aiSeoEnableScoreCalculation === '1';
            
            // Only show score calculation if there are successful (non-restored) products
            if (scoreCalcEnabled && successfulProductIds.length > 0) {
                var autoRestorePending = backup && backup.mode === 'auto';
                var autoRestoreNote = '';
                
                if (autoRestorePending) {
                    autoRestoreNote = '<div style="margin-bottom: 10px; padding: 8px; background: #fff3cd; border-radius: 4px;">';
                    autoRestoreNote += '<strong>⚠️ Required for Auto-Restore:</strong> Score calculation must run to determine which products to keep or restore.';
                    autoRestoreNote += '</div>';
                }
                
                html += '<div style="margin: 20px 0; padding: 15px; background: #e7f3ff; border: 1px solid #2271b1; border-radius: 4px;">';
                html += autoRestoreNote;
                
                // Show info about skipped restored products
                if (restoredCount > 0) {
                    html += '<div style="margin-bottom: 10px; padding: 8px; background: #f0f0f1; border-radius: 4px; color: #666;">';
                    html += '<small>ℹ️ ' + restoredCount + ' restored product' + (restoredCount > 1 ? 's' : '') + ' will be skipped (no new content to score)</small>';
                    html += '</div>';
                }
                
                html += '<button id="ai-seo-calculate-scores-btn" class="button button-primary" style="margin-right: 10px;">Calculate Scores Now</button>';
                html += '<button id="ai-seo-close-popup-btn" class="button">Close Without Calculating</button>';
                html += '<div id="ai-seo-refresh-status" style="margin-top: 10px;"></div>';
                html += '</div>';
            } else if (scoreCalcEnabled && successfulProductIds.length === 0) {
                // All products were restored - no scores to calculate
                html += '<div style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
                html += '<p style="margin: 0 0 10px 0;"><strong>⚠️ No scores to calculate</strong></p>';
                html += '<p style="margin: 0; color: #666;">All products were restored due to API errors. No new content was generated.</p>';
                html += '</div>';
                html += '<div style="margin: 20px 0;">';
                html += '<button id="ai-seo-close-popup-btn" class="button button-primary">Close & Refresh</button>';
                html += '</div>';
            } else {
                html += '<div style="margin: 20px 0;">';
                html += '<button id="ai-seo-close-popup-btn" class="button button-primary">Close</button>';
                html += '</div>';
            }
            
            html += '</div>';
            
            this.$container.html(html);
            
            // Update popup buttons
            $('#ai-seo-bulk-start').prop('disabled', false).text('Start Generation');
            
            // Store data for button handlers
            var self = this;
            var backupData = backup;
            
            // Bind close button
            $('#ai-seo-close-popup-btn').on('click', function() {
                $('#ai-seo-bulk-overlay, #ai-seo-bulk-popup').removeClass('visible');
                window.location.reload();
            });
            
            // Bind expand/collapse button for manual review
            $(document).off('click', '.ai-seo-expand-btn').on('click', '.ai-seo-expand-btn', function() {
                var $btn = $(this);
                var postId = $btn.data('post-id');
                var $details = $('.ai-seo-details[data-post-id="' + postId + '"]');
                var $summary = $btn.closest('li').find('.ai-seo-summary');
                
                if ($details.is(':visible')) {
                    $details.slideUp(200);
                    $summary.slideDown(200);
                    $btn.text('▼ Show Details');
                } else {
                    $summary.slideUp(200);
                    $details.slideDown(200);
                    $btn.text('▲ Hide Details');
                }
            });
            
            // Bind individual Restore button
            $(document).off('click', '.ai-seo-restore-btn').on('click', '.ai-seo-restore-btn', function() {
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
            
            // Bind individual Keep button
            $(document).off('click', '.ai-seo-keep-btn').on('click', '.ai-seo-keep-btn', function() {
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
            
            // Bind Keep All button
            $(document).off('click', '#ai-seo-keep-all-btn').on('click', '#ai-seo-keep-all-btn', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Saving all...');
                
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
            
            // Bind Calculate Scores button
            $('#ai-seo-calculate-scores-btn').on('click', function() {
                var $btn = $(this);
                var $closeBtn = $('#ai-seo-close-popup-btn');
                var $status = $('#ai-seo-refresh-status');
                
                $btn.prop('disabled', true).text('Calculating...');
                $closeBtn.prop('disabled', true);
                
                // In manual mode, clicking Calculate = implicit "Keep All" (only for successful products)
                if (backupData && backupData.mode === 'manual') {
                    $status.html('<em style="color: #856404;">Keeping all new content...</em>');
                    
                    // Only approve successful (non-restored) products
                    var approveIds = successfulProductIds;
                    
                    $.ajax({
                        url: aiSeoSettings.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ai_seo_bulk_backup_action',
                            restore_ids: [],
                            approve_ids: approveIds,
                            nonce: aiSeoSettings.nonce
                        },
                        success: function() {
                            startScoreCalculation();
                        },
                        error: function() {
                            startScoreCalculation();
                        }
                    });
                } else {
                    startScoreCalculation();
                }
                
                function startScoreCalculation() {
                    $status.html('<em style="color: #856404;">Starting score calculation...</em>');
                    
                    // Trigger the legacy score calculation flow
                    // This opens product pages in hidden iframes to trigger RankMath recalculation
                    // Only calculate scores for successful (non-restored) products
                    var currentIndex = 0;
                    var completedProducts = [];
                    var failedProducts = [];
                    
                    function calculateNextProduct() {
                        if (currentIndex >= successfulProductIds.length) {
                            // All done
                            var statusMsg = '<strong style="color: #28a745;">✓ Calculated ' + completedProducts.length + ' scores!</strong>';
                            if (failedProducts.length > 0) {
                                statusMsg += '<br><span style="color: #856404;">⚠ ' + failedProducts.length + ' products need manual update</span>';
                                statusMsg += '<br><small style="color: #666;">Tip: Open each product in the editor, wait 10 seconds, click Update.</small>';
                            }
                            
                            // Check for auto-restore
                            if (backupData && backupData.mode === 'auto' && completedProducts.length > 0) {
                                statusMsg += '<br><em>Running auto-restore check...</em>';
                                $status.html(statusMsg);
                                
                                $.ajax({
                                    url: aiSeoSettings.ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'ai_seo_auto_restore_check',
                                        product_ids: completedProducts,
                                        threshold: backupData.threshold,
                                        nonce: aiSeoSettings.nonce
                                    },
                                    success: function(response) {
                                        if (response.success && response.data.restored_count > 0) {
                                            statusMsg = '<strong style="color: #28a745;">✓ Scores calculated!</strong><br>';
                                            statusMsg += '<span style="color: #856404;">↩ Auto-restored ' + response.data.restored_count + ' products (score ≤ ' + backupData.threshold + ')</span>';
                                        }
                                        $status.html(statusMsg + '<br><br><button class="button button-primary" onclick="location.reload()">Close & Refresh</button>');
                                    },
                                    error: function() {
                                        $status.html(statusMsg + '<br><br><button class="button button-primary" onclick="location.reload()">Close & Refresh</button>');
                                    }
                                });
                            } else {
                                $status.html(statusMsg + '<br><br><button class="button button-primary" onclick="location.reload()">Close & Refresh</button>');
                            }
                            return;
                        }
                        
                        var productId = successfulProductIds[currentIndex];
                        $status.html('<em>Calculating score for Product #' + productId + ' (' + (currentIndex + 1) + '/' + successfulProductIds.length + '): Loading edit page...</em>');
                        
                        // STEP 1: Create hidden iframe to load product edit page
                        // This makes RankMath's JavaScript run and calculate the score
                        var editUrl = ajaxurl.replace('admin-ajax.php', 'post.php?post=' + productId + '&action=edit');
                        var $iframe = $('<iframe>', {
                            id: 'ai-seo-score-iframe-' + productId,
                            src: editUrl,
                            style: 'position: absolute; left: -9999px; width: 1px; height: 1px;'
                        }).appendTo('body');
                        
                        console.log('AI SEO: Iframe created for product ' + productId);
                        
                        // STEP 2: Wait for page to load and RankMath to calculate score
                        var scoreWaitTime = aiSeoSettings.scoreWaitTime || 5000;
                        console.log('AI SEO: Waiting ' + (scoreWaitTime / 1000) + ' seconds for score calculation...');
                        
                        setTimeout(function() {
                            $status.html('<em>Calculating score for Product #' + productId + ' (' + (currentIndex + 1) + '/' + successfulProductIds.length + '): Clicking Update...</em>');
                            
                            // STEP 3: Try to click the Update button in the iframe
                            var buttonClicked = false;
                            var clickMethod = 'none';
                            
                            try {
                                var iframeDoc = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
                                
                                if (iframeDoc) {
                                    // Try multiple button selectors
                                    var buttonSelectors = ['#publish', '.editor-post-publish-button', 'button[type="submit"]'];
                                    
                                    for (var i = 0; i < buttonSelectors.length && !buttonClicked; i++) {
                                        var $button = $(iframeDoc).find(buttonSelectors[i]);
                                        if ($button.length > 0) {
                                            $button.click();
                                            buttonClicked = true;
                                            clickMethod = buttonSelectors[i];
                                            console.log('AI SEO: ✓ Clicked button (' + clickMethod + ') for product ' + productId);
                                            break;
                                        }
                                    }
                                }
                            } catch (e) {
                                console.warn('AI SEO: Could not access iframe (cross-origin): ' + e.message);
                            }
                            
                            // STEP 4: Wait for save, then verify via AJAX
                            var saveWaitTime = buttonClicked ? 2000 : 500;
                            
                            setTimeout(function() {
                                $status.html('<em>Calculating score for Product #' + productId + ' (' + (currentIndex + 1) + '/' + successfulProductIds.length + '): Verifying...</em>');
                                
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
                                        if (response.success && response.data && response.data.score_after && response.data.score_after !== 'NOT SET') {
                                            completedProducts.push(productId);
                                            console.log('AI SEO: ✓ Score saved for product ' + productId + ': ' + response.data.score_after);
                                        } else {
                                            failedProducts.push(productId);
                                            console.log('AI SEO: Score not saved for product ' + productId);
                                        }
                                    },
                                    error: function() {
                                        failedProducts.push(productId);
                                    },
                                    complete: function() {
                                        // Clean up iframe
                                        $iframe.remove();
                                        currentIndex++;
                                        
                                        // Small delay then next product
                                        setTimeout(calculateNextProduct, 500);
                                    }
                                });
                            }, saveWaitTime);
                        }, scoreWaitTime);
                    }
                    
                    calculateNextProduct();
                }
            });
        }
        
        /**
         * Calculate scores for completed products (legacy method)
         */
        calculateScores(productIds) {
            // This is now handled inline in showResults
        }
        
        /**
         * Show error message
         */
        showError(errorData) {
            // Handle both string and object error data
            var message = typeof errorData === 'string' ? errorData : (errorData.message || 'Unknown error');
            var activeBatchId = typeof errorData === 'object' ? errorData.active_batch_id : null;
            
            var cancelButton = '';
            if (activeBatchId) {
                cancelButton = `
                    <button type="button" class="button button-primary ai-seo-cancel-stuck-btn" data-batch-id="${activeBatchId}" style="margin-right: 10px;">Cancel Stuck Batch</button>
                `;
            }
            
            const html = `
                <div class="ai-seo-queue-error" style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                    <h3 style="margin-top: 0; color: #721c24;">❌ Error</h3>
                    <p>${message}</p>
                    ${cancelButton}
                    <button type="button" class="button" onclick="location.reload()">Close</button>
                </div>
            `;
            
            this.$container.html(html);
            
            // Bind cancel stuck batch button
            if (activeBatchId) {
                var self = this;
                this.$container.find('.ai-seo-cancel-stuck-btn').on('click', function() {
                    var $btn = $(this);
                    var batchId = $btn.data('batch-id');
                    $btn.prop('disabled', true).text('Cancelling...');
                    
                    $.ajax({
                        url: aiSeoSettings.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ai_seo_cancel_batch',
                            batch_id: batchId,
                            nonce: aiSeoSettings.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                self.$container.html(`
                                    <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                                        <p style="margin: 0;"><strong>✓ Batch cancelled.</strong> You can now start a new generation.</p>
                                        <button type="button" class="button button-primary" onclick="location.reload()" style="margin-top: 10px;">Refresh & Continue</button>
                                    </div>
                                `);
                            } else {
                                $btn.prop('disabled', false).text('Cancel Stuck Batch');
                                alert('Failed to cancel: ' + (response.data ? response.data.message : 'Unknown error'));
                            }
                        },
                        error: function() {
                            $btn.prop('disabled', false).text('Cancel Stuck Batch');
                            alert('Error cancelling batch. Please try again.');
                        }
                    });
                });
            }
        }
        
        /**
         * Cancel current generation
         */
        cancelGeneration() {
            if (!confirm('Are you sure you want to cancel? Progress will be lost.')) {
                return;
            }
            
            this.$container.find('.ai-seo-progress-status').text('Cancelling...');
            this.$container.find('.ai-seo-cancel-btn').prop('disabled', true);
            
            this.queueManager.cancelBatch((success, error) => {
                if (success) {
                    this.$container.html(`
                        <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                            <p><strong>Cancelled.</strong> Some products may have been partially processed.</p>
                            <button type="button" class="button" onclick="location.reload()">Close</button>
                        </div>
                    `);
                } else {
                    this.showError('Failed to cancel: ' + error);
                }
            });
        }
        
        /**
         * Show dialog to resume existing batch
         */
        showResumeDialog(batch) {
            const html = `
                <div class="ai-seo-resume-dialog" style="padding: 15px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 4px; margin-bottom: 15px;">
                    <h4 style="margin-top: 0;">🔄 Active Generation Found</h4>
                    <p>You have an in-progress generation (${batch.progress}% complete).</p>
                    <button type="button" class="button button-primary ai-seo-resume-btn">Resume Monitoring</button>
                    <button type="button" class="button ai-seo-dismiss-btn">Dismiss</button>
                </div>
            `;
            
            this.$container.before(html);
            
            $('.ai-seo-resume-btn').on('click', () => {
                $('.ai-seo-resume-dialog').remove();
                this.showProgressUI();
                this.queueManager.resumeBatch(batch.batch_id, {
                    onProgress: (data) => this.updateProgress(data),
                    onComplete: (results) => this.showResults(results),
                    onError: (message) => this.showError(message)
                });
            });
            
            $('.ai-seo-dismiss-btn').on('click', () => {
                $('.ai-seo-resume-dialog').remove();
            });
        }
    }
    
    // Export for use in main admin JS
    window.AISEOQueueManager = AISEOQueueManager;
    window.AISEOProgressUI = AISEOProgressUI;
    
})(jQuery);
