<?php

namespace Modules\AttachmentSecurity\Services;

/**
 * Scanner Capabilities Detection
 * 
 * Detects which archive formats can be scanned on the current system.
 * Checks for PHP extensions, external binaries, and their versions.
 *
 * @package Modules\AttachmentSecurity\Services
 * @version 3.4.0
 */
class ScannerCapabilities
{
    /**
     * Detect all available archive format capabilities
     *
     * @return array Associative array with format capabilities
     */
    public static function detect(): array
    {
        return [
            'zip' => self::detectZip(),
            'rar' => self::detectRar(),
            'tar' => self::detectTar(),
            'gz' => self::detectGz(),
            'bz2' => self::detectBz2(),
        ];
    }

    /**
     * Detect ZIP support (ZipArchive)
     *
     * @return array
     */
    private static function detectZip(): array
    {
        $available = class_exists('ZipArchive');
        
        return [
            'available' => $available,
            'type' => 'native',
            'details' => $available ? 'ZipArchive (native PHP)' : 'ZipArchive not available',
            'method' => 'ZipArchive',
        ];
    }

    /**
     * Detect RAR support (extension, unrar-nonfree, or unrar-free)
     *
     * @return array
     */
    /**
     * Detect RAR support (unrar binary only, no PHP extension)
     *
     * Order of preference:
     * 1. unrar (nonfree/RAR Lab) - best option, full RAR 5.x support
     * 2. unrar-free - limited to RAR 2.x
     * 3. unrar (free) as fallback - if 'unrar' command is actually unrar-free
     *
     * @return array
     */
    private static function detectRar(): array
    {
        $result = [
            'available' => false,
            'type' => null,
            'version' => null,
            'rar5_support' => false,
            'command' => null,
            'details' => 'RAR support not available',
            'method' => null,
        ];

        $fallbackUnrar = null;
        
        // 1. Try 'unrar' command first
        $unrarPath = @shell_exec('which unrar 2>/dev/null');
        if (!$unrarPath || empty(trim($unrarPath))) {
            // Try common locations
            $commonPaths = ['/usr/bin/unrar', '/usr/local/bin/unrar', '/bin/unrar'];
            foreach ($commonPaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    $unrarPath = $path;
                    break;
                }
            }
        }
        
        if ($unrarPath && !empty(trim($unrarPath))) {
            $unrarPath = trim($unrarPath);
            $versionOutput = @shell_exec($unrarPath . ' 2>&1 | head -n 5');
            
            if ($versionOutput) {
                // Check if it's unrar-nonfree (RAR Lab) - preferred
                if (stripos($versionOutput, 'RARLAB') !== false || 
                    stripos($versionOutput, 'Alexander Roshal') !== false ||
                    stripos($versionOutput, 'www.rarlab.com') !== false) {
                    
                    // It's nonfree - use immediately (best option)
                    return self::parseUnrarNonfree($unrarPath, $versionOutput);
                }
                
                // Check if it's unrar-free
                elseif (stripos($versionOutput, 'unrar-free') !== false ||
                        stripos($versionOutput, 'Ben Asselstine') !== false) {
                    
                    // It's free - save as fallback but keep looking for explicit unrar-free
                    $fallbackUnrar = self::parseUnrarFree($unrarPath, $versionOutput);
                }
                
                // Unknown type - try to determine by version number
                elseif (preg_match('/(\d+)\.(\d+)/', $versionOutput, $matches)) {
                    $major = (int)$matches[1];
                    
                    // Modern versions (5.x+) are likely nonfree
                    if ($major >= 5) {
                        return self::parseUnrarNonfree($unrarPath, $versionOutput);
                    } else {
                        $fallbackUnrar = self::parseUnrarFree($unrarPath, $versionOutput);
                    }
                }
            }
        }
        
        // 2. Try explicit 'unrar-free' command
        $unrarFreePath = @shell_exec('which unrar-free 2>/dev/null');
        if (!$unrarFreePath || empty(trim($unrarFreePath))) {
            $commonPaths = ['/usr/bin/unrar-free', '/usr/local/bin/unrar-free', '/bin/unrar-free'];
            foreach ($commonPaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    $unrarFreePath = $path;
                    break;
                }
            }
        }
        
        if ($unrarFreePath && !empty(trim($unrarFreePath))) {
            $unrarFreePath = trim($unrarFreePath);
            $versionOutput = @shell_exec($unrarFreePath . ' 2>&1 | head -n 5');
            return self::parseUnrarFree($unrarFreePath, $versionOutput);
        }
        
        // 3. Use fallback if we found unrar-free as 'unrar'
        if ($fallbackUnrar) {
            return $fallbackUnrar;
        }
        
        // 4. No RAR support available
        $result['details'] = 'No RAR support found (install unrar or unrar-free)';
        return $result;
    }

    /**
     * Parse unrar-nonfree (RAR Lab) information
     *
     * @param string $command Path to unrar binary
     * @param string $versionOutput Version output from command
     * @return array
     */
    private static function parseUnrarNonfree(string $command, string $versionOutput): array
    {
        $result = [
            'available' => true,
            'type' => 'nonfree',
            'version' => null,
            'rar5_support' => true,
            'command' => $command,
            'details' => 'RAR (RAR Lab - nonfree)',
            'method' => 'unrar_nonfree',
        ];
        
        // Extract version (e.g., "UNRAR 6.24")
        if (preg_match('/UNRAR\s+(\d+\.\d+)/i', $versionOutput, $matches)) {
            $result['version'] = $matches[1];
        } elseif (preg_match('/(\d+\.\d+)/', $versionOutput, $matches)) {
            $result['version'] = $matches[1];
        }
        
        return $result;
    }

    /**
     * Parse unrar-free information
     *
     * @param string $command Path to unrar-free binary
     * @param string $versionOutput Version output from command
     * @return array
     */
    private static function parseUnrarFree(string $command, string $versionOutput): array
    {
        $result = [
            'available' => true,
            'type' => 'free',
            'version' => null,
            'rar5_support' => false,
            'command' => $command,
            'details' => 'RAR (unrar-free - limited to RAR 2.x)',
            'method' => 'unrar_free',
        ];
        
        // Extract version - unrar-free doesn't show version in normal output
        if (preg_match('/(\d+\.\d+\.\d+)/', $versionOutput, $matches)) {
            $result['version'] = $matches[1];
        } else {
            // Try -V flag for version
            $versionCmd = @shell_exec($command . ' -V 2>&1');
            if ($versionCmd && preg_match('/unrar-free\s+(\d+\.\d+\.\d+)/i', $versionCmd, $matches)) {
                $result['version'] = $matches[1];
            }
        }
        
        return $result;
    }
    private static function detectTar(): array
    {
        $available = class_exists('PharData');
        
        return [
            'available' => $available,
            'type' => 'native',
            'details' => $available ? 'TAR (PharData - native PHP)' : 'PharData not available',
            'method' => 'PharData',
        ];
    }

    /**
     * Detect GZ (gzip) support (PharData + zlib)
     *
     * @return array
     */
    private static function detectGz(): array
    {
        $pharAvailable = class_exists('PharData');
        $zlibAvailable = extension_loaded('zlib');
        $available = $pharAvailable && $zlibAvailable;
        
        $details = $available 
            ? 'GZ (PharData + zlib - native PHP)' 
            : 'GZ support not available';
        
        if (!$pharAvailable) {
            $details = 'PharData not available';
        } elseif (!$zlibAvailable) {
            $details = 'zlib extension not available';
        }
        
        return [
            'available' => $available,
            'type' => 'native',
            'details' => $details,
            'method' => 'PharData',
        ];
    }

    /**
     * Detect BZ2 (bzip2) support (PharData + bz2)
     *
     * @return array
     */
    private static function detectBz2(): array
    {
        $pharAvailable = class_exists('PharData');
        $bz2Available = extension_loaded('bz2');
        $available = $pharAvailable && $bz2Available;
        
        $details = $available 
            ? 'BZ2 (PharData + bz2 - native PHP)' 
            : 'BZ2 support not available';
        
        if (!$pharAvailable) {
            $details = 'PharData not available';
        } elseif (!$bz2Available) {
            $details = 'bz2 extension not available';
        }
        
        return [
            'available' => $available,
            'type' => 'native',
            'details' => $details,
            'method' => 'PharData',
        ];
    }

    /**
     * Get comma-separated list of available formats
     *
     * @param array|null $capabilities If null, will detect automatically
     * @return string
     */
    public static function getAvailableFormats(?array $capabilities = null): string
    {
        if ($capabilities === null) {
            $capabilities = self::detect();
        }

        $available = [];
        foreach ($capabilities as $format => $info) {
            if ($info['available']) {
                $available[] = $format;
            }
        }

        return implode(',', $available);
    }
}
