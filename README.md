# Attachment Security Module for FreeScout

**Version:** 3.0.0  
**Author:** Raimundo Alba  
**GitHub:** https://github.com/rabsym/freescout-attachment-security  
**License:** MIT

## Overview

The Attachment Security module enhances FreeScout's security by blocking downloads of potentially dangerous file attachments based on their file extensions. It provides flexible configuration options including role-based blocking modes, customizable blocked page, and comprehensive logging.

## Features

### Core Functionality
- ✅ **Extension-based blocking**: Block downloads by file extension (exe, php, js, etc.)
- ✅ **Role-based control**: Different blocking modes for administrators vs regular users
- ✅ **Customizable blocked page**: Page title, message with variables, and gradient colors
- ✅ **Real-time configuration**: Changes take effect immediately without cache clearing
- ✅ **Optimized performance**: Only processes attachment requests (storage/*) to minimize overhead
- ✅ **Optimized architecture**: Refactored codebase with separated concerns (v3.0.0)
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

## Installation

### Requirements
- FreeScout 1.8.0 or higher
- PHP 7.4 or higher
- Write permissions on `storage/logs/` directory

### Steps

1. **Upload the module:**
   ```bash
   cd /var/www/html/Modules
   tar -xzf AttachmentSecurity_v3.0.0.tar.gz
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
   - Configure blocked extensions, blocking mode, page title, message, and colors
   - Click **Save Settings**

## Configuration

### Settings Location
**Manage → Settings → Attachment Security**

#### Section 1: Security Configuration

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

**Blocking Mode**

Select who should be affected by the blocking rules.

#### Section 2: Notifications & Messages

**Page Title**

The title shown on the blocked page (default: "🚫 Download Blocked")

**Block Message**

Customize the message shown to users when a file download is blocked.

**Available variables:**
- `{filename}` - The name of the blocked file
- `{extension}` - The file extension

**Example message:**
```
Cannot download {filename}. Files with .{extension} extension are blocked for security reasons. Contact support if you need access to this file.
```

**Default message:**
```
For security reasons the file {filename} cannot be downloaded. If you need access to this content, please contact support.
```

**Background Color**

Two comma-separated hex color codes for the gradient background (default: "#4A90E2, #5C6AC4")

**Examples:**
- Blue: `#4A90E2, #5C6AC4`
- Purple: `#667eea, #764ba2`
- Green: `#11998e, #38ef7d`

**Reset to Defaults**

Click this button to restore all settings to their default values.

## Technical Details

### Code Architecture

**v3.0.0 introduces a refactored architecture for better maintainability:**

**Component Separation:**
- **ServiceProvider**: Handles module registration, settings management, and request filtering
- **AttachmentBlocker Middleware**: Dedicated class containing all blocking logic
- **Clear Responsibilities**: Each component has a single, well-defined purpose

**Benefits:**
- **Maintainability**: Cleaner code structure following Laravel best practices
- **Testability**: Isolated components are easier to unit test
- **Extensibility**: New features can be added without affecting existing code
- **Performance**: Targeted request processing with minimal overhead
- **Debugging**: Production-ready logging with optional debug logs

**Architecture Flow:**
```
Request → ServiceProvider (filters) → AttachmentBlocker Middleware → Block/Allow
```

The ServiceProvider acts as a gatekeeper, only delegating attachment requests to the middleware, ensuring the blocking logic only runs when necessary.

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
[2026-02-16 10:30:15] [INFO] [SERVICEPROVIDER] Configuration saved - Extensions: exe,php,bat | Mode: all
```

**Blocked download attempts:**
```
[2026-02-16 10:35:22] [WARNING] [MIDDLEWARE] BLOCKING DOWNLOAD | {"user":"user@example.com","ticket":"1523","file":"malware.exe","extension":"exe"}
```

The ticket number is obtained from the attachment's conversation (using the `?id=` parameter in the URL).

### Viewing Logs

```bash
# View recent log entries
tail -50 /var/www/html/storage/logs/attachmentsecurity.log

# Monitor logs in real-time
tail -f /var/www/html/storage/logs/attachmentsecurity.log
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

### Version 3.0.0 (2026-02-16)
- Customizable blocked page (title, message, colors)
- Custom blocked page generation
- External JavaScript for CSP compliance
- Reset to defaults button
- Optimized request handling
- Refactored architecture with separated middleware
- Production-ready logging

### Version 2.0.0 (2026-02-14)
- Role-based blocking modes
- Admin exemption capability

### Version 1.0.0 (2026-02-13)
- Initial release with core blocking functionality

## License

This module is open-source software licensed under the MIT license.

## Usage and Modifications

**Feel free to use and modify this module for your needs!** This is open source software and you're encouraged to adapt it to your requirements.

## Contributing

Contributions are welcome! If you have ideas for improvements or find any issues:

- **Bug Reports & Suggestions**: Please open an issue on [GitHub Issues](https://github.com/rabsym/freescout-attachment-security/issues)
- **Pull Requests**: Code contributions are appreciated
- **Documentation**: Help improve the docs

## Support the Project

If you find this module useful and want to support its development:

**PayPal Donations**: [rabsym@gmail.com](https://www.paypal.com/paypalme/rabsym)

Your support helps maintain and improve this project. Thank you! 🙏

## Support

For issues, questions, or feature requests:

1. **GitHub Issues**: https://github.com/rabsym/freescout-attachment-security/issues
2. **Check documentation** in this README
3. **Review logs**: `storage/logs/attachmentsecurity.log`

## Credits

**Developer:** Raimundo Alba  
**GitHub:** https://github.com/rabsym  
**Built for:** FreeScout - The free self-hosted help desk & shared mailbox

---

⭐ If you find this module useful, please star it on GitHub!
