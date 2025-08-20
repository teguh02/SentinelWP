<?php
/**
 * Manual Database Repair Script for SentinelWP
 * Run this script to manually create the missing notifications table
 */

// Ensure we're in WordPress context
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_config_path = '';
    
    // Look for wp-config.php in current directory and parent directories
    $current_dir = dirname(__FILE__);
    for ($i = 0; $i < 5; $i++) {
        if (file_exists($current_dir . '/wp-config.php')) {
            $wp_config_path = $current_dir . '/wp-config.php';
            break;
        }
        $current_dir = dirname($current_dir);
    }
    
    if (empty($wp_config_path)) {
        die("Error: Could not find wp-config.php. Please run this script from your WordPress installation directory.\n");
    }
    
    require_once($wp_config_path);
    require_once(ABSPATH . 'wp-includes/wp-db.php');
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Initialize WordPress database
    $wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
}

echo "=== SentinelWP Database Repair Script ===\n\n";

echo "1. Checking current database status...\n";

global $wpdb;

// Check which tables exist
$table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
$table_scans = $wpdb->prefix . 'sentinelwp_scans';
$table_issues = $wpdb->prefix . 'sentinelwp_issues';
$table_logs = $wpdb->prefix . 'sentinelwp_logs';
$table_settings = $wpdb->prefix . 'sentinelwp_settings';
$table_ai_recommendations = $wpdb->prefix . 'sentinelwp_ai_recommendations';

$tables_to_check = array(
    'Notifications' => $table_notifications,
    'Scans' => $table_scans,
    'Issues' => $table_issues,
    'Logs' => $table_logs,
    'Settings' => $table_settings,
    'AI Recommendations' => $table_ai_recommendations
);

foreach ($tables_to_check as $name => $table_name) {
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $table_name
    ));
    
    echo "  - {$name} ({$table_name}): " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "\n2. Creating missing tables...\n";

$charset_collate = $wpdb->get_charset_collate();

// Create the notifications table specifically
if ($wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME,
    $table_notifications
)) == 0) {
    
    echo "Creating notifications table...\n";
    
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
    
    $result = dbDelta($sql_notifications);
    
    // Verify creation
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $table_notifications
    ));
    
    if ($table_exists) {
        echo "  ✓ Notifications table created successfully!\n";
        
        // Insert a test notification to verify the table works
        $test_insert = $wpdb->insert(
            $table_notifications,
            array(
                'event_type' => 'system_repair',
                'description' => 'Database repair completed successfully',
                'severity' => 'low',
                'status' => 'new',
                'additional_data' => json_encode(array('repair_time' => current_time('mysql')))
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        if ($test_insert !== false) {
            echo "  ✓ Test notification inserted successfully!\n";
        } else {
            echo "  ⚠ Warning: Could not insert test notification. Error: " . $wpdb->last_error . "\n";
        }
        
    } else {
        echo "  ❌ Failed to create notifications table. Error: " . $wpdb->last_error . "\n";
        echo "  dbDelta result: " . print_r($result, true) . "\n";
    }
} else {
    echo "  ✓ Notifications table already exists\n";
}

// Create other missing tables if needed
$table_definitions = array(
    'scans' => "CREATE TABLE $table_scans (
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
    ) $charset_collate;",
    
    'issues' => "CREATE TABLE $table_issues (
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
        KEY resolved (resolved)
    ) $charset_collate;",
    
    'logs' => "CREATE TABLE $table_logs (
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
    ) $charset_collate;",
    
    'settings' => "CREATE TABLE $table_settings (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        setting_key varchar(100) NOT NULL,
        setting_value longtext,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY setting_key (setting_key)
    ) $charset_collate;",
    
    'ai_recommendations' => "CREATE TABLE $table_ai_recommendations (
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
    ) $charset_collate;"
);

$tables_to_create = array(
    'scans' => $table_scans,
    'issues' => $table_issues,
    'logs' => $table_logs,
    'settings' => $table_settings,
    'ai_recommendations' => $table_ai_recommendations
);

foreach ($tables_to_create as $key => $table_name) {
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $table_name
    ));
    
    if (!$exists) {
        echo "Creating {$key} table...\n";
        $result = dbDelta($table_definitions[$key]);
        
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));
        
        if ($table_exists) {
            echo "  ✓ {$key} table created successfully!\n";
        } else {
            echo "  ❌ Failed to create {$key} table. Error: " . $wpdb->last_error . "\n";
        }
    }
}

echo "\n3. Final verification...\n";

foreach ($tables_to_check as $name => $table_name) {
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $table_name
    ));
    
    echo "  - {$name}: " . ($exists ? "✓ EXISTS" : "❌ MISSING") . "\n";
}

echo "\n4. Testing notifications table functionality...\n";

// Test the query that was failing
$test_query = "SELECT event_type, ip_address, COUNT(*) as count 
               FROM $table_notifications 
               WHERE severity IN ('high', 'critical') 
               AND created_at >= '" . date('Y-m-d H:i:s', strtotime('-24 hours')) . "' 
               GROUP BY event_type, ip_address 
               ORDER BY count DESC";

$results = $wpdb->get_results($test_query);

if ($wpdb->last_error) {
    echo "  ❌ Test query failed: " . $wpdb->last_error . "\n";
} else {
    echo "  ✓ Test query executed successfully\n";
    echo "  Found " . count($results) . " notification records\n";
}

echo "\n=== Repair Complete ===\n";
echo "If the notifications table now exists, the dashboard error should be resolved.\n";
echo "Please refresh your WordPress admin dashboard to verify.\n";

if (!defined('ABSPATH')) {
    echo "\nNote: This script was run outside WordPress context.\n";
    echo "If issues persist, try running it from WordPress admin or contact support.\n";
}
?>
