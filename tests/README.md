# SentinelWP Testing Guide

## Overview

SentinelWP includes a comprehensive testing suite to ensure security features work correctly. The test suite covers:

- **AI Recommendations** - Tests Gemini API integration and security recommendations
- **Attack Detection** - Tests brute force detection, suspicious activity monitoring, and notification systems

## GitHub Actions Integration

The plugin includes automated testing workflows:

1. **Main Tests** (`.github/workflows/tests.yml`) - Full matrix testing across PHP versions
2. **Quick Tests** (`.github/workflows/quick-tests.yml`) - Fast feedback for development
3. **PR Tests** (`.github/workflows/pr-tests.yml`) - Detailed PR testing with comments and merge blocking

### Workflow Features
- 🔍 Syntax checking for PHP files
- 🧪 Complete test execution across PHP 7.4-8.3  
- 📊 Test result reporting with visual progress bars
- 💬 Automated PR comments with detailed results
- ⚡ Test artifact uploads for debugging
- 🚫 Merge blocking on test failures

## Quick Start

### Run All Tests
```bash
cd /path/to/sentinelwp/tests
php run-tests.php
```

### Run Specific Test Suite
```bash
# AI Recommendations only
php run-tests.php ai

# Attack Detection only
php run-tests.php attack
```

### Verbose Output
```bash
php run-tests.php --verbose
```

### Generate HTML Report
```bash
php run-tests.php --generate-report
```

## Test Suites

### 1. AI Recommendations Test Suite

**File:** `test-ai-recommendations.php`

**What it tests:**
- Gemini API response parsing for all model versions
- Recommendation priority extraction (critical, high, medium, low)
- Category classification (system_update, access_control, etc.)
- Confidence score calculation
- Error handling for invalid API responses
- Model-specific response differences

**Key Features:**
- Tests all 3 Gemini models: `gemini-2.5-flash`, `gemini-2.5-flash-lite`, `gemini-2.5-pro`
- Uses realistic fake API responses
- Validates recommendation structure and data quality
- Tests parsing algorithms for different response formats

**Sample Test Output:**
```
✓ PASS: Successfully parsed 5 recommendations from gemini-2.5-flash
✓ PASS: Correctly extracted priority 'critical' from: Critical security vulnerability detected
✓ PASS: Correctly categorized: Update WordPress core to latest version -> system_update
✓ PASS: Confidence score for critical priority: 0.95
```

### 2. Attack Detection Test Suite

**File:** `test-attack-detection.php`

**What it tests:**
- Brute force attack detection from single and multiple IPs
- Suspicious file access monitoring
- SQL injection and XSS pattern detection
- Attack threshold calculation and escalation
- Notification system (email alerts)
- Attack status management and expiration
- IP blocking and rate limiting
- False positive handling and whitelisting

**Key Features:**
- Simulates realistic attack scenarios
- Tests concurrent multi-vector attacks
- Validates mitigation measures
- Tests notification rate limiting
- Comprehensive false positive detection

**Sample Test Output:**
```
✓ PASS: Brute force attack detected after 8 failed attempts
✓ PASS: High threat detected for malicious file access: /etc/passwd
✓ PASS: SQL injection attempt detected: id=1' OR '1'='1...
✓ PASS: Attack notification sent successfully
✓ PASS: Coordinated attack detected successfully (threat score: 35)
```

## Fake Gemini Responses

**File:** `fixtures/gemini-responses.json`

This file contains realistic fake responses from each Gemini model version:

- **gemini-2.5-flash**: Balanced responses with good detail and structure
- **gemini-2.5-flash-lite**: Concise responses optimized for speed
- **gemini-2.5-pro**: Comprehensive responses with detailed analysis

Each response includes:
- Security analysis with prioritized recommendations
- Specific implementation commands
- Confidence scores and risk assessments
- Compliance and monitoring guidance

## Test Runner Features

**File:** `run-tests.php`

The test runner provides:

### Command Line Options
- `php run-tests.php` - Run all tests
- `php run-tests.php ai` - AI recommendations only  
- `php run-tests.php attack` - Attack detection only
- `--verbose` - Show detailed test output
- `--generate-report` - Create HTML report

### Features
- **Progress Tracking**: Real-time test progress with pass/fail indicators
- **Execution Timing**: Measures and reports test execution time
- **HTML Reports**: Generates professional test reports with charts
- **Error Handling**: Graceful handling of missing files or failed tests
- **Summary Statistics**: Comprehensive test result summaries

### Sample Output
```
SentinelWP Test Runner
=============================================================

Running AI Recommendations Test Suite
========================================
Tests Run: 12, Passed: 12, Failed: 0
✓ AI Recommendations tests completed in 0.45 seconds

Running Attack Detection Test Suite
===================================
Tests Run: 18, Passed: 17, Failed: 1
✓ Attack Detection tests completed in 0.78 seconds

=============================================================
OVERALL TEST SUMMARY
=============================================================
✓ Ai                  |  12 tests |  12 passed |   0 failed
✗ Attack              |  18 tests |  17 passed |   1 failed
------------------------------------------------------------
TOTALS                |  30 tests |  29 passed |   1 failed

Test Suites: 2 (1 passed)
Success Rate: 96.67%
Total Execution Time: 1.23 seconds

Overall Result: ✗ SOME TESTS FAILED
```

## HTML Test Reports

When using `--generate-report`, the test runner creates a professional HTML report (`test-report.html`) with:

- Executive summary with key metrics
- Visual progress indicators
- Detailed test output for each suite
- Responsive design for mobile and desktop
- Color-coded results (green for pass, red for fail)

## Test Architecture

### Mock WordPress Functions

Since these are standalone unit tests, WordPress functions are mocked:

```php
function update_option($option, $value) {
    $GLOBALS['wp_options'][$option] = $value;
    return true;
}

function wp_mail($to, $subject, $message) {
    $GLOBALS['test_emails'][] = compact('to', 'subject', 'message');
    return true;
}
```

### Test Data Management

- **Transients**: Used for time-based testing (attack status expiration)
- **Options**: Persistent settings simulation
- **Global Arrays**: Track test state (emails sent, blocked IPs)

### Assertion Methods

Custom assertion methods provide clear pass/fail feedback:

```php
private function pass($message) {
    $this->test_results[] = ['status' => 'PASS', 'message' => $message];
    echo "✓ PASS: $message\n";
}

private function fail($message) {
    $this->test_results[] = ['status' => 'FAIL', 'message' => $message];
    echo "✗ FAIL: $message\n";
}
```

## Integration with Plugin

### Manual Testing

Run tests manually during development:

```bash
cd wp-content/plugins/SentinelWP/tests
php test-ai-recommendations.php
php test-attack-detection.php
```

### CI/CD Integration

Add to your CI pipeline:

```yaml
# .github/workflows/tests.yml
- name: Run SentinelWP Tests
  run: |
    cd wp-content/plugins/SentinelWP/tests
    php run-tests.php --generate-report
```

### WordPress Integration

The tests can be integrated with WordPress test suite:

```php
// In your WordPress test file
require_once 'wp-content/plugins/SentinelWP/tests/test-ai-recommendations.php';
require_once 'wp-content/plugins/SentinelWP/tests/test-attack-detection.php';
```

## Best Practices

### Running Tests

1. **Before Deployment**: Always run full test suite
2. **After Changes**: Run specific test suite for modified features
3. **Regular Testing**: Weekly full test runs recommended
4. **Documentation**: Update tests when adding new features

### Extending Tests

1. **Add New Test Methods**: Follow naming convention `test_feature_name()`
2. **Mock External APIs**: Use fake responses for consistency
3. **Test Edge Cases**: Include error conditions and boundary values
4. **Maintain Test Data**: Keep fixtures updated with real-world scenarios

### Debugging Failed Tests

1. **Use Verbose Mode**: `php run-tests.php --verbose`
2. **Check Mock Data**: Verify fake responses are realistic
3. **Review Assertions**: Ensure test expectations match implementation
4. **Isolate Issues**: Run individual test methods when debugging

## File Structure

```
tests/
├── fixtures/
│   └── gemini-responses.json      # Fake API responses
├── test-ai-recommendations.php    # AI system tests
├── test-attack-detection.php      # Attack detection tests
├── run-tests.php                  # Test runner script
├── test-report.html              # Generated HTML report
└── README.md                     # This documentation
```

## Contributing

When adding new tests:

1. Follow existing naming conventions
2. Add comprehensive test coverage
3. Include both positive and negative test cases
4. Update this README with new test descriptions
5. Ensure tests run independently without dependencies

## Troubleshooting

### Common Issues

**Tests not running:**
- Check PHP CLI is installed: `php --version`
- Verify file permissions: `chmod +x run-tests.php`

**Missing fake responses:**
- Ensure `fixtures/gemini-responses.json` exists
- Check JSON syntax with: `php -m json`

**WordPress function errors:**
- Tests include WordPress function mocks
- No actual WordPress installation required

### Support

For test-related issues:
1. Check this README for guidance
2. Run with `--verbose` for detailed output
3. Review generated HTML reports for insights
4. Open issues on the SentinelWP GitHub repository

---

**Last Updated**: August 2025  
**Test Suite Version**: 1.0.0  
**Compatible with**: SentinelWP v1.0+
