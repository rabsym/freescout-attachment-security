<?php

namespace Modules\AttachmentSecurity\Http\Middleware;

use Closure;
use Module;
use Modules\AttachmentSecurity\Services\ArchiveScanner;

/**
 * Attachment Blocker Middleware
 *
 * Handles attachment download blocking based on file extension and blocking mode.
 * Separated from ServiceProvider in v3.0.0 for better code organization.
 * v3.0.1-dev: Added archive scanning functionality.
 *
 * @package Modules\AttachmentSecurity
 * @author  Raimundo Alba
 * @version 3.1.0
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
        // Debug logging commented for production
        // $this->log('DEBUG', 'Middleware handle() called');
        
        // Extract extension
        $path = $request->segment(count($request->segments()));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        // $this->log('DEBUG', 'Extension extracted', [
        //     'path' => $path,
        //     'extension' => $extension
        // ]);
        
        if (empty($extension)) {
            // $this->log('DEBUG', 'Extension empty, passing to next');
            return $next($request);
        }

        // Get blocked extensions (with cache clear)
        \Cache::forget('module_options_attachmentsecurity');
        $blockedExtStr = Module::getOption('attachmentsecurity', 'blocked_extensions', config('attachmentsecurity.blocked_extensions'));
        $blockedExtensions = array_filter(array_map('trim', explode(',', strtolower($blockedExtStr))));
        
        // $this->log('DEBUG', 'Blocked extensions loaded', [
        //     'raw' => $blockedExtStr,
        //     'array' => $blockedExtensions
        // ]);
        
        // EXISTING LOGIC: Check if extension is directly blocked
        if (in_array($extension, $blockedExtensions)) {
            // $this->log('INFO', 'Extension IS blocked, checking mode');

            // Check blocking mode
            $blockingMode = Module::getOption('attachmentsecurity', 'blocking_mode', self::MODE_ALL);
            
            // $this->log('DEBUG', 'Blocking mode', ['mode' => $blockingMode]);
            
            if ($blockingMode === self::MODE_DISABLED) {
                // $this->log('INFO', 'Blocking disabled, passing to next');
                return $next($request);
            }
            
            if ($blockingMode === self::MODE_REGULAR && auth()->check() && auth()->user()->isAdmin()) {
                // $this->log('INFO', 'User is admin, passing to next');
                return $next($request);
            }

            // Block this file (existing v3.0.0 logic)
            return $this->blockDownload($request, $path, $extension, 'regular');
        }
        
        // NEW LOGIC v3.0.1-dev: Check if this is an archive that should be scanned
        $archiveScanEnabled = Module::getOption('attachmentsecurity', 'archive_scan_enabled', false);
        $archiveExtensionsStr = Module::getOption('attachmentsecurity', 'archive_extensions', config('attachmentsecurity.archive_extensions'));
        $archiveExtensions = array_filter(array_map('trim', explode(',', strtolower($archiveExtensionsStr))));
        
        if ($archiveScanEnabled && in_array($extension, $archiveExtensions)) {
            // This is an archive file and scanning is enabled
            return $this->handleArchiveScan($request, $path, $extension, $blockedExtensions, $next);
        }

        // $this->log('DEBUG', 'Extension not blocked, passing to next');
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
            $result = $scanner->scanZip($filepath, $blockedExtensions, $maxNestingDepth);

            // Check if scanning resulted in an error
            if ($result['error']) {
                // Get unreadable archives mode
                $unreadableMode = Module::getOption('attachmentsecurity', 'unreadable_archives_mode', config('attachmentsecurity.unreadable_archives_mode'));
                
                if ($unreadableMode === self::UNREADABLE_MODE_BLOCK) {
                    // Block mode: block the unreadable archive
                    return $this->blockDownload($request, $path, $extension, 'unreadable', $result);
                } else {
                    // Allow mode: log error and allow download (fail-safe)
                    $this->log('ERROR', 'ARCHIVE SCAN FAILED', [
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
            $this->log('ERROR', 'ARCHIVE SCAN EXCEPTION', [
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
     * @param string $blockType Type: 'regular', 'encrypted', 'archive', 'unreadable'
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

        // Determine message and log based on block type
        if ($blockType === 'encrypted') {
            // Encrypted archive block
            $customMessage = Module::getOption('attachmentsecurity', 'encrypted_archive_block_message', config('attachmentsecurity.encrypted_archive_block_message'));
            
            $this->log('WARNING', 'ENCRYPTED ARCHIVE BLOCKED', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'file' => $filename
            ]);

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

            $this->log('WARNING', 'ARCHIVE CONTAINS BLOCKED FILES', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'archive' => $filename,
                'blocked_files' => $blockedFileNames,
                'nesting_level' => $scanResult['nesting_level']
            ]);

            $safeMessage = htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8');
            $blockedFilesStr = implode(', ', $blockedFileNames);
            
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
            
            $this->log('WARNING', 'UNREADABLE ARCHIVE BLOCKED', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'file' => $filename,
                'error' => $scanResult['error'] ?? 'Unknown error'
            ]);

            $safeMessage = htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8');
            $message = str_replace(
                '{filename}',
                '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($filename) . '</span>',
                $safeMessage
            );

        } else {
            // Regular extension block (v3.0.0 logic)
            $customMessage = Module::getOption('attachmentsecurity', 'block_message', config('attachmentsecurity.block_message'));
            
            $this->log('WARNING', 'BLOCKING DOWNLOAD', [
                'user' => $user,
                'ticket' => $ticketNumber,
                'file' => $filename,
                'extension' => $extension
            ]);

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
     * Log helper method using file_put_contents
     */
    protected function log($level, $message, $context = [])
    {
        $logFile = storage_path('logs/attachmentsecurity.log');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] [{$level}] [MIDDLEWARE] {$message}{$contextStr}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
