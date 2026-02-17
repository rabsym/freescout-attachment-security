# Changelog - Attachment Security Module

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
tar -xzf AttachmentSecurity_v3.0.0.tar.gz
sudo chown -R www-data:www-data AttachmentSecurity
php artisan cache:clear
sudo systemctl restart php8.x-fpm
```

Activate module in FreeScout admin panel.
