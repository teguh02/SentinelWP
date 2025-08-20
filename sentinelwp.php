<?php
/**
 * Plugin Name: SentinelWP - Hybrid Security Scanner
 * Plugin URI: https://github.com/teguh02/SentinelWP
 * Description: Hybrid security scanner for WordPress with ClamAV integration and AI-powered analysis using Google Gemini API.
 * Version: 1.0.0
 * Author: Teguh Rijanandi
 * Author URI: https://github.com/teguh02/SentinelWP
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sentinelwp
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SENTINELWP_VERSION', '1.0.0');
define('SENTINELWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SENTINELWP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SENTINELWP_PLUGIN_FILE', __FILE__);

/**
 * Main SentinelWP Class
 */
class SentinelWP {
    
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
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init_classes();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('SentinelWP', 'uninstall'));
        
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Schedule cron job
        add_action('wp', array($this, 'schedule_scan'));
        add_action('sentinelwp_scheduled_scan', array($this, 'run_scheduled_scan'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once SENTINELWP_PLUGIN_PATH . 'includes/class-logger.php';
        require_once SENTINELWP_PLUGIN_PATH . 'includes/class-database.php';
        require_once SENTINELWP_PLUGIN_PATH . 'includes/class-scanner.php';
        require_once SENTINELWP_PLUGIN_PATH . 'includes/class-dashboard.php';
        require_once SENTINELWP_PLUGIN_PATH . 'includes/class-recommendations.php';
        require_once SENTINELWP_PLUGIN_PATH . 'includes/class-ai-advisor.php';
        require_once SENTINELWP_PLUGIN_PATH . 'includes/class-notifications.php';
        require_once SENTINELWP_PLUGIN_PATH . 'includes/class-attack-detector.php';
        require_once SENTINELWP_PLUGIN_PATH . 'includes/helpers.php';
    }
    
    /**
     * Initialize classes
     */
    private function init_classes() {
        SentinelWP_Database::instance();
        SentinelWP_Scanner::instance();
        SentinelWP_Dashboard::instance();
        SentinelWP_Recommendations::instance();
        SentinelWP_AI_Advisor::instance();
        SentinelWP_Notifications::instance();
        SentinelWP_Attack_Detector::instance();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('sentinelwp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Run migration check for existing installations
        $migration_version = get_option('sentinelwp_migration_version', '0.0.0');
        if (version_compare($migration_version, SENTINELWP_VERSION, '<')) {
            SentinelWP_Database::migrate_database();
            update_option('sentinelwp_migration_version', SENTINELWP_VERSION);
        }
        
        // Check for system requirements
        $this->check_system_status();
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_menu_page(
            __('SentinelWP Security', 'sentinelwp'),
            __('SentinelWP', 'sentinelwp'),
            'manage_options',
            'sentinelwp',
            array($this, 'dashboard_page'),
            'dashicons-shield-alt',
            30
        );
        
        add_submenu_page(
            'sentinelwp',
            __('Dashboard', 'sentinelwp'),
            __('Dashboard', 'sentinelwp'),
            'manage_options',
            'sentinelwp',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'sentinelwp',
            __('Scan Results', 'sentinelwp'),
            __('Scan Results', 'sentinelwp'),
            'manage_options',
            'sentinelwp-scan-results',
            array($this, 'scan_results_page')
        );
        
        add_submenu_page(
            'sentinelwp',
            __('Security Notifications', 'sentinelwp'),
            __('Notifications', 'sentinelwp'),
            'manage_options',
            'sentinelwp-notifications',
            array($this, 'notifications_page')
        );
        
        add_submenu_page(
            'sentinelwp',
            __('Recommendations', 'sentinelwp'),
            __('Recommendations', 'sentinelwp'),
            'manage_options',
            'sentinelwp-recommendations',
            array($this, 'recommendations_page')
        );
        
        add_submenu_page(
            'sentinelwp',
            __('AI Security Advisor', 'sentinelwp'),
            __('AI Advisor', 'sentinelwp'),
            'manage_options',
            'sentinelwp-ai-advisor',
            array($this, 'ai_advisor_page')
        );
        
        add_submenu_page(
            'sentinelwp',
            __('Settings', 'sentinelwp'),
            __('Settings', 'sentinelwp'),
            'manage_options',
            'sentinelwp-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'sentinelwp',
            __('Debug Logs', 'sentinelwp'),
            __('Logs', 'sentinelwp'),
            'manage_options',
            'sentinelwp-logs',
            array($this, 'logs_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'sentinelwp') !== false) {
            wp_enqueue_style('sentinelwp-admin', SENTINELWP_PLUGIN_URL . 'assets/css/admin.css', array(), SENTINELWP_VERSION);
            wp_enqueue_script('sentinelwp-admin', SENTINELWP_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SENTINELWP_VERSION, true);
            
            wp_localize_script('sentinelwp-admin', 'sentinelwp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sentinelwp_nonce'),
                'strings' => array(
                    'scanning' => __('Scanning in progress...', 'sentinelwp'),
                    'scan_complete' => __('Scan completed!', 'sentinelwp'),
                    'error' => __('An error occurred', 'sentinelwp')
                )
            ));
        }
    }
    
    /**
     * Dashboard page callback
     */
    public function dashboard_page() {
        SentinelWP_Dashboard::render_dashboard();
    }
    
    /**
     * Scan results page callback
     */
    public function scan_results_page() {
        SentinelWP_Dashboard::render_scan_results();
    }
    
    /**
     * Security notifications page callback
     */
    public function notifications_page() {
        SentinelWP_Dashboard::render_notifications();
    }
    
    /**
     * Recommendations page callback
     */
    public function recommendations_page() {
        SentinelWP_Dashboard::render_recommendations();
    }
    
    /**
     * AI Advisor page callback
     */
    public function ai_advisor_page() {
        SentinelWP_Dashboard::render_ai_advisor();
    }
    
    /**
     * Render settings page
     */
    public function settings_page() {
        SentinelWP_Dashboard::render_settings();
    }
    
    /**
     * Render logs page
     */
    public function logs_page() {
        SentinelWP_Dashboard::render_logs();
    }
    
    /**
     * Check system status
     */
    private function check_system_status() {
        $status = array(
            'php_exec_enabled' => $this->check_php_exec_functions(),
            'clamav_installed' => $this->check_clamav_installation(),
            'scan_engine_mode' => 'heuristic'
        );
        
        if ($status['php_exec_enabled'] && $status['clamav_installed']) {
            $status['scan_engine_mode'] = 'clamav';
        }
        
        update_option('sentinelwp_system_status', $status);
    }
    
    /**
     * Check if PHP execution functions are available
     */
    private function check_php_exec_functions() {
        $functions = array('shell_exec', 'exec', 'passthru', 'proc_open');
        foreach ($functions as $func) {
            if (function_exists($func)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if ClamAV is installed
     */
    private function check_clamav_installation() {
        if (!function_exists('shell_exec')) {
            return false;
        }
        
        $clamscan = shell_exec('which clamscan 2>/dev/null');
        $clamdscan = shell_exec('which clamdscan 2>/dev/null');
        
        return !empty($clamscan) || !empty($clamdscan);
    }
    
    /**
     * Schedule scan cron job
     */
    public function schedule_scan() {
        if (!wp_next_scheduled('sentinelwp_scheduled_scan')) {
            $time = get_option('sentinelwp_scan_time', '02:00');
            $timestamp = strtotime('today ' . $time);
            if ($timestamp < time()) {
                $timestamp += 24 * 60 * 60; // Next day
            }
            wp_schedule_event($timestamp, 'daily', 'sentinelwp_scheduled_scan');
        }
    }
    
    /**
     * Run scheduled scan
     */
    public function run_scheduled_scan() {
        if (get_option('sentinelwp_auto_scan_enabled', true)) {
            $scanner = SentinelWP_Scanner::instance();
            $result = $scanner->run_full_scan();
            
            // Send notification if enabled
            if (get_option('sentinelwp_notify_scan_results', true)) {
                $notifications = SentinelWP_Notifications::instance();
                $notifications->send_scan_notification($result);
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        SentinelWP_Database::create_tables();
        SentinelWP_Database::migrate_database(); // Ensure existing installations get the new schema
        $this->check_system_status();
        
        // Set default options
        $defaults = array(
            'sentinelwp_auto_scan_enabled' => true,
            'sentinelwp_scan_time' => '02:00',
            'sentinelwp_notify_scan_results' => true,
            'sentinelwp_notify_threats' => true,
            'sentinelwp_notify_under_attack' => true,
            'sentinelwp_notification_email' => get_option('admin_email'),
            'sentinelwp_telegram_enabled' => false,
            'sentinelwp_gemini_enabled' => false,
            'sentinelwp_gemini_model' => 'gemini-2.5-flash'
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('sentinelwp_scheduled_scan');
    }
    
    /**
     * Plugin uninstallation
     */
    public static function uninstall() {
        SentinelWP_Database::drop_tables();
        
        // Remove options
        $options = array(
            'sentinelwp_system_status',
            'sentinelwp_auto_scan_enabled',
            'sentinelwp_scan_time',
            'sentinelwp_notify_scan_results',
            'sentinelwp_notify_threats',
            'sentinelwp_notify_under_attack',
            'sentinelwp_notification_email',
            'sentinelwp_telegram_enabled',
            'sentinelwp_telegram_bot_token',
            'sentinelwp_telegram_chat_id',
            'sentinelwp_gemini_enabled',
            'sentinelwp_gemini_api_key',
            'sentinelwp_gemini_model'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
}

// Initialize the plugin
SentinelWP::instance();
