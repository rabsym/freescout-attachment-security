<?php

namespace Modules\AttachmentSecurity\Http\Middleware;

use Closure;
use Module;
use Modules\AttachmentSecurity\Services\ArchiveScanner;
use Modules\AttachmentSecurity\Logging\LoggerAttachmentSecurity;

/**
 * Attachment Blocker Middleware
 *
 * Handles attachment download blocking based on file extension and blocking mode.
 * Separated from ServiceProvider in v3.0.0 for better code organization.
 * v3.2.0: Added multi-format archive scanning (ZIP, RAR, TAR, GZ, BZ2).
 *
 * @package Modules\AttachmentSecurity
 * @author  Raimundo Alba
 * @version 3.4.0
 */
class AttachmentBlocker
{
    const MODE_ALL = 'all';
    const MODE_REGULAR = 'regular';
    const MODE_DISABLED = 'disabled';

    // Unreadable archive modes
    const UNREADABLE_MODE_BLOCK = 'block';
    const UNREADABLE_MODE_ALLOW = 'allow';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        // Extract extension
        $path = $request->segment(count($request->segments()));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_BASENAME);

        // Check if file has no extension and blocking is enabled
        $blockNoExtension = Module::getOption('attachmentsecurity', 'block_no_extension', config('attachmentsecurity.block_no_extension'));
        
        if (empty($extension) && $blockNoExtension) {
            // File has no extension and blocking is enabled
            $blockingMode = Module::getOption('attachmentsecurity', 'blocking_mode', self::MODE_ALL);

            if ($blockingMode === self::MODE_DISABLED) {
                return $next($request);
            }

            if ($blockingMode === self::MODE_REGULAR && auth()->check() && auth()->user()->isAdmin()) {
                return $next($request);
            }

            // Block this file (no extension)
            return $this->blockDownload($request, $path, '', 'no_extension');
        }

        if (empty($extension)) {
            return $next($request);
        }

        // Get blocked extensions (with cache clear)
        \Cache::forget('module_options_attachmentsecurity');
        $blockedExtStr = Module::getOption('attachmentsecurity', 'blocked_extensions', config('attachmentsecurity.blocked_extensions'));
        $blockedExtensions = array_filter(array_map('trim', explode(',', strtolower($blockedExtStr))));

        // EXISTING LOGIC: Check if extension is directly blocked
        if (in_array($extension, $blockedExtensions)) {

            // Check blocking mode
            $blockingMode = Module::getOption('attachmentsecurity', 'blocking_mode', self::MODE_ALL);

            if ($blockingMode === self::MODE_DISABLED) {
                return $next($request);
            }

            if ($blockingMode === self::MODE_REGULAR && auth()->check() && auth()->user()->isAdmin()) {
                return $next($request);
            }

            // Block this file (existing v3.0.0 logic)
            return $this->blockDownload($request, $path, $extension, 'regular');
        }
        
        // NEW LOGIC v3.2.0: Check if this is an archive that should be scanned
        $archiveScanEnabled = Module::getOption('attachmentsecurity', 'archive_scan_enabled', false);
        
        // Get available archive formats from capabilities (not from archive_extensions setting)
        $archiveExtensions = [];
        if ($archiveScanEnabled) {
            $capabilitiesJson = Module::getOption('attachmentsecurity', 'archive_capabilities');
            if ($capabilitiesJson) {
                $capabilities = is_array($capabilitiesJson) ? $capabilitiesJson : json_decode($capabilitiesJson, true);
                if ($capabilities) {
                    foreach ($capabilities as $format => $info) {
                        if ($info['available'] ?? false) {
                            $archiveExtensions[] = $format;
                            
                            // Add alternative extensions for compound formats
                            // TGZ requires both TAR and GZ
                            if ($format === 'gz' && isset($capabilities['tar']) && ($capabilities['tar']['available'] ?? false)) {
                                $archiveExtensions[] = 'tgz';  // .tgz is tar.gz
                            }
                            // TBZ2/TB2 require both TAR and BZ2
                            elseif ($format === 'bz2' && isset($capabilities['tar']) && ($capabilities['tar']['available'] ?? false)) {
                                $archiveExtensions[] = 'tbz2'; // .tbz2 is tar.bz2
                                $archiveExtensions[] = 'tb2';  // .tb2 is tar.bz2
                            }
                        }
                    }
                }
            }
        }
        
        if ($archiveScanEnabled && !empty($archiveExtensions) && in_array($extension, $archiveExtensions)) {
            // This is an archive file and scanning is enabled
            return $this->handleArchiveScan($request, $path, $extension, $blockedExtensions, $next);
        }

        return $next($request);
    }

    /**
     * Handle archive scanning for ZIP files (v3.1.0)
     * 
     * Configurable behavior: can block or allow unreadable archives based on settings
     *
     * @param \Illuminate\Http\Request $request
     * @param string $path File path
     * @param string $extension File extension
     * @param array $blockedExtensions List of blocked extensions
     * @param \Closure $next
     * @return mixed
     */
    protected function handleArchiveScan($request, $path, $extension, $blockedExtensions, $next)
    {
        try {
            // Get attachment ID from query parameter
            $attachmentId = $request->query('id');
            
            if (!$attachmentId) {
                // No attachment ID, cannot scan - fail-safe: allow download
                return $next($request);
            }

            // Get attachment file path
            $attachment = \App\Attachment::find($attachmentId);
            
            if (!$attachment) {
                // Attachment not found - fail-safe: allow download
                return $next($request);
            }

            // Get physical file path
            $filepath = storage_path('app/' . $attachment->getStorageFilePath());
            
            if (!file_exists($filepath)) {
                // File doesn't exist - fail-safe: allow download
                return $next($request);
            }

            // Scan the archive
            $maxNestingDepth = Module::getOption('attachmentsecurity', 'max_nesting_depth', config('attachmentsecurity.max_nesting_depth'));
            $scanner = new ArchiveScanner();
            $result = $scanner->scan($filepath, $blockedExtensions, $maxNestingDepth);

            // Check if scanning resulted in an error
            if ($result['error']) {
                // Get unreadable archives mode
                $unreadableMode = Module::getOption('attachmentsecurity', 'unreadable_archives_mode', config('attachmentsecurity.unreadable_archives_mode'));
                
                if ($unreadableMode === self::UNREADABLE_MODE_BLOCK) {
                    // Block mode: block the unreadable archive
                    return $this->blockDownload($request, $path, $extension, 'unreadable', $result);
                } else {
                    // Allow mode: log error and allow download (fail-safe)
                    $this->log('ARCHIVE SCAN FAILED', [
                        'file' => pathinfo($path, PATHINFO_BASENAME),
                        'error' => $result['error']
                    ]);
                    return $next($request);
                }
            }

            // Check if archive is blocked
            if ($result['blocked']) {
                if ($result['encrypted']) {
                    // Encrypted archive - block it
                    return $this->blockDownload($request, $path, $extension, 'encrypted');
                } else {
                    // Contains blocked files - block it
                    return $this->blockDownload($request, $path, $extension, 'archive', $result);
                }
            }

            // Archive is clean, allow download
            return $next($request);

        } catch (\Exception $e) {
            // Any exception during scanning - fail-safe: allow download and log
            $this->log('ARCHIVE SCAN EXCEPTION', [
                'file' => pathinfo($path, PATHINFO_BASENAME),
                'exception' => $e->getMessage()
            ]);
            return $next($request);
        }
    }

    /**
     * Block a download and show blocked page
     *
     * @param \Illuminate\Http\Request $request
     * @param string $path File path
     * @param string $extension File extension
     * @param string $blockType Type: 'regular', 'encrypted', 'archive', 'unreadable', 'no_extension'
     * @param array|null $scanResult Scan result for archive/unreadable blocks
     * @return void
     */
    protected function blockDownload($request, $path, $extension, $blockType = 'regular', $scanResult = null)
    {
        // Check blocking mode (applies to all block types)
        $blockingMode = Module::getOption('attachmentsecurity', 'blocking_mode', self::MODE_ALL);
        
        if ($blockingMode === self::MODE_DISABLED) {
            // This shouldn't happen but just in case
            return response('', 200);
        }
        
        if ($blockingMode === self::MODE_REGULAR && auth()->check() && auth()->user()->isAdmin()) {
            // Admin bypass
            return response('', 200);
        }

        $filename = pathinfo($path, PATHINFO_BASENAME);
        $user = auth()->check() ? auth()->user()->email : 'guest';

        // Get ticket number from attachment
        $attachmentId = $request->query('id');
        $ticketNumber = 'unknown';
        
        if ($attachmentId) {
            try {
                $attachment = \App\Attachment::find($attachmentId);
                if ($attachment && $attachment->thread && $attachment->thread->conversation) {
                    $ticketNumber = $attachment->thread->conversation->number;
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Determine message, log, and email reason based on block type
        $emailReason = '';
        
        if ($blockType === 'no_extension') {
            // File without extension block
            $customMessage = Module::getOption('attachmentsecurity', 'block_message', config('attachmentsecurity.block_message'));
            
            $this->log('FILE WITHOUT EXTENSION BLOCKED', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'file' => $filename
            ]);

            $emailReason = 'File has no extension';
            
            $safeMessage = htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8');
            $message = str_replace(
                ['{filename}', '{extension}'],
                [
                    '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($filename) . '</span>',
                    '<span style="color: #000; font-weight: bold;">no extension</span>'
                ],
                $safeMessage
            );
            
        } elseif ($blockType === 'encrypted') {
            // Encrypted archive block
            $customMessage = Module::getOption('attachmentsecurity', 'encrypted_archive_block_message', config('attachmentsecurity.encrypted_archive_block_message'));
            
            $this->log('ENCRYPTED ARCHIVE BLOCKED', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'file' => $filename
            ]);

            $emailReason = 'Encrypted archive - cannot be scanned';

            $safeMessage = htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8');
            $message = str_replace(
                '{filename}',
                '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($filename) . '</span>',
                $safeMessage
            );

        } elseif ($blockType === 'archive' && $scanResult) {
            // Archive with blocked content
            $customMessage = Module::getOption('attachmentsecurity', 'archive_block_message', config('attachmentsecurity.archive_block_message'));
            
            $blockedFileNames = array_map(function($file) {
                return $file['name'];
            }, $scanResult['files']);

            $this->log('ARCHIVE CONTAINS BLOCKED FILES', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'archive' => $filename,
                'blocked_files' => $blockedFileNames,
                'nesting_level' => $scanResult['nesting_level']
            ]);

            $blockedFilesStr = implode(', ', $blockedFileNames);
            $emailReason = 'Archive contains blocked files: ' . $blockedFilesStr;

            $safeMessage = htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8');
            
            $message = str_replace(
                ['{filename}', '{blocked_files}'],
                [
                    '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($filename) . '</span>',
                    '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($blockedFilesStr) . '</span>'
                ],
                $safeMessage
            );

        } elseif ($blockType === 'unreadable' && $scanResult) {
            // Unreadable archive block
            $customMessage = Module::getOption('attachmentsecurity', 'unreadable_archive_block_message', config('attachmentsecurity.unreadable_archive_block_message'));
            
            $errorMsg = $scanResult['error'] ?? 'Unknown error';
            
            $this->log('UNREADABLE ARCHIVE BLOCKED', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'file' => $filename,
                'error' => $errorMsg
            ]);

            $emailReason = 'Unreadable/corrupted archive: ' . $errorMsg;

            $safeMessage = htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8');
            $message = str_replace(
                '{filename}',
                '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($filename) . '</span>',
                $safeMessage
            );

        } else {
            // Regular extension block (v3.0.0 logic)
            $customMessage = Module::getOption('attachmentsecurity', 'block_message', config('attachmentsecurity.block_message'));
            
            $this->log('BLOCKING DOWNLOAD', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'file' => $filename,
                'extension' => $extension
            ]);

            $emailReason = 'Blocked extension: .' . $extension;

            $safeMessage = htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8');
            $message = str_replace(
                ['{filename}', '{extension}'],
                [
                    '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($filename) . '</span>',
                    '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($extension) . '</span>'
                ],
                $safeMessage
            );
        }

        // Send email notification
        $this->sendEmailNotification($user, $ticketNumber, $filename, $emailReason);

        // Get page customization
        $pageTitle = Module::getOption('attachmentsecurity', 'page_title', '🚫 Download Blocked');
        $backgroundColor = Module::getOption('attachmentsecurity', 'background_color', '#4A90E2, #5C6AC4');

        // Generate and send blocked page
        $html = $this->generateBlockedPageHTML($message, $pageTitle, $backgroundColor);
        
        response($html, 403)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'inline; filename="download-blocked.html"')
            ->send();
        
        exit;
    }

    /**
     * Generate blocked page HTML dynamically.
     */
    protected function generateBlockedPageHTML($message, $pageTitle = '🚫 Download Blocked', $backgroundColor = '#4A90E2, #5C6AC4')
    {
        $messageHtml = $message;
        $pageTitleHtml = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
        
        // Parse background colors
        $colors = array_map('trim', explode(',', $backgroundColor));
        $color1 = $colors[0] ?? '#4A90E2';
        $color2 = $colors[1] ?? '#5C6AC4';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Blocked</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, {$color1} 0%, {$color2} 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 50px;
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.3);
        }
        .icon svg { width: 60px; height: 60px; fill: white; }
        h1 { color: #2C3E50; font-size: 32px; margin-bottom: 20px; font-weight: 700; }
        .message { color: #7F8C8D; font-size: 18px; line-height: 1.6; margin-bottom: 35px; }
        button {
            padding: 15px 35px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            background: linear-gradient(135deg, {$color1} 0%, {$color2} 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6); }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L3 7v5c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5zm0 10h7c-.53 4.12-3.28 7.79-7 8.94V12H5V7.89l7-3.78v8.89z"/>
            </svg>
        </div>
        <h1>{$pageTitleHtml}</h1>
        <div class="message">
            <p><strong>{$messageHtml}</strong></p>
        </div>
        <button onclick="handleClose()">✕ Close</button>
    </div>
    <script>
        function handleClose() {
            window.close();
            setTimeout(function() {
                window.history.back();
            }, 100);
        }
    </script>
</body>
</html>
HTML;
    }


    /**
     * Send email notification when a file is blocked
     * 
     * @param string $user User email
     * @param string $ticketNumber Ticket number
     * @param string $filename Blocked filename
     * @param string $reason Blocking reason
     */
    protected function sendEmailNotification($user, $ticketNumber, $filename, $reason)
    {
        try {
            // Check if notifications are enabled
            $emailEnabled = Module::getOption('attachmentsecurity', 'email_notifications_enabled', config('attachmentsecurity.email_notifications_enabled'));
            
            if (!$emailEnabled) {
                $this->log('EMAIL NOTIFICATION SKIPPED', [
                    'reason' => 'Email notifications are disabled'
                ]);
                return;
            }

            // Get notification email
            $notificationEmail = Module::getOption('attachmentsecurity', 'notification_email', config('attachmentsecurity.notification_email'));
            
            if (empty($notificationEmail) || !filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
                $this->log('EMAIL NOTIFICATION SKIPPED', [
                    'reason' => 'No valid email configured',
                    'configured_email' => $notificationEmail
                ]);
                return;
            }

            // Get email subject template
            $subjectTemplate = Module::getOption('attachmentsecurity', 'notification_subject', config('attachmentsecurity.notification_subject'));
            
            // Replace variables in subject
            $subject = str_replace(
                ['{user}', '{ticket}', '{filename}', '{reason}'],
                [$user, $ticketNumber, $filename, $reason],
                $subjectTemplate
            );

            // Build email body
            $body = $this->buildEmailBody($user, $ticketNumber, $filename, $reason);

            // CRITICAL: Force load SMTP configuration from FreeScout's database
            // Laravel defaults to sendmail, but FreeScout stores SMTP in options table
            $mailDriver = \Option::get('mail_driver', 'smtp');
            $mailHost = \Option::get('mail_host');
            $mailPort = \Option::get('mail_port', 587);
            $mailUsername = \Option::get('mail_username');
            $mailPassword = \Option::get('mail_password');
            $mailEncryption = \Option::get('mail_encryption', 'tls');
            $mailFromAddress = \Option::get('mail_from');
            $mailFromName = \Option::get('mail_from_name', 'FreeScout');
            
            // Decrypt password if encrypted (FreeScout stores passwords encrypted)
            if ($mailPassword && strpos($mailPassword, 'eyJ') === 0) {
                try {
                    $mailPassword = decrypt($mailPassword);
                } catch (\Exception $e) {
                    // Silent fail - password decryption failed
                    return;
                }
            }
            
            // Fallback: use username as FROM if FROM address is empty
            if (empty($mailFromAddress) && !empty($mailUsername)) {
                $mailFromAddress = $mailUsername;
            }
            
            // Set mail configuration from database
            config([
                'mail.driver' => $mailDriver,
                'mail.host' => $mailHost,
                'mail.port' => $mailPort,
                'mail.username' => $mailUsername,
                'mail.password' => $mailPassword,
                'mail.encryption' => $mailEncryption,
                'mail.from' => [
                    'address' => $mailFromAddress,
                    'name' => $mailFromName,
                ],
            ]);

            // Send email silently (no logging except PHP errors)
            try {
                \Mail::raw($body, function($message) use ($notificationEmail, $subject, $mailFromAddress, $mailFromName) {
                    $message->to($notificationEmail)
                           ->subject($subject);
                    
                    // Set FROM address (required by SMTP)
                    if ($mailFromAddress) {
                        $message->from($mailFromAddress, $mailFromName);
                    }
                });
            } catch (\Exception $e) {
                // Silent fail - email sending failed
            }

        } catch (\Exception $e) {
            // Silent fail - outer exception
        }
    }

    /**
     * Build email notification body
     * 
     * @param string $user User email
     * @param string $ticketNumber Ticket number
     * @param string $filename Blocked filename
     * @param string $reason Blocking reason
     * @return string
     */
    protected function buildEmailBody($user, $ticketNumber, $filename, $reason)
    {
        $timestamp = date('Y-m-d H:i:s');
        
        return <<<EMAIL
File Download Incident Report
========================================

A file download attempt was blocked by the Attachment Security module.

Incident Details:
-----------------
User:           {$user}
Ticket:         #{$ticketNumber}
Filename:       {$filename}
Reason:         {$reason}
Date/Time:      {$timestamp}

========================================
This is an automated notification from FreeScout Attachment Security Module.
EMAIL;
    }

    /**
     * Log helper method using LoggerAttachmentSecurity
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    protected function log($message, $context = [])
    {
        // Sanitize context to ensure valid UTF-8 and remove problematic characters for log viewers
        if (!empty($context)) {
            array_walk_recursive($context, function(&$value) {
                if (is_string($value)) {
                    // Remove non-UTF-8 characters and ensure valid encoding
                    $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    // Remove control characters that could break log viewers
                    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $value);
                    // Remove quotes to avoid JSON escaping issues in log viewers
                    $value = str_replace(['"', "'", '\\'], ['', '', ''], $value);
                    // Replace problematic keywords that break FreeScout log viewer
                    $value = str_ireplace('error:', 'error', $value);
                }
            });
        }

        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $logEntry = "[MIDDLEWARE] {$message}{$contextStr}";

        LoggerAttachmentSecurity::alert($logEntry);

    }
}

