<?php

namespace RamboWoon;

class ProjectDeployer
{
    private $baseDir;

    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    /**
     * Copy a directory recursively - Optimized for Windows
     */
    public function copyRecursive($src, $dst)
    {
        if (!is_dir($src)) return false;
        if (!is_dir($dst)) @mkdir($dst, 0777, true);

        // Use native Windows xcopy for much faster speed than PHP loops
        // /E: Copy subdirectories, including empty ones.
        // /I: If destination does not exist and copying more than one file, assumes that destination must be a directory.
        // /H: Copy hidden and system files also.
        // /Y: Suppress prompting to confirm you want to overwrite an existing destination file.
        $srcPath = str_replace('/', DIRECTORY_SEPARATOR, $src);
        $dstPath = str_replace('/', DIRECTORY_SEPARATOR, $dst);
        
        // Remove Windows Device Namespace prefix (\\.\) which breaks xcopy/tar
        if (substr($srcPath, 0, 4) === '\\\\.\\') $srcPath = substr($srcPath, 4);
        if (substr($dstPath, 0, 4) === '\\\\.\\') $dstPath = substr($dstPath, 4);
        
        $srcPath = rtrim($srcPath, '\\/');
        $dstPath = rtrim($dstPath, '\\/');
        
        // Use proc_open to explicitly pass standard handles to robocopy.
        // This completely bypasses the Windows bug where console apps instantly fail in detached headless environments.
        $cmd = "robocopy \"$srcPath\" \"$dstPath\" /E /NFL /NDL /NJH /NJS /nc /ns /np";
        
        $descriptorspec = [
           0 => ["pipe", "r"],  // stdin
           1 => ["pipe", "w"],  // stdout
           2 => ["pipe", "w"]   // stderr
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            fclose($pipes[0]); // close stdin immediately
            
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $returnVar = proc_close($process);
            
            // robocopy exit codes < 8 mean success
            if ($returnVar >= 8 || $returnVar === 0) {
                throw new \Exception("Lỗi robocopy (Code $returnVar). Lệnh: $cmd\nLog: $stdout\nErr: $stderr");
            }
        } else {
            throw new \Exception("Không thể khởi chạy tiến trình robocopy qua proc_open.");
        }
        
        return true;
    }

    /**
     * Extract a ZIP file - Highly recommended for speed
     */
    public function extractZip($zipPath, $dst)
    {
        if (!file_exists($zipPath)) return false;
        if (!is_dir($dst)) @mkdir($dst, 0777, true);

        $zipPath = str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
        $dstPath = str_replace('/', DIRECTORY_SEPARATOR, $dst);

        // Remove Windows Device Namespace prefix (\\.\) which breaks xcopy/tar
        if (substr($zipPath, 0, 4) === '\\\\.\\') $zipPath = substr($zipPath, 4);
        if (substr($dstPath, 0, 4) === '\\\\.\\') $dstPath = substr($dstPath, 4);

        // Windows 10+ has tar command built-in that handles .zip
        $cmd = "tar -xf \"$zipPath\" -C \"$dstPath\" 2>&1";
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            // Fallback to native PHP ZipArchive if tar fails
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive;
                $res = $zip->open($zipPath);
                if ($res === TRUE) {
                    $zip->extractTo($dstPath);
                    $zip->close();
                    return true;
                } else {
                    throw new \Exception("Lỗi giải nén (Tar Code $returnVar, ZipArchive Code $res): " . implode("\n", $output));
                }
            }
            throw new \Exception("Lỗi giải nén (Code $returnVar): " . implode("\n", $output));
        }

        return true;
    }

    /**
     * Create a new database on localhost using mysqli
     */
    public function createDatabase($dbName, $host = 'localhost', $user = 'root', $pass = '')
    {
        $mysqli = new \mysqli($host, $user, $pass);
        if ($mysqli->connect_error) {
            throw new \Exception("Kết nối MySQL thất bại: " . $mysqli->connect_error);
        }

        $dbSafeName = $mysqli->real_escape_string($dbName);
        $sql = "CREATE DATABASE IF NOT EXISTS `$dbSafeName`";
        
        if (!$mysqli->query($sql)) {
            $error = $mysqli->error;
            $mysqli->close();
            throw new \Exception("Lỗi tạo database: " . $error);
        }

        $mysqli->close();
        return true;
    }

    /**
     * Import SQL file to database - Fast Multi-Query Mode
     */
    public function importSql($dbName, $sqlFile, $host = 'localhost', $user = 'root', $pass = '')
    {
        if (!file_exists($sqlFile)) {
            throw new \Exception("File SQL không tồn tại: $sqlFile");
        }

        $mysqli = new \mysqli($host, $user, $pass, $dbName);
        if ($mysqli->connect_error) {
            throw new \Exception("Kết nối MySQL thất bại: " . $mysqli->connect_error);
        }
        $mysqli->set_charset("utf8mb4");

        // 1. Read and Sanitize SQL (Remove system commands for stability)
        $originalSql = file_get_contents($sqlFile);
        $sanitizedSql = preg_replace('/^SET .*;$/mi', '-- Removed SET', $originalSql);
        $sanitizedSql = preg_replace('/^START TRANSACTION.*;/mi', '-- Removed START', $sanitizedSql);
        $sanitizedSql = preg_replace('/^COMMIT.*;/mi', '-- Removed COMMIT', $sanitizedSql);
        $sanitizedSql = preg_replace('/^CREATE DATABASE.*;/mi', '-- Removed CREATE', $sanitizedSql);
        $sanitizedSql = preg_replace('/^USE .*;$/mi', '-- Removed USE', $sanitizedSql);
        $sanitizedSql = preg_replace('/^DROP DATABASE.*;/mi', '-- Removed DROP DB', $sanitizedSql);

        // 2. Execute Multi-Query (Fastest)
        $sanitizedSql = "SET FOREIGN_KEY_CHECKS=0;\n" . $sanitizedSql . "\nSET FOREIGN_KEY_CHECKS=1;";
        if ($mysqli->multi_query($sanitizedSql)) {
            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());
        }

        if ($mysqli->error) {
            $error = $mysqli->error;
            $mysqli->close();
            throw new \Exception("Lỗi import SQL: " . $error);
        }

        $mysqli->close();
        return true;
    }

    /**
     * Update .env file
     */
    public function updateEnv($envPath, $updates)
    {
        if (!file_exists($envPath)) return false;

        $content = file_get_contents($envPath);
        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $safeValue = str_replace('$', '\$', $value);
            $replacement = "{$key}={$safeValue}";
            
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                $content .= "\n{$key}={$value}";
            }
        }

        return file_put_contents($envPath, $content) !== false;
    }
}
