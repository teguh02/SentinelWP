<?php
/**
 * SentinelWP Notifications Class
 * 
 * Handles email and Telegram notifications for security events
 */

if (!defined('ABSPATH')) {
    exit;
}

class SentinelWP_Notifications {
    
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
        
        // Hook into scan completion
        add_action('sentinelwp_scan_completed', array($this, 'handle_scan_notification'), 10, 1);
        
        // Hook into threat detection
        add_action('sentinelwp_threat_detected', array($this, 'handle_threat_notification'), 10, 2);
    }
    
    /**
     * Send scan completion notification
     */
    public function send_scan_notification($scan_result) {
        if (!get_option('sentinelwp_notify_scan_results', true)) {
            return;
        }
        
        $notification_data = array(
            'type' => 'scan_completed',
            'scan_result' => $scan_result,
            'timestamp' => current_time('mysql')
        );
        
        // Send email notification
        if (get_option('sentinelwp_notification_email')) {
            $this->send_email_notification($notification_data);
        }
        
        // Send Telegram notification
        if (get_option('sentinelwp_telegram_enabled', false)) {
            $this->send_telegram_notification($notification_data);
        }
    }
    
    /**
     * Send threat detection notification
     */
    public function send_threat_notification($issue_data, $scan_id = null) {
        if (!get_option('sentinelwp_notify_threats', true)) {
            return;
        }
        
        $notification_data = array(
            'type' => 'threat_detected',
            'issue' => $issue_data,
            'scan_id' => $scan_id,
            'timestamp' => current_time('mysql')
        );
        
        // Send email notification
        if (get_option('sentinelwp_notification_email')) {
            $this->send_email_notification($notification_data);
        }
        
        // Send Telegram notification
        if (get_option('sentinelwp_telegram_enabled', false)) {
            $this->send_telegram_notification($notification_data);
        }
    }
    
    /**
     * Send email notification
     */
    private function send_email_notification($data) {
        $email = get_option('sentinelwp_notification_email');
        if (empty($email)) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        switch ($data['type']) {
            case 'scan_completed':
                $subject = sprintf('[%s] Security Scan Completed', $site_name);
                $message = $this->build_scan_email_message($data, $site_name, $site_url);
                break;
                
            case 'threat_detected':
                $subject = sprintf('[%s] SECURITY ALERT - Threat Detected', $site_name);
                $message = $this->build_threat_email_message($data, $site_name, $site_url);
                break;
                
            default:
                return false;
        }
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: SentinelWP Security <noreply@' . parse_url($site_url, PHP_URL_HOST) . '>'
        );
        
        $sent = wp_mail($email, $subject, $message, $headers);
        
        // Log notification
        $this->database->insert_log(array(
            'action' => 'email_notification_sent',
            'details' => sprintf('Email notification sent to %s for %s', $email, $data['type'])
        ));
        
        return $sent;
    }
    
    /**
     * Build scan completion email message
     */
    private function build_scan_email_message($data, $site_name, $site_url) {
        $scan_result = $data['scan_result'];
        
        $status_color = array(
            'safe' => '#28a745',
            'warning' => '#ffc107',
            'critical' => '#dc3545'
        );
        
        $current_status = $scan_result['threats_found'] > 0 ? 
            ($scan_result['threats_found'] > 5 ? 'critical' : 'warning') : 
            'safe';
        
        $color = $status_color[$current_status] ?? '#6c757d';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Security Scan Report</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: <?php echo $color; ?>; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .stats { display: flex; justify-content: space-between; margin: 20px 0; }
                .stat { text-align: center; padding: 10px; background: white; border-radius: 5px; flex: 1; margin: 0 5px; }
                .stat-number { font-size: 24px; font-weight: bold; color: <?php echo $color; ?>; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                .button { display: inline-block; padding: 12px 24px; background: <?php echo $color; ?>; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🛡️ SentinelWP Security Report</h1>
                    <p><?php echo esc_html($site_name); ?></p>
                </div>
                
                <div class="content">
                    <h2>Scan Completed Successfully</h2>
                    <p><strong>Scan Time:</strong> <?php echo date_i18n('F j, Y g:i A', strtotime($data['timestamp'])); ?></p>
                    <p><strong>Scan Mode:</strong> <?php echo ucfirst($scan_result['mode']); ?></p>
                    
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-number"><?php echo $scan_result['files_scanned']; ?></div>
                            <div>Files Scanned</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><?php echo $scan_result['threats_found']; ?></div>
                            <div>Threats Found</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number"><?php echo ucfirst($current_status); ?></div>
                            <div>Security Status</div>
                        </div>
                    </div>
                    
                    <?php if ($scan_result['threats_found'] > 0): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <strong>⚠️ Action Required:</strong> Security threats were detected during the scan. 
                        Please review the scan results and take appropriate action immediately.
                    </div>
                    <?php else: ?>
                    <div style="background: #d1edff; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <strong>✅ All Clear:</strong> No security threats were detected in this scan. 
                        Your website appears to be secure.
                    </div>
                    <?php endif; ?>
                    
                    <p style="text-align: center;">
                        <a href="<?php echo admin_url('admin.php?page=sentinelwp-scan-results&scan_id=' . $scan_result['scan_id']); ?>" class="button">
                            View Detailed Results
                        </a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>This notification was sent by SentinelWP Security Plugin</p>
                    <p><a href="<?php echo $site_url; ?>"><?php echo $site_url; ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Build threat detection email message
     */
    private function build_threat_email_message($data, $site_name, $site_url) {
        $issue = $data['issue'];
        
        $severity_colors = array(
            'low' => '#17a2b8',
            'medium' => '#ffc107', 
            'high' => '#dc3545'
        );
        
        $color = $severity_colors[$issue['severity']] ?? '#6c757d';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Security Alert</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .alert { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .severity { display: inline-block; padding: 4px 8px; color: white; border-radius: 3px; font-size: 12px; font-weight: bold; background: <?php echo $color; ?>; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                .button { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚨 SECURITY ALERT</h1>
                    <p><?php echo esc_html($site_name); ?></p>
                </div>
                
                <div class="content">
                    <div class="alert">
                        <strong>⚠️ Immediate Action Required!</strong><br>
                        A security threat has been detected on your WordPress website.
                    </div>
                    
                    <h2>Threat Details</h2>
                    <table style="width: 100%; background: white; padding: 15px; border-radius: 5px;">
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Severity:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                                <span class="severity"><?php echo strtoupper($issue['severity']); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Threat Type:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                                <?php echo esc_html(str_replace('_', ' ', ucwords($issue['issue_type'], '_'))); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>File:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                                <code><?php echo esc_html(basename($issue['file_path'])); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Description:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #eee;">
                                <?php echo esc_html($issue['description']); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px;"><strong>Detected:</strong></td>
                            <td style="padding: 8px;">
                                <?php echo date_i18n('F j, Y g:i A', strtotime($data['timestamp'])); ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if (!empty($issue['recommendation'])): ?>
                    <h3>Recommended Action</h3>
                    <div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid <?php echo $color; ?>;">
                        <?php echo esc_html($issue['recommendation']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <p style="text-align: center;">
                        <a href="<?php echo admin_url('admin.php?page=sentinelwp-scan-results' . ($data['scan_id'] ? '&scan_id=' . $data['scan_id'] : '')); ?>" class="button">
                            View Full Report
                        </a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>This alert was sent by SentinelWP Security Plugin</p>
                    <p><strong>Do not ignore this alert.</strong> Take action immediately to secure your website.</p>
                    <p><a href="<?php echo $site_url; ?>"><?php echo $site_url; ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Send Telegram notification
     */
    private function send_telegram_notification($data) {
        $bot_token = get_option('sentinelwp_telegram_bot_token');
        $chat_id = get_option('sentinelwp_telegram_chat_id');
        
        if (empty($bot_token) || empty($chat_id)) {
            return false;
        }
        
        $message = $this->build_telegram_message($data);
        
        $telegram_api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        
        $post_data = array(
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        );
        
        $response = wp_remote_post($telegram_api_url, array(
            'body' => $post_data,
            'timeout' => 15
        ));
        
        $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200;
        
        // Log notification
        $this->database->insert_log(array(
            'action' => 'telegram_notification_sent',
            'details' => sprintf('Telegram notification %s for %s', $success ? 'sent' : 'failed', $data['type'])
        ));
        
        return $success;
    }
    
    /**
     * Build Telegram message
     */
    private function build_telegram_message($data) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        switch ($data['type']) {
            case 'scan_completed':
                return $this->build_telegram_scan_message($data, $site_name, $site_url);
                
            case 'threat_detected':
                return $this->build_telegram_threat_message($data, $site_name, $site_url);
                
            default:
                return "SentinelWP Security notification for {$site_name}";
        }
    }
    
    /**
     * Build Telegram scan completion message
     */
    private function build_telegram_scan_message($data, $site_name, $site_url) {
        $scan_result = $data['scan_result'];
        
        $status_emoji = array(
            0 => '✅', // No threats
            1 => '⚠️', // Few threats  
            5 => '🚨'  // Many threats
        );
        
        $emoji = '✅';
        if ($scan_result['threats_found'] > 5) {
            $emoji = '🚨';
        } elseif ($scan_result['threats_found'] > 0) {
            $emoji = '⚠️';
        }
        
        $message = "{$emoji} *SentinelWP Security Scan Report*\n\n";
        $message .= "*Website:* {$site_name}\n";
        $message .= "*Scan Time:* " . date_i18n('M j, Y g:i A', strtotime($data['timestamp'])) . "\n";
        $message .= "*Scan Mode:* " . ucfirst($scan_result['mode']) . "\n\n";
        
        $message .= "*Results:*\n";
        $message .= "📁 Files Scanned: `{$scan_result['files_scanned']}`\n";
        $message .= "🔍 Threats Found: `{$scan_result['threats_found']}`\n";
        
        if ($scan_result['threats_found'] > 0) {
            $message .= "\n⚠️ *Action Required!*\n";
            $message .= "Security threats detected. Please review immediately.\n";
        } else {
            $message .= "\n✅ *All Clear!*\n";
            $message .= "No security threats detected.\n";
        }
        
        $message .= "\n🔗 [View Detailed Results](" . admin_url('admin.php?page=sentinelwp-scan-results&scan_id=' . $scan_result['scan_id']) . ")";
        
        return $message;
    }
    
    /**
     * Build Telegram threat detection message
     */
    private function build_telegram_threat_message($data, $site_name, $site_url) {
        $issue = $data['issue'];
        
        $severity_emoji = array(
            'low' => '🔵',
            'medium' => '🟡', 
            'high' => '🔴'
        );
        
        $emoji = $severity_emoji[$issue['severity']] ?? '⚪';
        
        $message = "🚨 *SECURITY ALERT*\n\n";
        $message .= "*Website:* {$site_name}\n";
        $message .= "*Detected:* " . date_i18n('M j, Y g:i A', strtotime($data['timestamp'])) . "\n\n";
        
        $message .= "*Threat Details:*\n";
        $message .= "{$emoji} *Severity:* " . strtoupper($issue['severity']) . "\n";
        $message .= "🦠 *Type:* " . str_replace('_', ' ', ucwords($issue['issue_type'], '_')) . "\n";
        $message .= "📄 *File:* `" . basename($issue['file_path']) . "`\n";
        $message .= "📝 *Description:* " . $issue['description'] . "\n";
        
        if (!empty($issue['recommendation'])) {
            $message .= "\n💡 *Recommendation:*\n" . $issue['recommendation'] . "\n";
        }
        
        $message .= "\n🔗 [View Full Report](" . admin_url('admin.php?page=sentinelwp-scan-results' . ($data['scan_id'] ? '&scan_id=' . $data['scan_id'] : '')) . ")";
        $message .= "\n\n⚠️ *Take action immediately to secure your website!*";
        
        return $message;
    }
    
    /**
     * Test email notification
     */
    public function test_email_notification() {
        $test_data = array(
            'type' => 'test',
            'timestamp' => current_time('mysql')
        );
        
        $email = get_option('sentinelwp_notification_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] SentinelWP Test Notification', $site_name);
        $message = "<h2>SentinelWP Test Email</h2>";
        $message .= "<p>This is a test email from SentinelWP Security Plugin.</p>";
        $message .= "<p>If you receive this email, your notification settings are working correctly.</p>";
        $message .= "<p><strong>Site:</strong> {$site_name}<br>";
        $message .= "<strong>Time:</strong> " . date_i18n('F j, Y g:i A') . "</p>";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8'
        );
        
        return wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Test Telegram notification
     */
    public function test_telegram_notification() {
        $test_data = array(
            'type' => 'test',
            'timestamp' => current_time('mysql')
        );
        
        $site_name = get_bloginfo('name');
        
        $message = "🧪 *SentinelWP Test Notification*\n\n";
        $message .= "*Website:* {$site_name}\n";
        $message .= "*Time:* " . date_i18n('M j, Y g:i A') . "\n\n";
        $message .= "✅ If you receive this message, your Telegram notifications are working correctly!";
        
        $bot_token = get_option('sentinelwp_telegram_bot_token');
        $chat_id = get_option('sentinelwp_telegram_chat_id');
        
        if (empty($bot_token) || empty($chat_id)) {
            return false;
        }
        
        $telegram_api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        
        $response = wp_remote_post($telegram_api_url, array(
            'body' => array(
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ),
            'timeout' => 15
        ));
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200;
    }
    
    /**
     * Handle scan completion notification
     */
    public function handle_scan_notification($scan_result) {
        $this->send_scan_notification($scan_result);
    }
    
    /**
     * Handle threat detection notification
     */
    public function handle_threat_notification($issue_data, $scan_id = null) {
        // Only send immediate notifications for high severity threats
        if (in_array($issue_data['severity'], array('high', 'critical'))) {
            $this->send_threat_notification($issue_data, $scan_id);
        }
    }
    
    /**
     * Send weekly security summary
     */
    public function send_weekly_summary() {
        if (!get_option('sentinelwp_weekly_summary', false)) {
            return;
        }
        
        $stats = $this->database->get_security_stats();
        $recent_scans = $this->database->get_latest_scans(7);
        
        // Calculate weekly statistics
        $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        global $wpdb;
        
        $weekly_scans = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sentinelwp_scans WHERE scan_time >= %s",
                $week_ago
            )
        );
        
        $weekly_issues = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sentinelwp_issues WHERE created_at >= %s",
                $week_ago
            )
        );
        
        $summary_data = array(
            'type' => 'weekly_summary',
            'period' => '7 days',
            'stats' => array(
                'scans_performed' => $weekly_scans,
                'issues_found' => $weekly_issues,
                'total_unresolved' => $stats['unresolved_issues'],
                'critical_issues' => $stats['critical_issues']
            ),
            'timestamp' => current_time('mysql')
        );
        
        // Send notifications
        if (get_option('sentinelwp_notification_email')) {
            $this->send_email_notification($summary_data);
        }
        
        if (get_option('sentinelwp_telegram_enabled', false)) {
            $this->send_telegram_notification($summary_data);
        }
    }
    
    /**
     * Send attack notification
     */
    public function send_attack_notification($event_type, $ip_address, $description, $severity, $additional_data = array()) {
        // Check if under attack notifications are enabled
        if (!get_option('sentinelwp_notify_under_attack', true)) {
            return;
        }
        
        $notification_data = array(
            'type' => 'attack_detected',
            'event_type' => $event_type,
            'ip_address' => $ip_address,
            'description' => $description,
            'severity' => $severity,
            'additional_data' => $additional_data,
            'timestamp' => current_time('mysql'),
            'site_url' => home_url()
        );
        
        // Send email notification for high/critical severity attacks
        if (in_array($severity, array('high', 'critical')) && get_option('sentinelwp_notification_email')) {
            $this->send_attack_email_notification($notification_data);
        }
        
        // Send Telegram notification for all attacks if enabled
        if (get_option('sentinelwp_telegram_enabled', false)) {
            $this->send_attack_telegram_notification($notification_data);
        }
        
        // Log the notification
        SentinelWP_Logger::info('Attack notification sent', array(
            'event_type' => $event_type,
            'ip_address' => $ip_address,
            'severity' => $severity,
            'email_sent' => in_array($severity, array('high', 'critical')),
            'telegram_sent' => get_option('sentinelwp_telegram_enabled', false)
        ));
    }
    
    /**
     * Send attack email notification
     */
    private function send_attack_email_notification($data) {
        $to = get_option('sentinelwp_notification_email', get_option('admin_email'));
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Security Alert - %s Detected', 
            $site_name, 
            ucfirst(str_replace('_', ' ', $data['event_type']))
        );
        
        // Create email content
        $message = $this->format_attack_email_message($data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: SentinelWP <' . get_option('admin_email') . '>'
        );
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if (!$sent) {
            SentinelWP_Logger::error('Failed to send attack email notification', array(
                'to' => $to,
                'subject' => $subject,
                'event_type' => $data['event_type']
            ));
        }
        
        return $sent;
    }
    
    /**
     * Format attack email message
     */
    private function format_attack_email_message($data) {
        $severity_colors = array(
            'low' => '#28a745',
            'medium' => '#ffc107', 
            'high' => '#fd7e14',
            'critical' => '#dc3545'
        );
        
        $severity_color = $severity_colors[$data['severity']] ?? '#6c757d';
        
        $attack_types = array(
            'brute_force' => 'Brute Force Attack',
            'xmlrpc_abuse' => 'XML-RPC Abuse',
            'malicious_upload' => 'Malicious File Upload',
            'suspicious_attachment' => 'Suspicious Attachment Upload',
            'direct_php_creation' => 'Direct PHP File Creation'
        );
        
        $attack_name = $attack_types[$data['event_type']] ?? 'Security Incident';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Security Alert</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: <?php echo esc_attr($severity_color); ?>; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .alert-box { background: #f8f9fa; border-left: 4px solid <?php echo esc_attr($severity_color); ?>; padding: 15px; margin: 20px 0; }
                .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .details-table th, .details-table td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #e9ecef; }
                .details-table th { background: #f8f9fa; font-weight: bold; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; }
                .button { display: inline-block; padding: 12px 24px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚨 Security Alert</h1>
                    <h2><?php echo esc_html($attack_name); ?></h2>
                </div>
                
                <div class="content">
                    <div class="alert-box">
                        <strong>⚠️ <?php echo esc_html(strtoupper($data['severity'])); ?> SEVERITY ALERT</strong><br>
                        <?php echo esc_html($data['description']); ?>
                    </div>
                    
                    <table class="details-table">
                        <tr>
                            <th>Attack Type</th>
                            <td><?php echo esc_html($attack_name); ?></td>
                        </tr>
                        <tr>
                            <th>IP Address</th>
                            <td><?php echo esc_html($data['ip_address']); ?></td>
                        </tr>
                        <tr>
                            <th>Severity</th>
                            <td><strong style="color: <?php echo esc_attr($severity_color); ?>"><?php echo esc_html(strtoupper($data['severity'])); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Time</th>
                            <td><?php echo esc_html($data['timestamp']); ?></td>
                        </tr>
                        <tr>
                            <th>Website</th>
                            <td><a href="<?php echo esc_url($data['site_url']); ?>"><?php echo esc_html($data['site_url']); ?></a></td>
                        </tr>
                    </table>
                    
                    <?php if (!empty($data['additional_data'])): ?>
                    <h3>Additional Information:</h3>
                    <ul>
                        <?php foreach ($data['additional_data'] as $key => $value): ?>
                            <li><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> <?php echo esc_html($value); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sentinelwp-notifications')); ?>" class="button">
                            View Security Dashboard
                        </a>
                    </div>
                    
                    <div class="alert-box">
                        <strong>Recommended Actions:</strong>
                        <ul>
                            <?php if ($data['event_type'] === 'brute_force'): ?>
                                <li>Consider blocking IP <?php echo esc_html($data['ip_address']); ?> at the firewall level</li>
                                <li>Review user account security and enforce strong passwords</li>
                                <li>Enable two-factor authentication if not already active</li>
                            <?php elseif ($data['event_type'] === 'xmlrpc_abuse'): ?>
                                <li>Consider disabling XML-RPC if not needed</li>
                                <li>Block IP <?php echo esc_html($data['ip_address']); ?> or limit XML-RPC access</li>
                            <?php elseif (in_array($data['event_type'], array('malicious_upload', 'direct_php_creation'))): ?>
                                <li>The suspicious file has been automatically quarantined</li>
                                <li>Review file upload permissions and restrictions</li>
                                <li>Scan for other potentially malicious files</li>
                            <?php endif; ?>
                            <li>Monitor your website for any unusual activity</li>
                            <li>Check your access logs for related suspicious activity</li>
                        </ul>
                    </div>
                </div>
                
                <div class="footer">
                    <p>This alert was generated by SentinelWP Security Plugin</p>
                    <p>If you believe this is a false positive, please review the security logs or contact support.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Send attack Telegram notification
     */
    private function send_attack_telegram_notification($data) {
        $bot_token = get_option('sentinelwp_telegram_bot_token');
        $chat_id = get_option('sentinelwp_telegram_chat_id');
        
        if (empty($bot_token) || empty($chat_id)) {
            SentinelWP_Logger::warning('Telegram notification skipped - missing bot token or chat ID');
            return false;
        }
        
        $severity_emojis = array(
            'low' => '🟡',
            'medium' => '🟠', 
            'high' => '🔴',
            'critical' => '🚨'
        );
        
        $severity_emoji = $severity_emojis[$data['severity']] ?? '⚪';
        
        $attack_types = array(
            'brute_force' => '🔓 Brute Force Attack',
            'xmlrpc_abuse' => '🤖 XML-RPC Abuse',
            'malicious_upload' => '☠️ Malicious Upload',
            'suspicious_attachment' => '📎 Suspicious File',
            'direct_php_creation' => '💀 Direct PHP Creation'
        );
        
        $attack_name = $attack_types[$data['event_type']] ?? '⚠️ Security Incident';
        
        $message = sprintf(
            "%s *SECURITY ALERT* %s\n\n" .
            "*%s*\n" .
            "%s\n\n" .
            "🌐 *Site:* %s\n" .
            "🌍 *IP:* `%s`\n" .
            "⚡ *Severity:* %s\n" .
            "🕐 *Time:* %s\n\n" .
            "[View Dashboard](%s)",
            $severity_emoji,
            $severity_emoji,
            $attack_name,
            $data['description'],
            parse_url($data['site_url'], PHP_URL_HOST),
            $data['ip_address'],
            strtoupper($data['severity']),
            $data['timestamp'],
            admin_url('admin.php?page=sentinelwp-notifications')
        );
        
        $telegram_data = array(
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        );
        
        $response = wp_remote_post('https://api.telegram.org/bot' . $bot_token . '/sendMessage', array(
            'body' => $telegram_data,
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            SentinelWP_Logger::error('Failed to send Telegram attack notification', array(
                'error' => $response->get_error_message(),
                'event_type' => $data['event_type']
            ));
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (!isset($result['ok']) || !$result['ok']) {
            SentinelWP_Logger::error('Telegram API error for attack notification', array(
                'response' => $result,
                'event_type' => $data['event_type']
            ));
            return false;
        }
        
        return true;
    }
}
