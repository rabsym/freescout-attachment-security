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
 * @version 3.4.0
 */
class ArchiveScanner
{
    /**
     * Cached archive format capabilities
     *
     * @var array
     */
    protected $capabilities;

    /**
     * Constructor - Load capabilities from database
     */
    public function __construct()
    {
        // Load cached capabilities from database
        $cached = \Module::getOption('attachmentsecurity', 'archive_capabilities');
        
        if ($cached) {
            // Check if already decoded (array) or needs decoding (string)
            if (is_array($cached)) {
                $this->capabilities = $cached;
            } else {
                $this->capabilities = json_decode($cached, true) ?: [];
            }
        } else {
            $this->capabilities = [];
        }
    }

    /**
     * Check if a format can be scanned
     *
     * @param string $extension File extension (without dot)
     * @return bool
     */
    public function canScan(string $extension): bool
    {
        $extension = strtolower($extension);
        
        // Map alternative extensions to their base capabilities
        $extensionMap = [
            'tgz' => 'gz',    // .tgz maps to gz capability
            'tbz2' => 'bz2',  // .tbz2 maps to bz2 capability  
            'tb2' => 'bz2',   // .tb2 maps to bz2 capability
        ];
        
        // Use mapped extension if exists
        $capabilityKey = $extensionMap[$extension] ?? $extension;
        
        return isset($this->capabilities[$capabilityKey]) && 
               ($this->capabilities[$capabilityKey]['available'] ?? false);
    }

    /**
     * Check if a file should be blocked
     *
     * @param string $filename Filename
     * @param array $blockedExtensions Array of blocked extensions
     * @return bool
     */
    protected function isFileBlocked(string $filename, array $blockedExtensions): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check if extension is in blocked list
        if (in_array($extension, $blockedExtensions)) {
            return true;
        }
        
        // Check if file has no extension and block_no_extension is enabled
        $blockNoExtension = \Module::getOption('attachmentsecurity', 'block_no_extension', config('attachmentsecurity.block_no_extension'));
        
        if (empty($extension) && $blockNoExtension) {
            return true;
        }
        
        return false;
    }

    /**
     * Get scanning method/type for a format
     *
     * @param string $extension File extension
     * @return string|null Method identifier (e.g., 'ZipArchive', 'unrar_nonfree')
     */
    protected function getScanMethod(string $extension): ?string
    {
        if (!$this->canScan($extension)) {
            return null;
        }
        
        return $this->capabilities[$extension]['method'] ?? null;
    }

    /**
     * Scan an archive file for blocked extensions
     *
     * Main entry point - routes to appropriate scanner based on format.
     *
     * @param string $filepath Full path to the archive file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $maxDepth Maximum nesting depth to scan
     * @param int $currentDepth Current nesting depth (internal use)
     * @return array Result with keys: 'blocked' (bool), 'files' (array), 'encrypted' (bool), 'error' (string|null)
     */
    public function scan(string $filepath, array $blockedExtensions, int $maxDepth = 1, int $currentDepth = 0): array
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        // Check if format is supported
        if (!$this->canScan($extension)) {
            return [
                'blocked' => false,
                'files' => [],
                'encrypted' => false,
                'error' => "Format '{$extension}' not supported on this system",
                'nesting_level' => 0
            ];
        }
        
        // Route to appropriate scanner
        switch ($extension) {
            case 'zip':
                return $this->scanZip($filepath, $blockedExtensions, $maxDepth, $currentDepth);
                
            case 'rar':
                return $this->scanRar($filepath, $blockedExtensions, $maxDepth, $currentDepth);
                
            case 'tar':
            case 'gz':
            case 'bz2':
            case 'tgz':   // tar.gz alternative
            case 'tbz2':  // tar.bz2 alternative
            case 'tb2':   // tar.bz2 alternative short form
                return $this->scanTarBased($filepath, $blockedExtensions, $maxDepth, $currentDepth);
                
            default:
                return [
                    'blocked' => false,
                    'files' => [],
                    'encrypted' => false,
                    'error' => "Handler for '{$extension}' not implemented yet",
                    'nesting_level' => 0
                ];
        }
    }

    /**
     * Scan a ZIP file for blocked extensions
     *
     * @param string $filepath Full path to the ZIP file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $maxDepth Maximum nesting depth to scan
     * @param int $currentDepth Current nesting depth
     * @return array Result with keys: 'blocked' (bool), 'files' (array), 'encrypted' (bool), 'error' (string|null)
     */
    public function scanZip($filepath, $blockedExtensions, $maxDepth = 2, $currentDepth = 0)
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

            // Scan the archive contents
            $blockedFiles = $this->scanZipContents($filepath, $blockedExtensions, $currentDepth, $maxDepth);
            
            if (!empty($blockedFiles)) {
                $result['blocked'] = true;
                $result['files'] = $blockedFiles;
                $result['nesting_level'] = $this->getMaxNestingLevel($blockedFiles);
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            // Middleware will handle based on unreadableMode
        }

        return $result;
    }

    /**
     * Scan ZIP file contents
     *
     * @param string $filepath Path to ZIP file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $currentDepth Current nesting level
     * @param int $maxDepth Maximum allowed depth
     * @return array Array of blocked files found
     */
    protected function scanZipContents($filepath, $blockedExtensions, $currentDepth, $maxDepth)
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

                // Check if this file should be blocked (extension or no extension)
                if ($this->isFileBlocked($filename, $blockedExtensions)) {
                    $blockedFiles[] = [
                        'name' => basename($filename),
                        'path' => $filename,
                        'depth' => $currentDepth
                    ];
                }

                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                // If it's a nested archive (any format)
                if (in_array($extension, ['zip', 'rar', 'tar', 'gz', 'bz2', 'tgz', 'tbz2', 'tb2'])) {
                    // If we haven't reached max depth, scan it
                    if ($currentDepth < $maxDepth) {
                        // Extract nested archive to temp location
                        if (!is_dir($tempDir)) {
                            mkdir($tempDir, 0700, true);
                        }

                        $tempFile = $tempDir . '/' . basename($filename);
                        
                        if (copy("zip://{$filepath}#{$filename}", $tempFile)) {
                            // Recursively scan nested archive (any format)
                            $nestedResult = $this->scan($tempFile, $blockedExtensions, $maxDepth, $currentDepth + 1);
                            
                            if ($nestedResult['blocked']) {
                                if ($nestedResult['encrypted']) {
                                    // Nested archive is encrypted - treat it as a blocked file
                                    $blockedFiles[] = [
                                        'name' => basename($filename) . __('(encrypted)'),
                                        'path' => $filename,
                                        'depth' => $currentDepth,
                                        'encrypted' => true
                                    ];
                                } else {
                                    $blockedFiles = array_merge($blockedFiles, $nestedResult['files']);
                                }
                            }
                            
                            unlink($tempFile);
                        }
                    } else {
                        // We've reached max depth - block this nested archive
                        $blockedFiles[] = [
                            'name' => basename($filename) . __('(nesting limit exceeded)'),
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

    /**
     * Scan a RAR file for blocked extensions
     *
     * Routes to appropriate RAR handler based on detected capability.
     *
     * @param string $filepath Full path to the RAR file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $maxDepth Maximum nesting depth to scan
     * @return array Result with keys: 'blocked' (bool), 'files' (array), 'encrypted' (bool), 'error' (string|null)
     */
    protected function scanRar(string $filepath, array $blockedExtensions, int $maxDepth = 1, int $currentDepth = 0): array
    {
        $rarType = $this->capabilities['rar']['type'] ?? null;
        
        switch ($rarType) {
            case 'nonfree':
                return $this->scanRarWithNonfree($filepath, $blockedExtensions, $maxDepth, $currentDepth);
                
            case 'free':
                return $this->scanRarWithFree($filepath, $blockedExtensions, $maxDepth, $currentDepth);
                
            default:
                return [
                    'blocked' => false,
                    'files' => [],
                    'encrypted' => false,
                    'error' => 'RAR handler not available',
                    'nesting_level' => 0
                ];
        }
    }

    /**
     * Scan RAR using unrar-nonfree (RAR Lab)
     *
     * @param string $filepath Full path to the RAR file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $maxDepth Maximum nesting depth to scan
     * @return array Scan result
     */
    protected function scanRarWithNonfree(string $filepath, array $blockedExtensions, int $maxDepth, int $currentDepth = 0): array
    {
        $result = [
            'blocked' => false,
            'files' => [],
            'encrypted' => false,
            'error' => null,
            'nesting_level' => 0
        ];

        try {
            $unrarPath = $this->capabilities['rar']['command'] ?? 'unrar';
            
            // Check if encrypted using test command (detects both header and content encryption)
            $testCmd = escapeshellarg($unrarPath) . ' t -p- ' . escapeshellarg($filepath) . ' 2>&1';
            $testOutput = shell_exec($testCmd);
            
            if ($testOutput && (
                stripos($testOutput, 'encrypted') !== false ||
                stripos($testOutput, 'password') !== false ||
                stripos($testOutput, 'incorrect password') !== false ||
                stripos($testOutput, 'CRC failed') !== false ||
                stripos($testOutput, 'checksum error') !== false ||
                (stripos($testOutput, 'All OK') === false && stripos($testOutput, 'Testing') !== false)
            )) {
                $result['blocked'] = true;
                $result['encrypted'] = true;
                return $result;
            }
            
            // List contents
            $listCmd = escapeshellarg($unrarPath) . ' lb ' . escapeshellarg($filepath) . ' 2>&1';
            $output = shell_exec($listCmd);
            
            if (!$output) {
                throw new \Exception('Failed to list RAR contents');
            }
            
            $lines = explode("\n", $output);
            $blockedFiles = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Check if this file should be blocked (extension or no extension)
                if ($this->isFileBlocked($line, $blockedExtensions)) {
                    $blockedFiles[] = [
                        'name' => basename($line),
                        'path' => $line,
                        'depth' => $currentDepth
                    ];
                }
                
                $extension = strtolower(pathinfo($line, PATHINFO_EXTENSION));
                
                // Check for nested archives (any format)
                if (in_array($extension, ['rar', 'zip', 'tar', 'gz', 'bz2', 'tgz', 'tbz2', 'tb2'])) {
                    if ($currentDepth < $maxDepth) {
                        // Extract and scan nested archive
                        $tempDir = sys_get_temp_dir() . '/attachmentsecurity_' . uniqid();
                        mkdir($tempDir, 0700, true);
                        
                        $tempFile = $tempDir . '/' . basename($line);
                        $extractCmd = escapeshellarg($unrarPath) . ' p -inul ' . escapeshellarg($filepath) . ' ' . escapeshellarg($line) . ' > ' . escapeshellarg($tempFile) . ' 2>&1';
                        shell_exec($extractCmd);
                        
                        if (file_exists($tempFile)) {
                            // Recursively scan nested archive (any format)
                            $nestedResult = $this->scan($tempFile, $blockedExtensions, $maxDepth, $currentDepth + 1);
                            
                            if ($nestedResult['blocked']) {
                                if ($nestedResult['encrypted']) {
                                    $blockedFiles[] = [
                                        'name' => basename($line) . __('(encrypted)'),
                                        'path' => $line,
                                        'depth' => $currentDepth,
                                        'encrypted' => true
                                    ];
                                } else {
                                    $blockedFiles = array_merge($blockedFiles, $nestedResult['files']);
                                }
                            }
                            
                            unlink($tempFile);
                        }
                        
                        $this->removeDirectory($tempDir);
                    } else {
                        // Max depth reached - block this nested archive
                        $blockedFiles[] = [
                            'name' => basename($line) . __('(nesting limit exceeded)'),
                            'path' => $line,
                            'depth' => $currentDepth,
                            'nesting_limit' => true
                        ];
                    }
                }
            }
            
            if (!empty($blockedFiles)) {
                $result['blocked'] = true;
                $result['files'] = $blockedFiles;
                $result['nesting_level'] = $this->getMaxNestingLevel($blockedFiles);
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['blocked'] = false;
        }

        return $result;
    }

    /**
     * Scan RAR using unrar-free binary
     *
     * Note: unrar-free only supports RAR 2.x format
     *
     * @param string $filepath Full path to the RAR file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $maxDepth Maximum nesting depth to scan
     * @return array Scan result
     */
    protected function scanRarWithFree(string $filepath, array $blockedExtensions, int $maxDepth, int $currentDepth = 0): array
    {
        $result = [
            'blocked' => false,
            'files' => [],
            'encrypted' => false,
            'error' => null,
            'nesting_level' => 0
        ];

        try {
            $unrarPath = $this->capabilities['rar']['command'] ?? 'unrar-free';
            
            // unrar-free uses -t flag to list contents
            $listCmd = escapeshellarg($unrarPath) . ' -t ' . escapeshellarg($filepath) . ' 2>&1';
            $output = shell_exec($listCmd);
            
            if (!$output) {
                throw new \Exception('Failed to list RAR contents with unrar-free');
            }
            
            // Check for encrypted archive (header encryption)
            if (stripos($output, 'Encryption is not supported') !== false ||
                stripos($output, 'encryption') !== false) {
                $result['blocked'] = true;
                $result['encrypted'] = true;
                return $result;
            }
            
            // Check for encrypted archive (content encryption without header encryption)
            // unrar-free shows 00-00-00 00:00 for encrypted files
            if (stripos($output, '00-00-00 00:00') !== false) {
                $result['blocked'] = true;
                $result['encrypted'] = true;
                return $result;
            }
            
            // Check for errors or unsupported format
            if (stripos($output, 'unsupported') !== false ||
                stripos($output, 'unknown') !== false ||
                stripos($output, 'corrupted') !== false ||
                stripos($output, 'Parsing filters is not supported') !== false ||
                stripos($output, 'Unrecognized archive format') !== false) {
                throw new \Exception('RAR format not supported by unrar-free (may be RAR 5.x or corrupted)');
            }
            
            // Parse output - unrar-free specific format
            // Output format:
            // Pathname/Comment
            //                   Size   Date   Time     Attr
            // ----------------------------------------------
            //  filename.ext
            //                  12345 02-05-24 17:56   .....A
            // ----------------------------------------------
            $lines = explode("\n", $output);
            $blockedFiles = [];
            $inFileList = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Start of file list (after dashes)
                if (strpos($line, '------') !== false) {
                    $inFileList = true;
                    continue;
                }
                
                // End of file list (second dashes line)
                if ($inFileList && strpos($line, '------') !== false) {
                    break;
                }
                
                if (!$inFileList || empty($line)) continue;
                
                // Skip lines with numbers at start (these are size/date lines)
                if (preg_match('/^\d+/', $line)) continue;
                
                // This is a filename line
                $filename = trim($line);
                
                if (empty($filename)) continue;
                
                // Check if this file should be blocked (extension or no extension)
                if ($this->isFileBlocked($filename, $blockedExtensions)) {
                    $blockedFiles[] = [
                        'name' => basename($filename),
                        'path' => $filename,
                        'depth' => $currentDepth
                    ];
                }
                
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // Check for nested archives (any format)
                if (in_array($extension, ['rar', 'zip', 'tar', 'gz', 'bz2', 'tgz', 'tbz2', 'tb2'])) {
                    if ($currentDepth < $maxDepth) {
                        // Extract nested archive
                        $tempDir = sys_get_temp_dir() . '/attachmentsecurity_' . uniqid();
                        mkdir($tempDir, 0700, true);
                        
                        // Extract this specific file from the archive
                        $extractCmd = escapeshellarg($unrarPath) . ' -x ' . escapeshellarg($filepath) . ' ' . escapeshellarg($filename) . ' ' . escapeshellarg($tempDir) . ' 2>&1';
                        exec($extractCmd, $extractOutput, $extractExitCode);
                        
                        // The extracted file will be at $tempDir/$filename (respecting internal path)
                        $tempFile = $tempDir . '/' . $filename;
                        
                        if (file_exists($tempFile)) {
                            // Recursively scan nested archive (any format)
                            $nestedResult = $this->scan($tempFile, $blockedExtensions, $maxDepth, $currentDepth + 1);
                            
                            if ($nestedResult['blocked']) {
                                if ($nestedResult['encrypted']) {
                                    $blockedFiles[] = [
                                        'name' => basename($filename) . __('(encrypted)'),
                                        'path' => $filename,
                                        'depth' => $currentDepth,
                                        'encrypted' => true
                                    ];
                                } else {
                                    $blockedFiles = array_merge($blockedFiles, $nestedResult['files']);
                                }
                            }
                            
                            unlink($tempFile);
                        }
                        
                        $this->removeDirectory($tempDir);
                    } else {
                        // Max depth reached - block this nested archive
                        $blockedFiles[] = [
                            'name' => basename($filename) . __('(nesting limit exceeded)'),
                            'path' => $filename,
                            'depth' => $currentDepth,
                            'nesting_limit' => true
                        ];
                    }
                }
            }
            
            if (!empty($blockedFiles)) {
                $result['blocked'] = true;
                $result['files'] = $blockedFiles;
                $result['nesting_level'] = $this->getMaxNestingLevel($blockedFiles);
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['blocked'] = false;
        }

        return $result;
    }

    /**
     * Scan TAR-based archives (TAR, GZ, BZ2) using PharData
     *
     * @param string $filepath Full path to the archive file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $maxDepth Maximum nesting depth to scan
     * @param int $currentDepth Current nesting depth
     * @return array Scan result
     */
    protected function scanTarBased(string $filepath, array $blockedExtensions, int $maxDepth, int $currentDepth = 0): array
    {
        $result = [
            'blocked' => false,
            'files' => [],
            'encrypted' => false,
            'error' => null,
            'nesting_level' => 0
        ];
        
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        try {
            // Handle simple compressed files (not tar-based)
            if ($extension === 'gz') {
                // Check if it's a compound extension (.tar.gz or just .gz)
                $filename = pathinfo($filepath, PATHINFO_FILENAME);
                $secondExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if ($secondExt !== 'tar') {
                    // Simple .gz file - decompress and scan content
                    return $this->scanSimpleCompressed($filepath, $blockedExtensions, $maxDepth, $currentDepth, 'gz', basename($filepath));
                }
                // Otherwise continue with PharData (it's a tar.gz)
            }
            
            if ($extension === 'bz2') {
                // Check if it's a compound extension (.tar.bz2 or just .bz2)
                $filename = pathinfo($filepath, PATHINFO_FILENAME);
                $secondExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if ($secondExt !== 'tar') {
                    // Simple .bz2 file - decompress and scan content
                    return $this->scanSimpleCompressed($filepath, $blockedExtensions, $maxDepth, $currentDepth, 'bz2', basename($filepath));
                }
                // Otherwise continue with PharData (it's a tar.bz2)
            }
            
            // .tgz, .tbz2, .tb2 are always tar-based, continue with PharData
            
            $phar = new \PharData($filepath);
            
            $blockedFiles = [];
            $tempDir = sys_get_temp_dir() . '/attachmentsecurity_' . uniqid();
            
            foreach ($phar as $file) {
                if ($file->isDir()) {
                    continue;
                }
                
                $filename = $file->getFilename();
                
                // Check if this file should be blocked (extension or no extension)
                if ($this->isFileBlocked($filename, $blockedExtensions)) {
                    $blockedFiles[] = [
                        'name' => basename($filename),
                        'path' => $filename,
                        'depth' => 0
                    ];
                }
                
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // Check for nested archives (respect depth)
                if (in_array($extension, ['tar', 'gz', 'bz2', 'zip', 'rar', 'tgz', 'tbz2', 'tb2']) && $maxDepth > 0) {
                    // Extract nested archive
                    if (!is_dir($tempDir)) {
                        mkdir($tempDir, 0700, true);
                    }
                    
                    $tempFile = $tempDir . '/' . basename($filename);
                    
                    try {
                        // Extract file
                        copy('phar://' . $filepath . '/' . $filename, $tempFile);
                        
                        if (file_exists($tempFile)) {
                            // Recursively scan nested archive using main scan() method
                            $nestedResult = $this->scan($tempFile, $blockedExtensions, $maxDepth - 1);
                            
                            if ($nestedResult['blocked']) {
                                if ($nestedResult['encrypted']) {
                                    $blockedFiles[] = [
                                        'name' => basename($filename) . __('(encrypted)'),
                                        'path' => $filename,
                                        'depth' => 0,
                                        'encrypted' => true
                                    ];
                                } else {
                                    foreach ($nestedResult['files'] as $nestedFile) {
                                        $nestedFile['depth']++;
                                        $blockedFiles[] = $nestedFile;
                                    }
                                }
                            }
                            
                            unlink($tempFile);
                        }
                    } catch (\Exception $e) {
                        // If extraction fails, continue with other files
                        continue;
                    }
                }
            }
            
            unset($phar);
            
            // Cleanup temp directory
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            if (!empty($blockedFiles)) {
                $result['blocked'] = true;
                $result['files'] = $blockedFiles;
                $result['nesting_level'] = $this->getMaxNestingLevel($blockedFiles);
            }
            
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            
            // Sanitize error message (remove newlines, tabs, excessive spaces)
            $errorMsg = str_replace(["\r", "\n", "\t"], ' ', $errorMsg);
            $errorMsg = preg_replace('/\s+/', ' ', $errorMsg); // Multiple spaces → single space
            $errorMsg = trim($errorMsg);
            
            // Ensure error message is not empty
            if (empty($errorMsg)) {
                $errorMsg = 'Archive file appears to be corrupted or invalid format';
            }
            
            $result['error'] = $errorMsg;
        }

        return $result;
    }

    /**
     * Scan a simple compressed file (.gz or .bz2 without tar)
     *
     * @param string $filepath Full path to compressed file
     * @param array $blockedExtensions List of blocked extensions
     * @param int $maxDepth Maximum nesting depth
     * @param int $currentDepth Current nesting depth
     * @param string $type Compression type ('gz' or 'bz2')
     * @param string|null $originalName Original filename for nested calls (to preserve multi-level extensions)
     * @return array Scan result
     */
    protected function scanSimpleCompressed(string $filepath, array $blockedExtensions, int $maxDepth, int $currentDepth, string $type, ?string $originalName = null): array
    {
        $result = [
            'blocked' => false,
            'files' => [],
            'encrypted' => false,
            'error' => null,
            'nesting_level' => 0
        ];

        try {
            // Create temp file for decompressed content
            $tempFile = tempnam(sys_get_temp_dir(), 'as_decompress_');
            
            if ($type === 'gz') {
                // Validate GZ magic signature before attempting decompression
                $handle = fopen($filepath, 'rb');
                if ($handle === false) {
                    throw new \Exception('Cannot read GZ file');
                }
                $magic = fread($handle, 2);
                fclose($handle);
                
                if ($magic !== "\x1f\x8b") {
                    throw new \Exception('File is not in valid gzip format');
                }
                
                // Decompress .gz file
                $gz = @gzopen($filepath, 'rb');
                if ($gz === false) {
                    throw new \Exception('Cannot open GZ file - not in gzip format or corrupted');
                }
                
                $out = fopen($tempFile, 'wb');
                $bytesWritten = 0;
                $readError = false;
                $hasReadData = false;


                while (!gzeof($gz)) {
                    $data = @gzread($gz, 4096);

                    // Check for read errors
                    if ($data === false) {
                        // Read error - file is corrupted
                        $readError = true;
                        break;
                    }

                    if ($data === '') {
                        // Empty data (valid empty file or EOF reached)
                        break;
                    }

                    $hasReadData = true;
                    $written = fwrite($out, $data);
                    if ($written === false) {
                        $readError = true;
                        break;
                    }
                    $bytesWritten += $written;
                }


                gzclose($gz);
                fclose($out);


                // Check if decompression failed (but allow valid 0-byte files)
                if ($readError) {
                    throw new \Exception('GZ file appears to be corrupted or not in valid gzip format');
                }

                // If 0 bytes were written, it's a valid empty compressed file
                // Continue processing to check extension (don't throw exception)



            } elseif ($type === 'bz2') {
                // Decompress .bz2 file
                $bz = @bzopen($filepath, 'r');
                if ($bz === false) {
                    throw new \Exception('Cannot open BZ2 file - not in bzip2 format or corrupted');
                }

                $out = fopen($tempFile, 'wb');
                $bytesWritten = 0;
                $readError = false;
                $hasReadData = false;

                while (!feof($bz)) {
                    $data = @bzread($bz, 4096);


                    // Check for read errors
                    if ($data === false) {
                        // Read error - file is corrupted
                        $readError = true;
                        break;
                    }

                    if ($data === '') {
                        // Empty data (valid empty file or EOF reached)
                        break;
                    }


                    $hasReadData = true;
                    $written = fwrite($out, $data);
                    if ($written === false) {
                        $readError = true;
                        break;
                    }
                    $bytesWritten += $written;
                }

                bzclose($bz);
                fclose($out);


                // Check if decompression failed (but allow valid 0-byte files)
                if ($readError) {
                    throw new \Exception('BZ2 file appears to be corrupted or not in valid bzip2 format');
                }

                // If 0 bytes were written, it's a valid empty compressed file
                // Continue processing to check extension (don't throw exception)

            }
            
            // Get the original filename without compression extension
            // If originalName is provided (nested call), use it; otherwise use filepath
            $baseName = $originalName ?: basename($filepath);
            $originalFilename = pathinfo($baseName, PATHINFO_FILENAME);
            
            // Check if decompressed file is blocked (extension or no extension)
            if ($this->isFileBlocked($originalFilename, $blockedExtensions)) {
                $result['blocked'] = true;
                $result['files'][] = [
                    'name' => basename($originalFilename),
                    'path' => $originalFilename,
                    'depth' => $currentDepth
                ];
            }
            
            $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            
            // Check if it's a nested archive
            if (in_array($extension, ['zip', 'rar', 'tar', 'gz', 'bz2', 'tgz', 'tbz2', 'tb2'])) {
                if ($currentDepth < $maxDepth) {
                    // Rename temp file to have correct extension for nested scanning
                    // This is necessary so scan() can detect the file type correctly
                    $tempFileWithExt = $tempFile . '.' . $extension;
                    rename($tempFile, $tempFileWithExt);
                    $tempFile = $tempFileWithExt;
                    
                    // For GZ/BZ2, call scanSimpleCompressed directly to preserve original name
                    if ($extension === 'gz' || $extension === 'bz2') {
                        $nestedResult = $this->scanSimpleCompressed($tempFile, $blockedExtensions, $maxDepth, $currentDepth + 1, $extension, $originalFilename);
                    } else {
                        // For other formats (zip, rar, tar), use scan()
                        $nestedResult = $this->scan($tempFile, $blockedExtensions, $maxDepth, $currentDepth + 1);
                    }
                    
                    if ($nestedResult['blocked']) {
                        if ($nestedResult['encrypted']) {
                            $result['blocked'] = true;
                            $result['files'][] = [
                                'name' => basename($originalFilename) . __('(encrypted)'),
                                'path' => $originalFilename,
                                'depth' => $currentDepth,
                                'encrypted' => true
                            ];
                        } else {
                            $result['blocked'] = true;
                            $result['files'] = array_merge($result['files'], $nestedResult['files']);
                        }
                    }
                } else {
                    // Max depth reached
                    $result['blocked'] = true;
                    $result['files'][] = [
                        'name' => basename($originalFilename) . __('(nesting limit exceeded)'),
                        'path' => $originalFilename,
                        'depth' => $currentDepth,
                        'nesting_limit' => true
                    ];
                }
            }
            
            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            if (!empty($result['files'])) {
                $result['nesting_level'] = $this->getMaxNestingLevel($result['files']);
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['blocked'] = false;
        }

        return $result;
    }
}
