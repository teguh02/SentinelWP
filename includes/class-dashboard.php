<?php
/**
 * SentinelWP Dashboard Class
 * 
 * Handles admin interface and dashboard rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_Dashboard {
    
    private static $instance = null;
    private $database;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->database = SentinelWP_Database::instance();
        
        // Add AJAX handlers
        add_action('wp_ajax_sentinelwp_resolve_issue', array($this, 'ajax_resolve_issue'));
        add_action('wp_ajax_sentinelwp_isolate_issue', array($this, 'ajax_isolate_issue'));
        add_action('wp_ajax_sentinelwp_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_sentinelwp_update_ai_recommendation', array($this, 'ajax_update_ai_recommendation'));
        add_action('wp_ajax_sentinelwp_check_database', array($this, 'ajax_check_database'));
        add_action('wp_ajax_sentinelwp_migrate_database', array($this, 'ajax_migrate_database'));
        add_action('wp_ajax_sentinelwp_migrate_schema', array($this, 'ajax_migrate_schema'));
        add_action('wp_ajax_sentinelwp_generate_issue_report', array($this, 'ajax_generate_issue_report'));
        add_action('wp_ajax_sentinelwp_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        add_action('wp_ajax_sentinelwp_delete_notification', array($this, 'ajax_delete_notification'));
        add_action('wp_ajax_sentinelwp_test_telegram', array($this, 'ajax_test_telegram'));
        add_action('wp_ajax_sentinelwp_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        add_action('wp_ajax_sentinelwp_delete_notification', array($this, 'ajax_delete_notification'));
    }
    
    /**
     * Render main dashboard page
     */
    public static function render_dashboard() {
        $database = SentinelWP_Database::instance();
        $attack_detector = SentinelWP_Attack_Detector::instance();
        $system_status = get_option('sentinelwp_system_status', array());
        $stats = $database->get_security_stats();
        $attack_status = $attack_detector->get_attack_status();
        
        ?>
        <div class="wrap sentinelwp-dashboard">
            <h1><?php _e('SentinelWP Security Dashboard', 'sentinelwp'); ?></h1>
            
            <?php if ($attack_status['under_attack']): ?>
            <div class="notice notice-error sentinelwp-attack-banner">
                <h2>🚨 <?php _e('Security Alert - Site Under Attack', 'sentinelwp'); ?></h2>
                <p><?php echo esc_html($attack_status['message']); ?></p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=sentinelwp-notifications'); ?>" class="button button-primary">
                        <?php _e('View Attack Details', 'sentinelwp'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sentinelwp-settings'); ?>" class="button">
                        <?php _e('Configure Security Settings', 'sentinelwp'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- System Status Cards -->
            <div class="sentinelwp-status-cards">
                <div class="sentinelwp-card">
                    <div class="sentinelwp-card-header">
                        <h3><?php _e('System Status', 'sentinelwp'); ?></h3>
                    </div>
                    <div class="sentinelwp-card-body">
                        <div class="status-item">
                            <span class="label"><?php _e('Scan Engine:', 'sentinelwp'); ?></span>
                            <span class="value <?php echo esc_attr($system_status['scan_engine_mode'] ?? 'heuristic'); ?>">
                                <?php echo esc_html(ucfirst($system_status['scan_engine_mode'] ?? 'heuristic')); ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span class="label"><?php _e('ClamAV:', 'sentinelwp'); ?></span>
                            <span class="value <?php echo ($system_status['clamav_installed'] ?? false) ? 'detected' : 'not-detected'; ?>">
                                <?php echo ($system_status['clamav_installed'] ?? false) ? __('Detected', 'sentinelwp') : __('Not Installed', 'sentinelwp'); ?>
                            </span>
                        </div>
                        <div class="status-item">
                            <span class="label"><?php _e('PHP Exec Functions:', 'sentinelwp'); ?></span>
                            <span class="value <?php echo ($system_status['php_exec_enabled'] ?? false) ? 'enabled' : 'disabled'; ?>">
                                <?php echo ($system_status['php_exec_enabled'] ?? false) ? __('Enabled', 'sentinelwp') : __('Disabled', 'sentinelwp'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="sentinelwp-card">
                    <div class="sentinelwp-card-header">
                        <h3><?php _e('Security Overview', 'sentinelwp'); ?></h3>
                    </div>
                    <div class="sentinelwp-card-body">
                        <div class="security-stat">
                            <div class="stat-number critical"><?php echo esc_html($stats['critical_issues'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Critical Issues', 'sentinelwp'); ?></div>
                        </div>
                        <div class="security-stat">
                            <div class="stat-number warning"><?php echo esc_html($stats['unresolved_issues'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Total Issues', 'sentinelwp'); ?></div>
                        </div>
                        <div class="security-stat">
                            <div class="stat-number info"><?php echo esc_html($stats['total_scans'] ?? 0); ?></div>
                            <div class="stat-label"><?php _e('Total Scans', 'sentinelwp'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="sentinelwp-card">
                    <div class="sentinelwp-card-header">
                        <h3><?php _e('Last Scan', 'sentinelwp'); ?></h3>
                        <div>
                            <button id="run-scan-btn" class="button button-primary">
                                <?php _e('Run Scan Now', 'sentinelwp'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="sentinelwp-card-body">
                        <?php if (isset($stats['last_scan']) && $stats['last_scan']): ?>
                            <div class="last-scan-info">
                                <div class="scan-status status-<?php echo esc_attr($stats['last_scan']->status); ?>">
                                    <?php echo esc_html(ucfirst($stats['last_scan']->status)); ?>
                                </div>
                                <div class="scan-details">
                                    <p><?php printf(__('Scanned %s ago', 'sentinelwp'), human_time_diff(strtotime($stats['last_scan']->scan_time))); ?></p>
                                    <p><?php printf(__('%d issues found in %d files', 'sentinelwp'), $stats['last_scan']->issues_found, $stats['last_scan']->files_scanned); ?></p>
                                    <p><?php printf(__('Mode: %s', 'sentinelwp'), ucfirst($stats['last_scan']->scan_mode)); ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <p><?php _e('No scans performed yet.', 'sentinelwp'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Issues Summary -->
            <?php if (!empty($stats['issues_by_type'])): ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('Issues by Type', 'sentinelwp'); ?></h3>
                </div>
                <div class="sentinelwp-card-body">
                    <div class="issues-chart">
                        <?php foreach ($stats['issues_by_type'] as $issue): ?>
                        <div class="issue-type-item">
                            <span class="issue-type"><?php echo esc_html(str_replace('_', ' ', ucwords($issue->issue_type, '_'))); ?></span>
                            <span class="issue-count"><?php echo esc_html($issue->count); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Scan Progress -->
            <div id="scan-progress" class="sentinelwp-card" style="display: none;">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('Scan Progress', 'sentinelwp'); ?></h3>
                </div>
                <div class="sentinelwp-card-body">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%; background: linear-gradient(90deg, #0073aa, #00a0d2);"></div>
                    </div>
                    <div class="progress-info">
                        <span id="progress-text"><?php _e('Initializing scan...', 'sentinelwp'); ?></span>
                        <span id="files-scanned">0 <?php _e('files scanned', 'sentinelwp'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#run-scan-btn').click(function() {
                var $btn = $(this);
                var $progress = $('#scan-progress');
                
                $btn.prop('disabled', true).text('<?php _e('Scanning...', 'sentinelwp'); ?>');
                $progress.show();
                
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_run_scan',
                        nonce: sentinelwp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message + '\\n' + 
                                  '<?php _e('Files scanned:', 'sentinelwp'); ?> ' + response.data.files_scanned + '\\n' +
                                  '<?php _e('Threats found:', 'sentinelwp'); ?> ' + response.data.threats_found);
                            location.reload();
                        } else {
                            alert('<?php _e('Scan failed:', 'sentinelwp'); ?> ' + response.data);
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred during scanning.', 'sentinelwp'); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('Run Scan Now', 'sentinelwp'); ?>');
                        $progress.hide();
                    }
                });
            });
            
            // Test progress button for debugging
            $('#test-progress-btn').click(function() {
                var $progress = $('#scan-progress');
                console.log('SentinelWP: Test progress button clicked');
                
                // Show progress bar
                $progress.show();
                
                // Initialize progress bar
                $('.progress-fill').css({
                    'width': '0%',
                    'background': 'linear-gradient(90deg, #0073aa, #00a0d2)'
                });
                
                // Test progress updates
                let testProgress = 0;
                const testInterval = setInterval(() => {
                    testProgress += 20;
                    if (testProgress > 100) {
                        testProgress = 100;
                        clearInterval(testInterval);
                        
                        setTimeout(() => {
                            $progress.hide();
                        }, 2000);
                    }
                    
                    $('.progress-fill').css('width', testProgress + '%');
                    $('#progress-text').text('Testing progress: ' + testProgress + '%');
                    
                    if (testProgress >= 100) {
                        $('.progress-fill').css('background', 'linear-gradient(90deg, #28a745, #5cb85c)');
                    }
                }, 500);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render scan results page
     */
    public static function render_scan_results() {
        $database = SentinelWP_Database::instance();
        $scans = $database->get_latest_scans(10);
        $current_scan_id = isset($_GET['scan_id']) ? intval($_GET['scan_id']) : null;
        $issues = array();
        
        if ($current_scan_id) {
            $issues = $database->get_issues_by_scan($current_scan_id);
        } elseif (!empty($scans)) {
            $current_scan_id = $scans[0]->id;
            $issues = $database->get_issues_by_scan($current_scan_id);
        }
        
        ?>
        <div class="wrap sentinelwp-scan-results">
            <h1><?php _e('Scan Results', 'sentinelwp'); ?></h1>
            
            <!-- Scan Selection -->
            <?php if (!empty($scans)): ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('Select Scan', 'sentinelwp'); ?></h3>
                </div>
                <div class="sentinelwp-card-body">
                    <select id="scan-selector" onchange="window.location.href = '<?php echo admin_url('admin.php?page=sentinelwp-scan-results&scan_id='); ?>' + this.value">
                        <?php foreach ($scans as $scan): ?>
                        <option value="<?php echo esc_attr($scan->id); ?>" <?php selected($current_scan_id, $scan->id); ?>>
                            <?php printf(__('Scan %d - %s (%s, %d issues)', 'sentinelwp'), 
                                $scan->id, 
                                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($scan->scan_time)),
                                ucfirst($scan->status),
                                $scan->issues_found
                            ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Issues List -->
            <?php if (!empty($issues)): ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('Security Issues', 'sentinelwp'); ?></h3>
                    <div class="card-actions">
                        <button class="button" onclick="toggleAllIssues()"><?php _e('Toggle All', 'sentinelwp'); ?></button>
                    </div>
                </div>
                <div class="sentinelwp-card-body">
                    <div class="issues-list">
                        <?php foreach ($issues as $issue): ?>
                        <div class="issue-item severity-<?php echo esc_attr($issue->severity); ?> <?php echo $issue->resolved ? 'resolved' : ''; ?> <?php echo $issue->isolated ? 'isolated' : ''; ?>">
                            <div class="issue-header" onclick="toggleIssue(<?php echo $issue->id; ?>)">
                                <div class="issue-info">
                                    <span class="severity-badge"><?php echo esc_html(ucfirst($issue->severity)); ?></span>
                                    <span class="issue-type"><?php echo esc_html(str_replace('_', ' ', ucwords($issue->issue_type, '_'))); ?></span>
                                    <span class="file-path"><?php echo esc_html(basename($issue->file_path)); ?></span>
                                    <?php if ($issue->resolved): ?>
                                    <span class="status-badge resolved"><?php _e('Resolved', 'sentinelwp'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($issue->isolated): ?>
                                    <span class="status-badge isolated"><?php _e('Isolated', 'sentinelwp'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="issue-toggle">▼</div>
                            </div>
                            <div class="issue-details" id="issue-<?php echo $issue->id; ?>" style="display: none;">
                                <div class="detail-row">
                                    <strong><?php _e('File Path:', 'sentinelwp'); ?></strong>
                                    <code><?php echo esc_html($issue->file_path); ?></code>
                                </div>
                                <div class="detail-row">
                                    <strong><?php _e('Description:', 'sentinelwp'); ?></strong>
                                    <span><?php echo esc_html($issue->description); ?></span>
                                </div>
                                <?php if ($issue->recommendation): ?>
                                <div class="detail-row">
                                    <strong><?php _e('Recommendation:', 'sentinelwp'); ?></strong>
                                    <span><?php echo esc_html($issue->recommendation); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-row">
                                    <strong><?php _e('Detected:', 'sentinelwp'); ?></strong>
                                    <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($issue->created_at)); ?></span>
                                </div>
                                <div class="issue-actions">
                                    <?php if (!$issue->resolved): ?>
                                    <button class="button button-secondary" onclick="resolveIssue(<?php echo $issue->id; ?>)">
                                        <?php _e('Mark as Resolved', 'sentinelwp'); ?>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (!$issue->isolated && in_array($issue->issue_type, array('malware_detected', 'suspicious_code', 'obfuscated_code'))): ?>
                                    <button class="button button-primary" onclick="isolateIssue(<?php echo $issue->id; ?>)">
                                        <?php _e('Isolate File', 'sentinelwp'); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-body">
                    <p><?php _e('No security issues found or no scans performed yet.', 'sentinelwp'); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        function toggleIssue(issueId) {
            var details = document.getElementById('issue-' + issueId);
            var header = details.previousElementSibling.querySelector('.issue-toggle');
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                header.textContent = '▲';
            } else {
                details.style.display = 'none';
                header.textContent = '▼';
            }
        }
        
        function toggleAllIssues() {
            var details = document.querySelectorAll('.issue-details');
            var allVisible = Array.from(details).some(detail => detail.style.display === 'block');
            
            details.forEach(function(detail, index) {
                var header = detail.previousElementSibling.querySelector('.issue-toggle');
                if (allVisible) {
                    detail.style.display = 'none';
                    header.textContent = '▼';
                } else {
                    detail.style.display = 'block';
                    header.textContent = '▲';
                }
            });
        }
        
        function resolveIssue(issueId) {
            if (!confirm('<?php _e('Are you sure you want to mark this issue as resolved?', 'sentinelwp'); ?>')) {
                return;
            }
            
            jQuery.ajax({
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
                        alert('<?php _e('Failed to resolve issue:', 'sentinelwp'); ?> ' + response.data);
                    }
                }
            });
        }
        
        function isolateIssue(issueId) {
            if (!confirm('<?php _e('Are you sure you want to isolate this file? This will move the file to a secure location.', 'sentinelwp'); ?>')) {
                return;
            }
            
            jQuery.ajax({
                url: sentinelwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sentinelwp_isolate_issue',
                    issue_id: issueId,
                    nonce: sentinelwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('File has been isolated successfully.', 'sentinelwp'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Failed to isolate file:', 'sentinelwp'); ?> ' + response.data);
                    }
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render recommendations page
     */
    public static function render_recommendations() {
        $recommendations = SentinelWP_Recommendations::instance();
        $security_recommendations = $recommendations->get_security_recommendations();
        
        // Get AI recommendations from database
        $database = SentinelWP_Database::instance();
        $ai_recommendations = $database->get_ai_recommendations(array('status' => 'active', 'limit' => 20));
        
        // Check if Gemini AI is enabled and configured
        $gemini_enabled = get_option('sentinelwp_gemini_enabled', false);
        $api_key = get_option('sentinelwp_gemini_api_key', '');
        $can_generate_ai = $gemini_enabled && !empty($api_key);
        
        ?>
        <div class="wrap sentinelwp-recommendations">
            <h1><?php _e('Security Recommendations', 'sentinelwp'); ?></h1>
            
            <?php if ($can_generate_ai): ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('AI-Powered Recommendations', 'sentinelwp'); ?></h3>
                </div>
                <div class="sentinelwp-card-body">
                    <p><?php _e('Get personalized security recommendations powered by AI analysis of your WordPress installation.', 'sentinelwp'); ?></p>
                    <button type="button" class="button button-secondary" id="generate-ai-recommendations-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Generate AI Recommendations', 'sentinelwp'); ?>
                    </button>
                    <div id="ai-recommendations-status" style="margin-top: 10px;"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($ai_recommendations)): ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('AI Generated Recommendations', 'sentinelwp'); ?></h3>
                </div>
                <div class="sentinelwp-card-body">
                    <div class="ai-recommendations-list">
                        <?php foreach ($ai_recommendations as $ai_rec): ?>
                        <div class="ai-recommendation-item priority-<?php echo esc_attr($ai_rec->priority); ?>" data-id="<?php echo esc_attr($ai_rec->id); ?>">
                            <div class="recommendation-header">
                                <span class="priority-badge"><?php echo esc_html(ucfirst($ai_rec->priority)); ?></span>
                                <span class="category-badge"><?php echo esc_html(ucwords(str_replace('_', ' ', $ai_rec->category))); ?></span>
                                <h4><?php echo esc_html($ai_rec->title); ?></h4>
                                <?php if ($ai_rec->confidence_score): ?>
                                <span class="confidence-score" title="AI Confidence Score">
                                    <?php echo round($ai_rec->confidence_score * 100); ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="recommendation-description">
                                <p><?php echo esc_html($ai_rec->description); ?></p>
                            </div>
                            <div class="recommendation-meta">
                                <small>
                                    <?php _e('Generated:', 'sentinelwp'); ?>
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ai_rec->generated_at))); ?>
                                </small>
                                <div class="recommendation-actions">
                                    <button type="button" class="button button-small mark-implemented" data-id="<?php echo esc_attr($ai_rec->id); ?>">
                                        <?php _e('Mark as Implemented', 'sentinelwp'); ?>
                                    </button>
                                    <button type="button" class="button button-small dismiss-recommendation" data-id="<?php echo esc_attr($ai_rec->id); ?>">
                                        <?php _e('Dismiss', 'sentinelwp'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($security_recommendations)): ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('System Recommendations', 'sentinelwp'); ?></h3>
                </div>
                <div class="sentinelwp-card-body">
                    <div class="recommendations-list">
                        <?php foreach ($security_recommendations as $category => $recs): ?>
                        <div class="recommendation-category">
                            <h4><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?></h4>
                            <?php foreach ($recs as $rec): ?>
                            <div class="recommendation-item priority-<?php echo esc_attr($rec['priority']); ?>">
                                <div class="recommendation-header">
                                    <span class="priority-badge"><?php echo esc_html(ucfirst($rec['priority'])); ?></span>
                                    <h5><?php echo esc_html($rec['title']); ?></h5>
                                </div>
                                <div class="recommendation-description">
                                    <p><?php echo esc_html($rec['description']); ?></p>
                                </div>
                                <?php if (!empty($rec['action'])): ?>
                                <div class="recommendation-action">
                                    <strong><?php _e('Recommended Action:', 'sentinelwp'); ?></strong>
                                    <code><?php echo esc_html($rec['action']); ?></code>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-body">
                    <p><?php _e('No system recommendations at this time. Run a scan to get personalized recommendations.', 'sentinelwp'); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!$can_generate_ai): ?>
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('Enable AI Recommendations', 'sentinelwp'); ?></h3>
                </div>
                <div class="sentinelwp-card-body">
                    <p><?php _e('To get AI-powered security recommendations, please enable Gemini AI in the settings and configure your API key.', 'sentinelwp'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=sentinelwp-settings'); ?>" class="button button-primary">
                        <?php _e('Configure AI Settings', 'sentinelwp'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render AI Advisor page
     */
    public static function render_ai_advisor() {
        $ai_advisor = SentinelWP_AI_Advisor::instance();
        $latest_analysis = $ai_advisor->get_latest_analysis();
        
        ?>
        <div class="wrap sentinelwp-ai-advisor">
            <h1><?php _e('AI Security Advisor', 'sentinelwp'); ?></h1>
            
            <?php if (!get_option('sentinelwp_gemini_enabled', false)): ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('AI Security Advisor is disabled. Enable Gemini API in settings to get AI-powered security analysis.', 'sentinelwp'); ?>
                    <a href="<?php echo admin_url('admin.php?page=sentinelwp-settings'); ?>"><?php _e('Go to Settings', 'sentinelwp'); ?></a>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="sentinelwp-card">
                <div class="sentinelwp-card-header">
                    <h3><?php _e('AI Security Analysis', 'sentinelwp'); ?></h3>
                    <button id="generate-analysis-btn" class="button button-primary">
                        <?php _e('Generate New Analysis', 'sentinelwp'); ?>
                    </button>
                </div>
                <div class="sentinelwp-card-body">
                    <?php if ($latest_analysis): ?>
                    <div class="ai-analysis">
                        <div class="analysis-meta">
                            <p><strong><?php _e('Generated:', 'sentinelwp'); ?></strong> 
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($latest_analysis['created_at'])); ?></p>
                            <p><strong><?php _e('Model:', 'sentinelwp'); ?></strong> <?php echo esc_html($latest_analysis['model']); ?></p>
                        </div>
                        <div class="analysis-content">
                            <?php echo wp_kses_post(nl2br($latest_analysis['analysis'])); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p><?php _e('No AI analysis available. Click "Generate New Analysis" to get AI-powered security insights.', 'sentinelwp'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#generate-analysis-btn').click(function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e('Generating...', 'sentinelwp'); ?>');
                
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_generate_ai_analysis',
                        nonce: sentinelwp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('<?php _e('Failed to generate analysis:', 'sentinelwp'); ?> ' + response.data);
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred while generating analysis.', 'sentinelwp'); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('Generate New Analysis', 'sentinelwp'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render settings page
     */
    public static function render_settings() {
        if (isset($_POST['submit'])) {
            // Settings will be saved via AJAX
        }
        
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap sentinelwp-settings">
            <h1><?php _e('SentinelWP Settings', 'sentinelwp'); ?></h1>
            
            <!-- Settings Tabs -->
            <nav class="nav-tab-wrapper">
                <a href="?page=sentinelwp-settings&tab=general" class="nav-tab <?php echo $current_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'sentinelwp'); ?>
                </a>
                <a href="?page=sentinelwp-settings&tab=notifications" class="nav-tab <?php echo $current_tab == 'notifications' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Notifications', 'sentinelwp'); ?>
                </a>
                <a href="?page=sentinelwp-settings&tab=gemini" class="nav-tab <?php echo $current_tab == 'gemini' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Gemini AI', 'sentinelwp'); ?>
                </a>
                <a href="?page=sentinelwp-settings&tab=database" class="nav-tab <?php echo $current_tab == 'database' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Database', 'sentinelwp'); ?>
                </a>
                <a href="?page=sentinelwp-settings&tab=report" class="nav-tab <?php echo $current_tab == 'report' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Report Issue', 'sentinelwp'); ?>
                </a>
            </nav>
            
            <form id="sentinelwp-settings-form" method="post">
                <?php wp_nonce_field('sentinelwp_settings'); ?>
                
                <?php if ($current_tab == 'general'): ?>
                <div class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Automatic Scanning', 'sentinelwp'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_scan_enabled" value="1" <?php checked(get_option('sentinelwp_auto_scan_enabled', true)); ?> />
                                    <?php _e('Enable automatic daily scans', 'sentinelwp'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Scan Time', 'sentinelwp'); ?></th>
                            <td>
                                <input type="time" name="scan_time" value="<?php echo esc_attr(get_option('sentinelwp_scan_time', '02:00')); ?>" />
                                <p class="description"><?php _e('Time when automatic scans should run (24-hour format)', 'sentinelwp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php elseif ($current_tab == 'notifications'): ?>
                <div class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Email Notifications', 'sentinelwp'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="notify_scan_results" value="1" <?php checked(get_option('sentinelwp_notify_scan_results', true)); ?> />
                                    <?php _e('Send scan results via email', 'sentinelwp'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="notify_threats" value="1" <?php checked(get_option('sentinelwp_notify_threats', true)); ?> />
                                    <?php _e('Send immediate alerts for threats', 'sentinelwp'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="notify_under_attack" value="1" <?php checked(get_option('sentinelwp_notify_under_attack', true)); ?> />
                                    <?php _e('Send notifications when site is under attack', 'sentinelwp'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Email Address', 'sentinelwp'); ?></th>
                            <td>
                                <input type="email" name="notification_email" value="<?php echo esc_attr(get_option('sentinelwp_notification_email', get_option('admin_email'))); ?>" class="regular-text" />
                                <p class="description"><?php _e('Email address for security notifications', 'sentinelwp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Telegram Notifications', 'sentinelwp'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="telegram_enabled" value="1" <?php checked(get_option('sentinelwp_telegram_enabled', false)); ?> />
                                    <?php _e('Enable Telegram notifications', 'sentinelwp'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Telegram Bot Token', 'sentinelwp'); ?></th>
                            <td>
                                <input type="text" name="telegram_bot_token" value="<?php echo esc_attr(get_option('sentinelwp_telegram_bot_token', '')); ?>" class="regular-text" />
                                <p class="description"><?php _e('Bot token from @BotFather', 'sentinelwp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Telegram Chat ID', 'sentinelwp'); ?></th>
                            <td>
                                <input type="text" name="telegram_chat_id" value="<?php echo esc_attr(get_option('sentinelwp_telegram_chat_id', '')); ?>" class="regular-text" />
                                <button type="button" id="test-telegram-btn" class="button button-secondary" style="margin-left: 10px;">
                                    <?php _e('Test Configuration', 'sentinelwp'); ?>
                                </button>
                                <p class="description"><?php _e('Chat ID to send notifications to. Click "Test Configuration" to verify your Telegram setup.', 'sentinelwp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php elseif ($current_tab == 'gemini'): ?>
                <div class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Gemini AI', 'sentinelwp'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="gemini_enabled" value="1" <?php checked(get_option('sentinelwp_gemini_enabled', false)); ?> />
                                    <?php _e('Enable AI Security Advisor', 'sentinelwp'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Gemini API Key', 'sentinelwp'); ?></th>
                            <td>
                                <input type="password" name="gemini_api_key" value="<?php echo esc_attr(get_option('sentinelwp_gemini_api_key', '')); ?>" class="regular-text" />
                                <p class="description"><?php _e('Your Gemini API key from Google AI Studio', 'sentinelwp'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Gemini Model', 'sentinelwp'); ?></th>
                            <td>
                                <select name="gemini_model">
                                    <option value="gemini-2.5-flash" <?php selected(get_option('sentinelwp_gemini_model', 'gemini-2.5-flash'), 'gemini-2.5-flash'); ?>>
                                        Gemini 2.5 Flash
                                    </option>
                                    <option value="gemini-2.5-flash-lite" <?php selected(get_option('sentinelwp_gemini_model'), 'gemini-2.5-flash-lite'); ?>>
                                        Gemini 2.5 Flash Lite
                                    </option>
                                    <option value="gemini-2.5-pro" <?php selected(get_option('sentinelwp_gemini_model'), 'gemini-2.5-pro'); ?>>
                                        Gemini 2.5 Pro
                                    </option>
                                </select>
                                <p class="description"><?php _e('Select the Gemini model to use for analysis', 'sentinelwp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if ($current_tab == 'database'): ?>
                <div class="tab-content">
                    <div class="notice notice-info">
                        <p><?php _e('Database management tools for troubleshooting table issues.', 'sentinelwp'); ?></p>
                    </div>
                    
                    <div id="database-status" style="margin: 20px 0;">
                        <h3><?php _e('Database Status', 'sentinelwp'); ?></h3>
                        <div id="table-status-container">
                            <button type="button" id="check-database-btn" class="button">
                                <?php _e('Check Database Tables', 'sentinelwp'); ?>
                            </button>
                            <div id="table-status-results" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                    
                    <div id="database-migration" style="margin: 20px 0; border-top: 1px solid #ccd0d4; padding-top: 20px;">
                        <h3><?php _e('Database Management', 'sentinelwp'); ?></h3>
                        <p><?php _e('If you experience scanning errors, try running a database migration first:', 'sentinelwp'); ?></p>
                        <div style="margin: 15px 0;">
                            <button type="button" id="migrate-schema-btn" class="button button-secondary" style="margin-right: 10px;">
                                <?php _e('Migrate Database Schema', 'sentinelwp'); ?>
                            </button>
                            <button type="button" id="migrate-database-btn" class="button button-secondary">
                                <?php _e('Recreate Database Tables', 'sentinelwp'); ?>
                            </button>
                            <p class="description" style="color: #666; margin-top: 5px;">
                                <?php _e('Migration: Updates table schema (safe). Recreate: Rebuilds all tables (warning: may affect data).', 'sentinelwp'); ?>
                            </p>
                            <p class="description" style="color: #d63638; margin-top: 5px;">
                                <?php _e('Warning: Recreate will rebuild all plugin tables. Existing data will be preserved where possible.', 'sentinelwp'); ?>
                            </p>
                        </div>
                        <div id="migration-results" style="margin-top: 10px;"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($current_tab == 'report'): ?>
                <div class="tab-content">
                    <div class="notice notice-info">
                        <p><?php _e('Report bugs, issues, or feature requests directly to the SentinelWP development team.', 'sentinelwp'); ?></p>
                    </div>
                    
                    <div class="report-issue-container" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">
                        <h3><?php _e('Report Issue', 'sentinelwp'); ?></h3>
                        <p><?php _e('When you click "Generate Issue Report", we\'ll collect system information and open a new GitHub issue with pre-filled details to help us assist you better.', 'sentinelwp'); ?></p>
                        
                        <div class="report-form" style="margin: 20px 0;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Issue Type', 'sentinelwp'); ?></th>
                                    <td>
                                        <select id="issue-type" style="min-width: 200px;">
                                            <option value="bug"><?php _e('Bug Report', 'sentinelwp'); ?></option>
                                            <option value="feature"><?php _e('Feature Request', 'sentinelwp'); ?></option>
                                            <option value="question"><?php _e('Question/Support', 'sentinelwp'); ?></option>
                                            <option value="documentation"><?php _e('Documentation Issue', 'sentinelwp'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Issue Title', 'sentinelwp'); ?></th>
                                    <td>
                                        <input type="text" id="issue-title" placeholder="<?php _e('Brief description of the issue...', 'sentinelwp'); ?>" style="width: 100%; max-width: 500px;" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Description', 'sentinelwp'); ?></th>
                                    <td>
                                        <textarea id="issue-description" rows="6" placeholder="<?php _e('Please describe the issue in detail...', 'sentinelwp'); ?>" style="width: 100%; max-width: 500px;"></textarea>
                                        <p class="description"><?php _e('Provide as much detail as possible to help us understand and resolve the issue.', 'sentinelwp'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Include System Info', 'sentinelwp'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="include-system-info" checked /> 
                                            <?php _e('Include system information (WordPress version, PHP version, plugin version, etc.)', 'sentinelwp'); ?>
                                        </label>
                                        <p class="description"><?php _e('This helps us diagnose issues faster. No sensitive data is included.', 'sentinelwp'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Include Recent Logs', 'sentinelwp'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="include-logs" /> 
                                            <?php _e('Include recent error logs (sanitized)', 'sentinelwp'); ?>
                                        </label>
                                        <p class="description"><?php _e('Only error logs from the last 24 hours. Personal data will be sanitized.', 'sentinelwp'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="report-actions" style="margin-top: 20px;">
                            <button type="button" id="generate-issue-report" class="button button-primary">
                                <span class="dashicons dashicons-external" style="margin-right: 5px;"></span>
                                <?php _e('Generate Issue Report', 'sentinelwp'); ?>
                            </button>
                            <p class="description" style="margin-top: 10px;">
                                <?php _e('This will open GitHub in a new tab with a pre-filled issue. You can review and edit the information before submitting.', 'sentinelwp'); ?>
                            </p>
                        </div>
                        
                        <div id="report-status" style="margin-top: 15px;"></div>
                    </div>
                    
                    <div class="help-links" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0;">
                        <h4><?php _e('Other Ways to Get Help', 'sentinelwp'); ?></h4>
                        <ul>
                            <li><a href="https://github.com/teguh02/SentinelWP" target="_blank"><?php _e('Visit GitHub Repository', 'sentinelwp'); ?></a></li>
                            <li><a href="https://github.com/teguh02/SentinelWP/issues" target="_blank"><?php _e('Browse Existing Issues', 'sentinelwp'); ?></a></li>
                            <li><a href="https://github.com/teguh02/SentinelWP/wiki" target="_blank"><?php _e('Documentation & Wiki', 'sentinelwp'); ?></a></li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($current_tab != 'database' && $current_tab != 'report'): ?>
                <p class="submit">
                    <button type="submit" class="button-primary" id="save-settings-btn">
                        <?php _e('Save Settings', 'sentinelwp'); ?>
                    </button>
                </p>
                <?php endif; ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#sentinelwp-settings-form').submit(function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $btn = $('#save-settings-btn');
                
                $btn.prop('disabled', true).text('<?php _e('Saving...', 'sentinelwp'); ?>');
                
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: $form.serialize() + '&action=sentinelwp_save_settings&nonce=' + sentinelwp_ajax.nonce,
                    success: function(response) {
                        if (response.success) {
                            $('<div class="notice notice-success is-dismissible"><p><?php _e('Settings saved successfully!', 'sentinelwp'); ?></p></div>').insertAfter('.nav-tab-wrapper');
                        } else {
                            $('<div class="notice notice-error is-dismissible"><p><?php _e('Failed to save settings:', 'sentinelwp'); ?> ' + response.data + '</p></div>').insertAfter('.nav-tab-wrapper');
                        }
                    },
                    error: function() {
                        $('<div class="notice notice-error is-dismissible"><p><?php _e('An error occurred while saving settings.', 'sentinelwp'); ?></p></div>').insertAfter('.nav-tab-wrapper');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('Save Settings', 'sentinelwp'); ?>');
                    }
                });
            });
            
            // Database operations
            $('#check-database-btn').click(function() {
                var $btn = $(this);
                var $results = $('#table-status-results');
                
                $btn.prop('disabled', true).text('Checking...');
                $results.html('<div class="spinner is-active" style="float: none; margin: 0;"></div>');
                
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_check_database',
                        nonce: sentinelwp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success"><p><strong>Database Check Results:</strong></p><ul>';
                            $.each(response.data.status, function(table, exists) {
                                var status = exists ? '<span style="color: green;">✓ Exists</span>' : '<span style="color: red;">✗ Missing</span>';
                                html += '<li>' + table + ': ' + status + '</li>';
                            });
                            html += '</ul></div>';
                            $results.html(html);
                        } else {
                            $results.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p>Failed to check database status.</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Check Database Tables');
                    }
                });
            });
            
            $('#migrate-database-btn').click(function() {
                var $btn = $(this);
                var $results = $('#migration-results');
                
                if (!confirm('Are you sure you want to recreate the database tables? This action cannot be undone.')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Migrating...');
                $results.html('<div class="spinner is-active" style="float: none; margin: 0;"></div>');
                
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_migrate_database',
                        nonce: sentinelwp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success"><p><strong>Migration Complete!</strong></p>';
                            if (response.data.before) {
                                html += '<p><strong>Before:</strong></p><ul>';
                                $.each(response.data.before, function(table, exists) {
                                    var status = exists ? '<span style="color: green;">✓</span>' : '<span style="color: red;">✗</span>';
                                    html += '<li>' + table + ': ' + status + '</li>';
                                });
                                html += '</ul>';
                            }
                            if (response.data.after) {
                                html += '<p><strong>After:</strong></p><ul>';
                                $.each(response.data.after, function(table, exists) {
                                    var status = exists ? '<span style="color: green;">✓</span>' : '<span style="color: red;">✗</span>';
                                    html += '<li>' + table + ': ' + status + '</li>';
                                });
                                html += '</ul>';
                            }
                            html += '</div>';
                            $results.html(html);
                        } else {
                            $results.html('<div class="notice notice-error"><p>Migration failed: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p>Failed to migrate database.</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Recreate Database Tables');
                    }
                });
            });
            
            $('#migrate-schema-btn').click(function() {
                var $btn = $(this);
                var $results = $('#migration-results');
                
                if (!confirm('Are you sure you want to migrate the database schema? This will add missing columns but is generally safe.')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Migrating Schema...');
                $results.html('<div class="spinner is-active" style="float: none; margin: 0;"></div>');
                
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_migrate_schema',
                        nonce: sentinelwp_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success"><p><strong>Schema Migration Complete!</strong></p>';
                            html += '<p>' + response.data.message + '</p>';
                            if (response.data.details) {
                                html += '<p><em>' + response.data.details + '</em></p>';
                            }
                            html += '</div>';
                            $results.html(html);
                        } else {
                            $results.html('<div class="notice notice-error"><p>Schema migration failed: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $results.html('<div class="notice notice-error"><p>Failed to migrate schema.</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Migrate Database Schema');
                    }
                });
            });
            
            // Issue report generation
            $('#generate-issue-report').click(function() {
                var $btn = $(this);
                var $status = $('#report-status');
                
                console.log('SentinelWP: Generate issue report button clicked');
                
                // Validate required fields
                var issueType = $('#issue-type').val();
                var issueTitle = $('#issue-title').val().trim();
                var issueDescription = $('#issue-description').val().trim();
                
                console.log('SentinelWP: Form data collected', {
                    issueType: issueType,
                    titleLength: issueTitle.length,
                    descriptionLength: issueDescription.length
                });
                
                if (!issueTitle) {
                    $status.html('<div class="notice notice-error"><p>Please provide an issue title.</p></div>');
                    return;
                }
                
                if (!issueDescription) {
                    $status.html('<div class="notice notice-error"><p>Please provide an issue description.</p></div>');
                    return;
                }
                
                $btn.prop('disabled', true).text('Generating Report...');
                $status.html('<div class="notice notice-info"><p>Collecting system information...</p></div>');
                
                var requestData = {
                    action: 'sentinelwp_generate_issue_report',
                    nonce: sentinelwp_ajax.nonce,
                    issue_type: issueType,
                    issue_title: issueTitle,
                    issue_description: issueDescription,
                    include_system_info: $('#include-system-info').is(':checked'),
                    include_logs: $('#include-logs').is(':checked')
                };
                
                console.log('SentinelWP: Sending AJAX request', requestData);
                
                $.ajax({
                    url: sentinelwp_ajax.ajax_url,
                    type: 'POST',
                    data: requestData,
                    timeout: 30000, // 30 second timeout
                    success: function(response) {
                        console.log('SentinelWP: AJAX response received', response);
                        
                        if (response.success) {
                            var githubUrl = response.data.github_url;
                            $status.html('<div class="notice notice-success"><p>Issue report generated! <a href="' + githubUrl + '" target="_blank" class="button button-secondary">Open GitHub Issue</a></p></div>');
                            
                            // Auto-open GitHub in new tab
                            window.open(githubUrl, '_blank');
                        } else {
                            $status.html('<div class="notice notice-error"><p>Failed to generate report: ' + response.data + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('SentinelWP: AJAX error', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText,
                            statusCode: xhr.status
                        });
                        
                        var errorMessage = 'An error occurred while generating the report.';
                        if (status === 'timeout') {
                            errorMessage = 'Request timed out. Please try again.';
                        } else if (xhr.responseJSON && xhr.responseJSON.data) {
                            errorMessage = 'Server error: ' + xhr.responseJSON.data;
                        } else if (error) {
                            errorMessage = 'Network error: ' + error;
                        }
                        
                        $status.html('<div class="notice notice-error"><p>' + errorMessage + '</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Generate Issue Report');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for resolving issues
     */
    public function ajax_resolve_issue() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $issue_id = intval($_POST['issue_id']);
        
        if ($this->database->resolve_issue($issue_id)) {
            wp_send_json_success('Issue resolved successfully');
        } else {
            wp_send_json_error('Failed to resolve issue');
        }
    }
    
    /**
     * AJAX handler for isolating issues
     */
    public function ajax_isolate_issue() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $issue_id = intval($_POST['issue_id']);
        
        if ($this->database->isolate_issue($issue_id)) {
            wp_send_json_success('File isolated successfully');
        } else {
            wp_send_json_error('Failed to isolate file');
        }
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $settings = array(
            'sentinelwp_auto_scan_enabled' => isset($_POST['auto_scan_enabled']) ? 1 : 0,
            'sentinelwp_scan_time' => sanitize_text_field($_POST['scan_time'] ?? '02:00'),
            'sentinelwp_notify_scan_results' => isset($_POST['notify_scan_results']) ? 1 : 0,
            'sentinelwp_notify_threats' => isset($_POST['notify_threats']) ? 1 : 0,
            'sentinelwp_notify_under_attack' => isset($_POST['notify_under_attack']) ? 1 : 0,
            'sentinelwp_notification_email' => sanitize_email($_POST['notification_email'] ?? ''),
            'sentinelwp_telegram_enabled' => isset($_POST['telegram_enabled']) ? 1 : 0,
            'sentinelwp_telegram_bot_token' => sanitize_text_field($_POST['telegram_bot_token'] ?? ''),
            'sentinelwp_telegram_chat_id' => sanitize_text_field($_POST['telegram_chat_id'] ?? ''),
            'sentinelwp_gemini_enabled' => isset($_POST['gemini_enabled']) ? 1 : 0,
            'sentinelwp_gemini_api_key' => sanitize_text_field($_POST['gemini_api_key'] ?? ''),
            'sentinelwp_gemini_model' => sanitize_text_field($_POST['gemini_model'] ?? 'gemini-2.5-flash')
        );
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        // Reschedule cron if time changed
        wp_clear_scheduled_hook('sentinelwp_scheduled_scan');
        
        wp_send_json_success('Settings saved successfully');
    }
    
    /**
     * AJAX handler for testing Telegram configuration
     */
    public function ajax_test_telegram() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get current settings (they might not be saved yet)
        $bot_token = get_option('sentinelwp_telegram_bot_token', '');
        $chat_id = get_option('sentinelwp_telegram_chat_id', '');
        
        if (empty($bot_token)) {
            wp_send_json_error('Telegram Bot Token is required. Please enter your bot token and save settings first.');
        }
        
        if (empty($chat_id)) {
            wp_send_json_error('Telegram Chat ID is required. Please enter your chat ID and save settings first.');
        }
        
        // Get notifications instance and test
        $notifications = SentinelWP_Notifications::instance();
        $result = $notifications->test_telegram_notification();
        
        if ($result) {
            wp_send_json_success('Test message sent successfully! Check your Telegram chat.');
        } else {
            wp_send_json_error('Failed to send test message. Please check your bot token and chat ID.');
        }
    }
    
    /**
     * AJAX handler for updating AI recommendation status
     */
    public function ajax_update_ai_recommendation() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $recommendation_id = intval($_POST['recommendation_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        if (!$recommendation_id || !in_array($status, array('implemented', 'dismissed'))) {
            wp_send_json_error('Invalid parameters');
        }
        
        $database = SentinelWP_Database::instance();
        $result = $database->update_ai_recommendation_status($recommendation_id, $status);
        
        if ($result !== false) {
            $status_text = $status === 'implemented' ? 'marked as implemented' : 'dismissed';
            wp_send_json_success("Recommendation $status_text successfully");
        } else {
            wp_send_json_error('Failed to update recommendation status');
        }
    }
    
    /**
     * Render logs page
     */
    public static function render_logs() {
        $logger = SentinelWP_Logger::instance();
        $log_files = $logger->get_log_files();
        
        // Handle log file download
        if (isset($_GET['download_log']) && !empty($_GET['download_log'])) {
            $log_file = sanitize_text_field($_GET['download_log']);
            $file_path = '';
            
            // Find the requested log file
            foreach ($log_files as $file) {
                if ($file['name'] === $log_file) {
                    $file_path = $file['path'];
                    break;
                }
            }
            
            if (!empty($file_path) && file_exists($file_path)) {
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="' . $log_file . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit;
            }
        }
        
        // Handle log level filter
        $selected_level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
        $recent_logs = $logger->get_recent_logs($selected_level, 200);
        
        ?>
        <div class="wrap sentinelwp-logs">
            <h1><?php _e('SentinelWP Debug Logs', 'sentinelwp'); ?></h1>
            
            <div class="sentinelwp-logs-header">
                <div class="log-filters">
                    <form method="get">
                        <input type="hidden" name="page" value="sentinelwp-logs">
                        <label for="log-level"><?php _e('Filter by Level:', 'sentinelwp'); ?></label>
                        <select name="level" id="log-level">
                            <option value=""><?php _e('All Levels', 'sentinelwp'); ?></option>
                            <option value="emergency" <?php selected($selected_level, 'emergency'); ?>><?php _e('Emergency', 'sentinelwp'); ?></option>
                            <option value="alert" <?php selected($selected_level, 'alert'); ?>><?php _e('Alert', 'sentinelwp'); ?></option>
                            <option value="critical" <?php selected($selected_level, 'critical'); ?>><?php _e('Critical', 'sentinelwp'); ?></option>
                            <option value="error" <?php selected($selected_level, 'error'); ?>><?php _e('Error', 'sentinelwp'); ?></option>
                            <option value="warning" <?php selected($selected_level, 'warning'); ?>><?php _e('Warning', 'sentinelwp'); ?></option>
                            <option value="notice" <?php selected($selected_level, 'notice'); ?>><?php _e('Notice', 'sentinelwp'); ?></option>
                            <option value="info" <?php selected($selected_level, 'info'); ?>><?php _e('Info', 'sentinelwp'); ?></option>
                            <option value="debug" <?php selected($selected_level, 'debug'); ?>><?php _e('Debug', 'sentinelwp'); ?></option>
                        </select>
                        <?php submit_button(__('Filter', 'sentinelwp'), 'secondary', 'submit', false); ?>
                    </form>
                </div>
                
                <div class="log-actions">
                    <button type="button" class="button" onclick="location.reload()"><?php _e('Refresh', 'sentinelwp'); ?></button>
                </div>
            </div>
            
            <div class="sentinelwp-logs-container">
                <div class="logs-tabs">
                    <div class="tab-content active" id="recent-logs">
                        <h2><?php _e('Recent Log Entries', 'sentinelwp'); ?></h2>
                        
                        <?php if (empty($recent_logs)): ?>
                            <p><?php _e('No log entries found.', 'sentinelwp'); ?></p>
                        <?php else: ?>
                            <div class="log-entries">
                                <?php foreach ($recent_logs as $log): ?>
                                    <div class="log-entry log-level-<?php echo esc_attr($log['level']); ?>">
                                        <div class="log-timestamp"><?php echo esc_html($log['timestamp']); ?></div>
                                        <div class="log-level"><?php echo esc_html(strtoupper($log['level'])); ?></div>
                                        <div class="log-message"><?php echo esc_html($log['message']); ?></div>
                                        <?php if (!empty($log['context'])): ?>
                                            <div class="log-context">
                                                <details>
                                                    <summary><?php _e('Context', 'sentinelwp'); ?></summary>
                                                    <pre><?php echo esc_html($log['context']); ?></pre>
                                                </details>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="logs-sidebar">
                    <div class="sidebar-section">
                        <h3><?php _e('Log Files', 'sentinelwp'); ?></h3>
                        <?php if (empty($log_files)): ?>
                            <p><?php _e('No log files found.', 'sentinelwp'); ?></p>
                        <?php else: ?>
                            <ul class="log-files-list">
                                <?php foreach ($log_files as $file): ?>
                                    <li>
                                        <div class="log-file-info">
                                            <strong><?php echo esc_html($file['name']); ?></strong>
                                            <span class="file-size"><?php echo size_format($file['size']); ?></span>
                                            <span class="file-date"><?php echo date('Y-m-d H:i', $file['modified']); ?></span>
                                        </div>
                                        <div class="log-file-actions">
                                            <a href="<?php echo esc_url(add_query_arg('download_log', $file['name'])); ?>" class="button button-small">
                                                <?php _e('Download', 'sentinelwp'); ?>
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sidebar-section">
                        <h3><?php _e('Log Information', 'sentinelwp'); ?></h3>
                        <p><?php _e('Logs are stored in the plugin directory and contain detailed information about plugin operations, API calls, and errors.', 'sentinelwp'); ?></p>
                        <p><?php _e('Log files are automatically rotated daily and old files are cleaned up periodically.', 'sentinelwp'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .sentinelwp-logs .sentinelwp-logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .sentinelwp-logs .log-filters form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sentinelwp-logs .sentinelwp-logs-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
        }
        
        .sentinelwp-logs .log-entries {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #ccd0d4;
            background: #fff;
        }
        
        .sentinelwp-logs .log-entry {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f1;
            font-family: Monaco, 'Lucida Console', monospace;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .sentinelwp-logs .log-entry:last-child {
            border-bottom: none;
        }
        
        .sentinelwp-logs .log-level-emergency,
        .sentinelwp-logs .log-level-alert,
        .sentinelwp-logs .log-level-critical {
            border-left: 4px solid #dc3232;
            background-color: #fff8f8;
        }
        
        .sentinelwp-logs .log-level-error {
            border-left: 4px solid #dc3232;
            background-color: #fff8f8;
        }
        
        .sentinelwp-logs .log-level-warning {
            border-left: 4px solid #ffb900;
            background-color: #fffbf0;
        }
        
        .sentinelwp-logs .log-level-info {
            border-left: 4px solid #00a0d2;
            background-color: #f0f8ff;
        }
        
        .sentinelwp-logs .log-level-debug {
            border-left: 4px solid #00a32a;
            background-color: #f0fff4;
        }
        
        .sentinelwp-logs .log-timestamp {
            color: #666;
            margin-bottom: 4px;
        }
        
        .sentinelwp-logs .log-level {
            display: inline-block;
            padding: 2px 8px;
            margin-bottom: 4px;
            font-weight: bold;
            border-radius: 3px;
            font-size: 10px;
        }
        
        .sentinelwp-logs .log-level-emergency .log-level,
        .sentinelwp-logs .log-level-alert .log-level,
        .sentinelwp-logs .log-level-critical .log-level,
        .sentinelwp-logs .log-level-error .log-level {
            background: #dc3232;
            color: white;
        }
        
        .sentinelwp-logs .log-level-warning .log-level {
            background: #ffb900;
            color: white;
        }
        
        .sentinelwp-logs .log-level-info .log-level {
            background: #00a0d2;
            color: white;
        }
        
        .sentinelwp-logs .log-level-debug .log-level {
            background: #00a32a;
            color: white;
        }
        
        .sentinelwp-logs .log-context {
            margin-top: 8px;
        }
        
        .sentinelwp-logs .log-context details {
            cursor: pointer;
        }
        
        .sentinelwp-logs .log-context pre {
            background: #f6f7f7;
            padding: 10px;
            margin: 5px 0;
            overflow-x: auto;
            font-size: 11px;
        }
        
        .sentinelwp-logs .logs-sidebar {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .sentinelwp-logs .sidebar-section {
            padding: 20px;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .sentinelwp-logs .sidebar-section:last-child {
            border-bottom: none;
        }
        
        .sentinelwp-logs .sidebar-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .sentinelwp-logs .log-files-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .sentinelwp-logs .log-files-list li {
            padding: 10px;
            border: 1px solid #f0f0f1;
            margin-bottom: 5px;
            background: #f9f9f9;
        }
        
        .sentinelwp-logs .log-file-info {
            margin-bottom: 8px;
        }
        
        .sentinelwp-logs .file-size,
        .sentinelwp-logs .file-date {
            display: block;
            font-size: 12px;
            color: #666;
        }
        
        @media (max-width: 782px) {
            .sentinelwp-logs .sentinelwp-logs-container {
                grid-template-columns: 1fr;
            }
            
            .sentinelwp-logs .sentinelwp-logs-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for checking database status
     */
    public function ajax_check_database() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $status = SentinelWP_Database::get_table_status();
            SentinelWP_Logger::info('Database status checked via AJAX', $status['summary']);
            
            wp_send_json_success($status);
            
        } catch (Exception $e) {
            SentinelWP_Logger::error('Database status check failed', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error('Failed to check database status: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for database migration
     */
    public function ajax_migrate_database() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            SentinelWP_Logger::warning('Database migration started by user', array(
                'user_id' => get_current_user_id()
            ));
            
            // Check current status first
            $status_before = SentinelWP_Database::get_table_status();
            
            // Run migration
            $migration_result = SentinelWP_Database::create_tables();
            
            // Check status after migration
            $status_after = SentinelWP_Database::get_table_status();
            
            SentinelWP_Logger::info('Database migration completed', array(
                'before' => $status_before['summary'],
                'after' => $status_after['summary'],
                'migration_result' => $migration_result
            ));
            
            wp_send_json_success(array(
                'message' => 'Database migration completed successfully',
                'status_before' => $status_before,
                'status_after' => $status_after,
                'migration_result' => $migration_result
            ));
            
        } catch (Exception $e) {
            SentinelWP_Logger::error('Database migration failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('Database migration failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for schema-only migration
     */
    public function ajax_migrate_schema() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            SentinelWP_Logger::info('Database schema migration started by user', array(
                'user_id' => get_current_user_id()
            ));
            
            // Run schema migration only
            $migration_result = SentinelWP_Database::migrate_database();
            
            if ($migration_result) {
                SentinelWP_Logger::info('Database schema migration completed successfully');
                
                wp_send_json_success(array(
                    'message' => 'Database schema migration completed successfully',
                    'details' => 'Schema has been updated. You can now try running a scan.'
                ));
            } else {
                wp_send_json_error('Schema migration failed. Check logs for details.');
            }
            
        } catch (Exception $e) {
            SentinelWP_Logger::error('Database schema migration failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('Schema migration failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for generating issue reports
     */
    public function ajax_generate_issue_report() {
        // Add detailed logging for debugging
        SentinelWP_Logger::info('AJAX handler called for issue report generation', array(
            'user_id' => get_current_user_id(),
            'post_data' => $_POST
        ));
        
        // Check nonce
        if (!check_ajax_referer('sentinelwp_nonce', 'nonce', false)) {
            SentinelWP_Logger::error('Nonce verification failed for issue report', array(
                'provided_nonce' => $_POST['nonce'] ?? 'missing',
                'user_id' => get_current_user_id()
            ));
            wp_send_json_error('Security verification failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            SentinelWP_Logger::error('Insufficient permissions for issue report', array(
                'user_id' => get_current_user_id()
            ));
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        try {
            $issue_type = sanitize_text_field($_POST['issue_type'] ?? 'bug');
            $issue_title = sanitize_text_field($_POST['issue_title'] ?? '');
            $issue_description = sanitize_textarea_field($_POST['issue_description'] ?? '');
            $include_system_info = isset($_POST['include_system_info']) && $_POST['include_system_info'] === 'true';
            $include_logs = isset($_POST['include_logs']) && $_POST['include_logs'] === 'true';
            
            SentinelWP_Logger::info('Processing issue report request', array(
                'issue_type' => $issue_type,
                'title_length' => strlen($issue_title),
                'description_length' => strlen($issue_description),
                'include_system_info' => $include_system_info,
                'include_logs' => $include_logs
            ));
            
            if (empty($issue_title) || empty($issue_description)) {
                wp_send_json_error('Issue title and description are required');
                return;
            }
            
            // Prepare issue content
            $issue_content = $this->build_issue_report_content(
                $issue_type,
                $issue_title,
                $issue_description,
                $include_system_info,
                $include_logs
            );
            
            SentinelWP_Logger::info('Issue content built', array(
                'content_length' => strlen($issue_content)
            ));
            
            // Generate GitHub issue URL
            $github_url = $this->generate_github_issue_url($issue_type, $issue_title, $issue_content);
            
            SentinelWP_Logger::info('GitHub URL generated successfully', array(
                'url_length' => strlen($github_url),
                'issue_type' => $issue_type
            ));
            
            wp_send_json_success(array(
                'message' => 'Issue report generated successfully',
                'github_url' => $github_url
            ));
            
        } catch (Exception $e) {
            SentinelWP_Logger::error('Issue report generation failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            wp_send_json_error('Failed to generate issue report: ' . $e->getMessage());
        }
    }
    
    /**
     * Build issue report content
     */
    private function build_issue_report_content($issue_type, $title, $description, $include_system_info, $include_logs) {
        $content = "## Issue Description\n\n";
        $content .= $description . "\n\n";
        
        if ($include_system_info) {
            $content .= "## System Information\n\n";
            $content .= $this->get_system_info_for_report();
        }
        
        if ($include_logs) {
            $content .= "\n## Recent Error Logs (Last 24 Hours)\n\n";
            $content .= $this->get_sanitized_logs_for_report();
        }
        
        $content .= "\n---\n";
        $content .= "*This issue was automatically generated from the SentinelWP plugin.*";
        
        return $content;
    }
    
    /**
     * Get system information for issue report
     */
    private function get_system_info_for_report() {
        global $wp_version;
        
        $system_info = "```\n";
        $system_info .= "WordPress Version: " . $wp_version . "\n";
        $system_info .= "PHP Version: " . PHP_VERSION . "\n";
        $system_info .= "Plugin Version: " . SENTINELWP_VERSION . "\n";
        $system_info .= "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
        $system_info .= "Memory Limit: " . ini_get('memory_limit') . "\n";
        $system_info .= "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
        $system_info .= "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
        
        // Plugin-specific information
        $system_info .= "\nPlugin Status:\n";
        $system_info .= "- Gemini API Key: " . (get_option('sentinelwp_gemini_api_key') ? 'Configured' : 'Not configured') . "\n";
        $system_info .= "- Auto Scan: " . (get_option('sentinelwp_auto_scan_enabled', true) ? 'Enabled' : 'Disabled') . "\n";
        $system_info .= "- Email Notifications: " . (get_option('sentinelwp_notification_email') ? 'Configured' : 'Not configured') . "\n";
        
        // Database status
        $database_status = SentinelWP_Database::get_table_status();
        $system_info .= "\nDatabase Tables:\n";
        if (isset($database_status['details'])) {
            foreach ($database_status['details'] as $table_info) {
                $system_info .= "- " . $table_info['name'] . ": " . $table_info['status'] . "\n";
            }
        } else {
            // Fallback method
            $tables_info = SentinelWP_Database::tables_exist();
            foreach ($tables_info['existing'] as $table) {
                $system_info .= "- " . $table . ": OK\n";
            }
            foreach ($tables_info['missing'] as $table) {
                $system_info .= "- " . $table . ": MISSING\n";
            }
        }
        
        $system_info .= "```\n\n";
        
        return $system_info;
    }
    
    /**
     * Get sanitized logs for issue report
     */
    private function get_sanitized_logs_for_report() {
        $logs_content = "```\n";
        
        try {
            $log_file = SENTINELWP_PLUGIN_PATH . 'logs/sentinelwp-' . date('Y-m-d') . '.log';
            
            if (file_exists($log_file)) {
                $log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recent_lines = array_slice($log_lines, -20); // Last 20 lines
                
                foreach ($recent_lines as $line) {
                    // Sanitize sensitive information
                    $sanitized_line = preg_replace('/AIzaSy[A-Za-z0-9_-]{35}/', 'AIzaSy***API_KEY_HIDDEN***', $line);
                    $sanitized_line = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', 'XXX.XXX.XXX.XXX', $sanitized_line);
                    $sanitized_line = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', 'email@hidden.com', $sanitized_line);
                    
                    $logs_content .= $sanitized_line . "\n";
                }
            } else {
                $logs_content .= "No recent log file found.\n";
            }
        } catch (Exception $e) {
            $logs_content .= "Error reading logs: " . $e->getMessage() . "\n";
        }
        
        $logs_content .= "```\n\n";
        
        return $logs_content;
    }
    
    /**
     * Generate GitHub issue URL
     */
    private function generate_github_issue_url($issue_type, $title, $content) {
        $repo_url = 'https://github.com/teguh02/SentinelWP';
        
        // Add type prefix to title
        $type_prefixes = array(
            'bug' => '[Bug]',
            'feature' => '[Feature Request]',
            'question' => '[Question]',
            'documentation' => '[Documentation]'
        );
        
        $prefixed_title = ($type_prefixes[$issue_type] ?? '[Issue]') . ' ' . $title;
        
        // Prepare labels based on issue type
        $labels = array();
        switch ($issue_type) {
            case 'bug':
                $labels[] = 'bug';
                break;
            case 'feature':
                $labels[] = 'enhancement';
                break;
            case 'question':
                $labels[] = 'question';
                break;
            case 'documentation':
                $labels[] = 'documentation';
                break;
        }
        $labels[] = 'auto-generated';
        
        // Build the GitHub issue URL
        $params = array(
            'title' => $prefixed_title,
            'body' => $content,
            'labels' => implode(',', $labels)
        );
        
        return $repo_url . '/issues/new?' . http_build_query($params);
    }
    
    /**
     * Render notifications page
     */
    public static function render_notifications() {
        $database = SentinelWP_Database::instance();
        $attack_detector = SentinelWP_Attack_Detector::instance();
        
        // Get filters from URL parameters
        $current_filter = array(
            'status' => sanitize_text_field($_GET['filter_status'] ?? ''),
            'severity' => sanitize_text_field($_GET['filter_severity'] ?? ''),
            'event_type' => sanitize_text_field($_GET['filter_event_type'] ?? ''),
            'order_by' => 'created_at',
            'order' => 'DESC',
            'limit' => 100  // Show more notifications by default
        );
        
        // Get notifications with filters (latest first)
        $notifications = $database->get_notifications($current_filter);
        
        // Additional sort to ensure proper ordering (in case of database inconsistencies)
        if (!empty($notifications)) {
            usort($notifications, function($a, $b) {
                $time_a = strtotime($a->created_at);
                $time_b = strtotime($b->created_at);
                
                // Primary sort: by created_at (newest first)
                if ($time_b !== $time_a) {
                    return $time_b - $time_a;
                }
                
                // Secondary sort: by ID (highest first)
                return (int)$b->id - (int)$a->id;
            });
        }
        $notification_counts = $database->get_notification_counts();
        $attack_stats = $attack_detector->get_attack_stats();
        
        ?>
        <div class="wrap sentinelwp-notifications">
            <h1><?php _e('Attack Detection & Notifications', 'sentinelwp'); ?></h1>
            
            <!-- Statistics Overview -->
            <div class="sentinelwp-stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($notification_counts['total']); ?></div>
                    <div class="stat-label"><?php _e('Total Notifications', 'sentinelwp'); ?></div>
                </div>
                <div class="stat-card critical">
                    <div class="stat-number"><?php echo esc_html($notification_counts['by_severity']['critical'] ?? 0); ?></div>
                    <div class="stat-label"><?php _e('Critical Alerts', 'sentinelwp'); ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-number"><?php echo esc_html($notification_counts['by_severity']['high'] ?? 0); ?></div>
                    <div class="stat-label"><?php _e('High Priority', 'sentinelwp'); ?></div>
                </div>
                <div class="stat-card info">
                    <div class="stat-number"><?php echo esc_html($notification_counts['by_status']['new'] ?? 0); ?></div>
                    <div class="stat-label"><?php _e('Unread', 'sentinelwp'); ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="sentinelwp-filters">
                <form method="GET" class="filter-form">
                    <input type="hidden" name="page" value="sentinelwp-notifications">
                    
                    <select name="filter_severity" onchange="this.form.submit()">
                        <option value=""><?php _e('All Severities', 'sentinelwp'); ?></option>
                        <option value="low" <?php selected($current_filter['severity'], 'low'); ?>><?php _e('Low', 'sentinelwp'); ?></option>
                        <option value="medium" <?php selected($current_filter['severity'], 'medium'); ?>><?php _e('Medium', 'sentinelwp'); ?></option>
                        <option value="high" <?php selected($current_filter['severity'], 'high'); ?>><?php _e('High', 'sentinelwp'); ?></option>
                        <option value="critical" <?php selected($current_filter['severity'], 'critical'); ?>><?php _e('Critical', 'sentinelwp'); ?></option>
                    </select>
                    
                    <select name="filter_status" onchange="this.form.submit()">
                        <option value=""><?php _e('All Statuses', 'sentinelwp'); ?></option>
                        <option value="new" <?php selected($current_filter['status'], 'new'); ?>><?php _e('New', 'sentinelwp'); ?></option>
                        <option value="read" <?php selected($current_filter['status'], 'read'); ?>><?php _e('Read', 'sentinelwp'); ?></option>
                        <option value="resolved" <?php selected($current_filter['status'], 'resolved'); ?>><?php _e('Resolved', 'sentinelwp'); ?></option>
                    </select>
                    
                    <select name="filter_event_type" onchange="this.form.submit()">
                        <option value=""><?php _e('All Event Types', 'sentinelwp'); ?></option>
                        <option value="brute_force" <?php selected($current_filter['event_type'], 'brute_force'); ?>><?php _e('Brute Force', 'sentinelwp'); ?></option>
                        <option value="xmlrpc_abuse" <?php selected($current_filter['event_type'], 'xmlrpc_abuse'); ?>><?php _e('XML-RPC Abuse', 'sentinelwp'); ?></option>
                        <option value="malicious_upload" <?php selected($current_filter['event_type'], 'malicious_upload'); ?>><?php _e('Malicious Upload', 'sentinelwp'); ?></option>
                        <option value="suspicious_attachment" <?php selected($current_filter['event_type'], 'suspicious_attachment'); ?>><?php _e('Suspicious File', 'sentinelwp'); ?></option>
                        <option value="direct_php_creation" <?php selected($current_filter['event_type'], 'direct_php_creation'); ?>><?php _e('Direct PHP Creation', 'sentinelwp'); ?></option>
                    </select>
                    
                    <?php if (!empty(array_filter($current_filter))): ?>
                    <a href="<?php echo admin_url('admin.php?page=sentinelwp-notifications'); ?>" class="button">
                        <?php _e('Clear Filters', 'sentinelwp'); ?>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Notifications List -->
            <div class="sentinelwp-notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications">
                        <h3><?php _e('No Attack Notifications', 'sentinelwp'); ?></h3>
                        <p><?php _e('Great! No security threats have been detected recently.', 'sentinelwp'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notifications-header">
                        <h3><?php _e('Security Notifications', 'sentinelwp'); ?></h3>
                        <span class="notifications-ordering"><?php _e('Showing latest notifications first', 'sentinelwp'); ?></span>
                    </div>
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $severity_class = 'notification-' . $notification->severity;
                        $status_class = 'notification-' . $notification->status;
                        
                        $event_icons = array(
                            'brute_force' => '🔓',
                            'xmlrpc_abuse' => '🤖',
                            'malicious_upload' => '☠️',
                            'suspicious_attachment' => '📎',
                            'direct_php_creation' => '💀'
                        );
                        
                        $event_names = array(
                            'brute_force' => __('Brute Force Attack', 'sentinelwp'),
                            'xmlrpc_abuse' => __('XML-RPC Abuse', 'sentinelwp'),
                            'malicious_upload' => __('Malicious Upload', 'sentinelwp'),
                            'suspicious_attachment' => __('Suspicious File', 'sentinelwp'),
                            'direct_php_creation' => __('Direct PHP Creation', 'sentinelwp')
                        );
                        
                        $icon = $event_icons[$notification->event_type] ?? '⚠️';
                        $event_name = $event_names[$notification->event_type] ?? ucfirst(str_replace('_', ' ', $notification->event_type));
                        ?>
                        
                        <div class="notification-item <?php echo esc_attr($severity_class . ' ' . $status_class); ?>" data-id="<?php echo esc_attr($notification->id); ?>">
                            <div class="notification-header">
                                <div class="notification-icon"><?php echo $icon; ?></div>
                                <div class="notification-meta">
                                    <h4 class="notification-title"><?php echo esc_html($event_name); ?></h4>
                                    <div class="notification-details">
                                        <span class="severity severity-<?php echo esc_attr($notification->severity); ?>">
                                            <?php echo esc_html(strtoupper($notification->severity)); ?>
                                        </span>
                                        <?php if ($notification->ip_address && $notification->ip_address !== 'unknown'): ?>
                                        <span class="ip-address">IP: <?php echo esc_html($notification->ip_address); ?></span>
                                        <?php endif; ?>
                                        <span class="timestamp" title="<?php echo esc_attr(date_i18n('F j, Y g:i A', strtotime($notification->created_at))); ?>">
                                            <?php echo esc_html(human_time_diff(strtotime($notification->created_at), current_time('timestamp')) . ' ago'); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="notification-actions">
                                    <?php if ($notification->status === 'new'): ?>
                                    <button class="button button-small mark-read-btn" data-id="<?php echo esc_attr($notification->id); ?>">
                                        <?php _e('Mark as Read', 'sentinelwp'); ?>
                                    </button>
                                    <?php endif; ?>
                                    <button class="button button-small delete-btn" data-id="<?php echo esc_attr($notification->id); ?>">
                                        <?php _e('Delete', 'sentinelwp'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="notification-description">
                                <p><?php echo esc_html($notification->description); ?></p>
                                
                                <?php if (!empty($notification->additional_data)): ?>
                                    <?php $additional_data = json_decode($notification->additional_data, true); ?>
                                    <?php if (is_array($additional_data) && !empty($additional_data)): ?>
                                    <div class="additional-data">
                                        <strong><?php _e('Additional Information:', 'sentinelwp'); ?></strong>
                                        <ul>
                                            <?php foreach ($additional_data as $key => $value): ?>
                                                <li><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> <?php echo esc_html($value); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Attack Statistics -->
            <?php if (!empty($attack_stats)): ?>
            <div class="sentinelwp-attack-stats">
                <h2><?php _e('Attack Statistics (Last 24 Hours)', 'sentinelwp'); ?></h2>
                <div class="stats-table">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Attack Type', 'sentinelwp'); ?></th>
                                <th><?php _e('Severity', 'sentinelwp'); ?></th>
                                <th><?php _e('Count', 'sentinelwp'); ?></th>
                                <th><?php _e('Unique IPs', 'sentinelwp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attack_stats as $stat): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $stat->event_type))); ?></td>
                                <td><span class="severity-badge severity-<?php echo esc_attr($stat->severity); ?>"><?php echo esc_html(strtoupper($stat->severity)); ?></span></td>
                                <td><?php echo esc_html($stat->count); ?></td>
                                <td><?php echo esc_html($stat->unique_ips); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .sentinelwp-notifications .sentinelwp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .sentinelwp-notifications .stat-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        
        .sentinelwp-notifications .stat-card.critical { border-left: 4px solid #dc3545; }
        .sentinelwp-notifications .stat-card.warning { border-left: 4px solid #fd7e14; }
        .sentinelwp-notifications .stat-card.info { border-left: 4px solid #17a2b8; }
        
        .sentinelwp-notifications .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .sentinelwp-notifications .sentinelwp-filters {
            background: #f9f9f9;
            border: 1px solid #ccd0d4;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .sentinelwp-notifications .filter-form select {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .sentinelwp-notifications .notification-item {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .sentinelwp-notifications .notification-item.notification-critical { border-left: 4px solid #dc3545; }
        .sentinelwp-notifications .notification-item.notification-high { border-left: 4px solid #fd7e14; }
        .sentinelwp-notifications .notification-item.notification-medium { border-left: 4px solid #ffc107; }
        .sentinelwp-notifications .notification-item.notification-low { border-left: 4px solid #28a745; }
        
        .sentinelwp-notifications .notification-item.notification-new {
            box-shadow: 0 0 5px rgba(0,123,255,0.3);
        }
        
        .sentinelwp-notifications .notification-header {
            display: flex;
            align-items: flex-start;
            padding: 20px;
            gap: 15px;
        }
        
        .sentinelwp-notifications .notification-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .sentinelwp-notifications .notification-meta {
            flex: 1;
        }
        
        .sentinelwp-notifications .notification-title {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .sentinelwp-notifications .notification-details {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .sentinelwp-notifications .severity {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }
        
        .sentinelwp-notifications .severity-critical { background: #dc3545; }
        .sentinelwp-notifications .severity-high { background: #fd7e14; }
        .sentinelwp-notifications .severity-medium { background: #ffc107; color: #000; }
        .sentinelwp-notifications .severity-low { background: #28a745; }
        
        .sentinelwp-notifications .ip-address {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .sentinelwp-notifications .timestamp {
            color: #6c757d;
            font-size: 12px;
        }
        
        .sentinelwp-notifications .notification-actions {
            flex-shrink: 0;
        }
        
        .sentinelwp-notifications .notification-description {
            padding: 0 20px 20px 59px;
            color: #555;
        }
        
        .sentinelwp-notifications .additional-data {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .sentinelwp-notifications .additional-data ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .sentinelwp-notifications .no-notifications {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .sentinelwp-notifications .sentinelwp-attack-stats {
            margin-top: 40px;
        }
        
        .sentinelwp-notifications .severity-badge {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }
        
        .sentinelwp-attack-banner {
            border: 2px solid #dc3545 !important;
            background: #f8d7da !important;
            color: #721c24 !important;
        }
        
        .sentinelwp-attack-banner h2 {
            color: #721c24 !important;
            margin-top: 0;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Mark notification as read
            $('.mark-read-btn').on('click', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const notificationId = $btn.data('id');
                const $notification = $btn.closest('.notification-item');
                
                $btn.prop('disabled', true).text('<?php _e('Updating...', 'sentinelwp'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_mark_notification_read',
                        notification_id: notificationId,
                        nonce: '<?php echo wp_create_nonce('sentinelwp_notifications'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $notification.removeClass('notification-new');
                            $btn.remove();
                        } else {
                            alert('<?php _e('Failed to update notification', 'sentinelwp'); ?>');
                            $btn.prop('disabled', false).text('<?php _e('Mark as Read', 'sentinelwp'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred', 'sentinelwp'); ?>');
                        $btn.prop('disabled', false).text('<?php _e('Mark as Read', 'sentinelwp'); ?>');
                    }
                });
            });
            
            // Delete notification
            $('.delete-btn').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php _e('Are you sure you want to delete this notification?', 'sentinelwp'); ?>')) {
                    return;
                }
                
                const $btn = $(this);
                const notificationId = $btn.data('id');
                const $notification = $btn.closest('.notification-item');
                
                $btn.prop('disabled', true).text('<?php _e('Deleting...', 'sentinelwp'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sentinelwp_delete_notification',
                        notification_id: notificationId,
                        nonce: '<?php echo wp_create_nonce('sentinelwp_notifications'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $notification.fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('<?php _e('Failed to delete notification', 'sentinelwp'); ?>');
                            $btn.prop('disabled', false).text('<?php _e('Delete', 'sentinelwp'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred', 'sentinelwp'); ?>');
                        $btn.prop('disabled', false).text('<?php _e('Delete', 'sentinelwp'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for marking notification as read
     */
    public function ajax_mark_notification_read() {
        check_ajax_referer('sentinelwp_notifications', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'sentinelwp'));
        }
        
        $notification_id = intval($_POST['notification_id'] ?? 0);
        
        if ($notification_id <= 0) {
            wp_send_json_error(__('Invalid notification ID', 'sentinelwp'));
        }
        
        $result = $this->database->update_notification_status($notification_id, 'read');
        
        if ($result) {
            wp_send_json_success(__('Notification marked as read', 'sentinelwp'));
        } else {
            wp_send_json_error(__('Failed to update notification', 'sentinelwp'));
        }
    }
    
    /**
     * AJAX handler for deleting notification
     */
    public function ajax_delete_notification() {
        check_ajax_referer('sentinelwp_notifications', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'sentinelwp'));
        }
        
        $notification_id = intval($_POST['notification_id'] ?? 0);
        
        if ($notification_id <= 0) {
            wp_send_json_error(__('Invalid notification ID', 'sentinelwp'));
        }
        
        global $wpdb;
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        
        $result = $wpdb->delete(
            $table_notifications,
            array('id' => $notification_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Notification deleted', 'sentinelwp'));
        } else {
            wp_send_json_error(__('Failed to delete notification', 'sentinelwp'));
        }
    }
}
