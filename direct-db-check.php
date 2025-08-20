<?php
// Direct database check
$wp_config_path = '../../../wp-config.php';
if (!file_exists($wp_config_path)) {
    $wp_config_path = '../../../../wp-config.php';
}
if (!file_exists($wp_config_path)) {
    die("Could not find wp-config.php\n");
}
require_once $wp_config_path;

echo "=== Direct Database Check ===\n\n";

global $wpdb;

echo "WordPress table prefix: '{$wpdb->prefix}'\n";
echo "Database name: " . DB_NAME . "\n\n";

// Get all tables in the database
$all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);

echo "All tables in database:\n";
foreach ($all_tables as $table) {
    $table_name = $table[0];
    if (strpos($table_name, 'sentinelwp') !== false) {
        echo "  ✓ $table_name (SentinelWP table)\n";
    } else {
        echo "    $table_name\n";
    }
}

// Check specifically for the notifications table with different possible prefixes
$possible_tables = array(
    $wpdb->prefix . 'sentinelwp_notifications',
    'wp_sentinelwp_notifications',
    'sentinelwp_notifications'
);

echo "\nChecking for notifications table:\n";
foreach ($possible_tables as $table_name) {
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $table_name
    ));
    
    if ($exists) {
        echo "  ✓ Found: $table_name\n";
        
        // Check table structure
        $columns = $wpdb->get_results("DESCRIBE $table_name");
        echo "    Columns: ";
        foreach ($columns as $column) {
            echo $column->Field . " ";
        }
        echo "\n";
        
        // Check record count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "    Records: $count\n";
    } else {
        echo "  ❌ Not found: $table_name\n";
    }
}

// Try to create the table manually if it doesn't exist
$table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
$exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME,
    $table_notifications
));

if (!$exists) {
    echo "\nAttempting to create notifications table manually...\n";
    
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_notifications (
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
    
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "  ✓ Table created successfully!\n";
        
        // Insert test record
        $insert_result = $wpdb->insert(
            $table_notifications,
            array(
                'event_type' => 'test_notification',
                'description' => 'Test notification created during manual setup',
                'severity' => 'low',
                'status' => 'new'
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        if ($insert_result) {
            echo "  ✓ Test record inserted successfully!\n";
        }
        
    } else {
        echo "  ❌ Failed to create table. Error: " . $wpdb->last_error . "\n";
    }
}

echo "\n=== Final Status ===\n";

// Test the original failing query
$table_notifications = $wpdb->prefix . 'sentinelwp_notifications';
$query = "SELECT event_type, ip_address, COUNT(*) as count 
          FROM $table_notifications 
          WHERE severity IN ('high', 'critical') 
          AND created_at >= '" . date('Y-m-d H:i:s', strtotime('-24 hours')) . "' 
          GROUP BY event_type, ip_address 
          ORDER BY count DESC";

$results = $wpdb->get_results($query);

if ($wpdb->last_error) {
    echo "❌ Dashboard query still failing: " . $wpdb->last_error . "\n";
} else {
    echo "✅ Dashboard query now works! Found " . count($results) . " results.\n";
}

echo "\nRecommendation: Refresh your WordPress dashboard to see if the error is resolved.\n";
?>
