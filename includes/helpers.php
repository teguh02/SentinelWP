<?php
/**
 * SentinelWP Helper Functions
 * 
 * Utility functions used throughout the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format file size in human readable format
 */
function sentinelwp_format_file_size($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Check if file extension is dangerous
 */
function sentinelwp_is_dangerous_extension($file_path) {
    $dangerous_extensions = array(
        'php', 'php3', 'php4', 'php5', 'phtml', 'phps',
        'js', 'asp', 'aspx', 'jsp', 'py', 'pl', 'cgi',
        'exe', 'bat', 'cmd', 'scr', 'vbs', 'sh'
    );
    
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($extension, $dangerous_extensions);
}

/**
 * Get WordPress core file checksums
 */
function sentinelwp_get_wp_checksums($version = null) {
    if (!$version) {
        global $wp_version;
        $version = $wp_version;
    }
    
    $checksums_url = "https://api.wordpress.org/core/checksums/1.0/?version={$version}";
    $response = wp_remote_get($checksums_url, array('timeout' => 30));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return isset($data['checksums']) ? $data['checksums'] : false;
}

/**
 * Check if file is in WordPress core
 */
function sentinelwp_is_wp_core_file($file_path) {
    $relative_path = str_replace(ABSPATH, '', $file_path);
    
    // Skip wp-content directory (not core)
    if (strpos($relative_path, 'wp-content/') === 0) {
        return false;
    }
    
    // Core directories
    $core_dirs = array(
        'wp-admin/',
        'wp-includes/'
    );
    
    foreach ($core_dirs as $dir) {
        if (strpos($relative_path, $dir) === 0) {
            return true;
        }
    }
    
    // Core files in root
    $core_files = array(
        'index.php', 'wp-activate.php', 'wp-blog-header.php',
        'wp-comments-post.php', 'wp-config-sample.php', 'wp-cron.php',
        'wp-links-opml.php', 'wp-load.php', 'wp-login.php',
        'wp-mail.php', 'wp-settings.php', 'wp-signup.php',
        'wp-trackback.php', 'xmlrpc.php'
    );
    
    return in_array($relative_path, $core_files);
}

/**
 * Sanitize file path for display
 */
function sentinelwp_sanitize_file_path($file_path) {
    // Remove ABSPATH for security
    $clean_path = str_replace(ABSPATH, '', $file_path);
    
    // Remove any ../ attempts
    $clean_path = str_replace('../', '', $clean_path);
    
    return $clean_path;
}

/**
 * Get file type icon
 */
function sentinelwp_get_file_icon($file_path) {
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    $icons = array(
        'php' => '🐘',
        'js' => '📜',
        'css' => '🎨',
        'html' => '📄',
        'htm' => '📄',
        'txt' => '📝',
        'htaccess' => '⚙️',
        'xml' => '📰',
        'json' => '📊'
    );
    
    return isset($icons[$extension]) ? $icons[$extension] : '📄';
}

/**
 * Generate secure random string
 */
function sentinelwp_generate_random_string($length = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    }
    
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    }
    
    // Fallback
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $string;
}

/**
 * Check if IP is valid
 */
function sentinelwp_is_valid_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/**
 * Get country from IP (basic implementation)
 */
function sentinelwp_get_country_from_ip($ip) {
    if (!sentinelwp_is_valid_ip($ip)) {
        return 'Unknown';
    }
    
    // This would require a GeoIP service or database
    // For now, return unknown
    return 'Unknown';
}

/**
 * Check if current user can manage security
 */
function sentinelwp_current_user_can_manage() {
    return current_user_can('manage_options') || current_user_can('administrator');
}

/**
 * Log security event
 */
function sentinelwp_log_event($action, $details = '', $ip = null) {
    if (!$ip) {
        $ip = sentinelwp_get_client_ip();
    }
    
    $database = SentinelWP_Database::instance();
    return $database->insert_log(array(
        'action' => $action,
        'details' => $details,
        'ip_address' => $ip
    ));
}

/**
 * Get client IP address
 */
function sentinelwp_get_client_ip() {
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
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}

/**
 * Check if request is from allowed IP
 */
function sentinelwp_is_ip_allowed($ip = null) {
    if (!$ip) {
        $ip = sentinelwp_get_client_ip();
    }
    
    $allowed_ips = get_option('sentinelwp_allowed_ips', array());
    
    if (empty($allowed_ips)) {
        return true; // No restrictions
    }
    
    return in_array($ip, $allowed_ips);
}

/**
 * Encrypt sensitive data
 */
function sentinelwp_encrypt($data, $key = null) {
    if (!$key) {
        $key = get_option('sentinelwp_encryption_key');
        if (!$key) {
            $key = sentinelwp_generate_random_string(64);
            update_option('sentinelwp_encryption_key', $key);
        }
    }
    
    if (function_exists('openssl_encrypt')) {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    return base64_encode($data); // Fallback (not secure)
}

/**
 * Decrypt sensitive data
 */
function sentinelwp_decrypt($encrypted_data, $key = null) {
    if (!$key) {
        $key = get_option('sentinelwp_encryption_key');
        if (!$key) {
            return false;
        }
    }
    
    $data = base64_decode($encrypted_data);
    
    if (function_exists('openssl_decrypt')) {
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    return $data; // Fallback
}

/**
 * Check if file has been modified recently
 */
function sentinelwp_file_modified_recently($file_path, $hours = 24) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $modified_time = filemtime($file_path);
    $threshold = time() - ($hours * 3600);
    
    return $modified_time > $threshold;
}

/**
 * Get file modification time in human readable format
 */
function sentinelwp_file_age($file_path) {
    if (!file_exists($file_path)) {
        return 'Unknown';
    }
    
    return human_time_diff(filemtime($file_path), current_time('timestamp'));
}

/**
 * Check if directory is writable with test file
 */
function sentinelwp_is_directory_writable($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $test_file = rtrim($dir, '/') . '/sentinelwp_write_test_' . time() . '.tmp';
    
    $handle = @fopen($test_file, 'w');
    if ($handle) {
        fclose($handle);
        unlink($test_file);
        return true;
    }
    
    return false;
}

/**
 * Get WordPress installation type
 */
function sentinelwp_get_wp_install_type() {
    if (is_multisite()) {
        return 'multisite';
    }
    
    if (defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE) {
        return 'multisite_ready';
    }
    
    return 'single_site';
}

/**
 * Get server information
 */
function sentinelwp_get_server_info() {
    return array(
        'php_version' => PHP_VERSION,
        'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
        'mysql_version' => function_exists('mysql_get_server_info') ? mysql_get_server_info() : 'Unknown',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    );
}

/**
 * Check system requirements
 */
function sentinelwp_check_requirements() {
    $requirements = array(
        'php_version' => array(
            'required' => '7.4',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4', '>=')
        ),
        'wordpress_version' => array(
            'required' => '5.0',
            'current' => get_bloginfo('version'),
            'status' => version_compare(get_bloginfo('version'), '5.0', '>=')
        ),
        'mysql_version' => array(
            'required' => '5.6',
            'current' => 'Unknown', // Would need database connection
            'status' => true // Assume OK for now
        )
    );
    
    return $requirements;
}

/**
 * Validate email address
 */
function sentinelwp_is_valid_email($email) {
    return is_email($email) !== false;
}

/**
 * Validate Telegram bot token format
 */
function sentinelwp_is_valid_telegram_token($token) {
    return preg_match('/^\d{8,10}:[a-zA-Z0-9_-]{35}$/', $token);
}

/**
 * Validate Telegram chat ID format
 */
function sentinelwp_is_valid_telegram_chat_id($chat_id) {
    return preg_match('/^-?\d+$/', $chat_id);
}

/**
 * Clean up temporary files
 */
function sentinelwp_cleanup_temp_files() {
    $temp_dir = wp_upload_dir()['basedir'] . '/sentinelwp-temp/';
    
    if (is_dir($temp_dir)) {
        $files = glob($temp_dir . '*');
        $cutoff = time() - (24 * 3600); // 24 hours ago
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

/**
 * Get plugin version
 */
function sentinelwp_get_version() {
    return defined('SENTINELWP_VERSION') ? SENTINELWP_VERSION : '1.0.0';
}

/**
 * Check for plugin updates
 */
function sentinelwp_check_for_updates() {
    // This would check for plugin updates from a remote server
    // For now, just return false (no updates available)
    return false;
}

/**
 * Format security score with color
 */
function sentinelwp_format_security_score($score) {
    if ($score >= 90) {
        return "<span style='color: #28a745; font-weight: bold;'>{$score}/100 (Excellent)</span>";
    } elseif ($score >= 80) {
        return "<span style='color: #28a745; font-weight: bold;'>{$score}/100 (Good)</span>";
    } elseif ($score >= 70) {
        return "<span style='color: #ffc107; font-weight: bold;'>{$score}/100 (Fair)</span>";
    } elseif ($score >= 60) {
        return "<span style='color: #fd7e14; font-weight: bold;'>{$score}/100 (Poor)</span>";
    } else {
        return "<span style='color: #dc3545; font-weight: bold;'>{$score}/100 (Critical)</span>";
    }
}

/**
 * Generate security report CSV
 */
function sentinelwp_generate_csv_report($data) {
    $csv = "Date,Scan ID,Files Scanned,Issues Found,Status,Duration\n";
    
    foreach ($data as $scan) {
        $csv .= sprintf(
            "%s,%d,%d,%d,%s,%d\n",
            $scan->scan_time,
            $scan->id,
            $scan->files_scanned,
            $scan->issues_found,
            $scan->status,
            $scan->scan_duration
        );
    }
    
    return $csv;
}

/**
 * Schedule cleanup tasks
 */
function sentinelwp_schedule_cleanup() {
    if (!wp_next_scheduled('sentinelwp_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'sentinelwp_daily_cleanup');
    }
}

/**
 * Perform daily cleanup
 */
function sentinelwp_daily_cleanup() {
    $database = SentinelWP_Database::instance();
    
    // Clean old logs (older than 30 days)
    $database->clean_old_logs(30);
    
    // Clean up temporary files
    sentinelwp_cleanup_temp_files();
    
    // Log cleanup
    sentinelwp_log_event('daily_cleanup_completed', 'Cleaned old logs and temporary files');
}

// Schedule cleanup on plugin activation
add_action('wp', 'sentinelwp_schedule_cleanup');
add_action('sentinelwp_daily_cleanup', 'sentinelwp_daily_cleanup');
