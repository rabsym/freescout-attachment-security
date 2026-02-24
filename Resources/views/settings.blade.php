<form class="form-horizontal margin-top margin-bottom" method="POST" action="">
    {{ csrf_field() }}

    <input type="hidden" name="settings[dummy]" value="1" />

    {{-- SECTION 1: Security Configuration --}}
    <h3 class="subheader">
        <i class="glyphicon glyphicon-lock"></i> {{ __('Security Configuration') }}
    </h3>
    <p class="form-help block-help">
        {{ __('Configure which file types should be blocked and who should be affected by these restrictions.') }}
    </p>

    {{-- Blocked File Extensions Field --}}
    <div class="form-group">
        <label for="blocked_extensions" class="col-sm-2 control-label">
            {{ __('Blocked File Extensions') }}
        </label>
        <div class="col-sm-6">
            <input
                type="text"
                class="form-control input-sized-lg"
                id="blocked_extensions"
                name="settings[attachmentsecurity.blocked_extensions]"
                value="{{ $settings['attachmentsecurity.blocked_extensions'] ?? '' }}"
                placeholder="exe,php,bat,cmd,js,html"
            >
            <p class="form-help">
                {{ __('Enter comma-separated file extensions to block. Do not include dots.') }}<br/>
                {{ __('Example:') }} <code>exe,php,bat,cmd,htm,html,js,vbs,ps1,sh,phar</code>
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

    {{-- Scanned Archive Extensions (read-only for now) --}}
    <div class="form-group">
        <label for="archive_extensions" class="col-sm-2 control-label">
            {{ __('Scanned Archive Extensions') }}
        </label>
        <div class="col-sm-6">
            <input
                type="text"
                class="form-control input-sized-lg"
                id="archive_extensions"
                name="settings[attachmentsecurity.archive_extensions]"
                value="{{ $settings['attachmentsecurity.archive_extensions'] ?? 'zip' }}"
                readonly
                style="background-color: #f5f5f5; cursor: not-allowed;"
            >
            <p class="form-help">
                {{ __('Archive file types to scan (read-only in current version).') }}<br/>
                <span style="color: #666;">{{ __('Currently supports ZIP files. More formats coming in future versions.') }}</span>
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
            >
                <option value="0" {{ ($settings['attachmentsecurity.max_nesting_depth'] ?? 1) == 0 ? 'selected' : '' }}>
                    {{ __('0 levels (scan ZIP only, do not scan nested archives)') }}
                </option>
                <option value="1" {{ ($settings['attachmentsecurity.max_nesting_depth'] ?? 1) == 1 ? 'selected' : '' }}>
                    {{ __('1 level (scan ZIP and 1 level of nested ZIPs - recommended)') }}
                </option>
                <option value="2" {{ ($settings['attachmentsecurity.max_nesting_depth'] ?? 1) == 2 ? 'selected' : '' }}>
                    {{ __('2 levels (scan ZIP and 2 levels of nested ZIPs)') }}
                </option>
            </select>
            <p class="form-help">
                {{ __('How many levels deep to scan for nested compressed files.') }}<br/>
                <strong>{{ __('Level 0:') }}</strong> {{ __('Only scan the main ZIP file') }}<br/>
                <strong>{{ __('Level 1:') }}</strong> {{ __('Scan main ZIP + ZIPs inside it (recommended)') }}<br/>
                <strong>{{ __('Level 2:') }}</strong> {{ __('Scan main ZIP + ZIPs inside + ZIPs inside those') }}
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

    <hr class="margin-top margin-bottom" style="margin-left: 15px; margin-right: 15px; border-top: 1px solid #e5e5e5;">

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
            >
                <option value="all" {{ ($settings['attachmentsecurity.blocking_mode'] ?? 'all') === 'all' ? 'selected' : '' }}>
                    {{ __('Block for all users') }}
                </option>
                <option value="regular" {{ ($settings['attachmentsecurity.blocking_mode'] ?? 'all') === 'regular' ? 'selected' : '' }}>
                    {{ __('Block for regular users only (exclude administrators)') }}
                </option>
                <option value="disabled" {{ ($settings['attachmentsecurity.blocking_mode'] ?? 'all') === 'disabled' ? 'selected' : '' }}>
                    {{ __('Blocking disabled') }}
                </option>
            </select>
            <p class="form-help">
                <strong>{{ __('Applies to both regular files and files inside archives.') }}</strong><br/>
                {{ __('Choose who should be restricted from downloading blocked file types.') }}<br/>
                <strong>{{ __('Block for all users:') }}</strong> {{ __('Prevents everyone from downloading blocked files.') }}<br/>
                <strong>{{ __('Block for regular users only:') }}</strong> {{ __('Administrators can download any file type.') }}<br/>
                <strong>{{ __('Blocking disabled:') }}</strong> {{ __('All file types are allowed for everyone.') }}
            </p>
        </div>
    </div>

    <hr class="margin-top margin-bottom">

    {{-- SECTION 2: Notifications & Messages --}}
    <h3 class="subheader">
        <i class="glyphicon glyphicon-comment"></i> {{ __('Notifications & Messages') }}
    </h3>
    <p class="form-help block-help">
        {{ __('Customize the blocked page appearance and message.') }}
    </p>

    {{-- Page Title Field (FIRST) --}}
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
                value="{{ $settings['attachmentsecurity.page_title'] ?? '🚫 Download Blocked' }}"
                placeholder="🚫 Download Blocked"
            >
            <p class="form-help">
                {{ __('Title shown at the top of the blocked page.') }}
            </p>
        </div>
    </div>

    {{-- Block Message Field (SECOND) --}}
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
                placeholder="For security reasons the file {filename} cannot be downloaded."
            >{{ $settings['attachmentsecurity.block_message'] ?? '' }}</textarea>
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
                placeholder="The file {filename} contains blocked files: {blocked_files}"
            >{{ $settings['attachmentsecurity.archive_block_message'] ?? '' }}</textarea>
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
                placeholder="The file {filename} is password-protected and cannot be scanned for security reasons."
            >{{ $settings['attachmentsecurity.encrypted_archive_block_message'] ?? '' }}</textarea>
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
                placeholder="The file {filename} cannot be scanned because it appears to be corrupted or has an invalid format. For security reasons, the download has been blocked."
            >{{ $settings['attachmentsecurity.unreadable_archive_block_message'] ?? '' }}</textarea>
            <p class="form-help">
                {{ __('Message shown when an archive cannot be scanned (corrupted, invalid format) and Block download mode is enabled.') }}<br/>
                {{ __('Available variables:') }} <code>{filename}</code>
            </p>
        </div>
    </div>

    {{-- Background Color Field (THIRD) --}}
    <div class="form-group">
        <label for="background_color" class="col-sm-2 control-label">
            {{ __('Background Color') }}
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
                        'blocking_mode' => 'all',
                        'archive_scan_enabled' => false,
                        'archive_extensions' => 'zip',
                        'max_nesting_depth' => 1,
                        'unreadable_archives_mode' => 'block',
                        'page_title' => '🚫 Download Blocked',
                        'block_message' => 'For security reasons the file {filename} cannot be downloaded. If you need access to this content, please contact support.',
                        'archive_block_message' => 'The file {filename} contains blocked files: {blocked_files}',
                        'encrypted_archive_block_message' => 'The file {filename} is password-protected and cannot be scanned for security reasons.',
                        'unreadable_archive_block_message' => 'The file {filename} cannot be scanned because it appears to be corrupted or has an invalid format. For security reasons, the download has been blocked.',
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
