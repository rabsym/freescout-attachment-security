<?php

namespace Modules\AttachmentSecurity\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AttachmentSecurity\Http\Middleware\AttachmentBlocker;
use Module;

/**
 * AttachmentSecurity Service Provider
 *
 * Integrates the AttachmentSecurity module with FreeScout's settings system,
 * registers middleware for blocking attachment downloads based on file extensions,
 * and provides role-based blocking capabilities.
 *
 * @package Modules\AttachmentSecurity
 * @author  Raimundo Alba
 * @version 3.1.0
 */
class AttachmentSecurityServiceProvider extends ServiceProvider
{
    /**
     * Module configuration constants
     */
    const MODULE_ALIAS = 'attachmentsecurity';
    const SETTINGS_SECTION = 'attachmentsecurity';
    
    /**
     * Blocking mode constants
     */
    const MODE_ALL = 'all';
    const MODE_REGULAR = 'regular';
    const MODE_DISABLED = 'disabled';

    /**
     * Unreadable archive modes
     */
    const UNREADABLE_MODE_BLOCK = 'block';
    const UNREADABLE_MODE_ALLOW = 'allow';



    /**
     * Bootstrap module services.
     *
     * This method is called after all other service providers have been registered,
     * meaning you have access to all other services that have been registered.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerViews();
        $this->registerConfiguration();
        $this->registerMiddleware();
        $this->registerSettingsHooks();
    }

    /**
     * Register the module's views.
     *
     * Uses the same registration method as FreeScout's core modules
     * to ensure proper view resolution and compatibility.
     *
     * @return void
     */
    protected function registerViews()
    {
        $viewPath = resource_path('views/modules/' . self::MODULE_ALIAS);
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(
            array_merge(
                array_map(function ($path) {
                    return $path . '/modules/' . self::MODULE_ALIAS;
                }, \Config::get('view.paths')),
                [$sourcePath]
            ),
            self::MODULE_ALIAS
        );
    }

    /**
     * Register module configuration.
     *
     * @return void
     */
    protected function registerConfiguration()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            self::MODULE_ALIAS
        );
    }

    /**
     * Register the attachment blocking middleware.
     *
     * Filters requests and delegates to AttachmentBlocker middleware.
     * 
     * v3.0.1-dev: Testing refactored architecture with logging.
     *
     * @return void
     */
    protected function registerMiddleware()
    {
        \Eventy::addAction('middleware.web.custom_handle', function ($request, $next = null) {
            // Debug logging commented for production
            // $this->logDebug('ServiceProvider: middleware hook called');
            
            // Ensure we have a valid $next closure
            if (!$next) {
                $next = fn($req) => $req;
            }

            // Only process attachment download requests
            if (!$request->is('storage/attachment/*')) {
                // $this->logDebug('ServiceProvider: Not attachment request, passing through');
                return $next($request);
            }

            // $this->logDebug('ServiceProvider: IS attachment request, delegating to Middleware');
            
            // Delegate to the AttachmentBlocker middleware
            $blocker = new AttachmentBlocker();
            return $blocker->handle($request, $next);
        }, 10, 2);
    }
    
    /**
     * Debug logging helper
     */
    protected function logDebug($message, $context = [])
    {
        $logFile = storage_path('logs/attachmentsecurity.log');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] [DEBUG] [SERVICEPROVIDER] {$message}{$contextStr}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Generate blocked page HTML dynamically.
     */
    
    /**
     * Register settings-related hooks.
     *
     * Integrates the module with FreeScout's settings system by registering
     * the settings section, providing settings data, and handling saves.
     *
     * @return void
     */
    protected function registerSettingsHooks()
    {
        $this->registerSettingsSection();
        $this->registerSettingsData();
        $this->registerSettingsView();
        $this->registerSettingsSave();
    }

    /**
     * Register the module's settings section in FreeScout's settings menu.
     *
     * @return void
     */
    protected function registerSettingsSection()
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            if (!is_array($sections)) {
                $sections = [];
            }

            $sections[self::SETTINGS_SECTION] = [
                'title' => __('Attachment Security'),
                'icon'  => 'lock',
                'order' => 500,
            ];

            return $sections;
        });
    }

    /**
     * Provide settings data for the module's settings page.
     *
     * @return void
     */
    protected function registerSettingsData()
    {
        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ($section !== self::SETTINGS_SECTION) {
                return $settings;
            }

            // Get blocked extensions from database or use defaults
            $blockedExtensions = Module::getOption(
                self::MODULE_ALIAS,
                'blocked_extensions',
                config('attachmentsecurity.blocked_extensions')
            );

            // Get blocking mode from database or use default
            $blockingMode = Module::getOption(
                self::MODULE_ALIAS,
                'blocking_mode',
                self::MODE_ALL
            );

            // Get custom block message or use default
            $blockMessage = Module::getOption(
                self::MODULE_ALIAS,
                'block_message',
                config('attachmentsecurity.block_message')
            );

            // Get page title or use default
            $pageTitle = Module::getOption(
                self::MODULE_ALIAS,
                'page_title',
                '🚫 Download Blocked'
            );

            // Get background color or use default (blue gradient)
            $backgroundColor = Module::getOption(
                self::MODULE_ALIAS,
                'background_color',
                '#4A90E2, #5C6AC4'
            );

            // Archive Scanning options
            $archiveScanEnabled = Module::getOption(
                self::MODULE_ALIAS,
                'archive_scan_enabled',
                false
            );

            $archiveExtensions = Module::getOption(
                self::MODULE_ALIAS,
                'archive_extensions',
                config('attachmentsecurity.archive_extensions')
            );

            $maxNestingDepth = Module::getOption(
                self::MODULE_ALIAS,
                'max_nesting_depth',
                config('attachmentsecurity.max_nesting_depth')
            );

            $archiveBlockMessage = Module::getOption(
                self::MODULE_ALIAS,
                'archive_block_message',
                config('attachmentsecurity.archive_block_message')
            );

            $encryptedArchiveBlockMessage = Module::getOption(
                self::MODULE_ALIAS,
                'encrypted_archive_block_message',
                config('attachmentsecurity.encrypted_archive_block_message')
            );

            $unreadableArchivesMode = Module::getOption(
                self::MODULE_ALIAS,
                'unreadable_archives_mode',
                config('attachmentsecurity.unreadable_archives_mode')
            );

            $unreadableArchiveBlockMessage = Module::getOption(
                self::MODULE_ALIAS,
                'unreadable_archive_block_message',
                config('attachmentsecurity.unreadable_archive_block_message')
            );

            $settings[self::SETTINGS_SECTION . '.blocked_extensions'] = $blockedExtensions;
            $settings[self::SETTINGS_SECTION . '.blocking_mode'] = $blockingMode;
            $settings[self::SETTINGS_SECTION . '.block_message'] = $blockMessage;
            $settings[self::SETTINGS_SECTION . '.page_title'] = $pageTitle;
            $settings[self::SETTINGS_SECTION . '.background_color'] = $backgroundColor;
            $settings[self::SETTINGS_SECTION . '.archive_scan_enabled'] = $archiveScanEnabled;
            $settings[self::SETTINGS_SECTION . '.archive_extensions'] = $archiveExtensions;
            $settings[self::SETTINGS_SECTION . '.max_nesting_depth'] = $maxNestingDepth;
            $settings[self::SETTINGS_SECTION . '.archive_block_message'] = $archiveBlockMessage;
            $settings[self::SETTINGS_SECTION . '.encrypted_archive_block_message'] = $encryptedArchiveBlockMessage;
            $settings[self::SETTINGS_SECTION . '.unreadable_archives_mode'] = $unreadableArchivesMode;
            $settings[self::SETTINGS_SECTION . '.unreadable_archive_block_message'] = $unreadableArchiveBlockMessage;

            return $settings;
        }, 20, 2);
    }

    /**
     * Register the view for the settings page.
     *
     * @return void
     */
    protected function registerSettingsView()
    {
        \Eventy::addFilter('settings.view', function ($view, $section) {
            if ($section === self::SETTINGS_SECTION) {
                return self::MODULE_ALIAS . '::settings';
            }
            return $view;
        }, 20, 2);
    }

    /**
     * Handle settings save operations.
     *
     * This hook is triggered before settings are saved, allowing us to
     * intercept and process the form data.
     *
     * @return void
     */
    protected function registerSettingsSave()
    {
        \Eventy::addFilter('settings.before_save', function ($request, $section, $settings) {
            if ($section !== self::SETTINGS_SECTION) {
                return $request;
            }

            // Extract settings data from request
            // Using array notation because the setting keys contain dots
            $settingsData = $request->input('settings', []);
            
            $blockedExtensions = $settingsData[self::SETTINGS_SECTION . '.blocked_extensions'] ?? '';
            $blockingMode = $settingsData[self::SETTINGS_SECTION . '.blocking_mode'] ?? self::MODE_ALL;
            $blockMessage = $settingsData[self::SETTINGS_SECTION . '.block_message'] ?? config('attachmentsecurity.block_message');
            $pageTitle = $settingsData[self::SETTINGS_SECTION . '.page_title'] ?? config('attachmentsecurity.page_title');
            $backgroundColor = $settingsData[self::SETTINGS_SECTION . '.background_color'] ?? config('attachmentsecurity.background_color');
            
            // Archive Scan settings
            $archiveScanEnabled = isset($settingsData[self::SETTINGS_SECTION . '.archive_scan_enabled']) 
                ? (bool)$settingsData[self::SETTINGS_SECTION . '.archive_scan_enabled'] 
                : false;
            $archiveExtensions = $settingsData[self::SETTINGS_SECTION . '.archive_extensions'] ?? config('attachmentsecurity.archive_extensions');
            $maxNestingDepth = $settingsData[self::SETTINGS_SECTION . '.max_nesting_depth'] ?? config('attachmentsecurity.max_nesting_depth');
            $archiveBlockMessage = $settingsData[self::SETTINGS_SECTION . '.archive_block_message'] ?? config('attachmentsecurity.archive_block_message');
            $encryptedArchiveBlockMessage = $settingsData[self::SETTINGS_SECTION . '.encrypted_archive_block_message'] ?? config('attachmentsecurity.encrypted_archive_block_message');
            $unreadableArchivesMode = $settingsData[self::SETTINGS_SECTION . '.unreadable_archives_mode'] ?? config('attachmentsecurity.unreadable_archives_mode');
            $unreadableArchiveBlockMessage = $settingsData[self::SETTINGS_SECTION . '.unreadable_archive_block_message'] ?? config('attachmentsecurity.unreadable_archive_block_message');

            // Validate blocking mode
            $validModes = [self::MODE_ALL, self::MODE_REGULAR, self::MODE_DISABLED];
            if (!in_array($blockingMode, $validModes)) {
                $blockingMode = self::MODE_ALL;
            }

            // Validate unreadable archives mode
            $validUnreadableModes = [self::UNREADABLE_MODE_BLOCK, self::UNREADABLE_MODE_ALLOW];
            if (!in_array($unreadableArchivesMode, $validUnreadableModes)) {
                $unreadableArchivesMode = config('attachmentsecurity.unreadable_archives_mode');
            }

            // Save blocked extensions
            Module::setOption(
                self::MODULE_ALIAS,
                'blocked_extensions',
                $blockedExtensions
            );

            // Save blocking mode
            Module::setOption(
                self::MODULE_ALIAS,
                'blocking_mode',
                $blockingMode
            );

            // Save block message
            Module::setOption(
                self::MODULE_ALIAS,
                'block_message',
                $blockMessage
            );

            // Save page title
            Module::setOption(
                self::MODULE_ALIAS,
                'page_title',
                $pageTitle
            );

            // Save background color
            Module::setOption(
                self::MODULE_ALIAS,
                'background_color',
                $backgroundColor
            );

            // Save archive scan settings
            Module::setOption(
                self::MODULE_ALIAS,
                'archive_scan_enabled',
                $archiveScanEnabled
            );

            Module::setOption(
                self::MODULE_ALIAS,
                'archive_extensions',
                $archiveExtensions
            );

            Module::setOption(
                self::MODULE_ALIAS,
                'max_nesting_depth',
                $maxNestingDepth
            );

            Module::setOption(
                self::MODULE_ALIAS,
                'archive_block_message',
                $archiveBlockMessage
            );

            Module::setOption(
                self::MODULE_ALIAS,
                'encrypted_archive_block_message',
                $encryptedArchiveBlockMessage
            );

            Module::setOption(
                self::MODULE_ALIAS,
                'unreadable_archives_mode',
                $unreadableArchivesMode
            );

            Module::setOption(
                self::MODULE_ALIAS,
                'unreadable_archive_block_message',
                $unreadableArchiveBlockMessage
            );

            // Log the configuration change
            $this->logConfigurationChange($blockedExtensions, $blockingMode, $archiveScanEnabled);

            return $request;
        }, 20, 3);
    }

    /**
     * Log configuration changes to the module's log file.
     *
     * @param string $blockedExtensions Comma-separated list of blocked extensions
     * @param string $blockingMode      Blocking mode (all, regular, disabled)
     * @param bool $archiveScanEnabled  Archive scan status
     * @return void
     */
    protected function logConfigurationChange($blockedExtensions, $blockingMode, $archiveScanEnabled = false)
    {
        $logFile = storage_path('logs/attachmentsecurity.log');
        
        $logEntry = sprintf(
            "[%s] [INFO] [SERVICEPROVIDER] Configuration saved - Extensions: %s | Mode: %s | Archive Scan: %s\n",
            date('Y-m-d H:i:s'),
            $blockedExtensions ?: 'none',
            $blockingMode,
            $archiveScanEnabled ? 'enabled' : 'disabled'
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Module service registration can be added here if needed
    }
}
