<form class="form-horizontal margin-top margin-bottom" method="POST" action="">
    {{ csrf_field() }}

    <input type="hidden" name="settings[dummy]" value="1" />

    @php
        // Check for conflicting archive extensions
        $archiveScanEnabled = $settings['attachmentsecurity.archive_scan_enabled'] ?? false;
        $blockedExtensions = $settings['attachmentsecurity.blocked_extensions'] ?? '';
        
        $conflictingExts = [];
        if ($archiveScanEnabled && !empty($blockedExtensions)) {
            $archiveExts = ['zip', 'rar', 'tar', 'gz', 'bz2', 'tgz', 'tbz2', 'tb2'];
            $blockedExtsArray = array_filter(array_map('trim', explode(',', strtolower($blockedExtensions))));
            $conflictingExts = array_intersect($archiveExts, $blockedExtsArray);
        }
    @endphp

    @if (!empty($conflictingExts))
        <div class="alert alert-warning" role="alert" style="margin-bottom: 20px;">
            <i class="glyphicon glyphicon-warning-sign"></i>
            <strong>{{ __('Warning') }}:</strong> 
            {{ __('The following archive extensions are in your blocked list') }}: 
            <strong>{{ implode(', ', $conflictingExts) }}</strong>. 
            {{ __('These archives will be blocked immediately without scanning their contents.') }} 
            {{ __('To allow content scanning, remove these extensions from the "Blocked Extensions" list.') }}
        </div>
    @endif

    {{-- ============================================ --}}
    {{-- SECTION 1: Security Configuration           --}}
    {{-- ============================================ --}}
    <h3 class="subheader">
        <i class="glyphicon glyphicon-lock"></i> {{ __('Security Configuration') }}
    </h3>
    <p class="form-help block-help">
        {{ __('Define security rules for file attachments and archive scanning.') }}
    </p>

    {{-- File Extension Blocking Subsection --}}
    <h4 style="margin-left: 15px; margin-bottom: 15px; color: #666;">
        <i class="glyphicon glyphicon-file"></i> {{ __('File Extension Blocking') }}
    </h4>

    {{-- Blocked File Extensions Field --}}
    <div class="form-group">
        <label for="blocked_extensions" class="col-sm-2 control-label">
            {{ __('Blocked File Extensions') }}
        </label>
        <div class="col-sm-6">
            <input
                type="text"
                class="form-control"
                style="width: 100%; max-width: 800px;"
                id="blocked_extensions"
                name="settings[attachmentsecurity.blocked_extensions]"
                value="{{ $settings['attachmentsecurity.blocked_extensions'] ?? '' }}"
                placeholder="exe,php,bat,cmd,js,html"
            >
            <p class="form-help">
                {{ __('Enter comma-separated file extensions to block. Do not include dots.') }}<br/>
                {{ __('Example:') }} <code>exe,php,bat,cmd,htm,html,js,vbs,ps1,sh,phar</code>
            </p>
            <span id="blocked_extensions_error" style="color: #d9534f; display: none; font-weight: bold;">
                ⚠️ Invalid format. Use only letters, numbers and commas (no spaces). Example: exe,php,js
            </span>
        </div>
    </div>

    {{-- Block Files Without Extension --}}
    <div class="form-group">
        <label for="block_no_extension" class="col-sm-2 control-label">
            {{ __('Block Files Without Extension') }}
        </label>
        <div class="col-sm-6">
            <div class="controls">
                <div class="onoffswitch-wrap">
                    <div class="onoffswitch">
                        <input
                            type="checkbox"
                            name="settings[attachmentsecurity.block_no_extension]"
                            value="1"
                            id="block_no_extension"
                            class="onoffswitch-checkbox"
                            {{ ($settings['attachmentsecurity.block_no_extension'] ?? false) ? 'checked' : '' }}
                        >
                        <label class="onoffswitch-label" for="block_no_extension"></label>
                    </div>
                </div>
            </div>
            <p class="form-help">
                {{ __('Block files that have no extension (e.g., readme, makefile, dockerfile).') }}<br/>
                {{ __('Applies to both direct attachments and files inside compressed archives.') }}
            </p>
        </div>
    </div>

    <hr class="margin-top margin-bottom" style="margin-left: 15px; margin-right: 15px; border-top: 1px solid #e5e5e5;">

    {{-- Archive Scanning Section --}}
    <h4 style="margin-left: 15px; margin-bottom: 15px; color: #666;">
        <i class="glyphicon glyphicon-compressed"></i> {{ __('Archive Scanning') }}
    </h4>

    {{-- Archive Scan Enabled --}}
    <div class="form-group">
        <label for="archive_scan_enabled" class="col-sm-2 control-label">
            {{ __('Archive Scanning') }}
        </label>
        <div class="col-sm-6">
            <div class="controls">
                <div class="onoffswitch-wrap">
                    <div class="onoffswitch">
                        <input
                            type="checkbox"
                            name="settings[attachmentsecurity.archive_scan_enabled]"
                            value="1"
                            id="archive_scan_enabled"
                            class="onoffswitch-checkbox"
                            {{ ($settings['attachmentsecurity.archive_scan_enabled'] ?? false) ? 'checked' : '' }}
                        >
                        <label class="onoffswitch-label" for="archive_scan_enabled"></label>
                    </div>
                </div>
            </div>
            <p class="form-help">
                {{ __('Scan compressed files for blocked file extensions before allowing download.') }}
            </p>
        </div>
    </div>

    {{-- Maximum Nesting Depth --}}
    <div class="form-group">
        <label for="max_nesting_depth" class="col-sm-2 control-label">
            {{ __('Archive Maximum Nesting Depth') }}
        </label>
        <div class="col-sm-6">
            <select
                class="form-control input-sized-lg"
                id="max_nesting_depth"
                name="settings[attachmentsecurity.max_nesting_depth]"
                style="min-width: 600px;"
            >
                <option value="0" {{ ($settings['attachmentsecurity.max_nesting_depth'] ?? 1) == 0 ? 'selected' : '' }}>
                    {{ __('0 levels (scan archive only, do not scan nested archives)') }}
                </option>
                <option value="1" {{ ($settings['attachmentsecurity.max_nesting_depth'] ?? 1) == 1 ? 'selected' : '' }}>
                    {{ __('1 level (scan archive and 1 level of nested archives - recommended)') }}
                </option>
                <option value="2" {{ ($settings['attachmentsecurity.max_nesting_depth'] ?? 1) == 2 ? 'selected' : '' }}>
                    {{ __('2 levels (scan archive and 2 levels of nested archives)') }}
                </option>
            </select>
            <p class="form-help">
                {{ __('How many levels deep to scan for nested compressed files.') }}<br/>
                <strong>{{ __('Level 0:') }}</strong> {{ __('Only scan the main archive file') }}<br/>
                <strong>{{ __('Level 1:') }}</strong> {{ __('Scan main archive + archives inside it (recommended)') }}<br/>
                <strong>{{ __('Level 2:') }}</strong> {{ __('Scan main archive + archives inside + archives inside those') }}<br/><br/>
                <em>{{ __('Example: A RAR file containing another RAR with a malicious file would be caught at level 1.') }}</em>
            </p>
        </div>
    </div>

    {{-- Unreadable Archives --}}
    <div class="form-group">
        <label for="unreadable_archives_mode" class="col-sm-2 control-label">
            {{ __('Unreadable Archives') }}
        </label>
        <div class="col-sm-6">
            <select
                class="form-control input-sized-lg"
                id="unreadable_archives_mode"
                name="settings[attachmentsecurity.unreadable_archives_mode]"
            >
                <option value="block" {{ ($settings['attachmentsecurity.unreadable_archives_mode'] ?? 'block') === 'block' ? 'selected' : '' }}>
                    {{ __('Block download (maximum security - recommended)') }}
                </option>
                <option value="allow" {{ ($settings['attachmentsecurity.unreadable_archives_mode'] ?? 'block') === 'allow' ? 'selected' : '' }}>
                    {{ __('Allow download (log error only)') }}
                </option>
            </select>
            <p class="form-help">
                {{ __('What to do when an archive cannot be scanned (corrupted file, invalid format, read error).') }}<br/>
                <strong>{{ __('Block download:') }}</strong> {{ __('Maximum security - prevents download of any archive that cannot be scanned') }}<br/>
                <strong>{{ __('Allow download:') }}</strong> {{ __('Fail-safe mode - logs the error but permits download') }}
            </p>
        </div>
    </div>

    {{-- Archive Format Support Status --}}
    <div class="form-group">
        <div class="col-sm-6 col-sm-offset-2">
            <div class="alert alert-info" style="margin-top: 15px;">
                <h4 style="margin-top: 0;">
                    <i class="glyphicon glyphicon-info-sign"></i> {{ __('Supported Archive Formats') }}
                </h4>
                <p class="text-muted" style="margin: 5px 0 10px 0; font-size: 12px;">
                    {{ __('Automatically updated when saving configuration changes') }}
                </p>
                
                <table class="table table-condensed" style="margin-bottom: 0; margin-top: 10px; background: white;">
                    <thead>
                        <tr>
                            <th>{{ __('Format') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- ZIP --}}
                        <tr>
                            <td><strong>ZIP</strong></td>
                            <td><span class="label label-success">✓ {{ __('Available') }}</span></td>
                            <td>{{ $settings['_archive_capabilities']['zip']['details'] ?? 'ZipArchive (native PHP)' }}</td>
                        </tr>
                        
                        {{-- RAR --}}
                        <tr>
                            <td><strong>RAR</strong></td>
                            <td>
                                @if($settings['_archive_capabilities']['rar']['available'] ?? false)
                                    <span class="label label-success">✓ {{ __('Available') }}</span>
                                @else
                                    <span class="label label-warning">✗ {{ __('Not Available') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($settings['_archive_capabilities']['rar']['available'] ?? false)
                                    @php
                                        $rarType = $settings['_archive_capabilities']['rar']['type'] ?? 'unknown';
                                        $rarVersion = $settings['_archive_capabilities']['rar']['version'] ?? null;
                                    @endphp
                                    @if($rarType === 'extension')
                                        <strong>{{ __('RAR (PHP Extension)') }}</strong>
                                        <span class="label label-info">{{ __('Full support') }}</span>
                                    @elseif($rarType === 'nonfree')
                                        <strong>{{ __('RAR (RAR Lab)') }}</strong>
                                        <span class="label label-info">{{ __('Full RAR 5.x') }}</span>
                                    @elseif($rarType === 'free')
                                        <strong>{{ __('RAR (Free)') }}</strong>
                                        <span class="label label-warning">⚠ {{ __('RAR 2.x only') }}</span>
                                    @else
                                        <strong>{{ __('RAR (Unknown)') }}</strong>
                                    @endif
                                    @if($rarVersion)
                                        <small class="text-muted">v{{ $rarVersion }}</small>
                                    @endif
                                @else
                                    <code>sudo apt install unrar-nonfree</code>
                                @endif
                            </td>
                        </tr>
                        
                        {{-- TAR --}}
                        <tr>
                            <td><strong>TAR</strong></td>
                            <td>
                                @if($settings['_archive_capabilities']['tar']['available'] ?? false)
                                    <span class="label label-success">✓ {{ __('Available') }}</span>
                                @else
                                    <span class="label label-danger">✗ {{ __('Not Available') }}</span>
                                @endif
                            </td>
                            <td>{{ $settings['_archive_capabilities']['tar']['details'] ?? 'PharData not available' }}</td>
                        </tr>
                        
                        {{-- GZ --}}
                        <tr>
                            <td><strong>GZ</strong></td>
                            <td>
                                @if($settings['_archive_capabilities']['gz']['available'] ?? false)
                                    <span class="label label-success">✓ {{ __('Available') }}</span>
                                @else
                                    <span class="label label-danger">✗ {{ __('Not Available') }}</span>
                                @endif
                            </td>
                            <td>{{ $settings['_archive_capabilities']['gz']['details'] ?? 'zlib extension not available' }}</td>
                        </tr>
                        
                        {{-- BZ2 --}}
                        <tr>
                            <td><strong>BZ2</strong></td>
                            <td>
                                @if($settings['_archive_capabilities']['bz2']['available'] ?? false)
                                    <span class="label label-success">✓ {{ __('Available') }}</span>
                                @else
                                    <span class="label label-danger">✗ {{ __('Not Available') }}</span>
                                @endif
                            </td>
                            <td>{{ $settings['_archive_capabilities']['bz2']['details'] ?? 'bz2 extension not available' }}</td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="text-muted" style="margin-top: 10px; margin-bottom: 0;">
                    <small>
                        <i class="glyphicon glyphicon-time"></i> 
                        {{ __('Last checked and updated:') }} {{ $settings['_archive_capabilities_scanned_at'] ?? __('Never') }}
                    </small>
                </p>
            </div>
        </div>
    </div>

    <hr class="margin-top margin-bottom" style="margin-left: 15px; margin-right: 15px; border-top: 1px solid #e5e5e5;">

    {{-- Blocking Mode Section --}}
    <h4 style="margin-left: 15px; margin-bottom: 15px; color: #666;">
        <i class="glyphicon glyphicon-ban-circle"></i> {{ __('Blocking Mode') }}
    </h4>

    {{-- Blocking Mode Field --}}
    <div class="form-group">
        <label for="blocking_mode" class="col-sm-2 control-label">
            {{ __('Blocking Mode') }}
        </label>
        <div class="col-sm-6">
            <select
                class="form-control input-sized-lg"
                id="blocking_mode"
                name="settings[attachmentsecurity.blocking_mode]"
                style="max-width: 650px !important; min-width: 600px;"
            >
                <option value="all" {{ ($settings['attachmentsecurity.blocking_mode'] ?? 'all') === 'all' ? 'selected' : '' }}>
                    {{ __('Block for all users (administrators included - maximum security)') }}
                </option>
                <option value="regular" {{ ($settings['attachmentsecurity.blocking_mode'] ?? 'all') === 'regular' ? 'selected' : '' }}>
                    {{ __('Block for regular users only (administrators exempted)') }}
                </option>
                <option value="disabled" {{ ($settings['attachmentsecurity.blocking_mode'] ?? 'all') === 'disabled' ? 'selected' : '' }}>
                    {{ __('Blocking disabled (allow all file types for everyone)') }}
                </option>
            </select>
            <p class="form-help">
                {{ __('Choose who should be affected by file blocking rules.') }}<br/>
                <strong>{{ __('Block for all users:') }}</strong> {{ __('Maximum security - blocks everyone including admins') }}<br/>
                <strong>{{ __('Block for regular users only:') }}</strong> {{ __('Admins can download blocked files, regular users cannot') }}<br/>
                <strong>{{ __('Blocking disabled:') }}</strong> {{ __('Temporarily disable all blocking (useful for testing)') }}
            </p>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- SECTION 2: Notifications                    --}}
    {{-- ============================================ --}}
    <h3 class="subheader margin-top-50">
        <i class="glyphicon glyphicon-envelope"></i> {{ __('Notifications') }}
    </h3>
    <p class="form-help block-help">
        {{ __('Send email alerts when files are blocked. Uses FreeScout\'s existing SMTP configuration.') }}
    </p>

    {{-- Enable Email Notifications --}}
    <div class="form-group">
        <label for="email_notifications_enabled" class="col-sm-2 control-label">
            {{ __('Enable Email Notifications') }}
        </label>
        <div class="col-sm-6">
            @php
                $mailDriver = $settings['_mail_driver'] ?? 'smtp';
                $isSmtp = ($mailDriver === 'smtp');
            @endphp
            
            <div class="controls">
                <div class="onoffswitch-wrap">
                    <div class="onoffswitch">
                        <input
                            type="checkbox"
                            name="settings[attachmentsecurity.email_notifications_enabled]"
                            value="1"
                            id="email_notifications_enabled"
                            class="onoffswitch-checkbox"
                            {{ ($settings['attachmentsecurity.email_notifications_enabled'] ?? false) ? 'checked' : '' }}
                            {{ !$isSmtp ? 'disabled' : '' }}
                            data-mail-driver="{{ $mailDriver }}"
                        >
                        <label class="onoffswitch-label" for="email_notifications_enabled"></label>
                    </div>
                </div>
            </div>
            <p class="form-help">
                {{ __('Send email notification when a file is blocked.') }}
                @if(!$isSmtp)
                    <br>
                    <span class="text-danger">
                        <i class="glyphicon glyphicon-warning-sign"></i>
                        <strong>{{ __('SMTP driver required') }}</strong> - {{ __('Email notifications are only available with SMTP mail driver. Current driver:') }} <code>{{ $mailDriver ?: 'not configured' }}</code>
                    </span>
                @else
                    <span class="text-muted">({{ __('only available with SMTP driver') }})</span>
                @endif
            </p>
        </div>
    </div>

    {{-- Notification Email Address --}}
    <div class="form-group">
        <label for="notification_email" class="col-sm-2 control-label">
            {{ __('Notification Email Address') }}
        </label>
        <div class="col-sm-6">
            <input
                type="email"
                class="form-control input-sized-lg"
                id="notification_email"
                name="settings[attachmentsecurity.notification_email]"
                value="{{ $settings['attachmentsecurity.notification_email'] ?? '' }}"
                placeholder="security@example.com"
            >
            <p class="form-help">
                {{ __('Email address to receive incident notifications.') }}<br/>
                <span class="text-warning" id="notification_email_warning" style="display: none;">
                    <i class="glyphicon glyphicon-warning-sign"></i>
                    {{ __('Email notifications are enabled but no valid email address is provided. Notifications will not be sent.') }}
                </span>
            </p>
            <span id="notification_email_error" style="color: #d9534f; display: none; font-weight: bold;">
                ⚠️ Invalid email format. Example: security@example.com
            </span>
        </div>
    </div>

    {{-- Notification Email Subject --}}
    <div class="form-group">
        <label for="notification_subject" class="col-sm-2 control-label">
            {{ __('Email Subject') }}
        </label>
        <div class="col-sm-6">
            <input
                type="text"
                class="form-control"
                style="width: 100%; max-width: 800px;"
                id="notification_subject"
                name="settings[attachmentsecurity.notification_subject]"
                value="{{ $settings['attachmentsecurity.notification_subject'] ?: __('default.notification_subject') }}"
                placeholder="{{ __('default.notification_subject') }}"
            >
            <p class="form-help">
                {{ __('Email subject for notifications.') }}<br/>
                {{ __('Available variables:') }} 
                <code>{user}</code> - {{ __('User who attempted download') }}, 
                <code>{ticket}</code> - {{ __('Ticket number') }}, 
                <code>{filename}</code> - {{ __('Blocked filename') }}, 
                <code>{reason}</code> - {{ __('Blocking reason') }}
            </p>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- SECTION 3: Messages & Appearance            --}}
    {{-- ============================================ --}}
    <h3 class="subheader margin-top-50">
        <i class="glyphicon glyphicon-comment"></i> {{ __('Messages & Appearance') }}
    </h3>
    <p class="form-help block-help">
        {{ __('Customize the blocked page appearance and messages shown to users.') }}
    </p>

    {{-- Page Title Field --}}
    <div class="form-group">
        <label for="page_title" class="col-sm-2 control-label">
            {{ __('Page Title') }}
        </label>
        <div class="col-sm-6">
            <input
                type="text"
                class="form-control input-sized-lg"
                id="page_title"
                name="settings[attachmentsecurity.page_title]"
                value="{{ $settings['attachmentsecurity.page_title'] ?: __('default.page_title') }}"
                placeholder="{{ __('default.page_title') }}"
            >
            <p class="form-help">
                {{ __('Title shown at the top of the blocked page.') }}
            </p>
        </div>
    </div>

    {{-- Block Message Field --}}
    <div class="form-group">
        <label for="block_message" class="col-sm-2 control-label">
            {{ __('Block Message') }}
        </label>
        <div class="col-sm-6">
            <textarea
                class="form-control"
                id="block_message"
                name="settings[attachmentsecurity.block_message]"
                rows="4"
                placeholder="{{ __('default.block_message') }}"
            >{{ $settings['attachmentsecurity.block_message'] ?: __('default.block_message') }}</textarea>
            <p class="form-help">
                {{ __('Message displayed when a file is blocked.') }}<br/>
                {{ __('Available variables:') }} <code>{filename}</code> - {{ __('Name of the blocked file') }}, 
                <code>{extension}</code> - {{ __('File extension') }}, 
                <code>{blocked_files}</code> - {{ __('Comma-separated list of blocked files (for archives)') }}
            </p>
        </div>
    </div>

    {{-- Archive Block Message --}}
    <div class="form-group">
        <label for="archive_block_message" class="col-sm-2 control-label">
            {{ __('Archive Block Message') }}
        </label>
        <div class="col-sm-6">
            <textarea
                class="form-control"
                id="archive_block_message"
                name="settings[attachmentsecurity.archive_block_message]"
                rows="3"
                placeholder="{{ __('default.archive_block_message') }}"
            >{{ $settings['attachmentsecurity.archive_block_message'] ?: __('default.archive_block_message') }}</textarea>
            <p class="form-help">
                {{ __('Message shown when a compressed file contains blocked files inside.') }}<br/>
                {{ __('Available variables:') }} <code>{filename}</code>, <code>{blocked_files}</code>
            </p>
        </div>
    </div>

    {{-- Encrypted Archive Block Message --}}
    <div class="form-group">
        <label for="encrypted_archive_block_message" class="col-sm-2 control-label">
            {{ __('Encrypted Archive Block Message') }}
        </label>
        <div class="col-sm-6">
            <textarea
                class="form-control"
                id="encrypted_archive_block_message"
                name="settings[attachmentsecurity.encrypted_archive_block_message]"
                rows="3"
                placeholder="{{ __('default.encrypted_archive_block_message') }}"
            >{{ $settings['attachmentsecurity.encrypted_archive_block_message'] ?: __('default.encrypted_archive_block_message') }}</textarea>
            <p class="form-help">
                {{ __('Message shown when a compressed file is password-protected and cannot be scanned.') }}<br/>
                {{ __('Available variables:') }} <code>{filename}</code>
            </p>
        </div>
    </div>

    {{-- Unreadable Archive Block Message --}}
    <div class="form-group">
        <label for="unreadable_archive_block_message" class="col-sm-2 control-label">
            {{ __('Unreadable Archive Block Message') }}
        </label>
        <div class="col-sm-6">
            <textarea
                class="form-control"
                id="unreadable_archive_block_message"
                name="settings[attachmentsecurity.unreadable_archive_block_message]"
                rows="3"
                placeholder="{{ __('default.unreadable_archive_block_message') }}"
            >{{ $settings['attachmentsecurity.unreadable_archive_block_message'] ?: __('default.unreadable_archive_block_message') }}</textarea>
            <p class="form-help">
                {{ __('Message shown when an archive cannot be scanned (corrupted, invalid format) and Block download mode is enabled.') }}<br/>
                {{ __('Available variables:') }} <code>{filename}</code>
            </p>
        </div>
    </div>

    {{-- Background Color Field --}}
    <div class="form-group">
        <label for="background_color" class="col-sm-2 control-label">
            {{ __('Background Gradient Colors') }}
        </label>
        <div class="col-sm-6">
            <div class="input-group">
                <input
                    type="text"
                    class="form-control"
                    id="background_color"
                    name="settings[attachmentsecurity.background_color]"
                    value="{{ $settings['attachmentsecurity.background_color'] ?? '#4A90E2, #5C6AC4' }}"
                    placeholder="#4A90E2, #5C6AC4"
                >
                <span class="input-group-addon">
                    <i class="glyphicon glyphicon-tint"></i>
                </span>
            </div>
            <p class="form-help">
                {{ __('Gradient background colors (comma-separated hex codes).') }}<br/>
                {{ __('Default:') }} <code>#4A90E2, #5C6AC4</code> ({{ __('Blue') }}), 
                {{ __('Purple:') }} <code>#667eea, #764ba2</code>, 
                {{ __('Green:') }} <code>#11998e, #38ef7d</code>
            </p>
        </div>
    </div>

    {{-- Save and Reset Buttons --}}
    <div class="form-group margin-top margin-bottom-0">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">
                <i class="glyphicon glyphicon-ok"></i> {{ __('Save Settings') }}
            </button>
            <button type="button" class="btn btn-default" 
                    id="reset-defaults" 
                    data-confirm="{{ __('Are you sure you want to reset all settings to default values?') }}"
                    data-defaults="{{ htmlspecialchars(json_encode([
                        'blocked_extensions' => 'exe,php,bat,cmd,htm,html,js,vbs,ps1,sh,phar',
                        'block_no_extension' => false,
                        'blocking_mode' => 'all',
                        'archive_scan_enabled' => false,
                        'max_nesting_depth' => 1,
                        'unreadable_archives_mode' => 'block',
                        'email_notifications_enabled' => false,
                        'notification_email' => '',
                        'notification_subject' => __('default.notification_subject'),
                        'page_title' => __('default.page_title'),
                        'block_message' => __('default.block_message'),
                        'archive_block_message' => __('default.archive_block_message'),
                        'encrypted_archive_block_message' => __('default.encrypted_archive_block_message'),
                        'unreadable_archive_block_message' => __('default.unreadable_archive_block_message'),
                        'background_color' => '#4A90E2, #5C6AC4'
                    ]), ENT_QUOTES) }}"
                    style="margin-left: 10px;">
                <i class="glyphicon glyphicon-refresh"></i> {{ __('Reset to Defaults') }}
            </button>
        </div>
    </div>

</form>

{{-- Load external JavaScript (no CSP issues with external files) --}}
<script src="{{ Module::getPublicPath('attachmentsecurity') }}/js/settings.js"></script>
