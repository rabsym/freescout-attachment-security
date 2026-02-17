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
            >{{ $settings['attachmentsecurity.block_message'] ?? 'For security reasons the file {filename} cannot be downloaded. If you need access to this content, please contact support.' }}</textarea>
            <p class="form-help">
                {{ __('Message displayed when a file is blocked. Variables will appear in bold black:') }}<br/>
                <code>{filename}</code> - {{ __('Name of the blocked file') }}, 
                <code>{extension}</code> - {{ __('File extension') }}
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
                    data-defaults="{{ json_encode([
                        'blocked_extensions' => 'exe,php,bat,cmd,htm,html,js,vbs,ps1,sh,phar',
                        'blocking_mode' => 'all',
                        'page_title' => '🚫 Download Blocked',
                        'block_message' => 'For security reasons the file {filename} cannot be downloaded. If you need access to this content, please contact support.',
                        'background_color' => '#4A90E2, #5C6AC4'
                    ]) }}"
                    style="margin-left: 10px;">
                <i class="glyphicon glyphicon-refresh"></i> {{ __('Reset to Defaults') }}
            </button>
        </div>
    </div>

</form>

{{-- Load external JavaScript (no CSP issues with external files) --}}
<script src="{{ Module::getPublicPath('attachmentsecurity') }}/js/settings.js"></script>
