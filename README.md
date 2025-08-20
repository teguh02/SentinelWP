# SentinelWP - Hybrid Security Scanner

Plugin keamanan WordPress yang canggih dengan dukungan scanning hybrid (ClamAV + Heuristic) dan AI Security Advisor menggunakan Google Gemini API.

## 🚀 Fitur Utama

### 1. **Hybrid Scanning Engine**
- **ClamAV Integration**: Menggunakan ClamAV untuk deep scan malware jika tersedia
- **Heuristic Scanning**: Fallback scanning menggunakan pattern detection jika ClamAV tidak tersedia
- **Automatic Detection**: Otomatis mendeteksi kemampuan sistem dan memilih mode scanning yang optimal

### 2. **Comprehensive Security Analysis**
- Scanning WordPress core files dengan integrity checking
- Analisis themes dan plugins untuk kerentanan
- Deteksi file mencurigakan di uploads directory
- Pemeriksaan konfigurasi keamanan WordPress
- Monitoring perubahan file dan permission

### 3. **AI-Powered Security Advisor**
- Integrasi dengan Google Gemini API
- Analisis keamanan berbasis AI dalam bahasa Indonesia
- Rekomendasi keamanan yang personal dan actionable
- Evaluasi risiko dan prioritas perbaikan

### 4. **Advanced Threat Detection**
- Deteksi malware dengan signature ClamAV
- Pattern-based detection untuk backdoor dan webshell
- Analisis code obfuscation dan encoding
- Deteksi file dalam lokasi mencurigakan

### 5. **Automated Response System**
- File isolation untuk threat yang terdeteksi
- Automatic quarantine system
- Issue resolution tracking
- Security event logging

### 6. **Smart Notification System**
- **Email Notifications**: HTML email dengan detail lengkap
- **Telegram Integration**: Real-time alerts via Telegram bot
- Customizable notification triggers
- Weekly security summary reports

### 7. **Comprehensive Dashboard**
- Real-time security status overview
- Detailed scan results dengan filtering
- Interactive issue management
- Security score dan recommendations
- System status monitoring

## 📋 Persyaratan Sistem

### Minimum Requirements
- WordPress 5.0 atau lebih tinggi
- PHP 7.4 atau lebih tinggi
- MySQL 5.6 atau lebih tinggi
- 128MB RAM (256MB direkomendasikan)

### Optional (untuk fitur lengkap)
- **ClamAV**: Untuk enhanced malware detection
- **PHP exec functions**: shell_exec, exec, passthru, proc_open
- **Google Gemini API Key**: Untuk AI Security Advisor
- **Telegram Bot**: Untuk notifikasi real-time

## 🔧 Instalasi

1. **Upload Plugin**
   ```
   wp-content/plugins/SentinelWP/
   ```

2. **Aktivasi melalui WordPress Admin**
   - Plugins → Installed Plugins → Activate SentinelWP

3. **Konfigurasi Awal**
   - Buka SentinelWP → Settings
   - Konfigurasikan notifikasi dan AI advisor
   - Jalankan scan pertama

## ⚙️ Konfigurasi

### General Settings
```php
// Automatic scanning
define('SENTINELWP_AUTO_SCAN', true);
define('SENTINELWP_SCAN_TIME', '02:00');
```

### Notification Settings
```php
// Email notifications
define('SENTINELWP_NOTIFY_EMAIL', 'admin@example.com');

// Telegram notifications
define('SENTINELWP_TELEGRAM_BOT_TOKEN', 'your_bot_token');
define('SENTINELWP_TELEGRAM_CHAT_ID', 'your_chat_id');
```

#### Testing Telegram Configuration
After setting up your Telegram bot:
1. Navigate to **SentinelWP → Settings → Notifications**
2. Enter your Bot Token and Chat ID
3. Click **"Test Configuration"** button to verify setup
4. You should receive a test message in your Telegram chat
5. If test fails, double-check your bot token and chat ID

### AI Advisor Settings
```php
// Gemini API configuration
define('SENTINELWP_GEMINI_API_KEY', 'your_api_key');
define('SENTINELWP_GEMINI_MODEL', 'gemini-2.5-flash');
```

## 🔍 Penggunaan

### Manual Scan
1. Buka SentinelWP Dashboard
2. Klik "Run Scan Now"
3. Tunggu proses scanning selesai
4. Review hasil di Scan Results

### Automated Scanning
- Scan otomatis berjalan sesuai jadwal (default: 02:00 AM)
- Notifikasi otomatis jika ditemukan ancaman
- Weekly security reports

### Issue Management
1. **View Issues**: SentinelWP → Scan Results
2. **Resolve Issues**: Tandai sebagai resolved setelah diperbaiki
3. **Isolate Files**: Quarantine file berbahaya otomatis
4. **Get Recommendations**: Lihat saran perbaikan

### AI Security Analysis
1. Buka SentinelWP → AI Security Advisor
2. Klik "Generate New Analysis"
3. Review analisis komprehensif dalam bahasa Indonesia
4. Implementasikan rekomendasi yang diberikan

## 🛡️ Jenis Ancaman yang Terdeteksi

### Malware Detection
- **Virus signatures** (via ClamAV)
- **Backdoors dan webshells**
- **Trojans dan rootkits**
- **Suspicious PHP code**

### Vulnerability Assessment
- **Outdated WordPress core**
- **Vulnerable plugins/themes**
- **Insecure configurations**
- **File permission issues**

### Suspicious Activities
- **Code obfuscation**
- **Unusual file locations**
- **Large file anomalies**
- **Recent file modifications**

## 📊 Database Schema

Plugin membuat 4 tabel khusus:

### wp_sentinelwp_scans
```sql
- id: Unique scan identifier
- scan_time: Waktu scan dilakukan
- scan_mode: Mode scanning (clamav/heuristic)
- issues_found: Jumlah issue yang ditemukan
- status: Status scan (safe/warning/critical)
- files_scanned: Jumlah file yang di-scan
- scan_duration: Durasi scanning
```

### wp_sentinelwp_issues
```sql
- id: Unique issue identifier
- scan_id: Reference ke scan
- file_path: Path file yang bermasalah
- issue_type: Jenis masalah
- description: Deskripsi masalah
- severity: Tingkat bahaya (low/medium/high)
- recommendation: Rekomendasi perbaikan
- resolved: Status resolved
- isolated: Status isolasi file
```

### wp_sentinelwp_logs
```sql
- id: Unique log identifier
- log_time: Waktu kejadian
- action: Aksi yang dilakukan
- ip_address: IP address user
- user_id: WordPress user ID
- details: Detail kejadian
```

### wp_sentinelwp_settings
```sql
- id: Setting identifier
- setting_key: Kunci setting
- setting_value: Nilai setting
- updated_at: Waktu update
```

## 🔧 Customization

### Custom Scan Patterns
```php
// Tambahkan pattern custom
add_filter('sentinelwp_suspicious_patterns', function($patterns) {
    $patterns[] = '/custom_malware_pattern/i';
    return $patterns;
});
```

### Custom Notifications
```php
// Custom notification handler
add_action('sentinelwp_threat_detected', function($issue_data) {
    // Custom notification logic
});
```

### Custom File Exclusions
```php
// Exclude files dari scanning
add_filter('sentinelwp_scan_exclusions', function($exclusions) {
    $exclusions[] = '/custom-uploads/';
    return $exclusions;
});
```

## 🚨 Troubleshooting

### Common Issues

#### 1. ClamAV Not Detected
```bash
# Install ClamAV (Ubuntu/Debian)
sudo apt-get update
sudo apt-get install clamav clamav-daemon

# Install ClamAV (CentOS/RHEL)
sudo yum install clamav clamav-update
```

#### 2. PHP Exec Functions Disabled
```php
// Check disabled functions
echo ini_get('disable_functions');

// Hubungi hosting provider untuk mengaktifkan:
// shell_exec, exec, passthru, proc_open
```

#### 3. Memory Limit Issues
```php
// Increase memory limit di wp-config.php
ini_set('memory_limit', '512M');
```

#### 4. Scan Timeout
```php
// Increase execution time
ini_set('max_execution_time', 300);
```

### Performance Optimization

#### 1. Exclude Large Directories
```php
// Exclude directories dari scan
add_filter('sentinelwp_scan_exclusions', function($exclusions) {
    $exclusions[] = '/large-data/';
    $exclusions[] = '/backups/';
    return $exclusions;
});
```

#### 2. Schedule Scans During Off-Peak
```php
// Set scan time ke jam sepi
update_option('sentinelwp_scan_time', '03:00');
```

#### 3. Limit File Types
```php
// Batasi jenis file yang di-scan
add_filter('sentinelwp_scan_extensions', function($extensions) {
    return ['php', 'js']; // Only scan PHP and JS
});
```

## 🔐 Security Considerations

### Best Practices
1. **Regular Updates**: Selalu update plugin ke versi terbaru
2. **API Key Security**: Simpan API key dengan aman
3. **File Permissions**: Set permission yang benar (644 files, 755 folders)
4. **Regular Monitoring**: Review scan results secara berkala

### API Key Management
```php
// Simpan API key di wp-config.php (lebih aman)
define('SENTINELWP_GEMINI_API_KEY', 'your_secure_api_key');
```

### Notification Security
- Gunakan email yang secure untuk notifikasi
- Batasi akses ke Telegram chat notifications
- Encrypt sensitive data dalam notifications

## 📈 Monitoring dan Reports

### Security Dashboard
- **Real-time status**: Current security posture
- **Threat overview**: Active threats dan statistics
- **Scan history**: Historical scan data
- **Performance metrics**: Scan duration dan coverage

### Automated Reports
- **Daily summaries**: Via email/Telegram
- **Weekly reports**: Comprehensive analysis
- **Monthly trends**: Long-term security trends
- **Incident reports**: Detailed threat analysis

### Export Functionality
```php
// Export scan data
$data = sentinelwp_export_scan_data($date_range);

// Generate CSV report
$csv = sentinelwp_generate_csv_report($data);
```

## 🤝 Support dan Kontribusi

### Getting Help
1. **Documentation**: Baca dokumentasi lengkap
2. **Issue Tracker**: Report bugs dan feature requests
3. **Community Forum**: Diskusi dengan pengguna lain
4. **Professional Support**: Support berbayar tersedia

### Contributing
1. Fork repository
2. Buat feature branch
3. Commit changes
4. Submit pull request

### Development Setup
```bash
# Clone repository
git clone https://github.com/yourusername/sentinelwp.git

# Install dependencies
composer install
npm install

# Run development server
wp server --host=0.0.0.0 --port=8080
```

## 📄 License

SentinelWP is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🔄 Changelog

### Version 1.0.0
- ✨ Initial release
- 🔍 Hybrid scanning engine (ClamAV + Heuristic)
- 🤖 AI Security Advisor dengan Gemini API
- 📧 Email dan Telegram notifications
- 🛡️ File isolation system
- 📊 Comprehensive security dashboard
- 📋 Security recommendations engine
- 🔧 Automated scheduling system

## 🙏 Credits

- **ClamAV Team**: Untuk antivirus engine
- **Google**: Untuk Gemini AI API
- **WordPress Community**: Untuk platform dan feedback
- **Security Researchers**: Untuk threat intelligence

---

**Made with ❤️ for WordPress Security**

Untuk informasi lebih lanjut, kunjungi [dokumentasi lengkap](https://example.com/sentinelwp-docs) atau hubungi [support team](mailto:support@example.com).
