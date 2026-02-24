<?php

namespace Modules\AttachmentSecurity\Services;

use ZipArchive;

/**
 * Archive Scanner Service
 *
 * Scans compressed files (ZIP) for blocked file extensions.
 * Implements fail-safe approach: if scanning fails, allows download and logs error.
 *
 * @package Modules\AttachmentSecurity
 * @author  Raimundo Alba
 * @version 3.1.0
 */
class ArchiveScanner
{
    /**
     * Scan a ZIP file for blocked extensions
     *
     * @param string $filepath Full path to the ZIP file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $maxDepth Maximum nesting depth to scan
     * @return array Result with keys: 'blocked' (bool), 'files' (array), 'encrypted' (bool), 'error' (string|null)
     */
    public function scanZip($filepath, $blockedExtensions, $maxDepth = 2)
    {
        $result = [
            'blocked' => false,
            'files' => [],
            'encrypted' => false,
            'error' => null,
            'nesting_level' => 0
        ];

        try {
            // Check if file is encrypted
            if ($this->isEncrypted($filepath)) {
                $result['blocked'] = true;
                $result['encrypted'] = true;
                return $result;
            }

            // Scan the archive
            $blockedFiles = $this->scanArchiveRecursive($filepath, $blockedExtensions, 0, $maxDepth);
            
            if (!empty($blockedFiles)) {
                $result['blocked'] = true;
                $result['files'] = $blockedFiles;
                $result['nesting_level'] = $this->getMaxNestingLevel($blockedFiles);
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            // Fail-safe: don't block on error
            $result['blocked'] = false;
        }

        return $result;
    }

    /**
     * Check if a ZIP file is encrypted
     *
     * @param string $filepath Path to ZIP file
     * @return bool
     */
    protected function isEncrypted($filepath)
    {
        $zip = new ZipArchive();
        
        if ($zip->open($filepath) !== true) {
            return false;
        }

        // Check if any file in the archive is encrypted
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            
            // Check encryption flag (bit 0 of the general purpose bit flag)
            if ($stat && isset($stat['encryption_method']) && $stat['encryption_method'] != 0) {
                $zip->close();
                return true;
            }
        }

        $zip->close();
        return false;
    }

    /**
     * Recursively scan archive contents
     *
     * @param string $filepath Path to archive
     * @param array $blockedExtensions List of blocked extensions
     * @param int $currentDepth Current nesting level
     * @param int $maxDepth Maximum allowed depth
     * @return array Array of blocked files found
     */
    protected function scanArchiveRecursive($filepath, $blockedExtensions, $currentDepth, $maxDepth)
    {
        $blockedFiles = [];

        if ($currentDepth > $maxDepth) {
            return $blockedFiles;
        }

        $zip = new ZipArchive();
        
        if ($zip->open($filepath) !== true) {
            throw new \Exception("Cannot open ZIP file");
        }

        $tempDir = sys_get_temp_dir() . '/attachmentsecurity_' . uniqid();
        
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Skip directories
                if (substr($filename, -1) === '/') {
                    continue;
                }

                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                // Check if this file has a blocked extension
                if (in_array($extension, $blockedExtensions)) {
                    $blockedFiles[] = [
                        'name' => basename($filename),
                        'path' => $filename,
                        'depth' => $currentDepth
                    ];
                }

                // If it's a nested ZIP
                if ($extension === 'zip') {
                    // If we haven't reached max depth, scan it
                    if ($currentDepth < $maxDepth) {
                        // Extract nested ZIP to temp location
                        if (!is_dir($tempDir)) {
                            mkdir($tempDir, 0700, true);
                        }

                        $tempFile = $tempDir . '/' . basename($filename);
                        
                        if (copy("zip://{$filepath}#{$filename}", $tempFile)) {
                            // Check if the nested ZIP is encrypted before scanning
                            if ($this->isEncrypted($tempFile)) {
                                // Nested ZIP is encrypted - treat it as a blocked file
                                $blockedFiles[] = [
                                    'name' => basename($filename) . ' (encrypted)',
                                    'path' => $filename,
                                    'depth' => $currentDepth,
                                    'encrypted' => true
                                ];
                            } else {
                                // Recursively scan nested ZIP
                                $nestedBlocked = $this->scanArchiveRecursive($tempFile, $blockedExtensions, $currentDepth + 1, $maxDepth);
                                $blockedFiles = array_merge($blockedFiles, $nestedBlocked);
                            }
                            
                            unlink($tempFile);
                        }
                    } else {
                        // We've reached max depth - block this nested ZIP
                        $blockedFiles[] = [
                            'name' => basename($filename) . ' (nesting limit exceeded)',
                            'path' => $filename,
                            'depth' => $currentDepth,
                            'nesting_limit' => true
                        ];
                    }
                }
            }

        } finally {
            $zip->close();
            
            // Clean up temp directory
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
        }

        return $blockedFiles;
    }

    /**
     * Get maximum nesting level from blocked files
     *
     * @param array $blockedFiles
     * @return int
     */
    protected function getMaxNestingLevel($blockedFiles)
    {
        $maxLevel = 0;
        
        foreach ($blockedFiles as $file) {
            if (isset($file['depth']) && $file['depth'] > $maxLevel) {
                $maxLevel = $file['depth'];
            }
        }
        
        return $maxLevel;
    }

    /**
     * Recursively remove a directory
     *
     * @param string $dir
     * @return void
     */
    protected function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}
