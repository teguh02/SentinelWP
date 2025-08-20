<?php
/**
 * SentinelWP Test Runner
 * 
 * Runs all test suites for the SentinelWP plugin
 * 
 * Usage:
 * php run-tests.php                    # Run all tests
 * php run-tests.php ai                 # Run only AI recommendation tests
 * php run-tests.php attack             # Run only attack detection tests
 * php run-tests.php --verbose          # Run with detailed output
 * php run-tests.php --generate-report  # Generate HTML test report
 * 
 * @package SentinelWP
 * @subpackage Tests
 */

class SentinelWP_Test_Runner {
    
    private $verbose = false;
    private $generate_report = false;
    private $test_results = [];
    private $start_time;
    
    public function __construct($args = []) {
        $this->start_time = microtime(true);
        $this->parse_arguments($args);
        $this->print_header();
    }
    
    /**
     * Main test runner method
     */
    public function run($test_suite = 'all') {
        echo "Initializing SentinelWP Test Suite...\n";
        echo "Test Suite: " . ucfirst($test_suite) . "\n";
        echo "Verbose Mode: " . ($this->verbose ? 'ON' : 'OFF') . "\n";
        echo str_repeat("-", 60) . "\n\n";
        
        $test_suites = $this->get_test_suites();
        
        if ($test_suite === 'all') {
            foreach ($test_suites as $suite_name => $suite_info) {
                $this->run_test_suite($suite_name, $suite_info);
            }
        } elseif (isset($test_suites[$test_suite])) {
            $this->run_test_suite($test_suite, $test_suites[$test_suite]);
        } else {
            echo "Error: Unknown test suite '$test_suite'\n";
            echo "Available test suites: " . implode(', ', array_keys($test_suites)) . "\n";
            return false;
        }
        
        $this->print_summary();
        
        if ($this->generate_report) {
            $this->generate_html_report();
        }
        
        return true;
    }
    
    /**
     * Parse command line arguments
     */
    private function parse_arguments($args) {
        foreach ($args as $arg) {
            switch ($arg) {
                case '--verbose':
                case '-v':
                    $this->verbose = true;
                    break;
                case '--generate-report':
                case '--report':
                    $this->generate_report = true;
                    break;
            }
        }
    }
    
    /**
     * Get available test suites
     */
    private function get_test_suites() {
        return [
            'ai' => [
                'name' => 'AI Recommendations',
                'file' => 'test-ai-recommendations.php',
                'class' => 'SentinelWP_AI_Recommendations_Test',
                'description' => 'Tests AI-powered security recommendations'
            ],
            'attack' => [
                'name' => 'Attack Detection',
                'file' => 'test-attack-detection.php',
                'class' => 'SentinelWP_Attack_Detection_Test',
                'description' => 'Tests attack detection and monitoring systems'
            ]
        ];
    }
    
    /**
     * Run individual test suite
     */
    private function run_test_suite($suite_name, $suite_info) {
        echo "Running {$suite_info['name']} Test Suite\n";
        echo str_repeat("=", 40) . "\n";
        
        $test_file = dirname(__FILE__) . '/' . $suite_info['file'];
        
        if (!file_exists($test_file)) {
            echo "ERROR: Test file not found: {$suite_info['file']}\n\n";
            $this->test_results[$suite_name] = [
                'status' => 'ERROR',
                'message' => 'Test file not found',
                'tests_run' => 0,
                'tests_passed' => 0,
                'execution_time' => 0
            ];
            return false;
        }
        
        $suite_start_time = microtime(true);
        
        // Ensure WordPress functions are mocked before including test files
        $this->setup_wordpress_mocks();
        
        // Capture output
        if (!$this->verbose) {
            ob_start();
        }
        
        try {
            // Include and run the test
            require_once $test_file;
            
            if (class_exists($suite_info['class'])) {
                $test_instance = new $suite_info['class']();
                
                if (method_exists($test_instance, 'run_all_tests')) {
                    $test_instance->run_all_tests();
                    $success = true;
                } else {
                    throw new Exception("run_all_tests method not found in {$suite_info['class']}");
                }
            } else {
                throw new Exception("Test class {$suite_info['class']} not found");
            }
            
        } catch (Exception $e) {
            $success = false;
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        
        if (!$this->verbose) {
            $output = ob_get_clean();
            $this->parse_test_output($suite_name, $output);
        }
        
        $suite_execution_time = microtime(true) - $suite_start_time;
        
        if ($success) {
            echo "✓ {$suite_info['name']} tests completed in " . 
                  round($suite_execution_time, 2) . " seconds\n\n";
        } else {
            echo "✗ {$suite_info['name']} tests failed\n\n";
        }
        
        return $success;
    }
    
    /**
     * Setup WordPress function mocks
     */
    private function setup_wordpress_mocks() {
        if (!function_exists('update_option')) {
            $GLOBALS['wp_options'] = array();
            $GLOBALS['wp_transients'] = array();
            
            function update_option($option, $value) {
                $GLOBALS['wp_options'][$option] = $value;
                return true;
            }
            
            function get_option($option, $default = false) {
                return isset($GLOBALS['wp_options'][$option]) ? 
                       $GLOBALS['wp_options'][$option] : $default;
            }
            
            function delete_option($option) {
                unset($GLOBALS['wp_options'][$option]);
                return true;
            }
            
            function set_transient($transient, $value, $expiration) {
                $GLOBALS['wp_transients'][$transient] = [
                    'value' => $value,
                    'expiration' => time() + $expiration
                ];
                return true;
            }
            
            function get_transient($transient) {
                if (!isset($GLOBALS['wp_transients'][$transient])) {
                    return false;
                }
                
                $data = $GLOBALS['wp_transients'][$transient];
                if ($data['expiration'] < time()) {
                    unset($GLOBALS['wp_transients'][$transient]);
                    return false;
                }
                
                return $data['value'];
            }
            
            function delete_transient($transient) {
                unset($GLOBALS['wp_transients'][$transient]);
                return true;
            }
            
            function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
                if (!isset($GLOBALS['test_emails'])) {
                    $GLOBALS['test_emails'] = [];
                }
                $GLOBALS['test_emails'][] = [
                    'to' => $to,
                    'subject' => $subject,
                    'message' => $message,
                    'headers' => $headers
                ];
                return true;
            }
            
            function current_time($type = 'mysql', $gmt = 0) {
                return date('Y-m-d H:i:s');
            }
            
            function human_time_diff($from, $to = null) {
                if ($to === null) $to = time();
                $diff = abs($to - $from);
                
                if ($diff < 60) return $diff . ' seconds';
                if ($diff < 3600) return floor($diff / 60) . ' minutes';
                if ($diff < 86400) return floor($diff / 3600) . ' hours';
                return floor($diff / 86400) . ' days';
            }
        }
    }
    
    /**
     * Parse test output to extract results
     */
    private function parse_test_output($suite_name, $output) {
        $lines = explode("\n", $output);
        $tests_passed = 0;
        $tests_failed = 0;
        $total_tests = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, '✓ PASS:') === 0) {
                $tests_passed++;
            } elseif (strpos($line, '✗ FAIL:') === 0) {
                $tests_failed++;
            } elseif (preg_match('/Total Tests: (\d+)/', $line, $matches)) {
                $total_tests = (int)$matches[1];
            } elseif (preg_match('/Passed: (\d+)/', $line, $matches)) {
                $tests_passed = (int)$matches[1];
            } elseif (preg_match('/Failed: (\d+)/', $line, $matches)) {
                $tests_failed = (int)$matches[1];
            }
        }
        
        if ($total_tests === 0) {
            $total_tests = $tests_passed + $tests_failed;
        }
        
        $this->test_results[$suite_name] = [
            'status' => $tests_failed === 0 ? 'PASS' : 'FAIL',
            'tests_run' => $total_tests,
            'tests_passed' => $tests_passed,
            'tests_failed' => $tests_failed,
            'output' => $output
        ];
        
        // Show summary even in non-verbose mode
        echo "Tests Run: $total_tests, Passed: $tests_passed, Failed: $tests_failed\n";
        if ($tests_failed > 0) {
            echo "Failed tests detected - run with --verbose for details\n";
        }
    }
    
    /**
     * Print overall test summary
     */
    private function print_summary() {
        echo str_repeat("=", 60) . "\n";
        echo "OVERALL TEST SUMMARY\n";
        echo str_repeat("=", 60) . "\n";
        
        $total_suites = count($this->test_results);
        $passed_suites = 0;
        $total_tests = 0;
        $total_passed = 0;
        $total_failed = 0;
        
        foreach ($this->test_results as $suite_name => $result) {
            if ($result['status'] === 'PASS') {
                $passed_suites++;
            }
            
            $total_tests += $result['tests_run'] ?? 0;
            $total_passed += $result['tests_passed'] ?? 0;
            $total_failed += $result['tests_failed'] ?? 0;
            
            $status_icon = $result['status'] === 'PASS' ? '✓' : '✗';
            echo sprintf(
                "%s %-20s | %3d tests | %3d passed | %3d failed\n",
                $status_icon,
                ucfirst($suite_name),
                $result['tests_run'] ?? 0,
                $result['tests_passed'] ?? 0,
                $result['tests_failed'] ?? 0
            );
        }
        
        echo str_repeat("-", 60) . "\n";
        echo sprintf(
            "%-22s | %3d tests | %3d passed | %3d failed\n",
            "TOTALS",
            $total_tests,
            $total_passed,
            $total_failed
        );
        
        $execution_time = microtime(true) - $this->start_time;
        echo "\nTest Suites: $total_suites ($passed_suites passed)\n";
        echo "Success Rate: " . ($total_tests > 0 ? round(($total_passed / $total_tests) * 100, 2) : 0) . "%\n";
        echo "Total Execution Time: " . round($execution_time, 2) . " seconds\n";
        
        // Overall result
        $overall_success = ($total_failed === 0 && $passed_suites === $total_suites);
        echo "\nOverall Result: " . ($overall_success ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED") . "\n";
        
        echo "\nTest run completed at " . date('Y-m-d H:i:s') . "\n";
    }
    
    /**
     * Generate HTML test report
     */
    private function generate_html_report() {
        echo "\nGenerating HTML test report...\n";
        
        $report_file = dirname(__FILE__) . '/test-report.html';
        $html = $this->build_html_report();
        
        if (file_put_contents($report_file, $html)) {
            echo "✓ HTML report generated: $report_file\n";
        } else {
            echo "✗ Failed to generate HTML report\n";
        }
    }
    
    /**
     * Build HTML report content
     */
    private function build_html_report() {
        $execution_time = microtime(true) - $this->start_time;
        $total_tests = array_sum(array_column($this->test_results, 'tests_run'));
        $total_passed = array_sum(array_column($this->test_results, 'tests_passed'));
        $total_failed = array_sum(array_column($this->test_results, 'tests_failed'));
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SentinelWP Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .summary { background: #ecf0f1; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .stats { display: flex; justify-content: space-around; text-align: center; }
        .stat { background: white; padding: 15px; border-radius: 5px; min-width: 120px; }
        .stat-number { font-size: 2em; font-weight: bold; }
        .stat-label { color: #7f8c8d; font-size: 0.9em; }
        .pass { color: #27ae60; }
        .fail { color: #e74c3c; }
        .suite { margin: 30px 0; border: 1px solid #ddd; border-radius: 5px; }
        .suite-header { background: #34495e; color: white; padding: 15px; font-weight: bold; }
        .suite-content { padding: 20px; }
        .test-output { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 0.9em; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
        .timestamp { color: #95a5a6; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SentinelWP Test Report</h1>
        <p class="timestamp">Generated on ' . date('Y-m-d H:i:s') . '</p>
        
        <div class="summary">
            <div class="stats">
                <div class="stat">
                    <div class="stat-number">' . $total_tests . '</div>
                    <div class="stat-label">Total Tests</div>
                </div>
                <div class="stat">
                    <div class="stat-number pass">' . $total_passed . '</div>
                    <div class="stat-label">Passed</div>
                </div>
                <div class="stat">
                    <div class="stat-number fail">' . $total_failed . '</div>
                    <div class="stat-label">Failed</div>
                </div>
                <div class="stat">
                    <div class="stat-number">' . round($execution_time, 2) . 's</div>
                    <div class="stat-label">Execution Time</div>
                </div>
                <div class="stat">
                    <div class="stat-number">' . ($total_tests > 0 ? round(($total_passed / $total_tests) * 100, 1) : 0) . '%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
        </div>';
        
        foreach ($this->test_results as $suite_name => $result) {
            $status_class = $result['status'] === 'PASS' ? 'pass' : 'fail';
            $status_icon = $result['status'] === 'PASS' ? '✓' : '✗';
            
            $html .= '
        <div class="suite">
            <div class="suite-header ' . $status_class . '">
                ' . $status_icon . ' ' . ucfirst($suite_name) . ' Test Suite
                <span style="float: right; font-weight: normal;">
                    ' . ($result['tests_run'] ?? 0) . ' tests, ' . 
                    ($result['tests_passed'] ?? 0) . ' passed, ' . 
                    ($result['tests_failed'] ?? 0) . ' failed
                </span>
            </div>
            <div class="suite-content">
                <div class="test-output">' . htmlspecialchars($result['output'] ?? 'No output captured') . '</div>
            </div>
        </div>';
        }
        
        $html .= '
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Print header information
     */
    private function print_header() {
        echo str_repeat("=", 60) . "\n";
        echo "SentinelWP Plugin Test Suite\n";
        echo "Version: 1.0.0\n";
        echo "Started: " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat("=", 60) . "\n\n";
    }
}

// Command line execution
if (php_sapi_name() === 'cli') {
    $args = array_slice($argv, 1);
    
    // Extract test suite from arguments
    $test_suite = 'all';
    $runner_args = [];
    
    foreach ($args as $arg) {
        if (in_array($arg, ['ai', 'attack', 'all'])) {
            $test_suite = $arg;
        } else {
            $runner_args[] = $arg;
        }
    }
    
    echo "SentinelWP Test Runner\n";
    echo "Usage: php run-tests.php [ai|attack|all] [--verbose] [--generate-report]\n\n";
    
    $runner = new SentinelWP_Test_Runner($runner_args);
    $runner->run($test_suite);
}
?>
