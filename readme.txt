=== SentinelWP - Hybrid Security Scanner ===
Contributors: teguh02
Tags: security, malware, scanner, clamav, antivirus, vulnerability, protection, monitoring, firewall, security-scanner
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced hybrid security scanner for WordPress with ClamAV integration and AI-powered threat analysis using Google Gemini API.

== Description ==

SentinelWP is a comprehensive security solution for WordPress that combines traditional antivirus scanning with modern AI-powered threat detection. It offers dual scanning engines and intelligent monitoring to keep your WordPress site secure.

= Key Features =

* **Hybrid Scanning Engine**: Choose between ClamAV integration or advanced heuristic scanning
* **AI-Powered Analysis**: Integration with Google Gemini API for intelligent threat assessment
* **Real-time Monitoring**: Continuous protection with scheduled scanning capabilities
* **Comprehensive Coverage**: Scans WordPress core, themes, plugins, and uploads
* **Smart Exclusions**: Automatically excludes legitimate files to prevent false positives
* **Detailed Reporting**: In-depth scan results with actionable recommendations
* **WordPress Core Integrity**: Validates WordPress core files against official checksums
* **Security Configuration Check**: Analyzes WordPress security settings and configurations
* **Advanced Logging**: Comprehensive logging system for debugging and monitoring
* **User-Friendly Dashboard**: Intuitive interface for managing security scans and settings

= Scanning Capabilities =

* **Malware Detection**: Identifies known malware signatures and suspicious code patterns
* **Vulnerability Assessment**: Detects dangerous PHP functions and suspicious patterns
* **File Integrity Monitoring**: Monitors changes to critical WordPress files
* **Obfuscated Code Detection**: Identifies base64 encoded and other obfuscated malicious content
* **Suspicious File Location Analysis**: Detects files in inappropriate locations (e.g., PHP files in uploads)
* **File Permission Auditing**: Checks for insecure file and directory permissions
* **Hidden File Detection**: Identifies potentially malicious hidden files

= ClamAV Integration =

SentinelWP can integrate with ClamAV antivirus engine for enterprise-grade malware detection:

* Automatic ClamAV detection and configuration
* Fallback to direct scanning if daemon is unavailable
* Real-time virus database status monitoring
* Performance optimization with daemon mode

= Security Configurations =

* XML-RPC security assessment
* File editing permissions analysis
* Debug mode security evaluation
* wp-config.php permission auditing

= AI-Powered Recommendations =

* Intelligent threat analysis using Google Gemini API
* Contextual security recommendations
* Risk assessment and prioritization
* Automated security hardening suggestions

= Developer Features =

* Extensive logging and debugging capabilities
* Hooks and filters for customization
* RESTful API endpoints
* Database migration system
* Modular architecture for extensions

== Installation ==

1. Upload the `sentinelwp` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'SentinelWP' in your admin menu to configure settings
4. Run your first security scan from the Dashboard
5. (Optional) Install ClamAV on your server for enhanced malware detection
6. (Optional) Configure Google Gemini API key for AI-powered analysis

= Minimum Requirements =

* WordPress 5.0 or higher
* PHP 8.0 or higher
* MySQL 5.6 or higher (or MariaDB equivalent)
* At least 128MB PHP memory limit
* cURL support for API integrations

= Recommended Requirements =

* WordPress 6.0 or higher
* PHP 8.1 or higher
* 256MB+ PHP memory limit
* ClamAV installed on server
* Google Gemini API access

== Frequently Asked Questions ==

= Do I need to install ClamAV? =

No, ClamAV is optional. SentinelWP includes a built-in heuristic scanning engine that works without ClamAV. However, installing ClamAV provides enhanced malware detection capabilities.

= How often should I run scans? =

We recommend running full scans weekly and enabling automatic scanning for high-traffic sites. You can configure automatic scanning intervals in the plugin settings.

= Will this slow down my website? =

SentinelWP is designed to run scans in the background without affecting your site's performance. Scans are typically performed during low-traffic periods.

= What file types are scanned? =

The scanner examines PHP, JavaScript, HTML, CSS, text files, and .htaccess files. Binary files and images are generally excluded unless they contain suspicious patterns.

= How does the AI analysis work? =

When configured with a Google Gemini API key, SentinelWP can send anonymized code samples for advanced threat analysis and receive intelligent recommendations for security improvements.

= Can I exclude certain files or directories? =

Yes, you can configure exclusions in the plugin settings. The plugin automatically excludes its own files and provides options to exclude custom directories.

= Is my data kept private? =

Yes, all scanning is performed locally on your server. API integrations (like Google Gemini) only send anonymized code snippets when explicitly enabled and never transmit sensitive data.

= What happens if malware is found? =

SentinelWP will log all detected threats, provide detailed information about each issue, and offer recommendations for remediation. The plugin does not automatically delete files - all actions require your approval.

== Screenshots ==

1. Main dashboard showing scan status and recent activity
2. Scan results page with detailed threat analysis
3. Security recommendations and AI-powered suggestions
4. Settings panel for configuring scan options
5. ClamAV integration status and configuration
6. Real-time scanning progress with detailed logging

== Changelog ==

= 1.0.1 =
* Enhanced ClamAV logging and debugging capabilities
* Improved database schema validation and migration
* Added comprehensive PHP version compatibility checks
* Fixed progress bar display issues
* Enhanced scanner self-exclusion to prevent false positives
* Resolved settings tab conflicts
* Improved error handling and user feedback
* Added extensive logging for ClamAV installation verification
* Performance optimizations for large file scans

= 1.0.0 =
* Initial release
* Hybrid scanning engine with ClamAV integration
* AI-powered threat analysis with Google Gemini API
* WordPress core integrity checking
* Comprehensive security configuration analysis
* Real-time scanning with progress tracking
* Advanced logging and reporting system
* User-friendly dashboard interface
* Automatic scheduling capabilities
* Database migration and schema management

== Upgrade Notice ==

= 1.0.1 =
This update includes important bug fixes and enhanced logging capabilities. Recommended for all users, especially those using ClamAV integration.

== Support ==

For support, bug reports, and feature requests:

* GitHub Repository: https://github.com/teguh02/SentinelWP
* Documentation: Available in the plugin's help sections
* Community Support: WordPress.org support forums

== Privacy Policy ==

SentinelWP respects your privacy:

* All scanning is performed locally on your server
* No data is transmitted to external services without explicit configuration
* API integrations (Google Gemini) are optional and can be disabled
* Only anonymized code snippets are sent for AI analysis when enabled
* No personally identifiable information is collected or transmitted
* All logs and scan results are stored locally on your server

== Contributing ==

SentinelWP is open source and welcomes contributions:

* Report bugs and request features on GitHub
* Submit pull requests for improvements
* Translate the plugin into your language
* Help improve documentation

== License ==

This plugin is licensed under the GPLv2 or later license. You are free to use, modify, and distribute this plugin according to the terms of the GPL license.
