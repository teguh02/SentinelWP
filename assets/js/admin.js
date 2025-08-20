/**
 * SentinelWP Admin JavaScript
 */

(function($) {
    'use strict';

    let SentinelWP = {
        
        init: function() {
            this.bindEvents();
            this.initTooltips();
        },
        
        bindEvents: function() {
            // Scan button
            $(document).on('click', '#run-scan-btn', this.runScan);
            
            // Issue actions
            $(document).on('click', '.resolve-issue-btn', this.resolveIssue);
            $(document).on('click', '.isolate-issue-btn', this.isolateIssue);
            
            // Settings form
            $(document).on('submit', '#sentinelwp-settings-form', this.saveSettings);
            
            // AI Analysis
            $(document).on('click', '#generate-analysis-btn', this.generateAIAnalysis);
            
            // AI Recommendations
            $(document).on('click', '#generate-ai-recommendations-btn', this.generateAIRecommendations);
            $(document).on('click', '.mark-implemented', this.markRecommendationImplemented);
            $(document).on('click', '.dismiss-recommendation', this.dismissRecommendation);
            
            // Test notifications
            $(document).on('click', '#test-email-btn', this.testEmailNotification);
            $(document).on('click', '#test-telegram-btn', this.testTelegramNotification);
            
            // Export data
            $(document).on('click', '#export-data-btn', this.exportData);
            
            // Refresh stats
            $(document).on('click', '#refresh-stats-btn', this.refreshStats);
            
            // Notifications
            $(document).on('click', '.mark-notification-read', this.markNotificationRead);
            $(document).on('click', '.delete-notification', this.deleteNotification);
        },
        
        runScan: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true)
                .addClass('loading')
                .text(sentinelwp_ajax.strings.scanning);
            
            // Show progress
            $('#scan-progress').show().addClass('active');
            
            // Initialize progress bar immediately
            $('.progress-fill').css({
                'width': '0%',
                'background': 'linear-gradient(90deg, #0073aa, #00a0d2)'
            });
            
            SentinelWP.updateProgress(0, 'Initializing scan...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_run_scan',
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SentinelWP.updateProgress(100, 'Scan completed!');
                        SentinelWP.showNotice('success', response.data.message + 
                            '\\nFiles scanned: ' + response.data.files_scanned +
                            '\\nThreats found: ' + response.data.threats_found
                        );
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        // Handle different error types
                        let errorMessage = 'Scan failed: ';
                        
                        if (response.data && typeof response.data === 'object') {
                            if (response.data.type === 'database_error') {
                                errorMessage += response.data.message + 
                                              '\\n\\nThis appears to be a database schema issue. ' +
                                              'Please try recreating the database tables from the Settings page.';
                            } else {
                                errorMessage += response.data.message || 'Unknown error occurred';
                            }
                        } else {
                            errorMessage += response.data || 'Unknown error occurred';
                        }
                        
                        SentinelWP.showNotice('error', errorMessage);
                        console.error('Scan error details:', response);
                    }
                },
                error: function(xhr, status, error) {
                    SentinelWP.showNotice('error', 'An error occurred during scanning: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false)
                        .removeClass('loading')
                        .text(originalText);
                    
                    setTimeout(() => {
                        $('#scan-progress').hide().removeClass('active');
                    }, 3000);
                }
            });
            
            // Simulate progress updates (start after a short delay)
            setTimeout(() => {
                SentinelWP.simulateProgress();
            }, 500);
        },
        
        simulateProgress: function() {
            console.log('SentinelWP: Starting progress simulation');
            
            let progress = 0;
            let stepCount = 0;
            
            const interval = setInterval(() => {
                // Increment progress with some randomness
                const increment = Math.random() * 15;
                progress = Math.min(progress + increment, 90);
                stepCount++;
                
                console.log('SentinelWP: Progress step ' + stepCount + ' - ' + progress + '%');
                
                if (progress >= 90) {
                    clearInterval(interval);
                    console.log('SentinelWP: Progress simulation completed at 90%');
                }
                
                const messages = [
                    'Scanning WordPress core files...',
                    'Checking themes for vulnerabilities...',
                    'Analyzing plugins...',
                    'Scanning uploads directory...',
                    'Validating file integrity...',
                    'Detecting suspicious patterns...',
                    'Running heuristic analysis...',
                    'Checking file permissions...'
                ];
                
                const randomMessage = messages[Math.floor(Math.random() * messages.length)];
                SentinelWP.updateProgress(progress, randomMessage);
            }, 1200);
            
            // Store interval for cleanup if needed
            SentinelWP.progressInterval = interval;
        },
        
        updateProgress: function(percent, message) {
            console.log('SentinelWP: Updating progress to ' + percent + '% - ' + message);
            
            const $progressFill = $('.progress-fill');
            const $progressText = $('#progress-text');
            
            // Debug logging
            console.log('Progress fill element found:', $progressFill.length);
            console.log('Progress text element found:', $progressText.length);
            
            // Update the progress bar width
            $progressFill.css('width', percent + '%');
            $progressText.text(message);
            
            // Change color when complete
            if (percent >= 100) {
                $progressFill.css('background', 'linear-gradient(90deg, #28a745, #5cb85c)');
            } else {
                $progressFill.css('background', 'linear-gradient(90deg, #0073aa, #00a0d2)');
            }
            
            // Force a repaint
            $progressFill[0].offsetHeight;
        },
        
        resolveIssue: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const issueId = $btn.data('issue-id');
            
            if (!confirm('Are you sure you want to mark this issue as resolved?')) {
                return;
            }
            
            $btn.prop('disabled', true).text('Resolving...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_resolve_issue',
                    issue_id: issueId,
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.issue-item').addClass('resolved');
                        $btn.remove();
                        SentinelWP.showNotice('success', 'Issue marked as resolved');
                    } else {
                        SentinelWP.showNotice('error', 'Failed to resolve issue: ' + response.data);
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while resolving the issue');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Mark as Resolved');
                }
            });
        },
        
        isolateIssue: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const issueId = $btn.data('issue-id');
            
            if (!confirm('Are you sure you want to isolate this file? This will move the file to a secure location.')) {
                return;
            }
            
            $btn.prop('disabled', true).text('Isolating...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_isolate_issue',
                    issue_id: issueId,
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.issue-item').addClass('isolated');
                        $btn.remove();
                        SentinelWP.showNotice('success', 'File has been isolated successfully');
                    } else {
                        SentinelWP.showNotice('error', 'Failed to isolate file: ' + response.data);
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while isolating the file');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Isolate File');
                }
            });
        },
        
        saveSettings: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $btn = $('#save-settings-btn');
            
            $btn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=sentinelwp_save_settings&nonce=' + sentinelwp_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        SentinelWP.showNotice('success', 'Settings saved successfully!');
                    } else {
                        SentinelWP.showNotice('error', 'Failed to save settings: ' + response.data);
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while saving settings');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Settings');
                }
            });
        },
        
        generateAIAnalysis: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true)
                .addClass('loading')
                .text('Generating...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_generate_ai_analysis',
                    nonce: sentinelwp_ajax.nonce
                },
                timeout: 60000, // 60 seconds timeout for AI analysis
                success: function(response) {
                    if (response.success) {
                        SentinelWP.showNotice('success', 'AI analysis generated successfully!');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        SentinelWP.showNotice('error', 'Failed to generate analysis: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'timeout') {
                        SentinelWP.showNotice('error', 'Analysis request timed out. Please try again.');
                    } else {
                        SentinelWP.showNotice('error', 'An error occurred while generating analysis: ' + error);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false)
                        .removeClass('loading')
                        .text(originalText);
                }
            });
        },
        
        generateAIRecommendations: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            const $status = $('#ai-recommendations-status');
            
            $btn.prop('disabled', true)
                .addClass('loading')
                .text('Generating AI Recommendations...');
            
            $status.html('<div class="notice notice-info inline"><p>🤖 Analyzing your WordPress security and generating personalized recommendations...</p></div>');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_generate_ai_recommendations',
                    nonce: sentinelwp_ajax.nonce
                },
                timeout: 120000, // 2 minutes timeout for AI recommendations
                success: function(response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success inline"><p>✅ ' + response.data + '</p></div>');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        $status.html('<div class="notice notice-error inline"><p>❌ Failed to generate recommendations: ' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'An error occurred while generating recommendations';
                    if (status === 'timeout') {
                        errorMsg = 'Request timed out. The AI service may be busy. Please try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMsg = xhr.responseJSON.data;
                    }
                    $status.html('<div class="notice notice-error inline"><p>❌ ' + errorMsg + '</p></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false)
                        .removeClass('loading')
                        .text(originalText);
                }
            });
        },
        
        markRecommendationImplemented: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const recommendationId = $btn.data('id');
            const $item = $btn.closest('.ai-recommendation-item');
            
            if (!recommendationId) return;
            
            $btn.prop('disabled', true).text('Updating...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_update_ai_recommendation',
                    nonce: sentinelwp_ajax.nonce,
                    recommendation_id: recommendationId,
                    status: 'implemented'
                },
                success: function(response) {
                    if (response.success) {
                        $item.addClass('implemented').fadeOut(500, function() {
                            $(this).remove();
                        });
                        SentinelWP.showNotice('success', 'Recommendation marked as implemented!');
                    } else {
                        SentinelWP.showNotice('error', 'Failed to update recommendation: ' + response.data);
                        $btn.prop('disabled', false).text('Mark as Implemented');
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while updating the recommendation.');
                    $btn.prop('disabled', false).text('Mark as Implemented');
                }
            });
        },
        
        dismissRecommendation: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const recommendationId = $btn.data('id');
            const $item = $btn.closest('.ai-recommendation-item');
            
            if (!recommendationId) return;
            
            if (!confirm('Are you sure you want to dismiss this recommendation?')) {
                return;
            }
            
            $btn.prop('disabled', true).text('Dismissing...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_update_ai_recommendation',
                    nonce: sentinelwp_ajax.nonce,
                    recommendation_id: recommendationId,
                    status: 'dismissed'
                },
                success: function(response) {
                    if (response.success) {
                        $item.addClass('dismissed').fadeOut(500, function() {
                            $(this).remove();
                        });
                        SentinelWP.showNotice('info', 'Recommendation dismissed.');
                    } else {
                        SentinelWP.showNotice('error', 'Failed to dismiss recommendation: ' + response.data);
                        $btn.prop('disabled', false).text('Dismiss');
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while dismissing the recommendation.');
                    $btn.prop('disabled', false).text('Dismiss');
                }
            });
        },
        
        testEmailNotification: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_test_email',
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SentinelWP.showNotice('success', 'Test email sent successfully!');
                    } else {
                        SentinelWP.showNotice('error', 'Failed to send test email: ' + response.data);
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while sending test email');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        testTelegramNotification: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).addClass('loading').text('Sending...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_test_telegram',
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SentinelWP.showNotice('success', response.data);
                    } else {
                        SentinelWP.showNotice('error', response.data);
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while sending test message');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading').text(originalText);
                }
            });
        },
        
        exportData: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true).text('Exporting...');
            
            // Create download link
            const downloadUrl = sentinelwp_ajax.ajax_url + '?action=sentinelwp_export_data&nonce=' + sentinelwp_ajax.nonce;
            
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = 'sentinelwp-data-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            setTimeout(() => {
                $btn.prop('disabled', false).text(originalText);
                SentinelWP.showNotice('success', 'Data exported successfully!');
            }, 1000);
        },
        
        refreshStats: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const originalText = $btn.text();
            
            $btn.prop('disabled', true)
                .addClass('loading')
                .text('Refreshing...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_refresh_stats',
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        SentinelWP.showNotice('error', 'Failed to refresh stats: ' + response.data);
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while refreshing stats');
                },
                complete: function() {
                    $btn.prop('disabled', false)
                        .removeClass('loading')
                        .text(originalText);
                }
            });
        },
        
        showNotice: function(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 
                              type === 'warning' ? 'notice-warning' : 'notice-error';
            
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible sentinelwp-notice">' +
                           '<p>' + message + '</p>' +
                           '<button type="button" class="notice-dismiss">' +
                           '<span class="screen-reader-text">Dismiss this notice.</span>' +
                           '</button></div>');
            
            $('.wrap h1').after(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut();
            }, 5000);
            
            // Handle dismiss button
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut();
            });
        },
        
        initTooltips: function() {
            // Simple tooltip implementation
            $('[data-tooltip]').each(function() {
                const $element = $(this);
                const tooltipText = $element.data('tooltip');
                
                $element.hover(
                    function() {
                        const tooltip = $('<div class="sentinelwp-tooltip">' + tooltipText + '</div>');
                        $('body').append(tooltip);
                        
                        const offset = $element.offset();
                        tooltip.css({
                            position: 'absolute',
                            top: offset.top - tooltip.outerHeight() - 5,
                            left: offset.left + ($element.outerWidth() / 2) - (tooltip.outerWidth() / 2),
                            background: '#1d2327',
                            color: '#fff',
                            padding: '5px 8px',
                            borderRadius: '3px',
                            fontSize: '12px',
                            zIndex: 9999
                        });
                    },
                    function() {
                        $('.sentinelwp-tooltip').remove();
                    }
                );
            });
        },
        
        // Issue toggle functionality
        toggleIssue: function(issueId) {
            const details = $('#issue-' + issueId);
            const header = details.prev().find('.issue-toggle');
            
            if (details.is(':visible')) {
                details.slideUp();
                header.text('▼');
            } else {
                details.slideDown();
                header.text('▲');
            }
        },
        
        toggleAllIssues: function() {
            const $details = $('.issue-details');
            const anyVisible = $details.filter(':visible').length > 0;
            
            if (anyVisible) {
                $details.slideUp();
                $('.issue-toggle').text('▼');
            } else {
                $details.slideDown();
                $('.issue-toggle').text('▲');
            }
        },
        
        // Confirm dangerous actions
        confirmAction: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },
        
        // Auto refresh functionality
        initAutoRefresh: function() {
            const refreshInterval = 30000; // 30 seconds
            
            setInterval(() => {
                // Only refresh stats on dashboard page
                if (window.location.href.includes('page=sentinelwp') && 
                    !window.location.href.includes('page=sentinelwp-')) {
                    this.refreshStats({ preventDefault: function() {} });
                }
            }, refreshInterval);
        },
        
        // Real-time scan progress
        pollScanProgress: function(scanId) {
            const interval = setInterval(() => {
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_scan_progress',
                        scan_id: scanId,
                        nonce: sentinelwp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.progress >= 100) {
                            clearInterval(interval);
                            window.location.reload();
                        } else if (response.success) {
                            SentinelWP.updateProgress(response.data.progress, response.data.status);
                        }
                    }
                });
            }, 2000);
            
            return interval;
        },
        
        // Mark notification as read
        markNotificationRead: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const notificationId = $btn.data('id');
            const $notification = $btn.closest('.notification-item');
            
            $btn.prop('disabled', true).text('Updating...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_mark_notification_read',
                    notification_id: notificationId,
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $notification.removeClass('notification-new');
                        $btn.remove();
                        SentinelWP.showNotice('success', 'Notification marked as read');
                    } else {
                        SentinelWP.showNotice('error', 'Failed to update notification');
                        $btn.prop('disabled', false).text('Mark as Read');
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while updating notification');
                    $btn.prop('disabled', false).text('Mark as Read');
                }
            });
        },
        
        // Delete notification
        deleteNotification: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const notificationId = $btn.data('id');
            const $notification = $btn.closest('.notification-item');
            
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }
            
            $btn.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_delete_notification',
                    notification_id: notificationId,
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $notification.fadeOut(300, function() {
                            $(this).remove();
                        });
                        SentinelWP.showNotice('success', 'Notification deleted');
                    } else {
                        SentinelWP.showNotice('error', 'Failed to delete notification');
                        $btn.prop('disabled', false).text('Delete');
                    }
                },
                error: function() {
                    SentinelWP.showNotice('error', 'An error occurred while deleting notification');
                    $btn.prop('disabled', false).text('Delete');
                }
            });
        },
        
        // Notifications page enhancements
        initNotificationOrdering: function() {
            // Add visual indicators to show notifications are ordered by time
            $('.notification-item').each(function(index) {
                $(this).attr('data-order', index + 1);
            });
            
            // Add hover tooltip showing exact timestamps
            $('.timestamp').each(function() {
                $(this).tooltip({
                    position: { my: "left+10 center", at: "right center" },
                    content: function() {
                        return $(this).attr('title');
                    }
                });
            });
        }
    };
    
    // Make functions available globally for inline event handlers
    window.toggleIssue = SentinelWP.toggleIssue;
    window.toggleAllIssues = SentinelWP.toggleAllIssues;
    window.resolveIssue = function(issueId) {
        SentinelWP.confirmAction(
            'Are you sure you want to mark this issue as resolved?',
            () => {
                // Trigger the resolve action
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_resolve_issue',
                        issue_id: issueId,
                        nonce: sentinelwp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed to resolve issue: ' + response.data);
                        }
                    }
                });
            }
        );
    };
    
    window.isolateIssue = function(issueId) {
        SentinelWP.confirmAction(
            'Are you sure you want to isolate this file? This will move the file to a secure location.',
            () => {
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_isolate_issue',
                        issue_id: issueId,
                        nonce: sentinelwp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('File has been isolated successfully.');
                            location.reload();
                        } else {
                            alert('Failed to isolate file: ' + response.data);
                        }
                    }
                });
            }
        );
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        SentinelWP.init();
        
        // Initialize auto refresh if enabled
        if (sentinelwp_ajax.auto_refresh) {
            SentinelWP.initAutoRefresh();
        }
        
        // Notifications page enhancements
        if ($('.sentinelwp-notifications').length) {
            this.initNotificationOrdering();
        }
    });

})(jQuery);
