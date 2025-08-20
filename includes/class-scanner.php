<?php
/**
 * SentinelWP Scanner Class
 * 
 * Hybrid scanning engine that supports both ClamAV and heuristic scanning
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_Scanner {
    
    private static $instance = null;
    private $database;
    private $system_status;
    private $scan_results = array();
    
    // Dangerous PHP functions to detect
    private $dangerous_functions = array(
        'eval',
        'base64_decode',
        'gzinflate',
        'gzuncompress',
        'gzdeflate',
        'str_rot13',
        'shell_exec',
        'system',
        'exec',
        'passthru',
        'proc_open',
        'popen',
        'file_get_contents',
        'curl_exec',
        'fsockopen',
        'fwrite',
        'fopen',
        'file_put_contents',
        'move_uploaded_file'
    );
    
    // Suspicious patterns
    private $suspicious_patterns = array(
        '/eval\s*\(/i',
        '/base64_decode\s*\(/i',
        '/gzinflate\s*\(/i',
        '/preg_replace\s*\(\s*[\'"].*\/e[\'"].*\)/i',
        '/\$_(?:GET|POST|REQUEST|COOKIE|SERVER)\s*\[.*\]\s*\(/i',
        '/<\?php\s+\/\*.*\*\/\s*\$[a-z0-9_]+=.*;\s*eval\(/is',
        '/assert\s*\(/i',
        '/create_function\s*\(/i',
        '/\${[^}]*}\s*=.*;\s*\${[^}]*}\(/is'
    );
    
    // File extensions to scan
    private $scan_extensions = array('php', 'js', 'html', 'htm', 'css', 'txt', 'htaccess');
    
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
        $this->system_status = get_option('sentinelwp_system_status', array());
        
        // Add AJAX handlers
        add_action('wp_ajax_sentinelwp_run_scan', array($this, 'ajax_run_scan'));
        add_action('wp_ajax_sentinelwp_scan_progress', array($this, 'ajax_scan_progress'));
    }
    
    /**
     * Run full security scan
     */
    public function run_full_scan() {
        $start_time = microtime(true);
        
        // Validate database schema before scanning
        if (!$this->validate_database_schema()) {
            SentinelWP_Logger::error('Database schema validation failed - attempting migration');
            
            // Try to migrate the database
            if (!SentinelWP_Database::migrate_database()) {
                SentinelWP_Logger::error('Database migration failed - cannot proceed with scan');
                return array(
                    'success' => false,
                    'error' => 'Database schema is not compatible. Please update the plugin or recreate database tables.'
                );
            }
            
            // Validate again after migration
            if (!$this->validate_database_schema()) {
                SentinelWP_Logger::error('Database schema validation still failed after migration');
                return array(
                    'success' => false,
                    'error' => 'Database schema migration was unsuccessful. Please recreate database tables.'
                );
            }
            
            SentinelWP_Logger::info('Database migration successful - proceeding with scan');
        }
        
        // Initialize scan
        $scan_mode = $this->get_scan_mode();
        $scan_data = array(
            'scan_mode' => $scan_mode,
            'scan_time' => current_time('mysql'),
            'status' => 'running'
        );
        
        $scan_id = $this->database->insert_scan($scan_data);
        if (!$scan_id) {
            return false;
        }
        
        $this->scan_results = array(
            'scan_id' => $scan_id,
            'mode' => $scan_mode,
            'issues' => array(),
            'files_scanned' => 0,
            'threats_found' => 0
        );
        
        // Log scan start
        $this->database->insert_log(array(
            'action' => 'scan_started',
            'details' => "Full scan started with mode: $scan_mode"
        ));
        
        try {
            // Scan WordPress core files
            $this->scan_wordpress_core();
            
            // Scan themes
            $this->scan_directory(get_theme_root(), 'themes');
            
            // Scan plugins
            $this->scan_directory(WP_PLUGIN_DIR, 'plugins');
            
            // Scan uploads (high priority for malware)
            $this->scan_directory(wp_upload_dir()['basedir'], 'uploads');
            
            // Scan wp-content root
            $this->scan_directory(WP_CONTENT_DIR, 'wp-content', array('themes', 'plugins', 'uploads'));
            
            // Check security configurations
            $this->check_security_configurations();
            
            // Update scan results
            $end_time = microtime(true);
            $scan_duration = round($end_time - $start_time, 2);
            
            $status = 'safe';
            if ($this->scan_results['threats_found'] > 0) {
                $status = $this->scan_results['threats_found'] > 5 ? 'critical' : 'warning';
            }
            
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'sentinelwp_scans',
                array(
                    'issues_found' => $this->scan_results['threats_found'],
                    'status' => $status,
                    'scan_duration' => $scan_duration,
                    'files_scanned' => $this->scan_results['files_scanned']
                ),
                array('id' => $scan_id),
                array('%d', '%s', '%d', '%d'),
                array('%d')
            );
            
            // Log scan completion
            $this->database->insert_log(array(
                'action' => 'scan_completed',
                'details' => sprintf(
                    'Scan completed. Files: %d, Issues: %d, Duration: %ds',
                    $this->scan_results['files_scanned'],
                    $this->scan_results['threats_found'],
                    $scan_duration
                )
            ));
            
            return $this->scan_results;
            
        } catch (Exception $e) {
            // Log error
            $this->database->insert_log(array(
                'action' => 'scan_error',
                'details' => 'Scan failed: ' . $e->getMessage()
            ));
            
            // Update scan status to error
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'sentinelwp_scans',
                array('status' => 'error'),
                array('id' => $scan_id),
                array('%s'),
                array('%d')
            );
            
            return false;
        }
    }
    
    /**
     * Get appropriate scan mode
     */
    private function get_scan_mode() {
        if (isset($this->system_status['scan_engine_mode'])) {
            return $this->system_status['scan_engine_mode'];
        }
        return 'heuristic';
    }
    
    /**
     * Scan WordPress core files
     */
    private function scan_wordpress_core() {
        if ($this->get_scan_mode() === 'clamav') {
            $this->clamav_scan_directory(ABSPATH, 'wordpress_core');
        } else {
            $this->heuristic_scan_directory(ABSPATH, 'wordpress_core', array('wp-content'));
            $this->check_wordpress_integrity();
        }
    }
    
    /**
     * Scan directory with appropriate method
     */
    private function scan_directory($directory, $context = 'general', $exclude = array()) {
        if (!is_dir($directory)) {
            return;
        }
        
        if ($this->get_scan_mode() === 'clamav') {
            $this->clamav_scan_directory($directory, $context);
        } else {
            $this->heuristic_scan_directory($directory, $context, $exclude);
        }
    }
    
    /**
     * ClamAV scan directory
     */
    private function clamav_scan_directory($directory, $context) {
        if (!function_exists('shell_exec')) {
            return;
        }
        
        $escaped_dir = escapeshellarg($directory);
        
        // Try clamdscan first (faster)
        $command = "clamdscan --multiscan --fdpass $escaped_dir 2>&1";
        $output = shell_exec($command);
        
        if (empty($output) || strpos($output, 'ERROR') !== false) {
            // Fallback to clamscan
            $command = "clamscan -r --bell -i $escaped_dir 2>&1";
            $output = shell_exec($command);
        }
        
        if (!empty($output)) {
            $this->parse_clamav_output($output, $context);
        }
    }
    
    /**
     * Parse ClamAV output
     */
    private function parse_clamav_output($output, $context) {
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // Check for infected files
            if (preg_match('/^(.+?):\s*(.+?)\s+FOUND$/', $line, $matches)) {
                $file_path = trim($matches[1]);
                $signature = trim($matches[2]);
                
                $this->add_security_issue(array(
                    'file_path' => $file_path,
                    'issue_type' => 'malware_detected',
                    'description' => "ClamAV detected: $signature",
                    'severity' => 'high',
                    'recommendation' => 'Remove or quarantine this file immediately.',
                    'context' => $context
                ));
            }
        }
    }
    
    /**
     * Heuristic scan directory
     */
    private function heuristic_scan_directory($directory, $context, $exclude = array()) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $file_path = $file->getPathname();
            $relative_path = str_replace($directory, '', $file_path);
            
            // Skip excluded directories
            $skip = false;
            foreach ($exclude as $exclude_dir) {
                if (strpos($relative_path, $exclude_dir) === 1) { // 1 because of leading slash
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            
            $this->scan_results['files_scanned']++;
            
            // Check file extension
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, $this->scan_extensions)) {
                continue;
            }
            
            // Scan file content
            $this->heuristic_scan_file($file_path, $context);
            
            // Check for suspicious file locations
            $this->check_suspicious_file_location($file_path, $context);
        }
    }
    
    /**
     * Heuristic scan single file
     */
    private function heuristic_scan_file($file_path, $context) {
        $content = file_get_contents($file_path);
        if ($content === false) {
            return;
        }
        
        $file_size = filesize($file_path);
        $issues = array();
        
        // Check for suspicious patterns
        foreach ($this->suspicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $issues[] = array(
                    'type' => 'suspicious_code',
                    'description' => 'Suspicious code pattern detected',
                    'severity' => 'medium'
                );
            }
        }
        
        // Check for dangerous functions
        foreach ($this->dangerous_functions as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $content)) {
                $issues[] = array(
                    'type' => 'dangerous_function',
                    'description' => "Dangerous function detected: $func",
                    'severity' => 'medium'
                );
            }
        }
        
        // Check for base64 encoded content (potential obfuscation)
        if (preg_match_all('/[a-zA-Z0-9+\/]{50,}={0,2}/', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $decoded = base64_decode($match, true);
                if ($decoded !== false && $this->is_suspicious_decoded_content($decoded)) {
                    $issues[] = array(
                        'type' => 'obfuscated_code',
                        'description' => 'Base64 encoded suspicious content detected',
                        'severity' => 'high'
                    );
                    break;
                }
            }
        }
        
        // Check file size anomalies
        if ($file_size > 1024 * 1024) { // Files larger than 1MB
            $extension = pathinfo($file_path, PATHINFO_EXTENSION);
            if (in_array($extension, array('php', 'js', 'css', 'txt'))) {
                $issues[] = array(
                    'type' => 'size_anomaly',
                    'description' => 'Unusually large file size: ' . $this->format_file_size($file_size),
                    'severity' => 'low'
                );
            }
        }
        
        // Add issues to scan results
        foreach ($issues as $issue) {
            $this->add_security_issue(array(
                'file_path' => $file_path,
                'issue_type' => $issue['type'],
                'description' => $issue['description'],
                'severity' => $issue['severity'],
                'recommendation' => $this->get_recommendation_for_issue($issue['type']),
                'context' => $context
            ));
        }
    }
    
    /**
     * Check if decoded content is suspicious
     */
    private function is_suspicious_decoded_content($content) {
        $suspicious_keywords = array(
            'eval(',
            'shell_exec(',
            'system(',
            'exec(',
            'passthru(',
            'file_get_contents(',
            'curl_exec(',
            'base64_decode(',
            '$_POST',
            '$_GET',
            '$_REQUEST',
            'assert('
        );
        
        foreach ($suspicious_keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check suspicious file locations
     */
    private function check_suspicious_file_location($file_path, $context) {
        $filename = basename($file_path);
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // PHP files in uploads directory
        if ($context === 'uploads' && $extension === 'php') {
            $this->add_security_issue(array(
                'file_path' => $file_path,
                'issue_type' => 'suspicious_location',
                'description' => 'PHP file found in uploads directory',
                'severity' => 'high',
                'recommendation' => 'PHP files should not exist in uploads directory. Remove or investigate.',
                'context' => $context
            ));
        }
        
        // Random filename patterns
        if (preg_match('/^[a-f0-9]{32}\.php$/i', $filename) || preg_match('/^[a-zA-Z0-9]{20,}\.php$/i', $filename)) {
            $this->add_security_issue(array(
                'file_path' => $file_path,
                'issue_type' => 'suspicious_filename',
                'description' => 'File has suspicious random-looking name',
                'severity' => 'medium',
                'recommendation' => 'Investigate file contents and remove if malicious.',
                'context' => $context
            ));
        }
        
        // Hidden files
        if (strpos($filename, '.') === 0 && $filename !== '.htaccess') {
            $this->add_security_issue(array(
                'file_path' => $file_path,
                'issue_type' => 'hidden_file',
                'description' => 'Hidden file detected',
                'severity' => 'low',
                'recommendation' => 'Review hidden file contents for security.',
                'context' => $context
            ));
        }
    }
    
    /**
     * Check WordPress core integrity
     */
    private function check_wordpress_integrity() {
        global $wp_version;
        
        // Get checksums from WordPress API
        $checksums_url = "https://api.wordpress.org/core/checksums/1.0/?version={$wp_version}";
        $response = wp_remote_get($checksums_url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $checksums_data = json_decode($body, true);
        
        if (!isset($checksums_data['checksums'])) {
            return;
        }
        
        $checksums = $checksums_data['checksums'];
        
        foreach ($checksums as $file => $expected_hash) {
            $file_path = ABSPATH . $file;
            
            if (!file_exists($file_path)) {
                $this->add_security_issue(array(
                    'file_path' => $file_path,
                    'issue_type' => 'missing_core_file',
                    'description' => 'WordPress core file is missing',
                    'severity' => 'medium',
                    'recommendation' => 'Restore missing WordPress core file.',
                    'context' => 'wordpress_core'
                ));
                continue;
            }
            
            $actual_hash = md5_file($file_path);
            if ($actual_hash !== $expected_hash) {
                $this->add_security_issue(array(
                    'file_path' => $file_path,
                    'issue_type' => 'modified_core_file',
                    'description' => 'WordPress core file has been modified',
                    'severity' => 'high',
                    'recommendation' => 'Restore original WordPress core file or investigate modifications.',
                    'context' => 'wordpress_core'
                ));
            }
        }
    }
    
    /**
     * Check security configurations
     */
    private function check_security_configurations() {
        // Check wp-config.php permissions
        $wp_config_path = ABSPATH . 'wp-config.php';
        if (file_exists($wp_config_path)) {
            $perms = fileperms($wp_config_path) & 0777;
            if ($perms > 0600) {
                $this->add_security_issue(array(
                    'file_path' => $wp_config_path,
                    'issue_type' => 'file_permissions',
                    'description' => sprintf('wp-config.php has insecure permissions: %o', $perms),
                    'severity' => 'medium',
                    'recommendation' => 'Set wp-config.php permissions to 600.',
                    'context' => 'configuration'
                ));
            }
        }
        
        // Check if XML-RPC is enabled
        if (!get_option('xmlrpc_enabled', true)) {
            // XML-RPC is disabled, which is good
        } else {
            $this->add_security_issue(array(
                'file_path' => ABSPATH . 'xmlrpc.php',
                'issue_type' => 'xmlrpc_enabled',
                'description' => 'XML-RPC is enabled and may pose security risk',
                'severity' => 'low',
                'recommendation' => 'Disable XML-RPC if not needed.',
                'context' => 'configuration'
            ));
        }
        
        // Check if file editing is enabled
        if (!defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT) {
            $this->add_security_issue(array(
                'file_path' => $wp_config_path,
                'issue_type' => 'file_editing_enabled',
                'description' => 'WordPress file editing is enabled in admin',
                'severity' => 'medium',
                'recommendation' => 'Add define(\'DISALLOW_FILE_EDIT\', true); to wp-config.php',
                'context' => 'configuration'
            ));
        }
        
        // Check debug mode
        if (defined('WP_DEBUG') && WP_DEBUG && (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG)) {
            $this->add_security_issue(array(
                'file_path' => $wp_config_path,
                'issue_type' => 'debug_mode_public',
                'description' => 'Debug mode is enabled without log file',
                'severity' => 'low',
                'recommendation' => 'Disable debug mode or enable debug logging.',
                'context' => 'configuration'
            ));
        }
    }
    
    /**
     * Add security issue to scan results
     */
    private function add_security_issue($issue_data) {
        $issue_id = $this->database->insert_issue(array_merge($issue_data, array(
            'scan_id' => $this->scan_results['scan_id']
        )));
        
        if ($issue_id) {
            $this->scan_results['issues'][] = $issue_data;
            $this->scan_results['threats_found']++;
        }
    }
    
    /**
     * Get recommendation for issue type
     */
    private function get_recommendation_for_issue($issue_type) {
        $recommendations = array(
            'suspicious_code' => 'Review and remove suspicious code patterns.',
            'dangerous_function' => 'Verify if dangerous function usage is legitimate.',
            'obfuscated_code' => 'Decode and analyze obfuscated content.',
            'size_anomaly' => 'Check if large file size is expected.',
            'suspicious_location' => 'Move file to appropriate location or remove.',
            'suspicious_filename' => 'Rename file or remove if malicious.',
            'hidden_file' => 'Review hidden file necessity and contents.'
        );
        
        return isset($recommendations[$issue_type]) ? $recommendations[$issue_type] : 'Investigate and take appropriate action.';
    }
    
    /**
     * Format file size
     */
    private function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * AJAX handler for running scan
     */
    public function ajax_run_scan() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $result = $this->run_full_scan();
        
        if ($result && is_array($result)) {
            // Check if result contains an error (from database validation failure)
            if (isset($result['success']) && $result['success'] === false) {
                wp_send_json_error(array(
                    'message' => $result['error'],
                    'type' => 'database_error'
                ));
                return;
            }
            
            // Successful scan
            wp_send_json_success(array(
                'message' => 'Scan completed successfully',
                'scan_id' => $result['scan_id'],
                'files_scanned' => $result['files_scanned'],
                'threats_found' => $result['threats_found']
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Scan failed - unexpected error occurred',
                'type' => 'general_error'
            ));
        }
    }
    
    /**
     * AJAX handler for scan progress
     */
    public function ajax_scan_progress() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // This would be used for real-time progress updates in a more advanced implementation
        wp_send_json_success(array(
            'progress' => 50,
            'status' => 'Scanning...',
            'files_scanned' => $this->scan_results['files_scanned'] ?? 0
        ));
    }
    
    /**
     * Validate database schema to ensure all required columns exist
     */
    private function validate_database_schema() {
        global $wpdb;
        
        // Check if context column exists in issues table
        $table_issues = $wpdb->prefix . 'sentinelwp_issues';
        $context_column_exists = $wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM {$table_issues} LIKE %s", 'context')
        );
        
        if (empty($context_column_exists)) {
            SentinelWP_Logger::warning('Missing context column in issues table', array(
                'table' => $table_issues
            ));
            return false;
        }
        
        SentinelWP_Logger::info('Database schema validation passed');
        return true;
    }
}
