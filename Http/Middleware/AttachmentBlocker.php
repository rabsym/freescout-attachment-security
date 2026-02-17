<?php

namespace Modules\AttachmentSecurity\Http\Middleware;

use Closure;
use Module;

/**
 * Attachment Blocker Middleware
 *
 * Handles attachment download blocking based on file extension and blocking mode.
 * Separated from ServiceProvider in v3.0.0 for better code organization.
 *
 * @package Modules\AttachmentSecurity
 * @author  Raimundo Alba
 * @version 3.0.0
 */
class AttachmentBlocker
{
    const MODE_ALL = 'all';
    const MODE_REGULAR = 'regular';
    const MODE_DISABLED = 'disabled';
    const DEFAULT_BLOCKED_EXTENSIONS = 'exe,php,bat,cmd,htm,html,js,vbs,ps1,sh,phar';
    const DEFAULT_BLOCK_MESSAGE = 'For security reasons the file {filename} cannot be downloaded. If you need access to this content, please contact support.';

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
        $blockedExtStr = Module::getOption('attachmentsecurity', 'blocked_extensions', self::DEFAULT_BLOCKED_EXTENSIONS);
        $blockedExtensions = array_filter(array_map('trim', explode(',', strtolower($blockedExtStr))));
        
        // $this->log('DEBUG', 'Blocked extensions loaded', [
        //     'raw' => $blockedExtStr,
        //     'array' => $blockedExtensions
        // ]);
        
        // Check if blocked
        if (!in_array($extension, $blockedExtensions)) {
            // $this->log('DEBUG', 'Extension not blocked, passing to next');
            return $next($request);
        }

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

        // Log blocked attempt (PRODUCTION LOG - DO NOT REMOVE)
        $user = auth()->check() ? auth()->user()->email : 'guest';
        
        // Extract attachment ID from query parameter (?id=xxx)
        $attachmentId = $request->query('id');
        
        // Get actual ticket number from attachment's thread conversation
        $ticketNumber = 'unknown';
        if ($attachmentId) {
            try {
                $attachment = \App\Attachment::find($attachmentId);
                if ($attachment && $attachment->thread && $attachment->thread->conversation) {
                    $ticketNumber = $attachment->thread->conversation->number;
                }
            } catch (\Exception $e) {
                // If attachment/thread/conversation not found, keep 'unknown'
            }
        }
        
        // Get filename
        $filename = pathinfo($path, PATHINFO_BASENAME);
        
        $this->log('WARNING', 'BLOCKING DOWNLOAD', [
            'user' => $user,
            'ticket' => $ticketNumber,
            'file' => $filename,
            'extension' => $extension
        ]);
        
        // Get customization options
        $customMessage = Module::getOption('attachmentsecurity', 'block_message', self::DEFAULT_BLOCK_MESSAGE);
        $pageTitle = Module::getOption('attachmentsecurity', 'page_title', '🚫 Download Blocked');
        $backgroundColor = Module::getOption('attachmentsecurity', 'background_color', '#4A90E2, #5C6AC4');
        
        // Escape the custom message first to prevent XSS
        $safeMessage = htmlspecialchars($customMessage, ENT_QUOTES, 'UTF-8');
        
        // Replace variables with styled spans
        $message = str_replace(
            ['{filename}', '{extension}'],
            ['<span style="color: #000; font-weight: bold;">' . htmlspecialchars($filename) . '</span>', 
             '<span style="color: #000; font-weight: bold;">' . htmlspecialchars($extension) . '</span>'],
            $safeMessage
        );
        
        // $this->log('DEBUG', 'About to generate and send HTML response');
        
        // Generate HTML
        $html = $this->generateBlockedPageHTML($message, $pageTitle, $backgroundColor);
        
        // $this->log('DEBUG', 'HTML generated, calling response()->send()');
        
        // CRITICAL: Use send() + exit to actually block
        response($html, 403)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'inline; filename="download-blocked.html"')
            ->send();
        
        // $this->log('DEBUG', 'response()->send() called, about to exit');
        
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
     * 
     * Used for production logging:
     * - WARNING: Blocked download attempts
     * - INFO: Configuration changes (from ServiceProvider)
     * 
     * Debug logs are commented out for production but can be
     * uncommented for troubleshooting if needed.
     */
    protected function log($level, $message, $context = [])
    {
        $logFile = storage_path('logs/attachmentsecurity.log');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] [{$level}] [MIDDLEWARE] {$message}{$contextStr}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
