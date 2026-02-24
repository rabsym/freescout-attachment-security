<?php

/**
 * Attachment Security Module Configuration
 *
 * This configuration file defines default settings for the AttachmentSecurity module.
 * These values are used as fallbacks when no custom configuration exists in the database.
 *
 * @package Modules\AttachmentSecurity
 * @author  Raimundo Alba
 * @version 3.1.0
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Blocked File Extensions
    |--------------------------------------------------------------------------
    |
    | Default list of file extensions that will be blocked from downloads.
    |
    */
    'blocked_extensions' => 'exe,php,bat,cmd,htm,html,js,vbs,ps1,sh,phar',

    /*
    |--------------------------------------------------------------------------
    | Blocking Mode
    |--------------------------------------------------------------------------
    |
    | Who should be affected by the blocking rules.
    | Options: 'all', 'regular', 'disabled'
    |
    */
    'blocking_mode' => 'all',

    /*
    |--------------------------------------------------------------------------
    | Page Title
    |--------------------------------------------------------------------------
    |
    | Title shown on the blocked page.
    |
    */
    'page_title' => '🚫 Download Blocked',

    /*
    |--------------------------------------------------------------------------
    | Background Color
    |--------------------------------------------------------------------------
    |
    | Gradient background colors (comma-separated hex codes).
    |
    */
    'background_color' => '#4A90E2, #5C6AC4',

    /*
    |--------------------------------------------------------------------------
    | Block Messages
    |--------------------------------------------------------------------------
    |
    | Messages shown when downloads are blocked.
    | Available variables: {filename}, {extension}, {blocked_files}
    |
    */
    'block_message' => 'For security reasons the file {filename} cannot be downloaded. If you need access to this content, please contact support.',

    'archive_block_message' => 'The file {filename} contains blocked files: {blocked_files}',

    'encrypted_archive_block_message' => 'The file {filename} is password-protected and cannot be scanned for security reasons.',

    'unreadable_archive_block_message' => 'The file {filename} cannot be scanned because it appears to be corrupted or has an invalid format. For security reasons, the download has been blocked.',

    /*
    |--------------------------------------------------------------------------
    | Archive Scanning Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for scanning compressed files.
    |
    */
    'archive_scan_enabled' => false,

    'archive_extensions' => 'zip',

    'max_nesting_depth' => 1,

    'unreadable_archives_mode' => 'block',

];
