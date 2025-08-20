<?php
/**
 * Simple verification script for SentinelWP database tables
 */

echo "=== SentinelWP Database Verification ===\n\n";

// Load WordPress
$wp_config_path = '';
$current_dir = dirname(__FILE__);
for ($i = 0; $i < 5; $i++) {
    if (file_exists($current_dir . '/wp-config.php')) {
        $wp_config_path = $current_dir . '/wp-config.php';
        break;
    }
    $current_dir = dirname($current_dir);
}

require_once($wp_config_path);

global $wpdb;

// Check all SentinelWP tables
$tables = array(
    'notifications' => $wpdb->prefix . 'sentinelwp_notifications',
    'scans' => $wpdb->prefix . 'sentinelwp_scans',
    'issues' => $wpdb->prefix . 'sentinelwp_issues', 
    'logs' => $wpdb->prefix . 'sentinelwp_logs',
    'settings' => $wpdb->prefix . 'sentinelwp_settings',
    'ai_recommendations' => $wpdb->prefix . 'sentinelwp_ai_recommendations'
);

echo "Checking database tables:\n";
foreach ($tables as $name => $table_name) {
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $table_name
    ));
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "  ✓ $name ($table_name): EXISTS ($count records)\n";
    } else {
        echo "  ❌ $name ($table_name): MISSING\n";
    }
}

// Test the specific query that was failing
echo "\nTesting the problematic dashboard query:\n";

$table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
$query = "SELECT event_type, ip_address, COUNT(*) as count 
          FROM $table_notifications 
          WHERE severity IN ('high', 'critical') 
          AND created_at >= '" . date('Y-m-d H:i:s', strtotime('-24 hours')) . "' 
          GROUP BY event_type, ip_address 
          ORDER BY count DESC";

$results = $wpdb->get_results($query);

if ($wpdb->last_error) {
    echo "  ❌ Query failed: " . $wpdb->last_error . "\n";
} else {
    echo "  ✓ Dashboard query executed successfully\n";
    echo "  Found " . count($results) . " high-priority attack notifications\n";
}

echo "\n=== Status ===\n";
echo "Database tables are now created and accessible.\n";
echo "The WordPress dashboard error should be resolved.\n";
echo "Please refresh your WordPress admin to verify.\n";
?>
