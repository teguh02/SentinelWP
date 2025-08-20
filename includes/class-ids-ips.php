<?php
/**
 * SentinelWP IDS/IPS Class
 * 
 * Intrusion Detection System (IDS) and Intrusion Prevention System (IPS)
 * for WordPress security monitoring and protection
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_IDS_IPS {
    
    private static $instance = null;
    private $log_file;
    private $blocked_ips_option = 'sentinelwp_blocked_ips';
    
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
    public function __construct() {
        $this->log_file = SENTINELWP_PLUGIN_PATH . 'logs/sentinelwp-ids.log';
        
        // Add comprehensive logging for initialization
        if (class_exists('SentinelWP_Logger')) {
            SentinelWP_Logger::info('IDS/IPS system initializing', array(
                'log_file' => $this->log_file,
                'log_file_exists' => file_exists($this->log_file),
                'log_dir_writable' => is_writable(dirname($this->log_file))
            ));
        }
        
        $this->init_hooks();
        $this->ensure_log_directory();
        $this->check_database_tables();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if current IP is blocked
        add_action('init', array($this, 'check_blocked_ip'), 1);
        
        // Login security hooks
        add_action('wp_login_failed', array($this, 'detect_brute_force'));
        
        // Request inspection hooks
        add_action('parse_request', array($this, 'inspect_request'));
        
        // XML-RPC protection
        add_filter('xmlrpc_enabled', array($this, 'check_xmlrpc_flood'));
        add_action('xmlrpc_call', array($this, 'detect_xmlrpc_flood'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_sentinelwp_unblock_ip', array($this, 'ajax_unblock_ip'));
        add_action('wp_ajax_sentinelwp_clear_ids_logs', array($this, 'ajax_clear_ids_logs'));
        add_action('wp_ajax_sentinelwp_get_blocked_ips', array($this, 'ajax_get_blocked_ips'));
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Create index.php for security
        $index_file = $log_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }
    
    /**
     * Check database tables for logging and monitoring
     */
    private function check_database_tables() {
        try {
            global $wpdb;
            
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::debug('Checking database tables for IDS/IPS system');
            }
            
            // Check if notifications table exists
            $notifications_table = $wpdb->prefix . 'sentinelwp_notifications';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$notifications_table'") == $notifications_table;
            
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::debug('Database table check', array(
                    'notifications_table' => $notifications_table,
                    'exists' => $table_exists,
                    'wpdb_ready' => !empty($wpdb)
                ));
            }
            
            if (!$table_exists) {
                if (class_exists('SentinelWP_Logger')) {
                    SentinelWP_Logger::warning('Notifications table does not exist', array(
                        'table_name' => $notifications_table
                    ));
                }
            }
            
        } catch (Exception $e) {
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::error('Database table check failed', array(
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }
        }
    }
    
    /**
     * Check if current IP is blocked
     */
    public function check_blocked_ip() {
        if (!$this->is_ips_enabled()) {
            return;
        }
        
        $client_ip = $this->get_client_ip();
        $blocked_key = "sentinelwp_blocked_ip_" . md5($client_ip);
        
        if (get_transient($blocked_key)) {
            $this->block_access();
        }
    }
    
    /**
     * Detect brute force login attempts
     */
    public function detect_brute_force($username) {
        if (!$this->is_ids_enabled()) {
            return;
        }
        
        $client_ip = $this->get_client_ip();
        $attempt_key = "sentinelwp_login_attempts_" . md5($client_ip);
        $attempts = get_transient($attempt_key) ?: 0;
        $attempts++;
        
        // Store attempt count for 5 minutes
        set_transient($attempt_key, $attempts, 5 * MINUTE_IN_SECONDS);
        
        $attack_data = array(
            'ip' => $client_ip,
            'attack_type' => 'brute_force_login',
            'payload' => 'Failed login attempt for username: ' . sanitize_text_field($username),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'time' => current_time('mysql'),
            'timestamp' => time()
        );
        
        $this->log_intrusion($attack_data);
        
        // Block IP if more than 5 attempts in 5 minutes
        if ($attempts >= 5 && $this->is_ips_enabled()) {
            $this->block_ip($client_ip, 'brute_force_login');
        }
        
        // Trigger action for notifications
        do_action('sentinelwp_intrusion_detected', $attack_data);
    }
    
    /**
     * Inspect incoming requests for malicious patterns
     */
    public function inspect_request($wp) {
        if (!$this->is_ids_enabled()) {
            return;
        }
        
        $client_ip = $this->get_client_ip();
        $request_data = array_merge($_GET, $_POST);
        $request_uri = sanitize_text_field($_SERVER['REQUEST_URI'] ?? '');
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Check for SQL injection patterns
        if ($this->detect_sql_injection($request_data, $request_uri)) {
            $attack_data = array(
                'ip' => $client_ip,
                'attack_type' => 'sql_injection',
                'payload' => 'Suspicious SQL patterns detected in: ' . $request_uri,
                'user_agent' => $user_agent,
                'time' => current_time('mysql'),
                'timestamp' => time()
            );
            
            $this->log_intrusion($attack_data);
            
            if ($this->is_ips_enabled()) {
                $this->block_ip($client_ip, 'sql_injection');
            }
            
            do_action('sentinelwp_intrusion_detected', $attack_data);
            return;
        }
        
        // Check for XSS patterns
        if ($this->detect_xss($request_data, $request_uri)) {
            $attack_data = array(
                'ip' => $client_ip,
                'attack_type' => 'xss_attempt',
                'payload' => 'Suspicious XSS patterns detected in: ' . $request_uri,
                'user_agent' => $user_agent,
                'time' => current_time('mysql'),
                'timestamp' => time()
            );
            
            $this->log_intrusion($attack_data);
            
            if ($this->is_ips_enabled()) {
                $this->block_ip($client_ip, 'xss_attempt');
            }
            
            do_action('sentinelwp_intrusion_detected', $attack_data);
        }
    }
    
    /**
     * Detect XML-RPC flood attacks
     */
    public function detect_xmlrpc_flood($call) {
        if (!$this->is_ids_enabled()) {
            return;
        }
        
        $client_ip = $this->get_client_ip();
        $xmlrpc_key = "sentinelwp_xmlrpc_calls_" . md5($client_ip);
        $calls = get_transient($xmlrpc_key) ?: 0;
        $calls++;
        
        // Store call count for 1 minute
        set_transient($xmlrpc_key, $calls, MINUTE_IN_SECONDS);
        
        // If more than 10 calls per minute, consider it flooding
        if ($calls > 10) {
            $attack_data = array(
                'ip' => $client_ip,
                'attack_type' => 'xmlrpc_flood',
                'payload' => 'XML-RPC flooding detected: ' . $calls . ' calls in 1 minute',
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'time' => current_time('mysql'),
                'timestamp' => time()
            );
            
            $this->log_intrusion($attack_data);
            
            if ($this->is_ips_enabled()) {
                $this->block_ip($client_ip, 'xmlrpc_flood');
            }
            
            do_action('sentinelwp_intrusion_detected', $attack_data);
        }
    }
    
    /**
     * Check XML-RPC flood before processing
     */
    public function check_xmlrpc_flood($enabled) {
        if (!$this->is_ids_enabled()) {
            return $enabled;
        }
        
        $client_ip = $this->get_client_ip();
        $blocked_key = "sentinelwp_blocked_ip_" . md5($client_ip);
        
        if (get_transient($blocked_key)) {
            return false;
        }
        
        return $enabled;
    }
    
    /**
     * Detect SQL injection patterns
     */
    private function detect_sql_injection($data, $uri = '') {
        $sql_patterns = array(
            '/union\s+select/i',
            '/select\s+.*\s+from/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/drop\s+table/i',
            '/update\s+.*\s+set/i',
            '/exec\s*\(/i',
            '/script\s*>/i',
            '/\'\s*or\s*\'/i',
            '/\'\s*and\s*\'/i',
            '/\'\s*;\s*--/i',
            '/benchmark\s*\(/i',
            '/sleep\s*\(/i',
            '/load_file\s*\(/i'
        );
        
        $all_data = array_merge($data, array($uri));
        
        foreach ($all_data as $value) {
            if (is_string($value)) {
                foreach ($sql_patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Detect XSS patterns
     */
    private function detect_xss($data, $uri = '') {
        $xss_patterns = array(
            '/<script[^>]*>/i',
            '/<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/<link[^>]*>/i',
            '/<meta[^>]*>/i',
            '/expression\s*\(/i',
            '/vbscript:/i',
            '/data:text\/html/i'
        );
        
        $all_data = array_merge($data, array($uri));
        
        foreach ($all_data as $value) {
            if (is_string($value)) {
                foreach ($xss_patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Block an IP address
     */
    private function block_ip($ip, $reason) {
        $block_duration = $this->get_block_duration();
        $blocked_key = "sentinelwp_blocked_ip_" . md5($ip);
        
        $block_data = array(
            'ip' => $ip,
            'reason' => $reason,
            'blocked_at' => time(),
            'expires_at' => time() + $block_duration
        );
        
        set_transient($blocked_key, $block_data, $block_duration);
        
        // Log the IP block
        $this->log_intrusion(array(
            'ip' => $ip,
            'attack_type' => 'ip_blocked',
            'payload' => 'IP blocked for ' . $reason . ' (duration: ' . ($block_duration / 60) . ' minutes)',
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'time' => current_time('mysql'),
            'timestamp' => time()
        ));
    }
    
    /**
     * Block access for current request
     */
    private function block_access() {
        status_header(403);
        wp_die(
            '🚫 Access denied. Your IP has been temporarily blocked by SentinelWP.',
            'Access Denied',
            array('response' => 403)
        );
    }
    
    /**
     * Log intrusion attempt
     */
    private function log_intrusion($data) {
        if (!$this->is_ids_enabled()) {
            return;
        }
        
        $log_entry = json_encode($data) . "\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get block duration in seconds
     */
    private function get_block_duration() {
        $duration_minutes = get_option('sentinelwp_ids_block_duration', 10);
        return $duration_minutes * MINUTE_IN_SECONDS;
    }
    
    /**
     * Check if IDS is enabled
     */
    private function is_ids_enabled() {
        return get_option('sentinelwp_ids_enabled', true);
    }
    
    /**
     * Check if IPS is enabled
     */
    private function is_ips_enabled() {
        return get_option('sentinelwp_ips_enabled', true);
    }
    
    /**
     * Get intrusion logs
     */
    public function get_intrusion_logs($limit = 100) {
        try {
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::debug('Starting get_intrusion_logs', array(
                    'log_file' => $this->log_file,
                    'limit' => $limit,
                    'file_exists' => file_exists($this->log_file)
                ));
            }
            
            if (!file_exists($this->log_file)) {
                if (class_exists('SentinelWP_Logger')) {
                    SentinelWP_Logger::warning('Intrusion log file does not exist', array(
                        'log_file' => $this->log_file
                    ));
                }
                return array();
            }
            
            $file_size = filesize($this->log_file);
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::debug('Intrusion log file info', array(
                    'file_size' => $file_size,
                    'readable' => is_readable($this->log_file)
                ));
            }
            
            $logs = array();
            $file_lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            if ($file_lines === false) {
                if (class_exists('SentinelWP_Logger')) {
                    SentinelWP_Logger::error('Failed to read intrusion log file', array(
                        'log_file' => $this->log_file,
                        'last_error' => error_get_last()
                    ));
                }
                return array();
            }
            
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::debug('Read intrusion log file', array(
                    'total_lines' => count($file_lines),
                    'requested_limit' => $limit
                ));
            }
            
            if ($file_lines) {
                // Get last N lines
                $lines = array_slice($file_lines, -$limit);
                $lines = array_reverse($lines);
                
                foreach ($lines as $line_num => $line) {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        $logs[] = $decoded;
                    } else {
                        if (class_exists('SentinelWP_Logger')) {
                            SentinelWP_Logger::debug('Failed to decode log line', array(
                                'line_num' => $line_num,
                                'line_preview' => substr($line, 0, 100),
                                'json_error' => json_last_error_msg()
                            ));
                        }
                    }
                }
            }
            
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::info('Successfully retrieved intrusion logs', array(
                    'total_logs' => count($logs)
                ));
            }
            
            return $logs;
            
        } catch (Exception $e) {
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::error('Exception in get_intrusion_logs', array(
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }
            return array();
        }
    }
    
    /**
     * Get currently blocked IPs
     */
    public function get_blocked_ips() {
        try {
            global $wpdb;
            
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::debug('Starting get_blocked_ips operation', array(
                    'wpdb_available' => !empty($wpdb)
                ));
            }
            
            $blocked_ips = array();
            $transient_prefix = '_transient_sentinelwp_blocked_ip_';
            
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::debug('Preparing database query', array(
                    'table' => $wpdb->options,
                    'prefix' => $transient_prefix
                ));
            }
            
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                $transient_prefix . '%'
            ));
            
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::debug('Database query completed', array(
                    'results_count' => count($results),
                    'wpdb_last_error' => $wpdb->last_error
                ));
            }
            
            foreach ($results as $result) {
                try {
                    $data = maybe_unserialize($result->option_value);
                    if (is_array($data) && isset($data['ip'])) {
                        $blocked_ips[] = $data;
                    }
                } catch (Exception $e) {
                    if (class_exists('SentinelWP_Logger')) {
                        SentinelWP_Logger::warning('Failed to unserialize blocked IP data', array(
                            'option_name' => $result->option_name,
                            'error' => $e->getMessage()
                        ));
                    }
                }
            }
            
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::info('Successfully retrieved blocked IPs', array(
                    'total_blocked_ips' => count($blocked_ips)
                ));
            }
            
            return $blocked_ips;
            
        } catch (Exception $e) {
            if (class_exists('SentinelWP_Logger')) {
                SentinelWP_Logger::error('Exception in get_blocked_ips', array(
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }
            return array();
        }
    }
    
    /**
     * Unblock an IP address
     */
    public function unblock_ip($ip) {
        $blocked_key = "sentinelwp_blocked_ip_" . md5($ip);
        return delete_transient($blocked_key);
    }
    
    /**
     * Clear intrusion logs
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true;
    }
    
    /**
     * AJAX handler to unblock IP
     */
    public function ajax_unblock_ip() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $ip = sanitize_text_field($_POST['ip'] ?? '');
        
        if (empty($ip)) {
            wp_send_json_error('Invalid IP address');
        }
        
        $result = $this->unblock_ip($ip);
        
        if ($result) {
            wp_send_json_success('IP unblocked successfully');
        } else {
            wp_send_json_error('Failed to unblock IP');
        }
    }
    
    /**
     * AJAX handler to clear logs
     */
    public function ajax_clear_ids_logs() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->clear_logs();
        
        if ($result) {
            wp_send_json_success('Logs cleared successfully');
        } else {
            wp_send_json_error('Failed to clear logs');
        }
    }
    
    /**
     * AJAX handler to get blocked IPs
     */
    public function ajax_get_blocked_ips() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $blocked_ips = $this->get_blocked_ips();
        wp_send_json_success($blocked_ips);
    }
}
