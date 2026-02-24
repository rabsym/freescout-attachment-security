# Changelog - Attachment Security Module

## [3.1.0] - 2026-02-22

### 🔍 Archive Scanning (NEW)

**Archive Security:**
- Scan ZIP files for blocked file extensions before allowing download
- Detects and blocks encrypted/password-protected archives (including nested)
- Configurable nesting depth (0, 1, or 2 levels) for nested ZIP files
- Configurable behavior for unreadable/corrupted archives

**Nesting Depth Options:**
- **0 levels**: Scan main ZIP only, do not scan nested archives
- **1 level** (recommended, default): Scan main ZIP + ZIPs inside it
- **2 levels**: Scan main ZIP + ZIPs inside + ZIPs inside those

**Unreadable Archive Handling (NEW):**
- **Block download** (default): Maximum security - blocks any archive that cannot be scanned
- **Allow download**: Fail-safe mode - logs error but permits download
- Covers: corrupted files, invalid formats, read errors

**New Settings:**
- Archive Scanning toggle (Enabled/Disabled)
- Maximum nesting depth (0, 1, or 2 levels - default: 1)
- Unreadable Archives mode (Block/Allow - default: Block)
- Archive Block Message (for archives containing blocked files)
- Encrypted Archive Block Message (for password-protected archives)
- Unreadable Archive Block Message (for corrupted/unreadable archives)

**Enhanced Logging:**
- `[WARNING] [MIDDLEWARE] ARCHIVE CONTAINS BLOCKED FILES` - Lists all blocked files found
- `[WARNING] [MIDDLEWARE] ENCRYPTED ARCHIVE BLOCKED` - Password-protected archive
- `[WARNING] [MIDDLEWARE] UNREADABLE ARCHIVE BLOCKED` - Archive cannot be scanned (Block mode)
- `[ERROR] [MIDDLEWARE] ARCHIVE SCAN FAILED` - Scanning error (Allow mode enabled)
- Removed debug logs from ServiceProvider (production-ready)
- Configuration log includes Archive Scan status

**Technical:**
- New `ArchiveScanner` service class
- Only scans ZIP files in this version
- Respects existing blocking modes (all/regular/disabled)
- Clean separation: archive scan code doesn't affect existing v3.0.0 functionality
- Fail-safe design: errors are logged and handled according to configuration

**Future versions:** Support for RAR, 7Z, TAR, GZ formats coming in future releases

---

## [3.0.0] - 2026-02-16

### 🎨 Customizable Blocked Page

**Customization Options:**
- Page title
- Block message with variables (`{filename}`, `{extension}`)
- Background gradient colors (two hex codes)
- Reset to defaults button

**Features:**
- Custom blocked page generation
- Dynamic HTML generation (no external files)
- Works with both link types (name and download icon)
- Proper filename for downloaded HTML (`download-blocked.html`)
- External JavaScript for CSP compliance
- Cache bypass for immediate configuration updates
- Activity logging of blocked attempts
- Optimized request handling (only processes attachment requests)

### ⚡ Performance & Architecture

**Code Refactoring:**
- Separated middleware logic from ServiceProvider
- Implemented dedicated `AttachmentBlocker` middleware class
- Better separation of concerns following Laravel patterns
- Cleaner, more maintainable codebase

**Optimizations:**
- Reduced code complexity
- Streamlined request filtering
- Optimized middleware registration
- Improved code organization and readability
- Production-ready logging (debug logs removed)

**Architecture:**
- ServiceProvider handles module registration and settings
- AttachmentBlocker middleware handles all blocking logic
- Clear separation of responsibilities
- Easier to test and extend

---

## [2.0.0] - 2026-02-14

### 👥 Role-Based Blocking

**Blocking Modes:**
- Block for all users
- Block for regular users only (admins exempt)
- Blocking disabled

---

## [1.0.0] - 2026-02-13

### 🔒 Core Security Features

**File Blocking:**
- Block downloads by extension
- Configurable extension list
- Default blocked: exe, php, bat, cmd, htm, html, js, vbs, ps1, sh, phar

**Module Foundation:**
- FreeScout integration
- Settings page
- Professional module structure

---

## Installation

```bash
cd /var/www/html/Modules
tar -xzf AttachmentSecurity_v3.1.0.tar.gz
sudo chown -R www-data:www-data AttachmentSecurity
php artisan cache:clear
sudo systemctl restart php8.x-fpm
```

Activate module in FreeScout admin panel.
