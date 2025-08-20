<?php
/**
 * SentinelWP Recommendations Class
 * 
 * Generates security recommendations based on scan results and system analysis
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_Recommendations {
    
    private static $instance = null;
    private $database;
    
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
    }
    
    /**
     * Get comprehensive security recommendations
     */
    public function get_security_recommendations() {
        $recommendations = array();
        
        // Get current issues
        $unresolved_issues = $this->database->get_unresolved_issues();
        
        // Issue-based recommendations
        if (!empty($unresolved_issues)) {
            $recommendations['immediate_actions'] = $this->get_issue_based_recommendations($unresolved_issues);
        }
        
        // System configuration recommendations
        $recommendations['system_configuration'] = $this->get_system_recommendations();
        
        // WordPress security recommendations
        $recommendations['wordpress_security'] = $this->get_wordpress_recommendations();
        
        // Plugin and theme recommendations
        $recommendations['plugins_themes'] = $this->get_plugin_theme_recommendations();
        
        // Server configuration recommendations
        $recommendations['server_configuration'] = $this->get_server_recommendations();
        
        return $recommendations;
    }
    
    /**
     * Get recommendations based on current issues
     */
    private function get_issue_based_recommendations($issues) {
        $recommendations = array();
        $issue_types = array();
        
        // Group issues by type
        foreach ($issues as $issue) {
            $issue_types[$issue->issue_type][] = $issue;
        }
        
        foreach ($issue_types as $type => $type_issues) {
            switch ($type) {
                case 'malware_detected':
                    $recommendations[] = array(
                        'title' => 'Immediate Malware Response Required',
                        'description' => sprintf(
                            'Found %d malware-infected files. These pose an immediate security risk and should be addressed immediately.',
                            count($type_issues)
                        ),
                        'priority' => 'critical',
                        'action' => 'Review each infected file and either clean or quarantine them. Consider restoring from clean backups.'
                    );
                    break;
                    
                case 'suspicious_code':
                    $recommendations[] = array(
                        'title' => 'Suspicious Code Patterns Detected',
                        'description' => sprintf(
                            'Found %d files with suspicious code patterns. These may indicate compromised files or backdoors.',
                            count($type_issues)
                        ),
                        'priority' => 'high',
                        'action' => 'Manually review each file for malicious code. Remove or clean suspicious content.'
                    );
                    break;
                    
                case 'obfuscated_code':
                    $recommendations[] = array(
                        'title' => 'Obfuscated Code Found',
                        'description' => sprintf(
                            'Found %d files with obfuscated code. Legitimate files rarely use heavy obfuscation.',
                            count($type_issues)
                        ),
                        'priority' => 'high',
                        'action' => 'Decode and analyze obfuscated content. Remove if malicious.'
                    );
                    break;
                    
                case 'suspicious_location':
                    $recommendations[] = array(
                        'title' => 'Files in Suspicious Locations',
                        'description' => sprintf(
                            'Found %d files in inappropriate locations (e.g., PHP files in uploads directory).',
                            count($type_issues)
                        ),
                        'priority' => 'high',
                        'action' => 'Move files to appropriate locations or remove if unauthorized.'
                    );
                    break;
                    
                case 'modified_core_file':
                    $recommendations[] = array(
                        'title' => 'WordPress Core Files Modified',
                        'description' => sprintf(
                            'Found %d modified WordPress core files. This could indicate compromise or customization issues.',
                            count($type_issues)
                        ),
                        'priority' => 'high',
                        'action' => 'Restore original WordPress core files or investigate modifications.'
                    );
                    break;
                    
                case 'file_permissions':
                    $recommendations[] = array(
                        'title' => 'Insecure File Permissions',
                        'description' => sprintf(
                            'Found %d files with insecure permissions.',
                            count($type_issues)
                        ),
                        'priority' => 'medium',
                        'action' => 'Set proper file permissions: 644 for files, 755 for directories, 600 for wp-config.php'
                    );
                    break;
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get system configuration recommendations
     */
    private function get_system_recommendations() {
        $recommendations = array();
        $system_status = get_option('sentinelwp_system_status', array());
        
        // ClamAV recommendations
        if (empty($system_status['clamav_installed'])) {
            if (!empty($system_status['php_exec_enabled'])) {
                $recommendations[] = array(
                    'title' => 'Install ClamAV for Enhanced Protection',
                    'description' => 'ClamAV is not installed. Installing it would provide more comprehensive malware detection capabilities.',
                    'priority' => 'medium',
                    'action' => 'Install ClamAV: sudo apt-get install clamav clamav-daemon (Ubuntu/Debian) or yum install clamav (CentOS/RHEL)'
                );
            }
        } else {
            // Check ClamAV database freshness
            $recommendations[] = array(
                'title' => 'Keep ClamAV Updated',
                'description' => 'Ensure ClamAV virus definitions are updated regularly for latest threat detection.',
                'priority' => 'low',
                'action' => 'Run freshclam to update virus definitions, or set up automatic updates via cron'
            );
        }
        
        // PHP execution functions
        if (empty($system_status['php_exec_enabled'])) {
            $recommendations[] = array(
                'title' => 'PHP Execution Functions Status',
                'description' => 'PHP execution functions are disabled, which limits scanning capabilities but improves security.',
                'priority' => 'info',
                'action' => 'This is actually good for security. Only enable if advanced scanning features are needed.'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Get WordPress security recommendations
     */
    private function get_wordpress_recommendations() {
        $recommendations = array();
        
        // Check WordPress version
        global $wp_version;
        $latest_version = $this->get_latest_wordpress_version();
        if ($latest_version && version_compare($wp_version, $latest_version, '<')) {
            $recommendations[] = array(
                'title' => 'Update WordPress to Latest Version',
                'description' => "WordPress version {$wp_version} is outdated. Latest version is {$latest_version}.",
                'priority' => 'high',
                'action' => 'Update WordPress through admin dashboard or wp-cli: wp core update'
            );
        }
        
        // Check wp-config.php security
        if (!defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT) {
            $recommendations[] = array(
                'title' => 'Disable File Editing in WordPress Admin',
                'description' => 'File editing is enabled in WordPress admin, which allows potential attackers to modify files.',
                'priority' => 'medium',
                'action' => "Add define('DISALLOW_FILE_EDIT', true); to wp-config.php"
            );
        }
        
        // Check debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
                $recommendations[] = array(
                    'title' => 'Secure Debug Mode Configuration',
                    'description' => 'Debug mode is enabled but not properly configured for production.',
                    'priority' => 'medium',
                    'action' => "Either disable debug mode or add define('WP_DEBUG_LOG', true); and define('WP_DEBUG_DISPLAY', false); to wp-config.php"
                );
            }
        }
        
        // Check database prefix
        global $wpdb;
        if ($wpdb->prefix === 'wp_') {
            $recommendations[] = array(
                'title' => 'Change Database Table Prefix',
                'description' => 'Using default database prefix "wp_" makes your site more vulnerable to SQL injection attacks.',
                'priority' => 'low',
                'action' => 'Change database prefix during installation or use a security plugin to modify it'
            );
        }
        
        // Check XML-RPC
        if (get_option('xmlrpc_enabled', true)) {
            $recommendations[] = array(
                'title' => 'Consider Disabling XML-RPC',
                'description' => 'XML-RPC is enabled and can be exploited for brute force attacks if not needed.',
                'priority' => 'low',
                'action' => 'Disable XML-RPC if not using mobile apps or remote publishing tools'
            );
        }
        
        // Check admin user
        $admin_users = get_users(array('role' => 'administrator'));
        foreach ($admin_users as $user) {
            if ($user->user_login === 'admin') {
                $recommendations[] = array(
                    'title' => 'Rename Default Admin Username',
                    'description' => 'Found user with username "admin" which is commonly targeted in brute force attacks.',
                    'priority' => 'medium',
                    'action' => 'Create a new admin user with a unique username and delete the "admin" user'
                );
                break;
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get plugin and theme recommendations
     */
    private function get_plugin_theme_recommendations() {
        $recommendations = array();
        
        // Check for outdated plugins
        $plugins = get_plugins();
        $plugin_updates = get_site_transient('update_plugins');
        
        if (!empty($plugin_updates->response)) {
            $outdated_count = count($plugin_updates->response);
            $recommendations[] = array(
                'title' => 'Update Outdated Plugins',
                'description' => "Found {$outdated_count} plugins with available updates. Outdated plugins are security risks.",
                'priority' => 'high',
                'action' => 'Update all plugins through WordPress admin or wp-cli: wp plugin update --all'
            );
        }
        
        // Check for inactive plugins
        $inactive_plugins = array();
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (!is_plugin_active($plugin_file)) {
                $inactive_plugins[] = $plugin_data['Name'];
            }
        }
        
        if (!empty($inactive_plugins)) {
            $recommendations[] = array(
                'title' => 'Remove Inactive Plugins',
                'description' => sprintf(
                    'Found %d inactive plugins. Even inactive plugins can be exploited.',
                    count($inactive_plugins)
                ),
                'priority' => 'medium',
                'action' => 'Delete unused plugins to reduce attack surface'
            );
        }
        
        // Check for outdated themes
        $theme_updates = get_site_transient('update_themes');
        if (!empty($theme_updates->response)) {
            $outdated_themes = count($theme_updates->response);
            $recommendations[] = array(
                'title' => 'Update Outdated Themes',
                'description' => "Found {$outdated_themes} themes with available updates.",
                'priority' => 'medium',
                'action' => 'Update all themes through WordPress admin or wp-cli: wp theme update --all'
            );
        }
        
        // Check for unused themes
        $all_themes = wp_get_themes();
        $active_theme = get_stylesheet();
        $unused_themes = array();
        
        foreach ($all_themes as $theme_slug => $theme_data) {
            if ($theme_slug !== $active_theme && !in_array($theme_slug, array('twentytwenty', 'twentytwentyone', 'twentytwentytwo'))) {
                $unused_themes[] = $theme_data->get('Name');
            }
        }
        
        if (!empty($unused_themes)) {
            $recommendations[] = array(
                'title' => 'Remove Unused Themes',
                'description' => sprintf(
                    'Found %d unused themes that could be potential security risks.',
                    count($unused_themes)
                ),
                'priority' => 'low',
                'action' => 'Delete unused themes, keep only the active theme and one default theme as backup'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Get server configuration recommendations
     */
    private function get_server_recommendations() {
        $recommendations = array();
        
        // Check PHP version
        $php_version = PHP_VERSION;
        $min_recommended = '7.4';
        
        if (version_compare($php_version, $min_recommended, '<')) {
            $recommendations[] = array(
                'title' => 'Update PHP Version',
                'description' => "PHP version {$php_version} is outdated. Minimum recommended version is {$min_recommended}.",
                'priority' => 'high',
                'action' => 'Update to PHP 8.0+ for better security and performance'
            );
        }
        
        // Check for security-related PHP settings
        $php_settings = array(
            'display_errors' => array(
                'recommended' => 'Off',
                'current' => ini_get('display_errors') ? 'On' : 'Off',
                'priority' => 'medium'
            ),
            'expose_php' => array(
                'recommended' => 'Off',
                'current' => ini_get('expose_php') ? 'On' : 'Off',
                'priority' => 'low'
            ),
            'allow_url_fopen' => array(
                'recommended' => 'Off',
                'current' => ini_get('allow_url_fopen') ? 'On' : 'Off',
                'priority' => 'medium'
            ),
            'allow_url_include' => array(
                'recommended' => 'Off',
                'current' => ini_get('allow_url_include') ? 'On' : 'Off',
                'priority' => 'high'
            )
        );
        
        foreach ($php_settings as $setting => $info) {
            if ($info['current'] !== $info['recommended']) {
                $recommendations[] = array(
                    'title' => "PHP Setting: {$setting}",
                    'description' => "PHP setting {$setting} is set to {$info['current']} but should be {$info['recommended']} for security.",
                    'priority' => $info['priority'],
                    'action' => "Set {$setting} = {$info['recommended']} in php.ini"
                );
            }
        }
        
        // Check file permissions on critical files
        $critical_files = array(
            ABSPATH . 'wp-config.php' => 0600,
            ABSPATH . '.htaccess' => 0644
        );
        
        foreach ($critical_files as $file => $recommended_perms) {
            if (file_exists($file)) {
                $current_perms = fileperms($file) & 0777;
                if ($current_perms !== $recommended_perms) {
                    $recommendations[] = array(
                        'title' => 'Fix File Permissions: ' . basename($file),
                        'description' => sprintf(
                            'File %s has permissions %o but should have %o.',
                            basename($file),
                            $current_perms,
                            $recommended_perms
                        ),
                        'priority' => 'medium',
                        'action' => sprintf('chmod %o %s', $recommended_perms, $file)
                    );
                }
            }
        }
        
        // Check for .htaccess security rules
        $htaccess_file = ABSPATH . '.htaccess';
        if (file_exists($htaccess_file)) {
            $htaccess_content = file_get_contents($htaccess_file);
            
            $security_rules = array(
                'ServerSignature Off' => 'Hide server signature',
                'Options -Indexes' => 'Prevent directory browsing',
                'Header always set X-Frame-Options DENY' => 'Prevent clickjacking',
                'Header always set X-Content-Type-Options nosniff' => 'Prevent MIME sniffing'
            );
            
            $missing_rules = array();
            foreach ($security_rules as $rule => $description) {
                if (strpos($htaccess_content, $rule) === false) {
                    $missing_rules[] = $description;
                }
            }
            
            if (!empty($missing_rules)) {
                $recommendations[] = array(
                    'title' => 'Add Security Headers to .htaccess',
                    'description' => 'Missing security headers in .htaccess: ' . implode(', ', $missing_rules),
                    'priority' => 'low',
                    'action' => 'Add security headers to .htaccess file for enhanced protection'
                );
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get latest WordPress version
     */
    private function get_latest_wordpress_version() {
        $version_check = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
        
        if (is_wp_error($version_check)) {
            return false;
        }
        
        $version_data = json_decode(wp_remote_retrieve_body($version_check), true);
        
        if (isset($version_data['offers'][0]['version'])) {
            return $version_data['offers'][0]['version'];
        }
        
        return false;
    }
    
    /**
     * Get recommendations for specific issue type
     */
    public function get_recommendation_for_issue_type($issue_type) {
        $recommendations = array(
            'malware_detected' => array(
                'immediate' => 'Isolate the infected file immediately to prevent further damage.',
                'investigation' => 'Analyze how the malware got there - check for vulnerabilities in plugins, themes, or server configuration.',
                'cleanup' => 'Remove or clean the malware. Consider restoring from a clean backup.',
                'prevention' => 'Update all software, change passwords, and implement stronger security measures.'
            ),
            'suspicious_code' => array(
                'immediate' => 'Review the suspicious code manually to determine if it\'s legitimate.',
                'investigation' => 'Check file modification dates and compare with known good versions.',
                'cleanup' => 'Remove or fix suspicious code patterns.',
                'prevention' => 'Implement file integrity monitoring and regular code reviews.'
            ),
            'obfuscated_code' => array(
                'immediate' => 'Decode the obfuscated content to understand its purpose.',
                'investigation' => 'Determine if the obfuscation is legitimate (some plugins use it) or malicious.',
                'cleanup' => 'Remove obfuscated code if it\'s malicious or replace with clean version.',
                'prevention' => 'Monitor for unauthorized file changes and use reputable plugins only.'
            ),
            'modified_core_file' => array(
                'immediate' => 'Compare the modified file with the original WordPress core file.',
                'investigation' => 'Determine if modifications are legitimate customizations or malicious changes.',
                'cleanup' => 'Restore original core file if modified maliciously.',
                'prevention' => 'Use child themes for customizations and avoid modifying core files.'
            ),
            'suspicious_location' => array(
                'immediate' => 'Move the file to an appropriate location or remove if unauthorized.',
                'investigation' => 'Determine how the file got to the suspicious location.',
                'cleanup' => 'Clean up any similar files in inappropriate locations.',
                'prevention' => 'Implement proper file upload restrictions and monitoring.'
            )
        );
        
        return isset($recommendations[$issue_type]) ? $recommendations[$issue_type] : array();
    }
    
    /**
     * Generate automated security report
     */
    public function generate_security_report() {
        $stats = $this->database->get_security_stats();
        $recommendations = $this->get_security_recommendations();
        $system_status = get_option('sentinelwp_system_status', array());
        
        $report = array(
            'generated_at' => current_time('mysql'),
            'summary' => array(
                'total_scans' => $stats['total_scans'] ?? 0,
                'unresolved_issues' => $stats['unresolved_issues'] ?? 0,
                'critical_issues' => $stats['critical_issues'] ?? 0,
                'last_scan' => $stats['last_scan']
            ),
            'system_status' => $system_status,
            'recommendations' => $recommendations,
            'security_score' => $this->calculate_security_score($stats, $recommendations)
        );
        
        return $report;
    }
    
    /**
     * Calculate security score based on issues and system status
     */
    private function calculate_security_score($stats, $recommendations) {
        $base_score = 100;
        
        // Deduct points for issues
        $critical_issues = $stats['critical_issues'] ?? 0;
        $total_issues = $stats['unresolved_issues'] ?? 0;
        
        $base_score -= ($critical_issues * 15); // -15 per critical issue
        $base_score -= (($total_issues - $critical_issues) * 5); // -5 per other issue
        
        // Deduct points for high priority recommendations
        foreach ($recommendations as $category => $recs) {
            foreach ($recs as $rec) {
                if ($rec['priority'] === 'critical') {
                    $base_score -= 10;
                } elseif ($rec['priority'] === 'high') {
                    $base_score -= 5;
                } elseif ($rec['priority'] === 'medium') {
                    $base_score -= 2;
                }
            }
        }
        
        // Ensure score doesn't go below 0
        $score = max(0, $base_score);
        
        // Determine grade
        if ($score >= 90) {
            $grade = 'A';
        } elseif ($score >= 80) {
            $grade = 'B';
        } elseif ($score >= 70) {
            $grade = 'C';
        } elseif ($score >= 60) {
            $grade = 'D';
        } else {
            $grade = 'F';
        }
        
        return array(
            'score' => $score,
            'grade' => $grade,
            'description' => $this->get_score_description($score)
        );
    }
    
    /**
     * Get description for security score
     */
    private function get_score_description($score) {
        if ($score >= 90) {
            return 'Excellent security posture with minimal issues.';
        } elseif ($score >= 80) {
            return 'Good security with some minor improvements needed.';
        } elseif ($score >= 70) {
            return 'Fair security with several issues to address.';
        } elseif ($score >= 60) {
            return 'Poor security with significant vulnerabilities.';
        } else {
            return 'Critical security issues requiring immediate attention.';
        }
    }
}
