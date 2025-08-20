<?php
/**
 * SentinelWP Debug Test File
 * 
 * Run this to test the logging system and AI recommendations debugging
 */

// Simulate WordPress environment for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include the logger class
require_once 'includes/class-logger.php';

echo "=== SentinelWP Logging System Test ===\n\n";

try {
    // Test logging functionality
    echo "1. Testing basic logging...\n";
    SentinelWP_Logger::info('Logging system test started');
    SentinelWP_Logger::debug('This is a debug message', array('test_data' => 'sample'));
    SentinelWP_Logger::warning('This is a warning message');
    SentinelWP_Logger::error('This is an error message', array('error_code' => 500));
    echo "   ✓ Basic logging successful\n";

    echo "2. Testing AI-specific logging...\n";
    SentinelWP_Logger::log_ai_generation('test_generation', array(
        'test_mode' => true,
        'user_id' => 1
    ));
    echo "   ✓ AI generation logging successful\n";

    SentinelWP_Logger::log_api_request('Gemini', 'generateContent', 
        array('prompt' => 'test prompt'), 
        array('response' => 'test response')
    );
    echo "   ✓ API request logging successful\n";

    SentinelWP_Logger::log_api_error('Gemini', 'Test API error', array(
        'status_code' => 400,
        'response_body' => 'Bad request'
    ));
    echo "   ✓ API error logging successful\n";

    echo "3. Testing database logging...\n";
    SentinelWP_Logger::log_database_operation('INSERT', 'sentinelwp_ai_recommendations', array(
        'title' => 'Test recommendation',
        'priority' => 'high'
    ));
    echo "   ✓ Database operation logging successful\n";

    echo "\n✅ All logging tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error during logging test:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Log Files Information ===\n";

// Display current log files if they exist
$log_dir = dirname(__FILE__) . '/logs/';
if (is_dir($log_dir)) {
    $files = glob($log_dir . '*.log');
    if (!empty($files)) {
        echo "Current log files:\n";
        foreach ($files as $file) {
            echo "- " . basename($file) . " (" . number_format(filesize($file)) . " bytes)\n";
        }
        
        echo "\n=== Sample from latest log ===\n";
        $latest_file = array_reduce($files, function($latest, $file) {
            return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
        });
        
        $sample = file_get_contents($latest_file);
        $lines = explode("\n", $sample);
        $last_lines = array_slice($lines, -5, 5);
        foreach ($last_lines as $line) {
            if (trim($line)) {
                echo $line . "\n";
            }
        }
    } else {
        echo "No log files found yet.\n";
    }
} else {
    echo "Log directory will be created automatically when first log is written.\n";
}

echo "\n=== How to Debug AI Recommendations Issues ===\n";
echo "1. Go to WordPress Admin > SentinelWP > Logs\n";
echo "2. Try to generate AI recommendations\n";
echo "3. Check the logs page for detailed error information\n";
echo "4. Look for entries with 'AI Generation', 'API Request', or 'ERROR' labels\n";
echo "5. Download the error log files if needed\n\n";

echo "Common issues to look for in logs:\n";
echo "- API key validation errors\n";
echo "- Network connectivity issues\n";
echo "- JSON parsing errors from Gemini API\n";
echo "- Database insertion failures\n";
echo "- Permission issues\n\n";

echo "Log files are stored in: " . dirname(__FILE__) . "/logs/\n";

echo "\n=== Debug Instructions ===\n";
echo "1. Upload this updated plugin to your WordPress site\n";
echo "2. Activate the plugin\n";
echo "3. Try to generate AI recommendations (this will trigger logging)\n";
echo "4. Check WordPress Admin > SentinelWP > Logs for detailed error info\n";
echo "5. The logs will show exactly where and why the error occurs\n";

echo "\n=== End of Test ===\n";
?>
