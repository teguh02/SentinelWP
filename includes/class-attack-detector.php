<?php
/**
 * SentinelWP Attack Detector Class
 * 
 * Monitors WordPress for suspicious activities and detects attacks
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_Attack_Detector {
    
    private static $instance = null;
    private $database;
    private $logger;
    private $notifications;
    
    // Attack thresholds
    const BRUTE_FORCE_THRESHOLD = 10; // Failed attempts in 1 minute
    const XMLRPC_THRESHOLD = 50; // Requests in 1 minute
    const BRUTE_FORCE_WINDOW = 60; // 1 minute in seconds
    const XMLRPC_WINDOW = 60; // 1 minute in seconds
    
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
        $this->logger = SentinelWP_Logger::instance();
        $this->notifications = SentinelWP_Notifications::instance();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Brute force detection
        add_action('wp_login_failed', array($this, 'track_failed_login'), 10, 1);
        
        // XML-RPC abuse detection
        add_action('init', array($this, 'monitor_xmlrpc_requests'));
        
        // File upload monitoring
        add_action('wp_handle_upload', array($this, 'monitor_file_uploads'), 10, 2);
        add_action('add_attachment', array($this, 'monitor_attachment_upload'));
        
        // Monitor direct file creation in uploads directory
        add_action('init', array($this, 'monitor_uploads_directory'));
    }
    
    /**
     * Track failed login attempts for brute force detection
     */
    public function track_failed_login($username) {
        $ip_address = $this->get_client_ip();
        $current_time = current_time('mysql');
        
        SentinelWP_Logger::info('Failed login attempt detected', array(
            'username' => $username,
            'ip_address' => $ip_address,
            'timestamp' => $current_time
        ));
        
        // Check for brute force pattern
        $this->check_brute_force_attack($ip_address, $username);
    }
    
    /**
     * Check if IP is performing brute force attack
     */
    private function check_brute_force_attack($ip_address, $username = '') {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        $time_window = date('Y-m-d H:i:s', strtotime('-' . self::BRUTE_FORCE_WINDOW . ' seconds'));
        
        // Count failed login attempts from this IP in the last minute
        $attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_notifications 
             WHERE event_type = 'brute_force_attempt' 
             AND ip_address = %s 
             AND created_at >= %s",
            $ip_address,
            $time_window
        ));
        
        // Log the attempt (even if below threshold)
        $this->log_attack_event(
            'brute_force_attempt',
            $ip_address,
            sprintf('Failed login attempt for username: %s', $username),
            'low'
        );
        
        // If threshold exceeded, mark as attack
        if ($attempts >= self::BRUTE_FORCE_THRESHOLD) {
            $this->trigger_attack_alert(
                'brute_force',
                $ip_address,
                sprintf('Brute force attack detected: %d failed login attempts in 1 minute', $attempts + 1),
                'high',
                array('username' => $username, 'attempts' => $attempts + 1)
            );
        }
    }
    
    /**
     * Monitor XML-RPC requests for abuse
     */
    public function monitor_xmlrpc_requests() {
        if (!is_admin() && $_SERVER['REQUEST_URI'] === '/xmlrpc.php') {
            $ip_address = $this->get_client_ip();
            
            SentinelWP_Logger::debug('XML-RPC request detected', array(
                'ip_address' => $ip_address,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ));
            
            $this->check_xmlrpc_abuse($ip_address);
        }
    }
    
    /**
     * Check for XML-RPC abuse patterns
     */
    private function check_xmlrpc_abuse($ip_address) {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        $time_window = date('Y-m-d H:i:s', strtotime('-' . self::XMLRPC_WINDOW . ' seconds'));
        
        // Count XML-RPC requests from this IP in the last minute
        $requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_notifications 
             WHERE event_type = 'xmlrpc_request' 
             AND ip_address = %s 
             AND created_at >= %s",
            $ip_address,
            $time_window
        ));
        
        // Log the request
        $this->log_attack_event(
            'xmlrpc_request',
            $ip_address,
            'XML-RPC request detected',
            'low'
        );
        
        // If threshold exceeded, mark as attack
        if ($requests >= self::XMLRPC_THRESHOLD) {
            $this->trigger_attack_alert(
                'xmlrpc_abuse',
                $ip_address,
                sprintf('XML-RPC abuse detected: %d requests in 1 minute', $requests + 1),
                'medium',
                array('requests' => $requests + 1)
            );
        }
    }
    
    /**
     * Monitor file uploads for malicious files
     */
    public function monitor_file_uploads($upload_data, $context) {
        if (!isset($upload_data['file'])) {
            return $upload_data;
        }
        
        $file_path = $upload_data['file'];
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Check for dangerous file extensions
        $dangerous_extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'pht', 'phar', 'phps');
        
        if (in_array($file_extension, $dangerous_extensions)) {
            $ip_address = $this->get_client_ip();
            
            $this->trigger_attack_alert(
                'malicious_upload',
                $ip_address,
                sprintf('Suspicious file upload detected: %s', basename($file_path)),
                'high',
                array(
                    'file_path' => $file_path,
                    'file_extension' => $file_extension,
                    'file_size' => filesize($file_path)
                )
            );
            
            // Optionally quarantine the file
            $this->quarantine_malicious_file($file_path);
        }
        
        return $upload_data;
    }
    
    /**
     * Monitor attachment uploads
     */
    public function monitor_attachment_upload($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if ($file_path) {
            $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            
            // Additional checks for attachments
            $suspicious_extensions = array('exe', 'bat', 'cmd', 'scr', 'com', 'pif', 'vbs', 'js');
            
            if (in_array($file_extension, $suspicious_extensions)) {
                $ip_address = $this->get_client_ip();
                
                $this->trigger_attack_alert(
                    'suspicious_attachment',
                    $ip_address,
                    sprintf('Suspicious attachment uploaded: %s (ID: %d)', basename($file_path), $attachment_id),
                    'medium',
                    array(
                        'attachment_id' => $attachment_id,
                        'file_path' => $file_path,
                        'file_extension' => $file_extension
                    )
                );
            }
        }
    }
    
    /**
     * Monitor uploads directory for directly created PHP files
     */
    public function monitor_uploads_directory() {
        // Only run this check occasionally to avoid performance issues
        if (rand(1, 100) !== 1) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        if (!is_dir($uploads_path)) {
            return;
        }
        
        // Check for recently created PHP files
        $php_files = glob($uploads_path . '/**/*.{php,phtml}', GLOB_BRACE);
        
        foreach ($php_files as $php_file) {
            $file_age = time() - filemtime($php_file);
            
            // If file was created in the last 5 minutes
            if ($file_age < 300) {
                $this->trigger_attack_alert(
                    'direct_php_creation',
                    'unknown',
                    sprintf('PHP file directly created in uploads directory: %s', basename($php_file)),
                    'critical',
                    array(
                        'file_path' => $php_file,
                        'file_age' => $file_age,
                        'file_size' => filesize($php_file)
                    )
                );
                
                // Immediately quarantine the file
                $this->quarantine_malicious_file($php_file);
            }
        }
    }
    
    /**
     * Log an attack event to the database
     */
    private function log_attack_event($event_type, $ip_address, $description, $severity) {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        
        $result = $wpdb->insert(
            $table_notifications,
            array(
                'event_type' => $event_type,
                'ip_address' => $ip_address,
                'description' => $description,
                'severity' => $severity,
                'status' => 'new',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            SentinelWP_Logger::error('Failed to log attack event', array(
                'event_type' => $event_type,
                'ip_address' => $ip_address,
                'error' => $wpdb->last_error
            ));
        }
    }
    
    /**
     * Trigger attack alert and notifications
     */
    private function trigger_attack_alert($event_type, $ip_address, $description, $severity, $additional_data = array()) {
        // Log the attack event
        $this->log_attack_event($event_type, $ip_address, $description, $severity);
        
        // Log to SentinelWP logger
        SentinelWP_Logger::warning('Attack detected', array(
            'event_type' => $event_type,
            'ip_address' => $ip_address,
            'description' => $description,
            'severity' => $severity,
            'additional_data' => $additional_data
        ));
        
        // Send notifications based on severity
        if (in_array($severity, array('high', 'critical'))) {
            // Send email notification
            $this->notifications->send_attack_notification($event_type, $ip_address, $description, $severity, $additional_data);
            
            // Log to attack log file
            $this->log_to_attack_file($event_type, $ip_address, $description, $severity);
            
            // Show admin notice for critical attacks
            if ($severity === 'critical') {
                $this->add_admin_notice($description, 'error');
            }
        }
    }
    
    /**
     * Log attack to dedicated attack log file
     */
    private function log_to_attack_file($event_type, $ip_address, $description, $severity) {
        $log_dir = SENTINELWP_PLUGIN_PATH . 'logs/';
        
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_file = $log_dir . 'attack-' . date('Y-m-d') . '.log';
        $timestamp = current_time('Y-m-d H:i:s');
        
        $log_entry = sprintf(
            "[%s] [%s] %s | IP: %s | Type: %s\n",
            $timestamp,
            strtoupper($severity),
            $description,
            $ip_address,
            $event_type
        );
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Add admin notice for critical attacks
     */
    private function add_admin_notice($message, $type = 'error') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible sentinelwp-attack-notice"><p><strong>SentinelWP Security Alert:</strong> %s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
    
    /**
     * Quarantine malicious file
     */
    private function quarantine_malicious_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $quarantine_dir = SENTINELWP_PLUGIN_PATH . 'quarantine/';
        
        if (!is_dir($quarantine_dir)) {
            wp_mkdir_p($quarantine_dir);
        }
        
        $quarantine_file = $quarantine_dir . basename($file_path) . '.quarantine';
        
        if (rename($file_path, $quarantine_file)) {
            SentinelWP_Logger::info('Malicious file quarantined', array(
                'original_path' => $file_path,
                'quarantine_path' => $quarantine_file
            ));
            
            return true;
        } else {
            SentinelWP_Logger::error('Failed to quarantine malicious file', array(
                'file_path' => $file_path,
                'quarantine_dir' => $quarantine_dir
            ));
            
            return false;
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get current attack status
     */
    public function get_attack_status() {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        $time_window = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        
        // Check for recent high/critical severity attacks
        $recent_attacks = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, ip_address, COUNT(*) as count 
             FROM $table_notifications 
             WHERE severity IN ('high', 'critical') 
             AND created_at >= %s 
             GROUP BY event_type, ip_address 
             ORDER BY count DESC",
            $time_window
        ));
        
        if (!empty($recent_attacks)) {
            return array(
                'under_attack' => true,
                'attacks' => $recent_attacks,
                'message' => $this->format_attack_status_message($recent_attacks)
            );
        }
        
        return array(
            'under_attack' => false,
            'attacks' => array(),
            'message' => 'No recent attacks detected'
        );
    }
    
    /**
     * Format attack status message
     */
    private function format_attack_status_message($attacks) {
        if (empty($attacks)) {
            return '';
        }
        
        $primary_attack = $attacks[0];
        
        switch ($primary_attack->event_type) {
            case 'brute_force':
                return sprintf('Brute force attack detected from IP %s', $primary_attack->ip_address);
            case 'xmlrpc_abuse':
                return sprintf('XML-RPC abuse detected from IP %s', $primary_attack->ip_address);
            case 'malicious_upload':
                return sprintf('Malicious file upload attempt from IP %s', $primary_attack->ip_address);
            default:
                return sprintf('Security threat detected from IP %s', $primary_attack->ip_address);
        }
    }
    
    /**
     * Get attack statistics
     */
    public function get_attack_stats($period = '24 hours') {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        $time_window = date('Y-m-d H:i:s', strtotime('-' . $period));
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_type,
                severity,
                COUNT(*) as count,
                COUNT(DISTINCT ip_address) as unique_ips
             FROM $table_notifications 
             WHERE created_at >= %s 
             GROUP BY event_type, severity 
             ORDER BY count DESC",
            $time_window
        ));
        
        return $stats;
    }
}
