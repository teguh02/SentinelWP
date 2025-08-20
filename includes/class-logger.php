<?php
/**
 * SentinelWP Logger Class
 * 
 * Laravel-style logging system for debugging and monitoring
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_Logger {
    
    private static $instance = null;
    private $log_directory;
    private $log_levels = array(
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7
    );
    
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
        $this->log_directory = dirname(__FILE__) . '/../logs/';
        $this->ensure_log_directory();
    }
    
    /**
     * Ensure log directory exists and is protected
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_directory)) {
            // Use WordPress function if available, otherwise use native PHP
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($this->log_directory);
            } else {
                // Fallback to native PHP
                if (!mkdir($this->log_directory, 0755, true) && !is_dir($this->log_directory)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->log_directory));
                }
            }
            
            // Create .htaccess to protect log files
            $htaccess_content = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($this->log_directory . '.htaccess', $htaccess_content);
            
            // Create index.php to prevent directory listing
            $index_content = "<?php\n// Silence is golden.\n";
            file_put_contents($this->log_directory . 'index.php', $index_content);
        }
    }
    
    /**
     * Log an emergency message
     */
    public static function emergency($message, $context = array()) {
        return self::instance()->log('emergency', $message, $context);
    }
    
    /**
     * Log an alert message
     */
    public static function alert($message, $context = array()) {
        return self::instance()->log('alert', $message, $context);
    }
    
    /**
     * Log a critical message
     */
    public static function critical($message, $context = array()) {
        return self::instance()->log('critical', $message, $context);
    }
    
    /**
     * Log an error message
     */
    public static function error($message, $context = array()) {
        return self::instance()->log('error', $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public static function warning($message, $context = array()) {
        return self::instance()->log('warning', $message, $context);
    }
    
    /**
     * Log a notice message
     */
    public static function notice($message, $context = array()) {
        return self::instance()->log('notice', $message, $context);
    }
    
    /**
     * Log an info message
     */
    public static function info($message, $context = array()) {
        return self::instance()->log('info', $message, $context);
    }
    
    /**
     * Log a debug message
     */
    public static function debug($message, $context = array()) {
        return self::instance()->log('debug', $message, $context);
    }
    
    /**
     * Log a message with specified level
     */
    public function log($level, $message, $context = array()) {
        if (!isset($this->log_levels[$level])) {
            $level = 'info';
        }
        
        $timestamp = $this->get_current_time('Y-m-d H:i:s');
        $log_entry = $this->format_log_entry($timestamp, $level, $message, $context);
        
        // Write to daily log file
        $log_file = $this->get_log_file($level);
        return file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Format log entry
     */
    private function format_log_entry($timestamp, $level, $message, $context = array()) {
        $level_upper = strtoupper($level);
        $user_id = $this->get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        // Build context string
        $context_string = '';
        if (!empty($context)) {
            $context_string = ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        // Add trace information for errors and critical issues
        $trace_info = '';
        if (in_array($level, array('error', 'critical', 'emergency', 'alert'))) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            if (isset($backtrace[2])) {
                $caller = $backtrace[2];
                $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
                $line = isset($caller['line']) ? $caller['line'] : 'unknown';
                $function = isset($caller['function']) ? $caller['function'] : 'unknown';
                $trace_info = " | Called from: {$file}:{$line} in {$function}()";
            }
        }
        
        return "[{$timestamp}] {$level_upper}: {$message} | User: {$user_id} | IP: {$ip_address}{$context_string}{$trace_info}" . PHP_EOL;
    }
    
    /**
     * Get log file path for the current day and level
     */
    private function get_log_file($level = 'info') {
        $date = $this->get_current_time('Y-m-d');
        
        // Create level-specific log files for important levels
        if (in_array($level, array('error', 'critical', 'emergency', 'alert'))) {
            $filename = "sentinelwp-{$level}-{$date}.log";
        } else {
            $filename = "sentinelwp-{$date}.log";
        }
        
        return $this->log_directory . $filename;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
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
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Get current time - WordPress aware
     */
    private function get_current_time($format = 'Y-m-d H:i:s') {
        if (function_exists('current_time')) {
            return current_time($format);
        }
        
        // Fallback to native PHP
        return date($format);
    }
    
    /**
     * Get current user ID - WordPress aware
     */
    private function get_current_user_id() {
        if (function_exists('get_current_user_id')) {
            return get_current_user_id();
        }
        
        // Fallback for standalone usage
        return 0;
    }
    
    /**
     * Log AI recommendation generation attempt
     */
    public static function log_ai_generation($action, $data = array()) {
        $context = array_merge(array(
            'action' => $action,
            'timestamp' => self::instance()->get_current_time('c'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ), $data);
        
        self::info("AI Generation: {$action}", $context);
    }
    
    /**
     * Log API request
     */
    public static function log_api_request($provider, $endpoint, $request_data = array(), $response_data = array()) {
        $context = array(
            'provider' => $provider,
            'endpoint' => $endpoint,
            'request_size' => strlen(json_encode($request_data)),
            'response_size' => strlen(json_encode($response_data)),
            'timestamp' => self::instance()->get_current_time('c')
        );
        
        if (!empty($request_data)) {
            $context['request_preview'] = substr(json_encode($request_data), 0, 500);
        }
        
        if (!empty($response_data)) {
            $context['response_preview'] = substr(json_encode($response_data), 0, 500);
        }
        
        self::info("API Request to {$provider}", $context);
    }
    
    /**
     * Log API error
     */
    public static function log_api_error($provider, $error_message, $error_data = array()) {
        $context = array_merge(array(
            'provider' => $provider,
            'error' => $error_message,
            'timestamp' => self::instance()->get_current_time('c')
        ), $error_data);
        
        self::error("API Error from {$provider}: {$error_message}", $context);
    }
    
    /**
     * Log database operation
     */
    public static function log_database_operation($operation, $table, $data = array()) {
        $context = array(
            'operation' => $operation,
            'table' => $table,
            'data_preview' => substr(json_encode($data), 0, 200),
            'timestamp' => self::instance()->get_current_time('c')
        );
        
        self::debug("Database {$operation} on {$table}", $context);
    }
    
    /**
     * Clean old log files
     */
    public function clean_old_logs($days = 30) {
        $files = glob($this->log_directory . '*.log');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $deleted_count = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        if ($deleted_count > 0) {
            self::info("Cleaned {$deleted_count} old log files older than {$days} days");
        }
        
        return $deleted_count;
    }
    
    /**
     * Get recent log entries
     */
    public function get_recent_logs($level = null, $lines = 100) {
        $log_files = array();
        
        if ($level && in_array($level, array('error', 'critical', 'emergency', 'alert'))) {
            $date = $this->get_current_time('Y-m-d');
            $file = $this->log_directory . "sentinelwp-{$level}-{$date}.log";
            if (file_exists($file)) {
                $log_files[] = $file;
            }
        } else {
            // Get all log files for today
            $date = $this->get_current_time('Y-m-d');
            $pattern = $this->log_directory . "sentinelwp*{$date}.log";
            $log_files = glob($pattern);
        }
        
        $logs = array();
        foreach ($log_files as $file) {
            if (file_exists($file)) {
                $file_logs = $this->tail_file($file, $lines);
                $logs = array_merge($logs, $file_logs);
            }
        }
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($logs, 0, $lines);
    }
    
    /**
     * Read last N lines from a file
     */
    private function tail_file($file, $lines) {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return array();
        }
        
        $line_array = array();
        while (($line = fgets($handle)) !== false) {
            $line_array[] = $this->parse_log_line($line);
        }
        fclose($handle);
        
        return array_slice($line_array, -$lines);
    }
    
    /**
     * Parse a log line into components
     */
    private function parse_log_line($line) {
        if (preg_match('/^\[(.*?)\] (\w+): (.*?) \| User: (\d+) \| IP: (.*?)(\s\|.*)?$/', $line, $matches)) {
            return array(
                'timestamp' => $matches[1],
                'level' => strtolower($matches[2]),
                'message' => $matches[3],
                'user_id' => $matches[4],
                'ip_address' => $matches[5],
                'context' => isset($matches[6]) ? trim($matches[6], ' |') : '',
                'raw' => trim($line)
            );
        }
        
        return array(
            'timestamp' => $this->get_current_time('Y-m-d H:i:s'),
            'level' => 'unknown',
            'message' => trim($line),
            'user_id' => 0,
            'ip_address' => 'unknown',
            'context' => '',
            'raw' => trim($line)
        );
    }
    
    /**
     * Get log file paths for download
     */
    public function get_log_files() {
        $files = glob($this->log_directory . '*.log');
        $log_files = array();
        
        foreach ($files as $file) {
            $log_files[] = array(
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file)
            );
        }
        
        // Sort by modified time (newest first)
        usort($log_files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $log_files;
    }
}
