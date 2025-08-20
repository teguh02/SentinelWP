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
    }
    
    /**
     * Render main dashboard page
     */
    public static function render_dashboard() {
        $database = SentinelWP_Database::instance();
        $system_status = get_option('sentinelwp_system_status', array());
        $stats = $database->get_security_stats();
        
        ?>
        <div class="wrap sentinelwp-dashboard">
            <h1><?php _e('SentinelWP Security Dashboard', 'sentinelwp'); ?></h1>
            
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
                        <button id="run-scan-btn" class="button button-primary">
                            <?php _e('Run Scan Now', 'sentinelwp'); ?>
                        </button>
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
                        <div class="progress-fill" style="width: 0%"></div>
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
                                <p class="description"><?php _e('Chat ID to send notifications to', 'sentinelwp'); ?></p>
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
                        <h3><?php _e('Database Migration', 'sentinelwp'); ?></h3>
                        <p><?php _e('If tables are missing or corrupted, you can recreate them:', 'sentinelwp'); ?></p>
                        <div style="margin: 15px 0;">
                            <button type="button" id="migrate-database-btn" class="button button-secondary">
                                <?php _e('Recreate Database Tables', 'sentinelwp'); ?>
                            </button>
                            <p class="description" style="color: #d63638; margin-top: 5px;">
                                <?php _e('Warning: This will recreate all plugin tables. Existing data will be preserved where possible.', 'sentinelwp'); ?>
                            </p>
                        </div>
                        <div id="migration-results" style="margin-top: 10px;"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($current_tab != 'database'): ?>
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
}
