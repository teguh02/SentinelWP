<?php
/**
 * SentinelWP AI Advisor Class
 * 
 * Integrates with Google Gemini API to provide AI-powered security analysis
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_AI_Advisor {
    
    private static $instance = null;
    private $database;
    private $api_base_url = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
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
        
        // Add AJAX handlers
        add_action('wp_ajax_sentinelwp_generate_ai_analysis', array($this, 'ajax_generate_analysis'));
        add_action('wp_ajax_sentinelwp_generate_ai_recommendations', array($this, 'ajax_generate_recommendations'));
    }
    
    /**
     * Check if AI advisor is enabled and configured
     */
    public function is_enabled() {
        return get_option('sentinelwp_gemini_enabled', false) && 
               !empty(get_option('sentinelwp_gemini_api_key', ''));
    }
    
    /**
     * Generate AI-powered security analysis
     */
    public function generate_security_analysis() {
        if (!$this->is_enabled()) {
            return new WP_Error('ai_disabled', 'AI Security Advisor is not enabled or configured.');
        }
        
        // Get current security data
        $stats = $this->database->get_security_stats();
        $unresolved_issues = $this->database->get_unresolved_issues();
        $system_status = get_option('sentinelwp_system_status', array());
        $recommendations = SentinelWP_Recommendations::instance();
        $security_recommendations = $recommendations->get_security_recommendations();
        
        // Prepare data for AI analysis
        $analysis_data = array(
            'wordpress_info' => array(
                'version' => get_bloginfo('version'),
                'site_url' => get_site_url(),
                'is_multisite' => is_multisite(),
                'active_theme' => wp_get_theme()->get('Name'),
                'active_plugins' => $this->get_active_plugins_summary()
            ),
            'security_stats' => $stats,
            'system_status' => $system_status,
            'recent_issues' => $this->format_issues_for_ai($unresolved_issues),
            'recommendations' => $this->format_recommendations_for_ai($security_recommendations)
        );
        
        // Generate AI analysis
        $analysis = $this->call_gemini_api($analysis_data);
        
        if (is_wp_error($analysis)) {
            return $analysis;
        }
        
        // Store analysis
        $this->store_analysis($analysis);
        
        return $analysis;
    }
    
    /**
     * Call Gemini API for security analysis
     */
    private function call_gemini_api($data) {
        $api_key = get_option('sentinelwp_gemini_api_key', '');
        $model = get_option('sentinelwp_gemini_model', 'gemini-2.5-flash');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Gemini API key is not configured.');
        }
        
        // Prepare the prompt
        $prompt = $this->build_analysis_prompt($data);
        
        // API request body
        $request_body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048
            ),
            'safetySettings' => array(
                array(
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                )
            )
        );
        
        // Make API request
        $response = wp_remote_post(
            $this->api_base_url . $model . ':generateContent?key=' . $api_key,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($request_body),
                'timeout' => 30
            )
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'Gemini API returned error: ' . $response_code . ' - ' . $response_body);
        }
        
        $response_data = json_decode($response_body, true);
        
        if (!isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('invalid_response', 'Invalid response from Gemini API');
        }
        
        return $response_data['candidates'][0]['content']['parts'][0]['text'];
    }
    
    /**
     * Build analysis prompt for Gemini API
     */
    private function build_analysis_prompt($data) {
        $prompt = "Analyze the following WordPress security and provide recommendations in professional English:\n\n";
        
        // WordPress info
        $prompt .= "=== WORDPRESS INFORMATION ===\n";
        $prompt .= "WordPress Version: " . $data['wordpress_info']['version'] . "\n";
        $prompt .= "Site URL: " . $data['wordpress_info']['site_url'] . "\n";
        $prompt .= "Multisite: " . ($data['wordpress_info']['is_multisite'] ? 'Yes' : 'No') . "\n";
        $prompt .= "Active Theme: " . $data['wordpress_info']['active_theme'] . "\n";
        $prompt .= "Active Plugins: " . implode(', ', $data['wordpress_info']['active_plugins']) . "\n\n";
        
        // Security statistics
        $prompt .= "=== SECURITY STATISTICS ===\n";
        $prompt .= "Total Scans: " . ($data['security_stats']['total_scans'] ?? 0) . "\n";
        $prompt .= "Total Issues: " . ($data['security_stats']['total_issues'] ?? 0) . "\n";
        $prompt .= "Unresolved Issues: " . ($data['security_stats']['unresolved_issues'] ?? 0) . "\n";
        $prompt .= "Critical Issues: " . ($data['security_stats']['critical_issues'] ?? 0) . "\n";
        
        if (isset($data['security_stats']['last_scan'])) {
            $last_scan = $data['security_stats']['last_scan'];
            $prompt .= "Last Scan: " . $last_scan->scan_time . " (Status: " . $last_scan->status . ")\n";
        }
        $prompt .= "\n";
        
        // System status
        $prompt .= "=== SYSTEM STATUS ===\n";
        $prompt .= "Scan Mode: " . ($data['system_status']['scan_engine_mode'] ?? 'heuristic') . "\n";
        $prompt .= "ClamAV: " . (($data['system_status']['clamav_installed'] ?? false) ? 'Installed' : 'Not Installed') . "\n";
        $prompt .= "PHP Exec Functions: " . (($data['system_status']['php_exec_enabled'] ?? false) ? 'Enabled' : 'Disabled') . "\n\n";
        
        // Recent issues
        if (!empty($data['recent_issues'])) {
            $prompt .= "=== RECENT ISSUES ===\n";
            foreach ($data['recent_issues'] as $issue) {
                $prompt .= "- " . $issue['type'] . " (" . $issue['severity'] . "): " . $issue['description'] . "\n";
            }
            $prompt .= "\n";
        }
        
        // Recommendations
        if (!empty($data['recommendations'])) {
            $prompt .= "=== SYSTEM RECOMMENDATIONS ===\n";
            foreach ($data['recommendations'] as $category => $recs) {
                $prompt .= ucwords(str_replace('_', ' ', $category)) . ":\n";
                foreach ($recs as $rec) {
                    $prompt .= "- [" . strtoupper($rec['priority']) . "] " . $rec['title'] . ": " . $rec['description'] . "\n";
                }
            }
            $prompt .= "\n";
        }
        
        $prompt .= "=== ANALYSIS INSTRUCTIONS ===\n";
        $prompt .= "Based on the WordPress security data above, provide a comprehensive analysis in English that includes:\n\n";
        $prompt .= "1. SECURITY CONDITION SUMMARY:\n";
        $prompt .= "   - Overall assessment (Excellent/Good/Fair/Poor/Critical)\n";
        $prompt .= "   - Current risk level\n";
        $prompt .= "   - Highlight the most critical issues\n\n";
        
        $prompt .= "2. DETAILED ANALYSIS:\n";
        $prompt .= "   - Security vulnerabilities found\n";
        $prompt .= "   - Potential attack vectors\n";
        $prompt .= "   - Impact if not addressed\n\n";
        
        $prompt .= "3. PRIORITY RECOMMENDATIONS:\n";
        $prompt .= "   - Immediate actions required\n";
        $prompt .= "   - Short-term fixes (1-2 weeks)\n";
        $prompt .= "   - Long-term improvements\n\n";
        
        $prompt .= "4. PREVENTION SUGGESTIONS:\n";
        $prompt .= "   - Best practices for routine maintenance\n";
        $prompt .= "   - Additional tools or plugins recommended\n";
        $prompt .= "   - Monitoring that should be implemented\n\n";
        
        $prompt .= "Use language that is easily understood by website administrators who may not be highly technical. ";
        $prompt .= "Provide actionable and specific explanations for each recommendation.";
        
        return $prompt;
    }
    
    /**
     * Format issues for AI analysis
     */
    private function format_issues_for_ai($issues) {
        $formatted = array();
        
        foreach ($issues as $issue) {
            $formatted[] = array(
                'type' => $issue->issue_type,
                'severity' => $issue->severity,
                'description' => $issue->description,
                'file_path' => basename($issue->file_path), // Only filename for privacy
                'created_at' => $issue->created_at
            );
        }
        
        return $formatted;
    }
    
    /**
     * Format recommendations for AI analysis
     */
    private function format_recommendations_for_ai($recommendations) {
        $formatted = array();
        
        foreach ($recommendations as $category => $recs) {
            $formatted[$category] = array();
            foreach ($recs as $rec) {
                $formatted[$category][] = array(
                    'title' => $rec['title'],
                    'description' => $rec['description'],
                    'priority' => $rec['priority']
                );
            }
        }
        
        return $formatted;
    }
    
    /**
     * Get active plugins summary
     */
    private function get_active_plugins_summary() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugins = get_plugins();
        $active_plugins = array();
        
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (is_plugin_active($plugin_file)) {
                $active_plugins[] = $plugin_data['Name'];
            }
        }
        
        return $active_plugins;
    }
    
    /**
     * Store AI analysis
     */
    private function store_analysis($analysis) {
        $model = get_option('sentinelwp_gemini_model', 'gemini-2.5-flash');
        
        $analysis_data = array(
            'analysis' => $analysis,
            'model' => $model,
            'created_at' => current_time('mysql')
        );
        
        // Store in WordPress options (could also use custom table)
        $stored_analyses = get_option('sentinelwp_ai_analyses', array());
        
        // Keep only last 10 analyses
        if (count($stored_analyses) >= 10) {
            $stored_analyses = array_slice($stored_analyses, -9, 9, true);
        }
        
        $stored_analyses[] = $analysis_data;
        update_option('sentinelwp_ai_analyses', $stored_analyses);
        
        // Log the analysis generation
        $this->database->insert_log(array(
            'action' => 'ai_analysis_generated',
            'details' => 'AI security analysis generated using ' . $model
        ));
    }
    
    /**
     * Get latest AI analysis
     */
    public function get_latest_analysis() {
        $analyses = get_option('sentinelwp_ai_analyses', array());
        
        if (empty($analyses)) {
            return null;
        }
        
        return end($analyses);
    }
    
    /**
     * Get all stored analyses
     */
    public function get_all_analyses() {
        return get_option('sentinelwp_ai_analyses', array());
    }
    
    /**
     * Clear all stored analyses
     */
    public function clear_analyses() {
        delete_option('sentinelwp_ai_analyses');
    }
    
    /**
     * Test Gemini API connection
     */
    public function test_api_connection() {
        $api_key = get_option('sentinelwp_gemini_api_key', '');
        $model = get_option('sentinelwp_gemini_model', 'gemini-2.5-flash');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API key is not configured.');
        }
        
        // Simple test request
        $request_body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => 'Test connection. Respond with "OK" if you can receive this message.'
                        )
                    )
                )
            )
        );
        
        $response = wp_remote_post(
            $this->api_base_url . $model . ':generateContent?key=' . $api_key,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($request_body),
                'timeout' => 15
            )
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'API returned error code: ' . $response_code);
        }
        
        return true;
    }
    
    /**
     * Generate threat analysis for specific issues
     */
    public function analyze_specific_threats($issue_ids) {
        if (!$this->is_enabled()) {
            return new WP_Error('ai_disabled', 'AI Security Advisor is not enabled.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'sentinelwp_issues';
        
        $placeholders = implode(',', array_fill(0, count($issue_ids), '%d'));
        $issues = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE id IN ($placeholders)", $issue_ids)
        );
        
        if (empty($issues)) {
            return new WP_Error('no_issues', 'No issues found for analysis.');
        }
        
        // Build specific threat analysis prompt
        $prompt = "Analyze the following specific security threats in English:\n\n";
        
        foreach ($issues as $issue) {
            $prompt .= "ISSUE #" . $issue->id . ":\n";
            $prompt .= "- Type: " . $issue->issue_type . "\n";
            $prompt .= "- Severity: " . $issue->severity . "\n";
            $prompt .= "- File: " . basename($issue->file_path) . "\n";
            $prompt .= "- Description: " . $issue->description . "\n";
            $prompt .= "- Discovered: " . $issue->created_at . "\n\n";
        }
        
        $prompt .= "Provide analysis for each issue that includes:\n";
        $prompt .= "1. Threat level and potential impact\n";
        $prompt .= "2. Possible exploitation methods by attackers\n";
        $prompt .= "3. Specific steps to resolve\n";
        $prompt .= "4. How to prevent similar issues in the future\n\n";
        $prompt .= "Use clear and actionable language.";
        
        return $this->call_gemini_api_simple($prompt);
    }
    
    /**
     * Simple Gemini API call for specific queries
     */
    private function call_gemini_api_simple($prompt) {
        $api_key = get_option('sentinelwp_gemini_api_key', '');
        $model = get_option('sentinelwp_gemini_model', 'gemini-2.5-flash');
        
        $request_body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            )
        );
        
        $response = wp_remote_post(
            $this->api_base_url . $model . ':generateContent?key=' . $api_key,
            array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($request_body),
                'timeout' => 30
            )
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['candidates'][0]['content']['parts'][0]['text'])) {
            return $response_body['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return new WP_Error('invalid_response', 'Invalid response from Gemini API');
    }
    
    /**
     * AJAX handler for generating AI analysis
     */
    public function ajax_generate_analysis() {
        check_ajax_referer('sentinelwp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->generate_security_analysis();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('AI analysis generated successfully');
        }
    }
    
    /**
     * Generate AI recommendations
     */
    public function generate_ai_recommendations() {
        SentinelWP_Logger::info('AI Recommendations generation started');
        
        // Check if Gemini is enabled and API key is configured
        if (!get_option('sentinelwp_gemini_enabled', false)) {
            SentinelWP_Logger::warning('AI Recommendations failed: Gemini AI is not enabled');
            return new WP_Error('gemini_disabled', 'Gemini AI is not enabled.');
        }
        
        $api_key = get_option('sentinelwp_gemini_api_key', '');
        if (empty($api_key)) {
            SentinelWP_Logger::error('AI Recommendations failed: No API key configured');
            return new WP_Error('no_api_key', 'Gemini API key is not configured.');
        }
        
        SentinelWP_Logger::debug('API key found, proceeding with data gathering', array(
            'api_key_length' => strlen($api_key),
            'api_key_preview' => substr($api_key, 0, 10) . '...'
        ));
        
        try {
            // Gather system data for recommendations
            SentinelWP_Logger::debug('Starting system data gathering');
            $data = $this->gather_system_data();
            SentinelWP_Logger::debug('System data gathered successfully', array(
                'data_keys' => array_keys($data),
                'wordpress_version' => $data['wordpress_info']['version'] ?? 'unknown',
                'total_plugins' => count($data['wordpress_info']['active_plugins'] ?? array())
            ));
            
            // Build recommendations prompt
            SentinelWP_Logger::debug('Building AI prompt');
            $prompt = $this->build_recommendations_prompt($data);
            $prompt_length = strlen($prompt);
            SentinelWP_Logger::debug('AI prompt built successfully', array(
                'prompt_length' => $prompt_length,
                'prompt_preview' => substr($prompt, 0, 200) . '...'
            ));
            
            // Call Gemini API
            SentinelWP_Logger::info('Calling Gemini API for recommendations');
            $response = $this->call_gemini_api_for_recommendations($prompt);
            
            if (is_wp_error($response)) {
                SentinelWP_Logger::error('Gemini API call failed', array(
                    'error_code' => $response->get_error_code(),
                    'error_message' => $response->get_error_message()
                ));
                return $response;
            }
            
            SentinelWP_Logger::info('Gemini API call successful', array(
                'response_type' => gettype($response),
                'response_length' => is_string($response) ? strlen($response) : 'non-string'
            ));
            
            // Parse and store recommendations
            SentinelWP_Logger::debug('Parsing AI recommendations');
            $recommendations = $this->parse_ai_recommendations($response);
            
            if (empty($recommendations)) {
                SentinelWP_Logger::warning('No recommendations parsed from AI response', array(
                    'response_preview' => is_string($response) ? substr($response, 0, 500) : 'non-string response'
                ));
            } else {
                SentinelWP_Logger::info('Recommendations parsed successfully', array(
                    'recommendation_count' => count($recommendations),
                    'recommendation_titles' => array_column($recommendations, 'title')
                ));
                
                // Store recommendations in database
                $database = SentinelWP_Database::instance();
                $stored_count = 0;
                
                foreach ($recommendations as $rec) {
                    SentinelWP_Logger::debug('Storing recommendation in database', array(
                        'title' => $rec['title'],
                        'category' => $rec['category'],
                        'priority' => $rec['priority']
                    ));
                    
                    $result = $database->insert_ai_recommendation($rec);
                    if ($result !== false) {
                        $stored_count++;
                    } else {
                        SentinelWP_Logger::error('Failed to store recommendation in database', array(
                            'recommendation' => $rec
                        ));
                    }
                }
                
                SentinelWP_Logger::info("Successfully stored {$stored_count} recommendations in database");
            }
            
            return $recommendations;
            
        } catch (Exception $e) {
            SentinelWP_Logger::critical('Exception during AI recommendations generation', array(
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ));
            return new WP_Error('exception', 'An error occurred during AI recommendations generation: ' . $e->getMessage());
        }
    }
    
    /**
     * Build prompt for AI recommendations
     */
    private function build_recommendations_prompt($data) {
        $prompt = "Based on the following WordPress security assessment, provide specific actionable recommendations in English. Focus on practical steps that can improve security:\n\n";
        
        // WordPress info
        $prompt .= "=== WORDPRESS ENVIRONMENT ===\n";
        $prompt .= "WordPress Version: " . $data['wordpress_info']['version'] . "\n";
        $prompt .= "Site URL: " . $data['wordpress_info']['site_url'] . "\n";
        $prompt .= "Active Theme: " . $data['wordpress_info']['active_theme'] . "\n";
        $prompt .= "Active Plugins Count: " . count($data['wordpress_info']['active_plugins']) . "\n\n";
        
        // Security statistics
        if (isset($data['security_stats'])) {
            $prompt .= "=== SECURITY STATUS ===\n";
            $prompt .= "Total Security Issues: " . ($data['security_stats']['total_issues'] ?? 0) . "\n";
            $prompt .= "Unresolved Issues: " . ($data['security_stats']['unresolved_issues'] ?? 0) . "\n";
            $prompt .= "Critical Issues: " . ($data['security_stats']['critical_issues'] ?? 0) . "\n\n";
        }
        
        // System status
        if (isset($data['system_status'])) {
            $prompt .= "=== SYSTEM CAPABILITIES ===\n";
            $prompt .= "ClamAV Available: " . (($data['system_status']['clamav_installed'] ?? false) ? 'Yes' : 'No') . "\n";
            $prompt .= "PHP Exec Functions: " . (($data['system_status']['php_exec_enabled'] ?? false) ? 'Enabled' : 'Disabled') . "\n\n";
        }
        
        $prompt .= "=== RECOMMENDATION REQUIREMENTS ===\n";
        $prompt .= "Please provide 5-8 specific security recommendations in this JSON format:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"category\": \"wordpress_core|plugins|themes|server|security_headers|file_permissions|user_management|backup\",\n";
        $prompt .= "    \"title\": \"Brief recommendation title\",\n";
        $prompt .= "    \"description\": \"Detailed explanation of the recommendation and why it's important\",\n";
        $prompt .= "    \"priority\": \"low|medium|high|critical\",\n";
        $prompt .= "    \"confidence_score\": 0.85\n";
        $prompt .= "  }\n";
        $prompt .= "]\n\n";
        $prompt .= "Focus on actionable items that can be implemented immediately. Prioritize based on security impact.";
        
        return $prompt;
    }
    
    /**
     * Call Gemini API for recommendations
     */
    private function call_gemini_api_for_recommendations($prompt) {
        $api_key = get_option('sentinelwp_gemini_api_key', '');
        $model = get_option('sentinelwp_gemini_model', 'gemini-2.5-flash');
        
        SentinelWP_Logger::debug('Preparing Gemini API request', array(
            'model' => $model,
            'api_key_length' => strlen($api_key),
            'prompt_length' => strlen($prompt)
        ));
        
        $request_body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048
            )
        );
        
        $api_url = $this->api_base_url . $model . ':generateContent?key=' . $api_key;
        $request_json = json_encode($request_body);
        
        SentinelWP_Logger::debug('Making API request to Gemini', array(
            'url' => substr($api_url, 0, strpos($api_url, '?key=')) . '?key=***',
            'request_body_size' => strlen($request_json),
            'timeout' => 30
        ));
        
        $response = wp_remote_post(
            $api_url,
            array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => $request_json,
                'timeout' => 30
            )
        );
        
        if (is_wp_error($response)) {
            SentinelWP_Logger::error('Gemini API HTTP request failed', array(
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message()
            ));
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        SentinelWP_Logger::debug('Gemini API HTTP response received', array(
            'status_code' => $response_code,
            'response_size' => strlen($response_body)
        ));
        
        if ($response_code !== 200) {
            SentinelWP_Logger::error('Gemini API returned non-200 status code', array(
                'status_code' => $response_code,
                'response_body' => substr($response_body, 0, 500)
            ));
            return new WP_Error('api_error', "Gemini API returned status code: {$response_code}");
        }
        
        $response_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            SentinelWP_Logger::error('Failed to parse Gemini API JSON response', array(
                'json_error' => json_last_error_msg(),
                'response_preview' => substr($response_body, 0, 500)
            ));
            return new WP_Error('json_error', 'Failed to parse API response as JSON: ' . json_last_error_msg());
        }
        
        SentinelWP_Logger::debug('Gemini API response parsed successfully', array(
            'response_keys' => array_keys($response_data),
            'has_candidates' => isset($response_data['candidates']),
            'candidate_count' => isset($response_data['candidates']) ? count($response_data['candidates']) : 0
        ));
        
        if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
            $ai_text = $response_data['candidates'][0]['content']['parts'][0]['text'];
            SentinelWP_Logger::info('Successfully extracted AI text from response', array(
                'text_length' => strlen($ai_text),
                'text_preview' => substr($ai_text, 0, 200)
            ));
            return $ai_text;
        }
        
        SentinelWP_Logger::error('Invalid Gemini API response structure', array(
            'response_structure' => $response_data,
            'missing_path' => 'candidates[0].content.parts[0].text'
        ));
        
        return new WP_Error('invalid_response', 'Invalid response from Gemini API');
    }
    
    /**
     * Parse AI recommendations from response
     */
    private function parse_ai_recommendations($response) {
        SentinelWP_Logger::debug('Starting to parse AI recommendations', array(
            'response_length' => strlen($response),
            'response_preview' => substr($response, 0, 300)
        ));
        
        // Extract JSON from the response
        $json_start = strpos($response, '[');
        $json_end = strrpos($response, ']') + 1;
        
        if ($json_start === false || $json_end === false) {
            SentinelWP_Logger::error('No JSON array found in AI response', array(
                'json_start_pos' => $json_start,
                'json_end_pos' => $json_end,
                'response' => $response
            ));
            return array();
        }
        
        $json_string = substr($response, $json_start, $json_end - $json_start);
        
        SentinelWP_Logger::debug('Extracted JSON string from response', array(
            'json_length' => strlen($json_string),
            'json_preview' => substr($json_string, 0, 500)
        ));
        
        $recommendations = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            SentinelWP_Logger::error('Failed to parse extracted JSON', array(
                'json_error' => json_last_error_msg(),
                'json_error_code' => json_last_error(),
                'json_string' => $json_string
            ));
            return array();
        }
        
        if (!is_array($recommendations)) {
            SentinelWP_Logger::error('Decoded JSON is not an array', array(
                'decoded_type' => gettype($recommendations),
                'decoded_data' => $recommendations
            ));
            return array();
        }
        
        SentinelWP_Logger::debug('JSON decoded successfully', array(
            'recommendation_count' => count($recommendations),
            'first_item_keys' => !empty($recommendations[0]) ? array_keys($recommendations[0]) : array()
        ));
        
        // Validate and clean recommendations
        $parsed_recommendations = array();
        foreach ($recommendations as $index => $rec) {
            if (!is_array($rec)) {
                SentinelWP_Logger::warning("Recommendation item $index is not an array", array(
                    'item_type' => gettype($rec),
                    'item_data' => $rec
                ));
                continue;
            }
            
            if (!isset($rec['title']) || !isset($rec['description'])) {
                SentinelWP_Logger::warning("Recommendation item $index missing required fields", array(
                    'available_keys' => array_keys($rec),
                    'has_title' => isset($rec['title']),
                    'has_description' => isset($rec['description']),
                    'item_data' => $rec
                ));
                continue;
            }
            
            $recommendation = array(
                'category' => $rec['category'] ?? 'general',
                'title' => sanitize_text_field($rec['title']),
                'description' => sanitize_textarea_field($rec['description']),
                'priority' => in_array($rec['priority'] ?? 'medium', array('low', 'medium', 'high', 'critical')) ? $rec['priority'] : 'medium',
                'recommendation_type' => 'security',
                'source' => 'ai',
                'status' => 'active',
                'confidence_score' => floatval($rec['confidence_score'] ?? 0.8)
            );
            
            $parsed_recommendations[] = $recommendation;
            
            SentinelWP_Logger::debug("Successfully parsed recommendation $index", array(
                'title' => $recommendation['title'],
                'category' => $recommendation['category'],
                'priority' => $recommendation['priority'],
                'confidence_score' => $recommendation['confidence_score']
            ));
        }
        
        SentinelWP_Logger::info('AI recommendations parsing completed', array(
            'total_items' => count($recommendations),
            'successfully_parsed' => count($parsed_recommendations),
            'failed_items' => count($recommendations) - count($parsed_recommendations)
        ));
        
        return $parsed_recommendations;
    }
    
    /**
     * AJAX handler for generating AI recommendations
     */
    public function ajax_generate_recommendations() {
        SentinelWP_Logger::info('AJAX request for AI recommendations received');
        
        try {
            check_ajax_referer('sentinelwp_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                SentinelWP_Logger::warning('Insufficient permissions for AI recommendations', array(
                    'user_id' => get_current_user_id(),
                    'user_capabilities' => wp_get_current_user()->allcaps ?? array()
                ));
                wp_send_json_error('Insufficient permissions');
            }
            
            SentinelWP_Logger::debug('Starting AI recommendations generation via AJAX');
            $result = $this->generate_ai_recommendations();
            
            if (is_wp_error($result)) {
                SentinelWP_Logger::error('AI recommendations generation failed via AJAX', array(
                    'error_code' => $result->get_error_code(),
                    'error_message' => $result->get_error_message()
                ));
                wp_send_json_error($result->get_error_message());
            } else {
                $count = count($result);
                SentinelWP_Logger::info("AI recommendations generation succeeded via AJAX", array(
                    'recommendation_count' => $count
                ));
                wp_send_json_success("Generated $count new AI recommendations successfully");
            }
            
        } catch (Exception $e) {
            SentinelWP_Logger::critical('Exception in AJAX AI recommendations handler', array(
                'exception_message' => $e->getMessage(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ));
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }
}
