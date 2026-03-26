<?php

namespace Modules\AttachmentSecurity\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AttachmentSecurity\Logging\LoggerAttachmentSecurity;
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
 * @version 3.4.0
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
        $this->registerTranslations();
        $this->registerConfiguration();
        $this->registerMiddleware();
        $this->registerSettingsHooks();
    }

    /**
     * Register translations.
     *
     * Loads JSON translation files from the module's lang directory,
     * enabling i18n support via Laravel's __() helper.
     *
     * @return void
     */
    protected function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ . '/../Resources/lang');
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

            // Ensure we have a valid $next closure
            if (!$next) {
                $next = fn($req) => $req;
            }

            // Only process attachment download requests
            if (!$request->is('storage/attachment/*')) {
                return $next($request);
            }

            // Delegate to the AttachmentBlocker middleware
            $blocker = new AttachmentBlocker();
            return $blocker->handle($request, $next);
        }, 10, 2);
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

            // v3.4.0: Block files without extension
            $blockNoExtension = Module::getOption(
                self::MODULE_ALIAS,
                'block_no_extension',
                config('attachmentsecurity.block_no_extension')
            );

            // v3.4.0: Email notifications
            $emailNotificationsEnabled = Module::getOption(
                self::MODULE_ALIAS,
                'email_notifications_enabled',
                config('attachmentsecurity.email_notifications_enabled')
            );

            $notificationEmail = Module::getOption(
                self::MODULE_ALIAS,
                'notification_email',
                config('attachmentsecurity.notification_email')
            );

            $notificationSubject = Module::getOption(
                self::MODULE_ALIAS,
                'notification_subject',
                config('attachmentsecurity.notification_subject')
            );

            $settings[self::SETTINGS_SECTION . '.blocked_extensions'] = $blockedExtensions;
            $settings[self::SETTINGS_SECTION . '.block_no_extension'] = $blockNoExtension;
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
            $settings[self::SETTINGS_SECTION . '.email_notifications_enabled'] = $emailNotificationsEnabled;
            $settings[self::SETTINGS_SECTION . '.notification_email'] = $notificationEmail;
            $settings[self::SETTINGS_SECTION . '.notification_subject'] = $notificationSubject;

            // Get archive format capabilities
            $capabilities = $this->getArchiveCapabilities();
            $availableFormats = $this->getAvailableArchiveFormats($capabilities);
            $lastScanDate = Module::getOption(self::MODULE_ALIAS, 'archive_capabilities_scanned_at');

            // Pass capabilities to view (use underscore prefix to distinguish from settings)
            $settings['_archive_capabilities'] = $capabilities;
            $settings['_available_archive_formats'] = $availableFormats;
            $settings['_archive_capabilities_scanned_at'] = $lastScanDate;
            
            // Pass mail driver from FreeScout global options for email notification validation
            $settings['_mail_driver'] = \Option::get('mail_driver', 'smtp');

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

            // Always detect and save capabilities on each save
            $this->detectAndSaveArchiveCapabilities();

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

            // v3.4.0: Block files without extension
            $blockNoExtension = isset($settingsData[self::SETTINGS_SECTION . '.block_no_extension']) 
                ? (bool)$settingsData[self::SETTINGS_SECTION . '.block_no_extension'] 
                : false;

            // v3.4.0: Email notifications
            $emailNotificationsEnabled = isset($settingsData[self::SETTINGS_SECTION . '.email_notifications_enabled']) 
                ? (bool)$settingsData[self::SETTINGS_SECTION . '.email_notifications_enabled'] 
                : false;
            $notificationEmail = $settingsData[self::SETTINGS_SECTION . '.notification_email'] ?? '';
            $notificationSubject = $settingsData[self::SETTINGS_SECTION . '.notification_subject'] ?? config('attachmentsecurity.notification_subject');

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

            // v3.4.0: Validate email notifications
            // Email notifications require SMTP mail driver
            $mailDriver = \Option::get('mail_driver');
            
            if ($emailNotificationsEnabled) {
                // Check if mail driver is SMTP
                if ($mailDriver !== 'smtp') {
                    // Force disable notifications if not using SMTP
                    $emailNotificationsEnabled = false;
                    LoggerAttachmentSecurity::alert('[SETTINGS] Email notifications disabled: SMTP driver required (current driver: ' . ($mailDriver ?: 'not configured') . ')');
                }
                // If using SMTP, validate email
                elseif ($emailNotificationsEnabled) {
                    $notificationEmail = trim($notificationEmail);
                    
                    // Check if email is empty
                    if (empty($notificationEmail)) {
                        // Force disable notifications if no email
                        $emailNotificationsEnabled = false;
                        LoggerAttachmentSecurity::alert('[SETTINGS] Email notifications disabled: no email address provided');
                    }
                    // Check if email format is valid
                    elseif (!filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
                        // Force disable notifications if invalid email
                        $emailNotificationsEnabled = false;
                        LoggerAttachmentSecurity::alert('[SETTINGS] Email notifications disabled: invalid email format - ' . $notificationEmail);
                    }
                }
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

            // v3.4.0: Save block_no_extension setting
            Module::setOption(
                self::MODULE_ALIAS,
                'block_no_extension',
                $blockNoExtension
            );

            // v3.4.0: Save email notification settings
            Module::setOption(
                self::MODULE_ALIAS,
                'email_notifications_enabled',
                $emailNotificationsEnabled
            );

            Module::setOption(
                self::MODULE_ALIAS,
                'notification_email',
                $notificationEmail
            );

            Module::setOption(
                self::MODULE_ALIAS,
                'notification_subject',
                $notificationSubject
            );

            // Log the configuration change
            $this->logConfigurationChange(
                $blockedExtensions, 
                $blockingMode, 
                $archiveScanEnabled, 
                $maxNestingDepth, 
                $unreadableArchivesMode,
                $blockNoExtension,
                $emailNotificationsEnabled,
                $notificationEmail
            );

            return $request;
        }, 20, 3);
    }

    /**
     * Log configuration changes to the module's log file.
     *
     * @param string $blockedExtensions Comma-separated list of blocked extensions
     * @param string $blockingMode      Blocking mode (all, regular, disabled)
     * @param bool $archiveScanEnabled  Archive scan status
     * @param int $maxNestingDepth      Maximum nesting depth
     * @param string $unreadableArchivesMode Unreadable archives mode (block, allow)
     * @param bool $blockNoExtension    Block files without extension
     * @param bool $emailNotificationsEnabled Email notifications status
     * @param string $notificationEmail Email address for notifications
     * @return void
     */
    protected function logConfigurationChange(
        $blockedExtensions, 
        $blockingMode, 
        $archiveScanEnabled = false, 
        $maxNestingDepth = 1, 
        $unreadableArchivesMode = 'block',
        $blockNoExtension = false,
        $emailNotificationsEnabled = false,
        $notificationEmail = ''
    )
    {
        $logFile = storage_path('logs/attachmentsecurity.log');
        
        // Get available formats from capabilities
        $capabilitiesJson = \Module::getOption('attachmentsecurity', 'archive_capabilities');
        $availableFormats = [];
        if ($capabilitiesJson) {
            $capabilities = is_array($capabilitiesJson) ? $capabilitiesJson : json_decode($capabilitiesJson, true);
            if ($capabilities) {
                foreach ($capabilities as $format => $info) {
                    if ($info['available'] ?? false) {
                        $type = $info['type'] ?? 'unknown';
                        $availableFormats[] = $format . '(' . $type . ')';
                    }
                }
            }
        }
        $formatsString = !empty($availableFormats) ? implode(', ', $availableFormats) : 'none';


        LoggerAttachmentSecurity::info(
            '[SERVICEPROVIDER] CONFIGURATION SAVED - ' . sprintf(
                "Blocked Extensions: %s | Block No Extension: %s | Block Mode: %s | Archive Scan: %s | Nesting Depth: %d | Unreadable Archives: %s | Email Notifications: %s%s | Supported Archive Formats: %s",
                $blockedExtensions ?: 'none',
                $blockNoExtension ? 'enabled' : 'disabled',
                $blockingMode,
                $archiveScanEnabled ? 'enabled' : 'disabled',
                $maxNestingDepth,
                $unreadableArchivesMode,
                $emailNotificationsEnabled ? 'enabled' : 'disabled',
                $emailNotificationsEnabled ? " | Notification Email: $notificationEmail" : '',
                $formatsString
            )
        );

    }

    /**
     * Detect and save archive format capabilities to database
     *
     * @return array Detected capabilities
     */
    protected function detectAndSaveArchiveCapabilities(): array
    {
        $capabilities = \Modules\AttachmentSecurity\Services\ScannerCapabilities::detect();
        
        // Save capabilities as JSON
        \Module::setOption(
            self::MODULE_ALIAS, 
            'archive_capabilities', 
            json_encode($capabilities)
        );
        
        // Save scan timestamp
        \Module::setOption(
            self::MODULE_ALIAS,
            'archive_capabilities_scanned_at',
            date('Y-m-d H:i:s')
        );
        
        return $capabilities;
    }

    /**
     * Get cached archive capabilities (or detect if not cached)
     *
     * @return array Archive format capabilities
     */
    protected function getArchiveCapabilities(): array
    {
        $cached = \Module::getOption(self::MODULE_ALIAS, 'archive_capabilities');
        
        if (!$cached) {
            // First time - detect and save
            return $this->detectAndSaveArchiveCapabilities();
        }
        
        // Check if already decoded (array) or needs decoding (string)
        if (is_array($cached)) {
            return $cached;
        }
        
        return json_decode($cached, true) ?: [];
    }

    /**
     * Get comma-separated list of available archive formats
     *
     * @param array|null $capabilities If null, will load from cache
     * @return string Comma-separated format list (e.g., "zip,rar,tar")
     */
    protected function getAvailableArchiveFormats(?array $capabilities = null): string
    {
        if ($capabilities === null) {
            $capabilities = $this->getArchiveCapabilities();
        }

        $available = [];
        foreach ($capabilities as $format => $info) {
            if ($info['available']) {
                $available[] = $format;
            }
        }

        return implode(',', $available);
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
