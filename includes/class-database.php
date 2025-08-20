<?php
/**
 * SentinelWP Database Class
 * 
 * Handles database operations for security scans, issues, logs, and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_Database {
    
    private static $instance = null;
    
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
     * Create database tables
     */
    public static function create_tables() {
        SentinelWP_Logger::info('Starting database table creation/migration');
        
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        SentinelWP_Logger::debug('Database charset and collation', array(
            'charset_collate' => $charset_collate
        ));
        
        // Table for security scans
        $table_scans = $wpdb->prefix . 'sentinelwp_scans';
        $sql_scans = "CREATE TABLE $table_scans (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_time datetime DEFAULT CURRENT_TIMESTAMP,
            scan_mode varchar(20) NOT NULL DEFAULT 'heuristic',
            issues_found int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'safe',
            scan_duration int(11) DEFAULT 0,
            files_scanned int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY scan_time (scan_time),
            KEY status (status)
        ) $charset_collate;";
        
        // Table for security issues
        $table_issues = $wpdb->prefix . 'sentinelwp_issues';
        $sql_issues = "CREATE TABLE $table_issues (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_id bigint(20) UNSIGNED NOT NULL,
            file_path text NOT NULL,
            issue_type varchar(50) NOT NULL,
            description text NOT NULL,
            severity varchar(20) NOT NULL DEFAULT 'medium',
            recommendation text,
            resolved tinyint(1) NOT NULL DEFAULT 0,
            isolated tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY scan_id (scan_id),
            KEY issue_type (issue_type),
            KEY severity (severity),
            KEY resolved (resolved),
            FOREIGN KEY (scan_id) REFERENCES $table_scans(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Table for security logs
        $table_logs = $wpdb->prefix . 'sentinelwp_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            log_time datetime DEFAULT CURRENT_TIMESTAMP,
            action varchar(100) NOT NULL,
            ip_address varchar(45),
            user_id bigint(20) UNSIGNED,
            details text,
            PRIMARY KEY (id),
            KEY log_time (log_time),
            KEY action (action),
            KEY ip_address (ip_address)
        ) $charset_collate;";
        
        // Table for security settings
        $table_settings = $wpdb->prefix . 'sentinelwp_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        // Table for AI recommendations
        $table_ai_recommendations = $wpdb->prefix . 'sentinelwp_ai_recommendations';
        $sql_ai_recommendations = "CREATE TABLE $table_ai_recommendations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            category varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            priority enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            recommendation_type enum('security','performance','maintenance','compliance') NOT NULL DEFAULT 'security',
            source enum('ai','system') NOT NULL DEFAULT 'ai',
            status enum('active','dismissed','implemented') NOT NULL DEFAULT 'active',
            confidence_score decimal(3,2) DEFAULT NULL,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY priority (priority),
            KEY status (status),
            KEY generated_at (generated_at)
        ) $charset_collate;";
        
        // Table for security notifications (attack detection)
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            ip_address varchar(45),
            description text NOT NULL,
            severity enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
            status enum('new','read','resolved') NOT NULL DEFAULT 'new',
            additional_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY ip_address (ip_address),
            KEY severity (severity),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables and log results
        $tables = array(
            'scans' => array('sql' => $sql_scans, 'name' => $table_scans),
            'issues' => array('sql' => $sql_issues, 'name' => $table_issues),
            'logs' => array('sql' => $sql_logs, 'name' => $table_logs),
            'settings' => array('sql' => $sql_settings, 'name' => $table_settings),
            'ai_recommendations' => array('sql' => $sql_ai_recommendations, 'name' => $table_ai_recommendations),
            'notifications' => array('sql' => $sql_notifications, 'name' => $table_notifications)
        );
        
        $results = array();
        foreach ($tables as $key => $table_info) {
            SentinelWP_Logger::debug("Creating table: {$table_info['name']}");
            
            $result = dbDelta($table_info['sql']);
            $results[$key] = $result;
            
            // Check if table exists after creation
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_info['name']
            ));
            
            if ($table_exists) {
                SentinelWP_Logger::info("Table created successfully: {$table_info['name']}");
            } else {
                SentinelWP_Logger::error("Failed to create table: {$table_info['name']}", array(
                    'dbdelta_result' => $result,
                    'last_error' => $wpdb->last_error
                ));
            }
        }
        
        // Log overall completion
        SentinelWP_Logger::info('Database table creation completed', array(
            'results' => $results,
            'tables_created' => array_keys($tables)
        ));
        
        return $results;
    }
    
    /**
     * Drop database tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'sentinelwp_issues',
            $wpdb->prefix . 'sentinelwp_scans',
            $wpdb->prefix . 'sentinelwp_logs',
            $wpdb->prefix . 'sentinelwp_settings',
            $wpdb->prefix . 'sentinelwp_ai_recommendations',
            $wpdb->prefix . 'sentinelwp_notifications'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Insert scan record
     */
    public function insert_scan($data) {
        global $wpdb;
        
        $defaults = array(
            'scan_time' => current_time('mysql'),
            'scan_mode' => 'heuristic',
            'issues_found' => 0,
            'status' => 'safe',
            'scan_duration' => 0,
            'files_scanned' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'sentinelwp_scans',
            $data,
            array('%s', '%s', '%d', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Insert security issue
     */
    public function insert_issue($data) {
        global $wpdb;
        
        $defaults = array(
            'severity' => 'medium',
            'resolved' => 0,
            'isolated' => 0,
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'sentinelwp_issues',
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Insert log entry
     */
    public function insert_log($data) {
        global $wpdb;
        
        $defaults = array(
            'log_time' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_id' => get_current_user_id()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'sentinelwp_logs',
            $data,
            array('%s', '%s', '%s', '%d', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }
    
    /**
     * Get latest scans
     */
    public function get_latest_scans($limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sentinelwp_scans';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY scan_time DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Get scan by ID
     */
    public function get_scan($scan_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sentinelwp_scans';
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $scan_id)
        );
    }
    
    /**
     * Get issues by scan ID
     */
    public function get_issues_by_scan($scan_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sentinelwp_issues';
        
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE scan_id = %d ORDER BY severity DESC, created_at DESC", $scan_id)
        );
    }
    
    /**
     * Get unresolved issues
     */
    public function get_unresolved_issues() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sentinelwp_issues';
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE resolved = 0 ORDER BY severity DESC, created_at DESC"
        );
    }
    
    /**
     * Get security statistics
     */
    public function get_security_stats() {
        global $wpdb;
        
        $scans_table = $wpdb->prefix . 'sentinelwp_scans';
        $issues_table = $wpdb->prefix . 'sentinelwp_issues';
        
        $stats = array();
        
        // Total scans
        $stats['total_scans'] = $wpdb->get_var("SELECT COUNT(*) FROM $scans_table");
        
        // Last scan
        $stats['last_scan'] = $wpdb->get_row(
            "SELECT * FROM $scans_table ORDER BY scan_time DESC LIMIT 1"
        );
        
        // Total issues
        $stats['total_issues'] = $wpdb->get_var("SELECT COUNT(*) FROM $issues_table");
        
        // Unresolved issues
        $stats['unresolved_issues'] = $wpdb->get_var("SELECT COUNT(*) FROM $issues_table WHERE resolved = 0");
        
        // Critical issues
        $stats['critical_issues'] = $wpdb->get_var("SELECT COUNT(*) FROM $issues_table WHERE severity = 'high' AND resolved = 0");
        
        // Issues by type
        $stats['issues_by_type'] = $wpdb->get_results(
            "SELECT issue_type, COUNT(*) as count FROM $issues_table WHERE resolved = 0 GROUP BY issue_type ORDER BY count DESC"
        );
        
        return $stats;
    }
    
    /**
     * Mark issue as resolved
     */
    public function resolve_issue($issue_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'sentinelwp_issues',
            array('resolved' => 1),
            array('id' => $issue_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->insert_log(array(
                'action' => 'issue_resolved',
                'details' => "Issue ID: $issue_id resolved"
            ));
        }
        
        return $result;
    }
    
    /**
     * Mark issue as isolated
     */
    public function isolate_issue($issue_id) {
        global $wpdb;
        
        $issue = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . 'sentinelwp_issues' . " WHERE id = %d", $issue_id)
        );
        
        if (!$issue) {
            return false;
        }
        
        // Create isolated directory if not exists
        $isolated_dir = WP_CONTENT_DIR . '/sentinelwp-isolated/';
        if (!file_exists($isolated_dir)) {
            wp_mkdir_p($isolated_dir);
            // Create .htaccess to deny access
            file_put_contents($isolated_dir . '.htaccess', "Deny from all\n");
        }
        
        // Move file to isolated directory
        $original_file = $issue->file_path;
        $isolated_file = $isolated_dir . basename($original_file) . '.isolated.' . time();
        
        if (file_exists($original_file) && rename($original_file, $isolated_file)) {
            $result = $wpdb->update(
                $wpdb->prefix . 'sentinelwp_issues',
                array(
                    'isolated' => 1,
                    'file_path' => $isolated_file
                ),
                array('id' => $issue_id),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $this->insert_log(array(
                    'action' => 'file_isolated',
                    'details' => "File isolated: $original_file -> $isolated_file"
                ));
                return true;
            }
        }
        
        return false;
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
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * Clean old logs
     */
    public function clean_old_logs($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sentinelwp_logs';
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query(
            $wpdb->prepare("DELETE FROM $table WHERE log_time < %s", $date)
        );
    }
    
    /**
     * Insert AI recommendation
     */
    public function insert_ai_recommendation($data) {
        SentinelWP_Logger::debug('Attempting to insert AI recommendation', array(
            'title' => $data['title'] ?? 'unknown',
            'category' => $data['category'] ?? 'unknown',
            'priority' => $data['priority'] ?? 'unknown'
        ));
        
        global $wpdb;
        
        $defaults = array(
            'category' => 'general',
            'title' => '',
            'description' => '',
            'priority' => 'medium',
            'recommendation_type' => 'security',
            'source' => 'ai',
            'status' => 'active',
            'confidence_score' => null,
            'generated_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        $table = $wpdb->prefix . 'sentinelwp_ai_recommendations';
        
        SentinelWP_Logger::debug('Prepared data for AI recommendation insertion', array(
            'table' => $table,
            'data_keys' => array_keys($data)
        ));
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            SentinelWP_Logger::error('Failed to insert AI recommendation', array(
                'data' => $data,
                'wpdb_last_error' => $wpdb->last_error,
                'wpdb_last_query' => $wpdb->last_query
            ));
        } else {
            $insert_id = $wpdb->insert_id;
            SentinelWP_Logger::info('AI recommendation inserted successfully', array(
                'insert_id' => $insert_id,
                'title' => $data['title']
            ));
        }
        
        return $result;
    }
    
    /**
     * Get AI recommendations
     */
    public function get_ai_recommendations($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'active',
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'generated_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        $table = $wpdb->prefix . 'sentinelwp_ai_recommendations';
        
        $where = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['category'])) {
            $where[] = 'category = %s';
            $where_values[] = $args['category'];
        }
        
        if (!empty($args['priority'])) {
            $where[] = 'priority = %s';
            $where_values[] = $args['priority'];
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = sprintf('ORDER BY %s %s', $args['order_by'], $args['order']);
        $limit_clause = sprintf('LIMIT %d, %d', $args['offset'], $args['limit']);
        
        $query = "SELECT * FROM $table $where_clause $order_clause $limit_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update AI recommendation status
     */
    public function update_ai_recommendation_status($id, $status) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sentinelwp_ai_recommendations';
        
        return $wpdb->update(
            $table,
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Delete old AI recommendations
     */
    public function clean_old_ai_recommendations($days = 90) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'sentinelwp_ai_recommendations';
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query(
            $wpdb->prepare("DELETE FROM $table WHERE generated_at < %s AND status = 'dismissed'", $date)
        );
    }
    
    /**
     * Check if all required tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'sentinelwp_scans',
            $wpdb->prefix . 'sentinelwp_issues',
            $wpdb->prefix . 'sentinelwp_logs',
            $wpdb->prefix . 'sentinelwp_settings',
            $wpdb->prefix . 'sentinelwp_ai_recommendations',
            $wpdb->prefix . 'sentinelwp_notifications'
        );
        
        $existing_tables = array();
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table
            ));
            
            if ($table_exists) {
                $existing_tables[] = $table;
            } else {
                $missing_tables[] = $table;
            }
        }
        
        return array(
            'all_exist' => empty($missing_tables),
            'existing' => $existing_tables,
            'missing' => $missing_tables,
            'total_required' => count($required_tables),
            'total_existing' => count($existing_tables)
        );
    }
    
    /**
     * Get table status information
     */
    public static function get_table_status() {
        global $wpdb;
        
        $tables_info = self::tables_exist();
        $table_status = array();
        
        foreach ($tables_info['existing'] as $table) {
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $table_status[] = array(
                'name' => $table,
                'exists' => true,
                'row_count' => $row_count,
                'status' => 'OK'
            );
        }
        
        foreach ($tables_info['missing'] as $table) {
            $table_status[] = array(
                'name' => $table,
                'exists' => false,
                'row_count' => 0,
                'status' => 'MISSING'
            );
        }
        
        return array(
            'summary' => $tables_info,
            'details' => $table_status
        );
    }
    
    /**
     * Force recreate all tables (for migration/reset)
     */
    public static function recreate_tables() {
        SentinelWP_Logger::warning('Starting database table recreation (destructive operation)');
        
        // Drop existing tables first
        self::drop_tables();
        
        // Create tables again
        $result = self::create_tables();
        
        SentinelWP_Logger::info('Database table recreation completed');
        
        return $result;
    }
    
    /**
     * Get security notifications with filtering
     */
    public function get_notifications($filters = array()) {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        
        $defaults = array(
            'status' => '',
            'severity' => '',
            'event_type' => '',
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $filters = wp_parse_args($filters, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['severity'])) {
            $where_conditions[] = 'severity = %s';
            $where_values[] = $filters['severity'];
        }
        
        if (!empty($filters['event_type'])) {
            $where_conditions[] = 'event_type = %s';
            $where_values[] = $filters['event_type'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_notifications 
             WHERE $where_clause 
             ORDER BY {$filters['order_by']} {$filters['order']}, id DESC 
             LIMIT %d OFFSET %d",
            array_merge($where_values, array($filters['limit'], $filters['offset']))
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get notification counts by status and severity
     */
    public function get_notification_counts() {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        
        $counts = $wpdb->get_results(
            "SELECT 
                status,
                severity,
                COUNT(*) as count 
             FROM $table_notifications 
             GROUP BY status, severity"
        );
        
        $result = array(
            'total' => 0,
            'by_status' => array('new' => 0, 'read' => 0, 'resolved' => 0),
            'by_severity' => array('low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0)
        );
        
        foreach ($counts as $count) {
            $result['total'] += $count->count;
            $result['by_status'][$count->status] = ($result['by_status'][$count->status] ?? 0) + $count->count;
            $result['by_severity'][$count->severity] = ($result['by_severity'][$count->severity] ?? 0) + $count->count;
        }
        
        return $result;
    }
    
    /**
     * Update notification status
     */
    public function update_notification_status($notification_id, $status) {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        
        $result = $wpdb->update(
            $table_notifications,
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $notification_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            SentinelWP_Logger::error('Failed to update notification status', array(
                'notification_id' => $notification_id,
                'status' => $status,
                'error' => $wpdb->last_error
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete old notifications (cleanup)
     */
    public function cleanup_old_notifications($days = 30) {
        global $wpdb;
        
        $table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_notifications WHERE created_at < %s AND status != 'new'",
            $cutoff_date
        ));
        
        SentinelWP_Logger::info('Cleaned up old notifications', array(
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoff_date
        ));
        
        return $deleted;
    }
}
