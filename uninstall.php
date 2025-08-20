<?php
/**
 * SentinelWP Uninstall Script
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It performs cleanup operations including:
 * - Removing database tables
 * - Deleting plugin options
 * - Cleaning up scheduled events
 * - Removing isolated files
 * - Clearing cache data
 *
 * @package SentinelWP
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

class SentinelWP_Uninstaller {
    
    /**
     * Run the uninstallation process
     */
    public static function uninstall() {
        // Check user capabilities
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Verify we're uninstalling the correct plugin
        $plugin_file = plugin_basename(__FILE__);
        if ($plugin_file !== 'sentinelwp/sentinelwp.php') {
            return;
        }
        
        // Remove database tables
        self::remove_database_tables();
        
        // Remove plugin options
        self::remove_plugin_options();
        
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Remove isolated files directory
        self::remove_isolated_files();
        
        // Clear transients and cache
        self::clear_cache_data();
        
        // Remove user meta
        self::remove_user_meta();
        
        // Log uninstallation
        self::log_uninstallation();
    }
    
    /**
     * Remove all plugin database tables
     */
    private static function remove_database_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'sentinelwp_scans',
            $wpdb->prefix . 'sentinelwp_issues',
            $wpdb->prefix . 'sentinelwp_logs',
            $wpdb->prefix . 'sentinelwp_settings'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $table));
        }
        
        // Remove any custom database modifications
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sentinelwp_%'");
    }
    
    /**
     * Remove all plugin options from wp_options table
     */
    private static function remove_plugin_options() {
        $options = [
            'sentinelwp_version',
            'sentinelwp_settings',
            'sentinelwp_scan_settings',
            'sentinelwp_notification_settings',
            'sentinelwp_ai_settings',
            'sentinelwp_general_settings',
            'sentinelwp_last_scan',
            'sentinelwp_security_score',
            'sentinelwp_installation_date',
            'sentinelwp_activation_notice_dismissed',
            'sentinelwp_clamav_status',
            'sentinelwp_system_info',
            'sentinelwp_threat_count',
            'sentinelwp_scan_count',
            'sentinelwp_api_key_encrypted',
            'sentinelwp_telegram_configured',
            'sentinelwp_email_configured'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
            delete_site_option($option); // For multisite
        }
        
        // Remove any remaining options with plugin prefix
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sentinelwp_%'");
        
        // For multisite, remove from site options too
        if (is_multisite()) {
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'sentinelwp_%'");
        }
    }
    
    /**
     * Clear all scheduled events created by the plugin
     */
    private static function clear_scheduled_events() {
        // Remove scheduled scans
        wp_clear_scheduled_hook('sentinelwp_scheduled_scan');
        wp_clear_scheduled_hook('sentinelwp_cleanup_old_data');
        wp_clear_scheduled_hook('sentinelwp_send_weekly_report');
        wp_clear_scheduled_hook('sentinelwp_update_signatures');
        wp_clear_scheduled_hook('sentinelwp_system_check');
        
        // Remove any custom intervals
        $schedules = wp_get_schedules();
        if (isset($schedules['sentinelwp_weekly'])) {
            wp_clear_scheduled_hook('wp_sentinelwp_weekly_event');
        }
        
        // Clear all cron events that might be related
        $crons = _get_cron_array();
        if ($crons) {
            foreach ($crons as $timestamp => $cron) {
                if (is_array($cron)) {
                    foreach ($cron as $hook => $events) {
                        if (strpos($hook, 'sentinelwp_') === 0) {
                            wp_clear_scheduled_hook($hook);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Remove isolated files directory and contents
     */
    private static function remove_isolated_files() {
        $upload_dir = wp_upload_dir();
        $isolated_dir = $upload_dir['basedir'] . '/sentinelwp-isolated/';
        
        if (is_dir($isolated_dir)) {
            self::delete_directory($isolated_dir);
        }
        
        // Also remove backup directory if it exists
        $backup_dir = $upload_dir['basedir'] . '/sentinelwp-backups/';
        if (is_dir($backup_dir)) {
            self::delete_directory($backup_dir);
        }
        
        // Remove any temporary files
        $temp_dir = sys_get_temp_dir() . '/sentinelwp/';
        if (is_dir($temp_dir)) {
            self::delete_directory($temp_dir);
        }
    }
    
    /**
     * Recursively delete a directory and its contents
     *
     * @param string $dir Directory path to delete
     * @return bool True on success, false on failure
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Clear all plugin-related transients and cache data
     */
    private static function clear_cache_data() {
        // Clear transients
        $transients = [
            'sentinelwp_system_status',
            'sentinelwp_clamav_check',
            'sentinelwp_wp_integrity',
            'sentinelwp_plugin_versions',
            'sentinelwp_theme_versions',
            'sentinelwp_security_headers',
            'sentinelwp_file_permissions',
            'sentinelwp_last_scan_summary',
            'sentinelwp_threat_signatures'
        ];
        
        foreach ($transients as $transient) {
            delete_transient($transient);
            delete_site_transient($transient); // For multisite
        }
        
        // Clear any remaining transients with plugin prefix
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sentinelwp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sentinelwp_%'");
        
        // For multisite
        if (is_multisite()) {
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_sentinelwp_%'");
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_sentinelwp_%'");
        }
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('sentinelwp');
        }
    }
    
    /**
     * Remove user meta data related to the plugin
     */
    private static function remove_user_meta() {
        global $wpdb;
        
        $meta_keys = [
            'sentinelwp_dashboard_preferences',
            'sentinelwp_notification_preferences',
            'sentinelwp_last_login_check',
            'sentinelwp_scan_history_view',
            'sentinelwp_dismissed_notices',
            'sentinelwp_user_settings'
        ];
        
        foreach ($meta_keys as $meta_key) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            ));
        }
        
        // Remove any remaining user meta with plugin prefix
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'sentinelwp_%'");
    }
    
    /**
     * Log the uninstallation event
     */
    private static function log_uninstallation() {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_email' => wp_get_current_user()->user_email,
            'site_url' => site_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => '1.0.0',
            'reason' => 'plugin_deleted'
        ];
        
        // Try to send uninstallation log to remote server (optional)
        wp_remote_post('https://api.sentinelwp.com/uninstall-log', [
            'body' => json_encode($log_data),
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 5,
            'blocking' => false // Non-blocking request
        ]);
        
        // Also log to WordPress error log
        error_log('SentinelWP: Plugin uninstalled by user ' . wp_get_current_user()->user_login);
    }
    
    /**
     * Check if we should preserve data
     * 
     * @return bool True if data should be preserved
     */
    private static function should_preserve_data() {
        // Check if there's a constant or option to preserve data
        if (defined('SENTINELWP_PRESERVE_DATA') && SENTINELWP_PRESERVE_DATA) {
            return true;
        }
        
        $preserve = get_option('sentinelwp_preserve_data_on_uninstall', false);
        return (bool) $preserve;
    }
    
    /**
     * Create backup before deletion (if requested)
     */
    private static function create_backup() {
        if (!self::should_preserve_data()) {
            return;
        }
        
        global $wpdb;
        
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/sentinelwp-backups/';
        
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . 'sentinelwp_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Create SQL backup of plugin tables
        $tables = [
            $wpdb->prefix . 'sentinelwp_scans',
            $wpdb->prefix . 'sentinelwp_issues',
            $wpdb->prefix . 'sentinelwp_logs',
            $wpdb->prefix . 'sentinelwp_settings'
        ];
        
        $sql_content = "-- SentinelWP Backup " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $result = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
            if ($result) {
                $sql_content .= "-- Table: {$table}\n";
                $sql_content .= "-- Data:\n";
                foreach ($result as $row) {
                    $sql_content .= "INSERT INTO {$table} (" . implode(',', array_keys($row)) . ") VALUES ('" . implode("','", array_values($row)) . "');\n";
                }
                $sql_content .= "\n";
            }
        }
        
        file_put_contents($backup_file, $sql_content);
    }
}

// Run the uninstallation
if (class_exists('SentinelWP_Uninstaller')) {
    // Create backup if needed (before deletion)
    if (defined('SENTINELWP_CREATE_BACKUP_ON_UNINSTALL') && SENTINELWP_CREATE_BACKUP_ON_UNINSTALL) {
        SentinelWP_Uninstaller::create_backup();
    }
    
    // Run the uninstallation
    SentinelWP_Uninstaller::uninstall();
}

// Final cleanup - remove this file itself from any remaining references
unset($GLOBALS['sentinelwp_uninstaller']);
