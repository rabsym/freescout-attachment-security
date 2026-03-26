# Attachment Security Module for FreeScout

The Attachment Security module enhances FreeScout's security by blocking downloads of potentially dangerous file attachments based on their file extensions. It provides flexible configuration options including role-based blocking modes, customizable blocked page, comprehensive logging, and **multi-format archive scanning** (ZIP, RAR, TAR, GZ, BZ2) with automatic detection of supported archive formats to detect malicious files hidden inside compressed archives.

## Features

### Core Functionality
- ✅ **Internationalization (i18n)** (v3.5.0): Spanish, French, German, Portuguese, Italian and Dutch translations included.
- ✅ **Extension-based blocking**: Block downloads by file extension (exe, php, js, etc.)
- ✅ **Block files without extension** (v3.4.0): Optional blocking of extensionless files (readme, makefile, etc.)
- ✅ **Email notifications** (v3.4.0): Automated alerts for blocked file incidents (requires SMTP driver) with customizable subject and variables
- ✅ **Multi-format archive scanning** (v3.2.0): Scan ZIP, RAR, TAR, GZ, and BZ2 files for hidden malicious content
- ✅ **Automatic detection of supported archive formats** (v3.2.0): System detects available archive handlers and uses the best method
- ✅ **Enhanced encryption detection** (v3.2.0): Detects both header and content-encrypted archives
- ✅ **Universal nesting control** (v3.2.0): Configurable depth for all archive formats
- ✅ **Better 0-byte file handling** (v3.3.0): Valid empty compressed files no longer flagged as corrupted
- ✅ **Unreadable archive handling**: Configurable behavior for corrupted/unreadable archives
- ✅ **Automated check for new module versions**: Integrated update notification and one-click updates
- ✅ **Enhanced logging system** (v3.3.0): Laravel-compatible log format, improved readability in FreeScout admin interface
- ✅ **Improved settings validation** (v3.3.0): Real-time format validation for blocked extensions
- ✅ **Role-based control**: Different blocking modes for administrators vs regular users
- ✅ **Customizable blocked page**: Page title, message with variables, and gradient colors
- ✅ **Real-time configuration**: Changes take effect immediately without cache clearing
- ✅ **Optimized performance**: Only processes attachment requests (storage/*) to minimize overhead
- ✅ **Detailed logging**: All blocked attempts and configuration changes are logged
- ✅ **User-friendly interface**: Easy-to-use settings page with organized sections
- ✅ **GitHub integration**: Professional metadata with repository links

### Blocking Modes

1. **Block for all users** (Default)
   - Prevents everyone, including administrators, from downloading blocked file types
   - Maximum security option
   - Recommended for strict security policies

2. **Block for regular users only**
   - Regular users cannot download blocked file types
   - Administrators are exempted and can download any file type
   - Useful when admins need access but want to protect regular users

3. **Blocking disabled**
   - All file types are allowed for everyone
   - Use this to temporarily disable blocking without changing extension list
   - Useful for testing or maintenance

### Archive Scanning

**Scans compressed files for hidden malicious content:**

- **Multi-format scanning**: Detects blocked file extensions inside ZIP, RAR, TAR, GZ, and BZ2 archives
- **Encrypted archive detection**: Automatically blocks password-protected archives (including nested encrypted archives)
- **Nested archive support**: Scans archives within archives, even across different formats (configurable depth: 0, 1, or 2 levels)
- **Unreadable archive handling**: Configurable behavior for archives that cannot be scanned (corrupted, invalid format)
- **Fail-safe design**: Configurable between maximum security (block unreadable) or permissive (allow with log)
- **Performance optimized**: Only scans when Archive Scanning is enabled

**Nesting Depth Options:**
- **0 levels**: Scan main archive only, do not scan nested archives
- **1 level** (recommended): Scan main archive + archives inside it
- **2 levels**: Scan main archive + archives inside + archives inside those

**Unreadable Archive Modes:**
- **Block download** (default): Archives that cannot be scanned are blocked (maximum security)
- **Allow download**: Archives that cannot be scanned are allowed with error logged (fail-safe mode)

**Example scenarios:**
- `malware.zip` contains `virus.exe` → **BLOCKED** with custom message listing blocked files
- `protected.rar` is password-protected → **BLOCKED** (cannot scan encrypted archives)
- `nested.zip` contains `inner.rar` which contains `script.js` → **BLOCKED** (respects nesting depth and multi-format)
- `archive.tar.gz` contains encrypted `data.zip` → **BLOCKED** (encrypted nested archives are detected)
- `corrupted.rar` cannot be opened → **BLOCKED** (default) or **ALLOWED** (if configured to allow)
- `documents.zip` contains only `report.pdf` and `data.csv` → **ALLOWED** (no blocked extensions)

**Configuration:**
- Enable/disable archive scanning
- Set maximum nesting depth (0, 1, or 2 levels - default: 1)
- Choose behavior for unreadable archives (Block or Allow - default: Block)
- Customize messages for blocked content, encrypted archives, and unreadable archives
- Works with existing blocking modes (respects admin exemptions)

## Supported Archive Formats

The module automatically detects which archive formats your server can handle:

| Format | Handler | Status |
|--------|---------|--------|
| **ZIP** | ZipArchive (PHP native) | Always available |
| **RAR** | unrar-nonfree or unrar-free | Auto-detected |
| **TAR** | PharData (PHP native) | Always available |
| **GZ** | PharData + zlib extension | Auto-detected |
| **BZ2** | PharData + bz2 extension | Auto-detected |

### RAR Support

Two methods are supported (in order of preference):

1. **unrar (RAR Lab - nonfree)**: Full RAR 5.x support - **recommended**
   ```bash
   # Debian/Ubuntu
   apt-get install unrar
   
   # RHEL/CentOS (requires EPEL)
   yum install unrar
   ```

2. **unrar-free**: Limited to RAR 2.x only (older RAR archives)
   ```bash
   # Debian/Ubuntu
   apt-get install unrar-free
   ```

The module will automatically detect and use the best available method. Check the settings page under "Supported Archive Formats" to see which formats are available on your server.

## Installation

### Requirements
- FreeScout 1.8.0 or higher
- PHP 7.4 or higher
- PHP extensions: `zip` (required), `zlib` (for GZ), `bz2` (for BZ2)
- Optional: `unrar` or `unrar-free` binary for RAR support
- Write permissions on `storage/logs/` directory

### Steps

1. **Upload the module:**
   ```bash
   cd /var/www/html/Modules
   tar -xzf AttachmentSecurity_v3.5.0.tar.gz
   ```

2. **Set permissions:**
   ```bash
   sudo chown -R www-data:www-data AttachmentSecurity
   ```

3. **Clear cache and restart:**
   ```bash
   php artisan cache:clear
   sudo systemctl restart php8.x-fpm
   ```

4. **Activate the module:**
   - Go to **Manage → Modules** in FreeScout
   - Find "Attachment Security"
   - Click **Activate**

5. **Configure settings:**
   - Go to **Manage → Settings → Attachment Security**
   - Configure blocked extensions, archive scan, blocking mode, page title, messages, and colors
   - Click **Save Settings**

## Configuration

### Settings Location
**Manage → Settings → Attachment Security**

The settings are organized into three sections:

---

### Section 1: Security Configuration 🔒

Configure file blocking rules and archive scanning behavior.

#### File Extension Blocking

**Blocked File Extensions**

Enter a comma-separated list of file extensions to block (without dots):

**Example:**
```
exe,php,bat,cmd,htm,html,js,vbs,ps1,sh,phar,jar,msi
```

**Default blocked extensions:**
- **Executables:** exe, bat, cmd, sh, ps1
- **Scripts:** php, js, vbs, phar
- **Web files:** htm, html

**Block Files Without Extension**

When enabled, blocks files that have no file extension (e.g., readme, makefile, dockerfile).
- Applies to both direct attachments and files inside compressed archives
- **Default:** Disabled

---

#### Archive Scanning

**Enable Archive Scanning**

When enabled, compressed files (ZIP, RAR, TAR, GZ, BZ2) are scanned for blocked file extensions before allowing download.

- **Enabled**: Scans inside archives for malicious content
- **Disabled**: Archives treated as regular files (only blocked if archive extension is in blocked list)

The system automatically detects which archive formats are available on your server. See "Supported Archive Formats" in Settings.

**Archive Maximum Nesting Depth**

Controls how many levels deep to scan for nested compressed files:
- **0 levels**: Scan main archive only, ignore nested archives
- **1 level** (recommended): Scan main archive + 1 level of nested archives
- **2 levels**: Scan main archive + 2 levels of nested archives

Supports multi-format nesting (e.g., RAR inside ZIP inside TAR).

**Unreadable Archives**

Behavior when an archive cannot be scanned (corrupted file, invalid format, read error):
- **Block download** (Default): Maximum security - prevents download
- **Allow download**: Fail-safe mode - logs error but allows download

---

#### Blocking Mode

Controls who is affected by the blocking rules (applies to all file types and files inside archives):

- **Block for all users** (Default): Maximum security - blocks everyone including administrators
- **Block for regular users only**: Admins can download any file, regular users cannot download blocked files
- **Blocking disabled**: Temporarily disable all blocking (useful for testing)

---

### Section 2: Notifications 📧

Configure email alerts for blocked file incidents.

**⚠️ Important:** Email notifications require **SMTP mail driver**. The feature is automatically disabled if using other mail drivers (sendmail, mailgun, etc.).

**Enable Email Notifications**

Toggle to enable/disable email notifications when files are blocked.
- **Requirement:** SMTP mail driver must be configured in FreeScout
- **Default:** Disabled

**Notification Email Address**

Email address to receive incident notifications.
- **Required** when notifications are enabled
- **Validation:** Must be a valid email format

**Email Subject**

Customize the email subject line for notifications.

**Available variables:**
- `{user}` - User who attempted the download
- `{ticket}` - Ticket number
- `{filename}` - Name of the blocked file
- `{reason}` - Reason for blocking

**Default subject:**
```
FreeScout AttachmentSecurity alert - {user}
```

**Example subjects:**
```
Security Alert: {user} attempted to download {filename}
Blocked file in ticket #{ticket} - {filename}
File security incident - {reason}
```

**Email Content:**

Each notification includes:
- User email
- Ticket number
- Filename
- Blocking reason (extension blocked, archive with malware, encrypted archive, etc.)
- Timestamp

---

### Section 3: Messages & Appearance 🎨

Customize the blocked page appearance and user-facing messages.

**Page Title**

Title shown at the top of the blocked page.
- **Default:** "🚫 Download Blocked"

**Block Message**

Message shown when a regular file download is blocked.

**Available variables:**
- `{filename}` - Name of the blocked file
- `{extension}` - File extension
- `{blocked_files}` - Comma-separated list (for archives)

**Default:**
```
For security reasons the file {filename} cannot be downloaded. If you need access to this content, please contact support.
```

**Archive Block Message**

Message shown when an archive contains blocked files.

**Available variables:**
- `{filename}` - Archive name
- `{blocked_files}` - List of blocked files found inside

**Default:**
```
The file {filename} contains blocked files: {blocked_files}
```

**Encrypted Archive Block Message**

Message shown when an archive is password-protected and cannot be scanned.

**Available variables:**
- `{filename}` - Archive name

**Default:**
```
The file {filename} is password-protected and cannot be scanned for security reasons.
```

**Unreadable Archive Block Message**

Message shown when an archive cannot be scanned and "Block download" mode is enabled.

**Available variables:**
- `{filename}` - Archive name

**Default:**
```
The file {filename} cannot be scanned because it appears to be corrupted or has an invalid format. For security reasons, the download has been blocked.
```

**Background Gradient Colors**

Customize the blocked page background with two hex color codes (comma-separated).

**Examples:**
- **Blue** (Default): `#4A90E2, #5C6AC4`
- **Purple:** `#667eea, #764ba2`
- **Green:** `#11998e, #38ef7d`
- **Red:** `#eb3349, #f45c43`

---

### Reset to Defaults

Click the "Reset to Defaults" button to restore all settings to their default values.

---

## Technical Details

### Performance Optimization

The module uses an optimized middleware registration that only processes requests to attachment URLs (`storage/attachment/*`). This approach:
- Minimizes performance impact on regular FreeScout operations
- Reduces unnecessary processing for non-attachment requests
- Ensures the middleware only activates when needed

### How It Works

1. **Request Filtering**: ServiceProvider checks if the request is for an attachment URL
2. **Extension Check**: If blocked extension is detected, the download is intercepted
3. **Page Generation**: A custom HTML page is generated with your configured message
4. **Response**: The blocked page is returned with proper headers (HTTP 403)

## User Experience

### What Users See

When a user attempts to download a blocked file:

1. **Custom blocked page**: A professional page appears with your configured title and message
2. **Customized content**: Shows your message with filename and extension highlighted
3. **Close button**: User can close the page or go back
4. **Consistent behavior**: Works with both link types (filename link and download icon)

### Example

With default settings:
- **Title**: 🚫 Download Blocked
- **Message**: For security reasons the file **malware.exe** cannot be downloaded. If you need access to this content, please contact support.
- **Background**: Blue gradient

## Logging

### Log Location
```
storage/logs/attachmentsecurity.log
```

### Log Entries

**Configuration changes:**
```
[2026-03-12 10:30:15] attachmentsecurity.ALERT: [SERVICEPROVIDER] CONFIGURATION SAVED - Blocked Extensions: exe,php,bat | Block No Extension: disabled | Block Mode: all | Archive Scan: enabled | Nesting Depth: 1 | Unreadable Archives: block | Email Notifications: enabled | Notification Email: security@example.com | Supported Archive Formats: zip(native), rar(nonfree), tar(native), gz(native), bz2(native)
```

**Regular blocked download:**
```
[2026-03-12 10:35:22] attachmentsecurity.ALERT: [MIDDLEWARE] BLOCKING DOWNLOAD {"user":"user@example.com","ticket":1523,"file":"malware.exe","extension":"exe"}
```

**File without extension blocked:**
```
[2026-03-12 10:36:45] attachmentsecurity.ALERT: [MIDDLEWARE] FILE WITHOUT EXTENSION BLOCKED {"user":"user@example.com","ticket":1524,"file":"readme"}
```

**Archive with blocked content:**
```
[2026-03-12 10:40:15] attachmentsecurity.ALERT: [MIDDLEWARE] ARCHIVE CONTAINS BLOCKED FILES {"user":"user@example.com","ticket":1525,"archive":"malware.rar","blocked_files":["virus.exe","trojan.bat"],"nesting_level":1}
```

**Encrypted archive blocked:**
```
[2026-03-12 10:42:30] attachmentsecurity.ALERT: [MIDDLEWARE] ENCRYPTED ARCHIVE BLOCKED {"user":"user@example.com","ticket":1526,"file":"protected.rar"}
```

**Unreadable archive blocked (Block mode - default):**
```
[2026-03-12 10:45:00] attachmentsecurity.ALERT: [MIDDLEWARE] UNREADABLE ARCHIVE BLOCKED {"user":"user@example.com","ticket":1527,"file":"corrupted.zip","error":"Cannot open ZIP file"}
```

**Archive scan error (Allow mode enabled):**
```
[2026-03-12 10:50:00] attachmentsecurity.ALERT: [MIDDLEWARE] ARCHIVE SCAN FAILED {"file":"corrupted.zip","error":"Cannot open ZIP file"}
```

**Email notification validation (when not using SMTP):**
```
[2026-03-12 11:00:00] attachmentsecurity.ALERT: [SETTINGS] Email notifications disabled: SMTP driver required (current driver: sendmail)
```

The ticket number is obtained from the attachment's conversation (using the `?id=` parameter in the URL).

**Note:** Email notifications are sent silently without logging to avoid cluttering logs. Only validation errors are logged.

### Viewing Logs

```bash
# View recent log entries
tail -50 /var/www/html/storage/logs/attachmentsecurity.log

# Monitor logs in real-time
tail -f /var/www/html/storage/logs/attachmentsecurity.log

# View from FreeScout admin interface
Manage → Logs → Select attachmentsecurity.log
```

## GitHub Repository

This module is open source and available on GitHub:

**Repository:** https://github.com/rabsym/freescout-attachment-security

- 📖 Full documentation
- 🐛 Issue tracking
- 🚀 Latest releases
- 💬 Discussions and support
- 🤝 Contributions welcome

## Troubleshooting

### Downloads not being blocked

**Check 1: Module is active**
```bash
php artisan module:list | grep -i attachment
```

**Check 2: Clear cache and restart**
```bash
cd /var/www/html
php artisan cache:clear
php artisan config:clear
sudo systemctl restart php8.x-fpm
```

### Blocked page not showing or looks wrong

**Check 1: Clear browser cache**
- Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)

**Check 2: Verify settings**
- Check that custom message doesn't contain HTML tags
- Verify color codes are valid hex codes

### Log file not writing

**Fix permissions:**
```bash
sudo chown www-data:www-data /var/www/html/storage/logs/attachmentsecurity.log
sudo chmod 666 /var/www/html/storage/logs/attachmentsecurity.log
```

## Security Considerations

### Recommended Configuration

For maximum security:
1. **Use "Block for all users" mode**
2. **Block these extensions at minimum:**
   ```
   exe,bat,cmd,sh,ps1,php,js,vbs,phar,jar,msi,app,htm,html
   ```
3. **Regularly review logs** for suspicious activity
4. **Keep the module updated** via GitHub releases

### Important Notes

- ⚠️ **Extension-based blocking is not foolproof** - Users can rename files
- ⚠️ **This module does not scan file content** - It only checks extensions
- ⚠️ **Consider additional security layers** - Antivirus, content filtering, etc.
- ✅ **This module complements other security measures** - Use it as part of a defense-in-depth strategy

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

### Version 3.5.0 (2026-03-26)
- Internationalization (i18n) support via JSON translation files
- Added Spanish (es.json) and French (fr.json) translations
- Wrapped untranslated UI strings in __() helper
- Default configurable messages (Block Message, Archive Block Message, Encrypted Archive Block Message, Unreadable Archive Block Message, Page Title, Email Subject) now resolved through translation system when no value is stored in database
- **Existing installations:** use Reset to Defaults in Settings and save to get translated default messages. Note that Reset to Defaults restores **all** configuration values to their defaults — review all settings carefully before saving

### Version 3.4.0 (2026-03-12)
- Block files without extension (readme, makefile, etc.)
- Email notification system for blocked file incidents (requires SMTP)
- Customizable email subject with variables: {user}, {ticket}, {filename}, {reason}
- Reorganized settings UI into three clear sections
- Better visual organization with subsection headers and icons

### Version 3.3.0 (2026-03-06)
- Improved settings UI with enlarged input field and real-time validation
- Enhanced logging system with Laravel-compatible format
- Fixed false-positive corruption detection for valid 0-byte compressed files
- Better log readability in FreeScout admin interface

### Version 3.2.0 (2026-03-01)
- Multi-format archive scanning: ZIP, RAR, TAR, GZ, BZ2
- Automatic detection of supported archive formats with capabilities UI
- Multi-format nesting support (e.g., RAR → ZIP → malware)
- Compound format support (.tgz, .tbz2, .tar.gz, .tar.bz2)
- Enhanced encryption detection (header + content)
- Universal nesting control for all formats
- Automated update checking and one-click updates
- Improved logging and UI terminology
- Configuration warnings for conflicting settings

### Version 3.1.0 (2026-02-22)
- Archive Scanning: Scan ZIP files for malicious content
- Configurable handling for unreadable/corrupted archives
- Encrypted archive detection (including nested)
- Nesting depth options (0, 1, 2)
- Three customizable block messages
- Production-ready logging

### Version 3.0.0 (2026-02-16)
- Customizable blocked page (title, message, colors)
- Custom blocked page generation
- External JavaScript for CSP compliance
- Reset to defaults button
- Optimized request handling

### Version 2.0.0 (2026-02-14)
- Role-based blocking modes
- Admin exemption capability

### Version 1.0.0 (2026-02-13)
- Initial release with core blocking functionality

## License

GNU Affero General Public License v3.0 — see the LICENSE file for details.

## Contributing & Support

Contributions are welcome! If you have ideas for improvements or find any issues:

- **Bug Reports, Questions & Feature Requests**: [GitHub Issues](https://github.com/rabsym/freescout-attachment-security/issues)
- **Pull Requests**: Code contributions are appreciated
- **Documentation**: Help improve the docs

## Support the Project

If you find this module useful and would like to support its development, please feel free to sponsor this project via [GitHub Sponsors](https://github.com/sponsors/rabsym).

Your support helps maintain and improve this project. Thank you! 🙏

## Credits

- **Developer**: Raimundo Alba
- **GitHub**: [@rabsym](https://github.com/rabsym)
- **Built for**: [FreeScout](https://freescout.net) — The free self-hosted help desk & shared mailbox

⭐ If you find this module useful, please star it on GitHub!
