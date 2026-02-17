<?php

/**
 * Attachment Security Module Configuration
 *
 * This configuration file defines default settings for the AttachmentSecurity module.
 * Settings defined here serve as fallbacks when no custom configuration exists in the database.
 *
 * The module reads configuration dynamically from FreeScout's admin panel, allowing
 * administrators to customize settings without modifying code. Changes made through
 * the admin interface take effect immediately without requiring cache clearing.
 *
 * @package Modules\AttachmentSecurity
 * @author  Raimundo Alba
 * @version 3.0.0
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Blocked File Extensions
    |--------------------------------------------------------------------------
    |
    | List of file extensions that will be blocked from attachment downloads.
    | Administrators can modify this list through the FreeScout settings interface.
    |
    | Format: Comma-separated string (e.g., 'exe,php,bat') or array
    | Example: ['exe', 'php', 'bat', 'cmd']
    |
    | Default extensions blocked:
    | - Executable files: exe, bat, cmd, sh, ps1
    | - Script files: php, js, vbs, phar
    | - Web files: htm, html
    |
    */

    'blocked_extensions' => Module::getOption(
        'attachmentsecurity',
        'blocked_extensions',
        'exe,php,bat,cmd,htm,html,js,vbs,ps1,sh,phar'
    ),

    /*
    |--------------------------------------------------------------------------
    | Blocking Mode
    |--------------------------------------------------------------------------
    |
    | Determines who is affected by the attachment blocking rules.
    |
    | Available modes:
    | - 'all':      Block downloads for all users (administrators included)
    | - 'regular':  Block downloads only for regular users (administrators exempted)
    | - 'disabled': Disable blocking entirely (all file types allowed)
    |
    | Default: 'all' (maximum security)
    |
    */

    'blocking_mode' => Module::getOption(
        'attachmentsecurity',
        'blocking_mode',
        'all'
    ),

    /*
    |--------------------------------------------------------------------------
    | Block Message
    |--------------------------------------------------------------------------
    |
    | Custom message displayed to users when a file download is blocked.
    | This message appears on a custom blocked page.
    |
    | Available variables:
    | - {filename}  : The name of the blocked file
    | - {extension} : The file extension (without dot)
    |
    | Example: "Cannot download {filename} - .{extension} files are blocked."
    |
    | Default: Standard security message in English
    |
    */

    'block_message' => Module::getOption(
        'attachmentsecurity',
        'block_message',
        'For security reasons the file {filename} cannot be downloaded. If you need access to this content, please contact support.'
    ),

];
