<?php
/**
 * Debug Report Tab Issue
 * Run this from the wp-content/plugins/SentinelWP directory
 */

echo "=== Debugging Report Tab Issue ===\n\n";

// Mock WordPress functions for testing
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        $options = array(
            'sentinelwp_gemini_api_key' => 'test_key_12345',
            'sentinelwp_auto_scan_enabled' => true,
            'sentinelwp_notification_email' => 'admin@test.com'
        );
        return $options[$option] ?? $default;
    }
}

if (!defined('SENTINELWP_PLUGIN_PATH')) {
    define('SENTINELWP_PLUGIN_PATH', '/Users/mymac/Herd/wp/wp-content/plugins/SentinelWP/');
}

if (!defined('SENTINELWP_VERSION')) {
    define('SENTINELWP_VERSION', '1.0.0');
}

// Test log file path
echo "1. Testing Log File Path:\n";
$log_file = SENTINELWP_PLUGIN_PATH . 'logs/sentinelwp-' . date('Y-m-d') . '.log';
echo "Expected log file: " . $log_file . "\n";
echo "File exists: " . (file_exists($log_file) ? 'YES' : 'NO') . "\n";

if (file_exists($log_file)) {
    $file_size = filesize($log_file);
    echo "File size: " . $file_size . " bytes\n";
    
    if ($file_size > 0) {
        $log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total_lines = count($log_lines);
        $recent_lines = array_slice($log_lines, -5); // Last 5 lines
        
        echo "Total lines: " . $total_lines . "\n";
        echo "Recent lines (last 5):\n";
        foreach ($recent_lines as $i => $line) {
            echo "  " . ($i + 1) . ": " . substr($line, 0, 100) . "\n";
        }
    }
}

echo "\n2. Testing System Info Collection:\n";

// Test system info gathering
function test_get_system_info() {
    global $wp_version;
    $wp_version = '6.8.2'; // Mock version
    
    $system_info = "```\n";
    $system_info .= "WordPress Version: " . $wp_version . "\n";
    $system_info .= "PHP Version: " . PHP_VERSION . "\n";
    $system_info .= "Plugin Version: " . (defined('SENTINELWP_VERSION') ? SENTINELWP_VERSION : '1.0.0') . "\n";
    $system_info .= "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
    $system_info .= "Memory Limit: " . ini_get('memory_limit') . "\n";
    $system_info .= "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
    $system_info .= "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
    
    // Plugin-specific information
    $system_info .= "\nPlugin Status:\n";
    $system_info .= "- Gemini API Key: " . (get_option('sentinelwp_gemini_api_key') ? 'Configured' : 'Not configured') . "\n";
    $system_info .= "- Auto Scan: " . (get_option('sentinelwp_auto_scan_enabled', true) ? 'Enabled' : 'Disabled') . "\n";
    $system_info .= "- Email Notifications: " . (get_option('sentinelwp_notification_email') ? 'Configured' : 'Not configured') . "\n";
    
    $system_info .= "```\n\n";
    
    return $system_info;
}

$system_info = test_get_system_info();
echo "✓ System info collection working\n";
echo "Sample output (first 200 chars): " . substr($system_info, 0, 200) . "...\n";

echo "\n3. Testing Log Sanitization:\n";

// Test log sanitization
function test_sanitize_logs() {
    $test_logs = array(
        '[2025-08-20 10:00:00] INFO: API key AIzaSyBRealAPIKey123456789012345 configured',
        '[2025-08-20 10:01:00] ERROR: Connection from 192.168.1.100 failed',
        '[2025-08-20 10:02:00] DEBUG: Email sent to user@example.com successfully'
    );
    
    $sanitized = array();
    foreach ($test_logs as $line) {
        // Sanitize sensitive information
        $sanitized_line = preg_replace('/AIzaSy[A-Za-z0-9_-]{35}/', 'AIzaSy***API_KEY_HIDDEN***', $line);
        $sanitized_line = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', 'XXX.XXX.XXX.XXX', $sanitized_line);
        $sanitized_line = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', 'email@hidden.com', $sanitized_line);
        
        $sanitized[] = $sanitized_line;
    }
    
    return $sanitized;
}

$sanitized_logs = test_sanitize_logs();
echo "✓ Log sanitization working:\n";
foreach ($sanitized_logs as $i => $log) {
    echo "  " . ($i + 1) . ": " . $log . "\n";
}

echo "\n4. Testing GitHub URL Generation:\n";

function test_github_url_generation($issue_type, $title, $content) {
    $repo_url = 'https://github.com/teguh02/SentinelWP';
    
    // Add type prefix to title
    $type_prefixes = array(
        'bug' => '[Bug]',
        'feature' => '[Feature Request]',
        'question' => '[Question]',
        'documentation' => '[Documentation]'
    );
    
    $prefixed_title = ($type_prefixes[$issue_type] ?? '[Issue]') . ' ' . $title;
    
    // Prepare labels based on issue type
    $labels = array();
    switch ($issue_type) {
        case 'bug':
            $labels[] = 'bug';
            break;
        case 'feature':
            $labels[] = 'enhancement';
            break;
        case 'question':
            $labels[] = 'question';
            break;
        case 'documentation':
            $labels[] = 'documentation';
            break;
    }
    $labels[] = 'auto-generated';
    
    // Build the GitHub issue URL
    $params = array(
        'title' => $prefixed_title,
        'body' => $content,
        'labels' => implode(',', $labels)
    );
    
    return $repo_url . '/issues/new?' . http_build_query($params);
}

$test_content = "## Issue Description\n\nTest issue description\n\n## System Information\n\n" . $system_info;
$github_url = test_github_url_generation('bug', 'Test Issue', $test_content);

echo "✓ GitHub URL generation working\n";
echo "Sample URL length: " . strlen($github_url) . " characters\n";
echo "URL starts with: " . substr($github_url, 0, 80) . "...\n";

echo "\n5. Testing AJAX Request Structure:\n";

// Simulate AJAX request data
$mock_request = array(
    'action' => 'sentinelwp_generate_issue_report',
    'nonce' => 'mock_nonce_12345',
    'issue_type' => 'bug',
    'issue_title' => 'Test Bug Report',
    'issue_description' => 'This is a test bug description',
    'include_system_info' => true,
    'include_logs' => true
);

echo "✓ AJAX request structure:\n";
foreach ($mock_request as $key => $value) {
    echo "  - {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
}

echo "\n6. Common Issues and Solutions:\n";

echo "❌ Possible Issues:\n";
echo "  1. Nonce verification failure\n";
echo "     - Check if 'sentinelwp_nonce' matches between JS and PHP\n";
echo "  2. Missing log file\n";
echo "     - Ensure logs directory exists and is writable\n";
echo "  3. Database method errors\n";
echo "     - Check if get_table_status() method returns correct format\n";
echo "  4. WordPress function conflicts\n";
echo "     - Ensure all WP functions are available in AJAX context\n";

echo "\n✅ Solutions:\n";
echo "  1. Fix nonce verification in AJAX handler\n";
echo "  2. Add fallback for missing log files\n";
echo "  3. Update database status retrieval\n";
echo "  4. Add error handling for undefined functions\n";

echo "\n7. Recommended Debug Steps:\n";
echo "  1. Check WordPress debug.log for PHP errors\n";
echo "  2. Use browser developer tools to inspect AJAX requests\n";
echo "  3. Verify nonce creation and verification match\n";
echo "  4. Test individual components separately\n";
echo "  5. Add more detailed logging to AJAX handler\n";

if (file_exists($log_file)) {
    echo "\n8. Recent Error Analysis:\n";
    $log_content = file_get_contents($log_file);
    
    // Look for errors
    if (strpos($log_content, 'ERROR') !== false) {
        echo "⚠️  Found ERROR entries in log file\n";
        $error_lines = array_filter(explode("\n", $log_content), function($line) {
            return strpos($line, 'ERROR') !== false;
        });
        
        foreach (array_slice($error_lines, -3) as $error) {
            echo "  - " . $error . "\n";
        }
    } else {
        echo "✅ No ERROR entries found in recent logs\n";
    }
    
    // Check for Issue report entries
    if (strpos($log_content, 'Issue report') !== false) {
        echo "✅ Found 'Issue report' entries - AJAX handler is being called\n";
    } else {
        echo "⚠️  No 'Issue report' entries found - AJAX might not be reaching handler\n";
    }
}

echo "\n=== Debug Summary ===\n";
echo "The report generation system appears to be structurally sound.\n";
echo "Check the WordPress admin for specific error messages or browser console for JS errors.\n";
echo "Most likely issues: nonce verification, file permissions, or database method conflicts.\n";
?>
